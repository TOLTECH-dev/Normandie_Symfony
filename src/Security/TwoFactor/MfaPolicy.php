<?php

namespace App\Security\TwoFactor;

use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Détermine les comptes soumis à l'authentification multi-facteur (MFA).
 *
 * Le rapport d'audit ALMOND NOR005-22 préconise un second facteur pour les profils
 * Administrateur, Instructeur et Client. Les rôles concernés sont portés par le paramètre
 * "security_mfa_roles" (config/services.yaml).
 *
 * La hiérarchie des rôles de security.yaml est résolue : un compte qui hérite d'un rôle
 * concerné est lui aussi soumis au second facteur.
 */
final class MfaPolicy
{
    /**
     * @param string[] $mfaRoles
     */
    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly array $mfaRoles
    ) {
    }

    public function isRequiredFor(UserInterface $user): bool
    {
        $reachableRoles = $this->roleHierarchy->getReachableRoleNames($user->getRoles());

        return [] !== array_intersect($reachableRoles, $this->mfaRoles);
    }
}
