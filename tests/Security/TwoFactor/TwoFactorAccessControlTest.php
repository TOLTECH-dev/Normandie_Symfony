<?php

declare(strict_types=1);

namespace App\Tests\Security\TwoFactor;

use App\Entity\User;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorToken;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Ce que le jeton intermédiaire du second facteur a le droit d'atteindre.
 *
 * Entre la validation du mot de passe et la saisie du code, le compte ne porte aucun rôle : il ne
 * détient qu'un TwoFactorToken. Les règles `access_control` doivent donc traiter ce cas
 * explicitement, faute de quoi la règle générale « ^/admin », qui exige un rôle métier, ferme des
 * chemins qui doivent rester praticables.
 *
 * Défaut constaté en recette le 06/11/2026 : `/admin/bo_logout` tombait sous la règle générale.
 * Symfony refusait l'accès avant que le LogoutListener — exécuté en dernier sur un pare-feu `lazy` —
 * n'ait pu agir, et le point d'entrée renvoyait sur le formulaire de code. Un utilisateur qui ne
 * recevait pas son courriel restait enfermé sur cette page.
 */
class TwoFactorAccessControlTest extends KernelTestCase
{
    /**
     * @dataProvider provideCheminsPraticables
     */
    public function testLeJetonIntermediaireAtteintLesCheminsNecessairesAuSecondFacteur(string $chemin): void
    {
        self::assertTrue(
            $this->accesAutorise($chemin),
            sprintf('Le chemin %s doit rester joignable pendant la saisie du code.', $chemin)
        );
    }

    public static function provideCheminsPraticables(): iterable
    {
        yield 'formulaire de saisie du code' => ['/admin/2fa'];
        yield 'validation du code' => ['/admin/2fa_check'];
        // Sans cette ouverture, un compte dont l'adresse de courriel est invalide ne peut plus
        // quitter la page de code autrement qu'en vidant ses témoins de connexion.
        yield 'déconnexion' => ['/admin/bo_logout'];
        yield 'retour au formulaire de connexion' => ['/admin/bo_login'];
    }

    /**
     * Le pendant indispensable : l'ouverture ci-dessus ne doit pas déborder sur le back-office.
     *
     * @dataProvider provideCheminsFermes
     */
    public function testLeJetonIntermediaireNAtteintPasLeBackOffice(string $chemin): void
    {
        self::assertFalse(
            $this->accesAutorise($chemin),
            sprintf('Le chemin %s ne doit pas être atteignable avant la validation du code.', $chemin)
        );
    }

    public static function provideCheminsFermes(): iterable
    {
        yield 'accueil du back-office' => ['/admin/'];
        yield 'un écran quelconque' => ['/admin/utilisateur/liste'];
    }

    /**
     * Reproduit la décision de Symfony\Component\Security\Http\Firewall\AccessListener.
     */
    private function accesAutorise(string $chemin): bool
    {
        $conteneur = static::getContainer();
        $requete = Request::create($chemin);
        [$exigences] = $conteneur->get('security.access_map')->getPatterns($requete);

        if (empty($exigences)) {
            return true;
        }

        return $conteneur->get('security.access.decision_manager')
            ->decide($this->jetonSecondFacteurEnCours(), $exigences, $requete, true);
    }

    private function jetonSecondFacteurEnCours(): TokenInterface
    {
        $utilisateur = new User();
        $utilisateur->setUsername('second-facteur-en-cours');
        $utilisateur->setRoles(['ROLE_ADMIN']);

        // Le jeton issu du mot de passe est encapsulé, et ses rôles ne sont PAS exposés : c'est tout
        // l'intérêt du mécanisme, et la raison pour laquelle la règle « ^/admin » ne passe pas.
        $jetonMotDePasse = new UsernamePasswordToken($utilisateur, 'backoffice', $utilisateur->getRoles());

        return new TwoFactorToken($jetonMotDePasse, null, 'backoffice', ['email_code']);
    }

    protected function setUp(): void
    {
        self::bootKernel();
    }
}
