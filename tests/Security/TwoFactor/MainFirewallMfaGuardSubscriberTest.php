<?php

namespace App\Tests\Security\TwoFactor;

use App\Entity\User;
use App\Security\Subscriber\MainFirewallMfaGuardSubscriber;
use App\Security\TwoFactor\MfaPolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Les firewalls "main" et "backoffice" partagent leur contexte de sécurité : un profil
 * soumis au MFA ne doit pas pouvoir ouvrir de session par le formulaire du front-office,
 * qui n'impose pas de second facteur.
 */
class MainFirewallMfaGuardSubscriberTest extends TestCase
{
    public function testUnProfilSoumisAuMfaEstRefuseSurLeFirewallMain(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);

        $this->subscriber()->checkPassport($this->evenement($this->user(['ROLE_ADMIN']), true));
    }

    public function testUnBeneficiaireSeConnecteNormalement(): void
    {
        $this->subscriber()->checkPassport($this->evenement($this->user(['ROLE_MEMBER']), true));

        $this->addToAssertionCount(1);
    }

    /**
     * Tant que le mot de passe n'est pas validé, rien ne doit distinguer un compte soumis
     * au MFA d'un autre.
     */
    public function testLeRefusNIntervientQuApresValidationDuMotDePasse(): void
    {
        $this->subscriber()->checkPassport($this->evenement($this->user(['ROLE_ADMIN']), false));
        $this->addToAssertionCount(1);

        [, $priorite] = MainFirewallMfaGuardSubscriber::getSubscribedEvents()[CheckPassportEvent::class];
        $this->assertLessThan(0, $priorite, 'Doit s\'exécuter après CheckCredentialsListener (priorité 0).');
    }

    private function subscriber(): MainFirewallMfaGuardSubscriber
    {
        return new MainFirewallMfaGuardSubscriber(new MfaPolicy(new RoleHierarchy([]), ['ROLE_ADMIN']));
    }

    private function evenement(User $user, bool $motDePasseValide): CheckPassportEvent
    {
        $credentials = new PasswordCredentials('mot-de-passe');
        if ($motDePasseValide) {
            $credentials->markResolved();
        }

        $passport = new Passport(new UserBadge($user->getUserIdentifier(), fn () => $user), $credentials);

        return new CheckPassportEvent($this->createMock(AuthenticatorInterface::class), $passport);
    }

    private function user(array $roles): User
    {
        $user = new User();
        $user->setUsername('compte-de-test');
        $user->setRoles($roles);

        return $user;
    }
}
