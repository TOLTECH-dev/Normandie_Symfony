<?php

namespace App\Service;

use App\Repository\UserRepository;
use App\Security\TwoFactor\MfaPolicy;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service pour gérer l'authentification FranceConnect
 */
class FranceConnectService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository         $userRepository,
        private readonly DemandeServiceBO       $demandeServiceBO,
        private readonly AdminService           $adminService,
        private readonly MfaPolicy              $mfaPolicy
    )
    {
    }

    /**
     * Vérifie et gère la connexion FranceConnect
     *
     * @param string $email Email de l'utilisateur
     * @param string $firstName Prénom de l'utilisateur
     * @param string $lastName Nom de l'utilisateur
     * @return string|bool Résultat de la vérification
     */
    public function check(string $email, string $firstName, string $lastName): string|bool
    {
        $userData = $this->userRepository->search($email);

        if (!$userData) {
            // Vérification si la création est autorisée
            if (empty($this->demandeServiceBO->checkIsOkDemandeCreateActionByDate())) {
                return 'isCreateViaFranceConnectForbidden';
            } else {
                // Pas de compte – création via FC
                $isCreateUser = $this->adminService->createUser(-1, -1, $firstName, $lastName, $email, $email);
                if ($isCreateUser) {
                    return true;
                }
            }
        } else {
            $user = $this->userRepository->find($userData['userId']);

            if ($user) {
                // Vérification si l'utilisateur est activé
                if (!$user->isEnabled()) {
                    return 'isConnectViaFranceConnectForbidden';
                }

                // Les profils soumis au MFA ne peuvent pas se connecter via FranceConnect :
                // AdminService::authenticateManually() pose le jeton directement, sans passer
                // par le firewall "backoffice" qui impose le second facteur, alors que le
                // contexte de sécurité est partagé avec /admin.
                if ($this->mfaPolicy->isRequiredFor($user)) {
                    return 'isConnectViaFranceConnectForbidden';
                }

                // Rapprochement via FranceConnect
                if ('existe_hors_fc' === $userData['choice']) {
                    $user->setIsFranceConnect(true);
                    $user->setDateModif(new \DateTime());
                    $user->setAuteurModif('Automate');

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                }

                // Authentification manuelle de l'utilisateur
                $this->adminService->authenticateManually($user);

                return true;

            } else {
                throw new \Exception('l\'Utilisateur n\'existe pas');
            }
        }

        return false;
    }
}
