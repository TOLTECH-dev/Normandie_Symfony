<?php

namespace App\Security\TwoFactor\Exception;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * Met fin au processus de double authentification en cours : le code ne peut plus être
 * validé, l'utilisateur doit se reconnecter pour en recevoir un nouveau.
 *
 * Traitée par App\Security\TwoFactor\TwoFactorFailureHandler.
 */
final class TwoFactorProcessAbortedException extends CustomUserMessageAuthenticationException
{
    public static function codeExpired(): self
    {
        return new self('Le code de vérification a expiré. Veuillez vous reconnecter pour en recevoir un nouveau.');
    }

    public static function tooManyAttempts(): self
    {
        return new self('Trop de codes de vérification erronés ont été saisis. Veuillez vous reconnecter pour en recevoir un nouveau.');
    }
}
