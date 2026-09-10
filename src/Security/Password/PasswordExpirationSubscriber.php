<?php

namespace App\Security\Password;

use App\Entity\User;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Impose le renouvellement d'un mot de passe expiré (Reco-15) : tant qu'il n'est pas changé, le
 * back-office se limite à la page de changement et à la déconnexion.
 *
 * L'expiration est évaluée à chaque requête : une session ouverte avant l'échéance, ou par le
 * cookie « Se souvenir de moi », est aussi concernée. Pendant la double authentification (jeton
 * intermédiaire), rien n'est fait : le contrôle intervient une fois la connexion complète.
 *
 * Seul le back-office est couvert : les profils soumis à l'expiration ne peuvent pas se connecter
 * au front-office (cf. MainFirewallMfaGuardSubscriber).
 */
final class PasswordExpirationSubscriber implements EventSubscriberInterface
{
    public const CHANGE_ROUTE = 'admin_password_change';

    /**
     * Routes restant accessibles avec un mot de passe expiré.
     */
    private const ALLOWED_ROUTES = [
        self::CHANGE_ROUTE,
        'admin_user_logout',
        'admin_user_login',
        '2fa_login',
        '2fa_login_check',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly PasswordExpirationPolicy $passwordExpirationPolicy,
        private readonly RouterInterface $router
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Après le firewall (priorité 8), qui établit le jeton de sécurité.
        return [KernelEvents::REQUEST => ['onKernelRequest', 7]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/admin')
            || \in_array($request->attributes->get('_route'), self::ALLOWED_ROUTES, true)
        ) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token || $token instanceof TwoFactorTokenInterface) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User || !$this->passwordExpirationPolicy->isExpired($user)) {
            return;
        }

        if ($request->isXmlHttpRequest()) {
            $event->setResponse(new JsonResponse(['message' => 'Votre mot de passe a expiré.'], JsonResponse::HTTP_FORBIDDEN));

            return;
        }

        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add(
                'warning',
                'Votre mot de passe a expiré : choisissez-en un nouveau pour continuer.'
            );
        }

        $event->setResponse(new RedirectResponse($this->router->generate(self::CHANGE_ROUTE)));
    }
}
