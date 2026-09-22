<?php

declare(strict_types=1);

namespace App\Security\Ip;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Reco-18 : deuxième contrôle du réseau d'origine, à chaque requête du back-office.
 *
 * Le premier contrôle a lieu à l'authentification (App\Security\UserChecker) : il refuse la
 * connexion avant même l'envoi du code de double authentification. Celui-ci ferme les cas où la
 * session a été ouverte depuis un réseau autorisé, puis poursuivie ailleurs (cookie de session
 * recopié, changement de réseau en cours de session).
 *
 * Les pages de connexion et de déconnexion restent accessibles : sans cela, un compte filtré ne
 * pourrait plus se déconnecter, et la page de connexion afficherait une erreur au lieu du
 * formulaire.
 */
final class IpFilterSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_ROUTES = [
        'admin_user_login',
        'admin_user_logout',
        '2fa_login',
        '2fa_login_check',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly IpFilterPolicy $ipFilterPolicy,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Après le firewall (priorité 8), qui établit le jeton de sécurité, et avant l'expiration
        // des mots de passe (priorité 7) : le réseau prime sur toute autre règle.
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
        if (!$user instanceof User || $this->ipFilterPolicy->autorise($user, $request->getClientIp())) {
            return;
        }

        $this->journaliser($user, $request);

        if ($request->isXmlHttpRequest()) {
            $event->setResponse(new JsonResponse(
                ['message' => "L'accès n'est pas autorisé depuis ce réseau."],
                JsonResponse::HTTP_FORBIDDEN
            ));

            return;
        }

        // Page « Accès refusé » (VUL02/VUL06) : aucun détail sur les réseaux attendus.
        throw new AccessDeniedHttpException();
    }

    private function journaliser(User $user, Request $request): void
    {
        $this->logger->warning('Session poursuivie depuis un réseau non autorisé (Reco-18)', [
            'username'    => $user->getUserIdentifier(),
            'groupes'     => $this->ipFilterPolicy->groupes($user),
            'ip_client'   => $request->getClientIp(),
            'ip_reelle'   => $request->server->get('REMOTE_ADDR'),
            'chemin'      => $request->getPathInfo(),
        ]);
    }
}
