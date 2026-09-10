<?php

namespace App\Security\Subscriber;

use App\Security\TwoFactor\MfaPolicy;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Interdit aux profils soumis au MFA de s'authentifier par le firewall "main"
 * (formulaire de connexion du front-office).
 *
 * Les firewalls "main" et "backoffice" partagent le contexte de sécurité "primary_auth" :
 * une session ouverte en front-office est donc valable sur /admin. Or seul le firewall
 * "backoffice" impose le second facteur. Sans ce garde-fou, un administrateur connecté via
 * /login conserverait une session exploitable sur le back-office, sans avoir saisi de code
 * (MainLoginSuccessHandler le redirige bien vers la déconnexion, mais rien n'oblige le
 * client à suivre cette redirection).
 *
 * Le refus intervient après le contrôle du mot de passe, comme celui que
 * MainLoginSuccessHandler opposait déjà à ces profils : il n'apprend rien de plus à qui
 * ignore le mot de passe.
 *
 * Abonné au seul dispatcher du firewall "main" (cf. config/services.yaml).
 */
final class MainFirewallMfaGuardSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly MfaPolicy $mfaPolicy)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Après CheckCredentialsListener (priorité 0), qui valide le mot de passe.
        return [CheckPassportEvent::class => ['checkPassport', -10]];
    }

    public function checkPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();

        if (!$passport instanceof Passport || !$passport->hasBadge(PasswordCredentials::class)) {
            return;
        }

        if (!$passport->getBadge(PasswordCredentials::class)->isResolved()) {
            return;
        }

        if ($this->mfaPolicy->isRequiredFor($passport->getUser())) {
            throw new CustomUserMessageAccountStatusException('Droits insuffisants.');
        }
    }
}
