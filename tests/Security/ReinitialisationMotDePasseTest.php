<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\SecurityService;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

/**
 * Qui reçoit un courriel de réinitialisation, et qui n'en reçoit pas.
 *
 * Signalé le 25/11/2026 : les comptes du profil Technique ne recevaient rien
 * en recette. La règle n'a rien de propre à ce profil — elle écarte tout compte
 * DÉSACTIVÉ ET SANS JETON — mais elle les frappe presque tous : sur la copie de
 * production du 20/10, 8 des 10 comptes Technique sont dans cet état.
 *
 * Le refus est volontairement SILENCIEUX : l'écran affiche « Vérifiez vos
 * e-mails » dans tous les cas, pour ne pas révéler qu'un compte existe. La
 * contrepartie est qu'un cas légitime — un compte désactivé qu'on voulait
 * réveiller — ne se distingue pas d'un compte inconnu. D'où ces contrôles, qui
 * figent la règle et la rendent lisible.
 *
 * Aucun courriel n'est envoyé ici : le service de messagerie est simulé.
 */
class ReinitialisationMotDePasseTest extends TestCase
{
    private const TTL = '3600';

    /** @var list<string> adresses auxquelles un message a été demandé */
    private array $envois = [];

    private function service(): SecurityService
    {
        $this->envois = [];

        $messagerie = $this->createMock(MailerService::class);
        $messagerie->method('sendTemplateEmail')->willReturnCallback(
            function (string $destinataire): bool {
                $this->envois[] = $destinataire;

                return true;
            }
        );

        $routeur = $this->createMock(RouterInterface::class);
        $routeur->method('generate')->willReturn('https://exemple/resetting/jeton');

        $generateur = $this->createMock(TokenGeneratorInterface::class);
        $generateur->method('generateToken')->willReturn('jeton-neuf');

        return new SecurityService(
            $messagerie,
            $routeur,
            $generateur,
            $this->createMock(EntityManagerInterface::class)
        );
    }

    private function compte(bool $actif, ?string $jeton, ?\DateTime $demandeLe = null): User
    {
        $utilisateur = new User();
        $utilisateur->setUsername('essai');
        $utilisateur->setEmail('essai@exemple.fr');
        $utilisateur->setEnabled($actif);
        $utilisateur->setConfirmationToken($jeton);

        if (null !== $demandeLe) {
            $utilisateur->setPasswordRequestedAt($demandeLe);
        }

        return $utilisateur;
    }

    /**
     * Le cas signalé : rien ne part, et rien ne le dit.
     */
    public function testUnCompteDesactiveSansJetonNeRecoitRien(): void
    {
        $this->service()->handleSendResettingEmail($this->compte(false, null), self::TTL);

        self::assertSame([], $this->envois, 'Un compte désactivé sans jeton ne doit déclencher aucun envoi.');
    }

    /**
     * Le compte qui vient d'être créé : désactivé, mais porteur du jeton
     * d'activation. Celui-là doit pouvoir redemander son courriel.
     */
    public function testUnCompteEnAttenteDActivationRecoitSonCourriel(): void
    {
        $this->service()->handleSendResettingEmail($this->compte(false, 'jeton-activation'), self::TTL);

        self::assertSame(['essai@exemple.fr'], $this->envois);
    }

    public function testUnCompteActifRecoitSonCourriel(): void
    {
        $this->service()->handleSendResettingEmail($this->compte(true, null), self::TTL);

        self::assertSame(['essai@exemple.fr'], $this->envois);
    }

    /**
     * Deuxième cause de silence, bien moins grave mais tout aussi muette : une
     * demande a déjà été faite il y a moins d'une heure.
     */
    public function testUneSecondeDemandeDansLHeureEstIgnoree(): void
    {
        $service = $this->service();
        $service->handleSendResettingEmail($this->compte(true, 'jeton', new \DateTime('-10 minutes')), self::TTL);

        self::assertSame([], $this->envois, 'Le délai de renvoi protège contre l\'usage du formulaire comme arme d\'envoi.');
    }

    public function testUneDemandeAncienneRouvreLeDroitAUnCourriel(): void
    {
        $service = $this->service();
        $service->handleSendResettingEmail($this->compte(true, 'jeton', new \DateTime('-2 hours')), self::TTL);

        self::assertSame(['essai@exemple.fr'], $this->envois);
    }

    public function testUnIdentifiantInconnuNeDeclencheAucunEnvoi(): void
    {
        $this->service()->handleSendResettingEmail(null, self::TTL);

        self::assertSame([], $this->envois);
    }
}
