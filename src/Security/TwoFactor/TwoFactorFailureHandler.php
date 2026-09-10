<?php

namespace App\Security\TwoFactor;

use App\Security\TwoFactor\Exception\TwoFactorProcessAbortedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Échec de la saisie du code MFA (option "two_factor.failure_handler" du firewall
 * "backoffice").
 *
 * - Code erroné : retour au formulaire de saisie, comme le handler par défaut du bundle.
 * - Processus interrompu (code expiré ou invalidé, blocage par login_throttling) : le jeton
 *   intermédiaire est retiré et l'utilisateur renvoyé à la page de connexion. Obtenir un
 *   nouveau code impose de valider à nouveau le mot de passe.
 */
final class TwoFactorFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $session = $request->getSession();

        if ($exception instanceof TwoFactorProcessAbortedException || $exception instanceof TooManyLoginAttemptsAuthenticationException) {
            $this->tokenStorage->setToken(null);
            $session->getFlashBag()->add(
                'danger',
                $this->translator->trans($exception->getMessageKey(), $exception->getMessageData(), 'security')
            );

            return new RedirectResponse($this->router->generate('admin_user_login'));
        }

        $session->set(Security::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->router->generate('2fa_login'));
    }
}
