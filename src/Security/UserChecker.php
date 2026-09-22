<?php

namespace App\Security;

use App\Entity\User;
use App\Security\Ip\IpFilterPolicy;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly IpFilterPolicy $ipFilterPolicy,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isEnabled()) {
            throw new CustomUserMessageAccountStatusException(
                'Le compte est désactivé.'
            );
        }

        $this->verifierLeReseau($user);
    }

    public function checkPostAuth(UserInterface $user)
    {

    }

    /**
     * Reco-18 : les profils Région, Docaposte et administrateurs ne se connectent que depuis les
     * réseaux de leur organisme.
     *
     * Le contrôle a lieu avant l'envoi du code de double authentification : un mot de passe volé
     * ne déclenche aucun courriel depuis l'extérieur.
     *
     * Le refus prend la forme d'un échec d'authentification ordinaire : le message affiché reste
     * « Identifiants invalides », comme pour un mot de passe erroné. Indiquer que le mot de passe
     * était bon mais le réseau mauvais confirmerait la validité des identifiants à un attaquant.
     * Le motif réel est journalisé côté serveur.
     */
    private function verifierLeReseau(User $user): void
    {
        $request = $this->requestStack->getCurrentRequest();

        // Hors requête HTTP (commandes en console, traitements automatiques) : pas de filtrage.
        if (null === $request || !str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }

        if ($this->ipFilterPolicy->autorise($user, $request->getClientIp())) {
            return;
        }

        $this->logger->warning('Connexion refusée : réseau non autorisé (Reco-18)', [
            'username'  => $user->getUserIdentifier(),
            'groupes'   => $this->ipFilterPolicy->groupes($user),
            'ip_client' => $request->getClientIp(),
            'ip_reelle' => $request->server->get('REMOTE_ADDR'),
        ]);

        throw new BadCredentialsException('Réseau non autorisé pour ce profil.');
    }
}