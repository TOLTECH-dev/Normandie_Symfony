<?php

declare(strict_types=1);

namespace App\Tests\Mailer;

use App\Mailer\RedirectionCourrielListener;
use App\Service\MailerService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Détournement du courrier sur les environnements qui rejouent une copie de la production.
 *
 * Deux exigences qui se contredisent, et que l'écouteur doit concilier : aucun message ne doit
 * atteindre un vrai bénéficiaire, et le code de double authentification doit arriver chez son
 * destinataire, sans quoi personne ne peut se connecter à l'environnement.
 */
class RedirectionCourrielListenerTest extends TestCase
{
    private const CONTROLE = 'contact@toltech.fr';

    public function testLeCourrierOrdinaireEstDetourne(): void
    {
        $evenement = $this->evenement($this->courriel('beneficiaire@exemple.fr'));

        ($this->ecouteur())($evenement);

        self::assertSame([self::CONTROLE], $this->destinataires($evenement));
    }

    /**
     * Le « À : » du message n'est pas touché : on voit à qui le message était destiné, ce qui est
     * tout l'intérêt par rapport à un envoi purement supprimé.
     */
    public function testLeDestinataireAfficheEstConserve(): void
    {
        $courriel = $this->courriel('beneficiaire@exemple.fr');
        $evenement = $this->evenement($courriel);

        ($this->ecouteur())($evenement);

        self::assertSame('beneficiaire@exemple.fr', $courriel->getTo()[0]->getAddress());
    }

    public function testLeCodeDeConnexionNEstPasDetourne(): void
    {
        $courriel = $this->courriel('agent@region-normandie.fr');
        $courriel->getHeaders()->addTextHeader(MailerService::ENTETE_VOIE, MailerService::TRANSPORT_MFA);

        $evenement = $this->evenement($courriel);

        ($this->ecouteur())($evenement);

        self::assertSame(['agent@region-normandie.fr'], $this->destinataires($evenement));
    }

    /**
     * Production : la variable est vide, l'écouteur ne doit rien faire.
     */
    public function testSansAdresseDeControleRienNEstTouche(): void
    {
        $evenement = $this->evenement($this->courriel('beneficiaire@exemple.fr'));

        ($this->ecouteur(''))($evenement);

        self::assertSame(['beneficiaire@exemple.fr'], $this->destinataires($evenement));
    }

    /**
     * Mailer::send() diffuse un CLONE du message avant la mise en file, pour que les écouteurs
     * puissent observer sans modifier. Y réécrire l'enveloppe serait sans effet : l'écouteur doit
     * laisser passer ce tour et agir au suivant, celui du transport.
     */
    public function testLePassageEnFileEstIgnore(): void
    {
        $evenement = $this->evenement($this->courriel('beneficiaire@exemple.fr'), true);

        ($this->ecouteur())($evenement);

        self::assertSame(['beneficiaire@exemple.fr'], $this->destinataires($evenement));
    }

    public function testPlusieursAdressesDeControle(): void
    {
        $evenement = $this->evenement($this->courriel('beneficiaire@exemple.fr'));

        ($this->ecouteur(' contact@toltech.fr , recette@toltech.fr ,, '))($evenement);

        self::assertSame(['contact@toltech.fr', 'recette@toltech.fr'], $this->destinataires($evenement));
    }

    /**
     * L'évènement est diffusé une fois par le transport ; si une configuration le rejouait, le
     * second passage ne doit rien casser.
     */
    public function testLEcouteurEstIdempotent(): void
    {
        $evenement = $this->evenement($this->courriel('beneficiaire@exemple.fr'));
        $ecouteur = $this->ecouteur();

        $ecouteur($evenement);
        $ecouteur($evenement);

        self::assertSame([self::CONTROLE], $this->destinataires($evenement));
    }

    /**
     * Garde-fou de cohérence : l'écouteur reconnaît la voie du second facteur par un marquage
     * propre au projet, et NON par « X-Transport », que Transports::send() retire du message avant
     * que le transport ne diffuse l'évènement. Si quelqu'un revenait à « X-Transport », le code de
     * connexion serait détourné et plus personne ne pourrait se connecter en recette.
     */
    public function testUnMessagePortantSeulementXTransportEstDetourne(): void
    {
        $courriel = $this->courriel('agent@region-normandie.fr');
        $courriel->getHeaders()->addTextHeader('X-Transport', MailerService::TRANSPORT_MFA);

        $evenement = $this->evenement($courriel);

        ($this->ecouteur())($evenement);

        self::assertSame(
            [self::CONTROLE],
            $this->destinataires($evenement),
            'Seul ' . MailerService::ENTETE_VOIE . ' fait foi : X-Transport ne survit pas au choix du transport.'
        );
    }

    private function ecouteur(string $destinataires = self::CONTROLE): RedirectionCourrielListener
    {
        return new RedirectionCourrielListener($destinataires, new NullLogger());
    }

    private function courriel(string $destinataire): Email
    {
        return (new Email())
            ->from('noreply@cheque-eco-energie.fr')
            ->to($destinataire)
            ->subject('Essai')
            ->text('Essai');
    }

    private function evenement(Email $courriel, bool $enFile = false): MessageEvent
    {
        return new MessageEvent($courriel, Envelope::create($courriel), 'transport-de-test', $enFile);
    }

    /**
     * @return list<string>
     */
    private function destinataires(MessageEvent $evenement): array
    {
        return array_map(
            static fn (Address $adresse): string => $adresse->getAddress(),
            $evenement->getEnvelope()->getRecipients()
        );
    }
}
