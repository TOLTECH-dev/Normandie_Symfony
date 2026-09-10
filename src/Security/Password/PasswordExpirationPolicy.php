<?php

namespace App\Security\Password;

use App\Entity\User;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Expiration périodique des mots de passe (Reco-15 du plan de traitement des risques CEE).
 *
 * Conformément au guide ANSSI « Recommandations relatives à l'authentification multifacteur et
 * aux mots de passe » (PG-078 v2.0), l'expiration vise les comptes à privilèges (R25) et n'est pas
 * imposée par défaut aux comptes peu sensibles (R24). Les rôles concernés, la durée et le délai
 * d'avertissement sont portés par config/services.yaml et .env ; la hiérarchie des rôles de
 * security.yaml est résolue.
 *
 * Un compte concerné dont la date de dernier changement est inconnue est considéré comme expiré :
 * c'est aussi le moyen d'imposer un renouvellement immédiat (R26, cf. ExpirePasswordsCommand).
 */
final class PasswordExpirationPolicy
{
    /**
     * @param string[] $expirationRoles
     */
    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly array $expirationRoles,
        private readonly int $expirationMonths,
        private readonly int $warningDays
    ) {
    }

    /**
     * Une durée nulle ou négative désactive l'expiration.
     */
    public function appliesTo(UserInterface $user): bool
    {
        if (!$user instanceof User || $this->expirationMonths <= 0) {
            return false;
        }

        $reachableRoles = $this->roleHierarchy->getReachableRoleNames($user->getRoles());

        return [] !== array_intersect($reachableRoles, $this->expirationRoles);
    }

    public function isExpired(UserInterface $user, ?\DateTimeInterface $now = null): bool
    {
        if (!$this->appliesTo($user)) {
            return false;
        }

        $expirationDate = $this->getExpirationDate($user);

        return null === $expirationDate || $this->now($now) >= $expirationDate;
    }

    /**
     * Date d'expiration du mot de passe, ou null si le compte n'est pas concerné ou si la date de
     * dernier changement est inconnue.
     */
    public function getExpirationDate(UserInterface $user): ?\DateTimeImmutable
    {
        if (!$user instanceof User || !$this->appliesTo($user) || null === $user->getPasswordChangedAt()) {
            return null;
        }

        return \DateTimeImmutable::createFromMutable($user->getPasswordChangedAt())
            ->modify(sprintf('+%d months', $this->expirationMonths));
    }

    /**
     * Nombre de jours avant l'expiration lorsqu'elle entre dans le délai d'avertissement, sinon null
     * (compte non concerné, échéance lointaine ou mot de passe déjà expiré).
     */
    public function getDaysBeforeExpiration(UserInterface $user, ?\DateTimeInterface $now = null): ?int
    {
        $expirationDate = $this->getExpirationDate($user);
        $now = $this->now($now);

        if (null === $expirationDate || $now >= $expirationDate) {
            return null;
        }

        $days = (int) ceil(($expirationDate->getTimestamp() - $now->getTimestamp()) / 86400);

        return $days <= $this->warningDays ? $days : null;
    }

    private function now(?\DateTimeInterface $now): \DateTimeImmutable
    {
        return null === $now ? new \DateTimeImmutable() : \DateTimeImmutable::createFromInterface($now);
    }
}
