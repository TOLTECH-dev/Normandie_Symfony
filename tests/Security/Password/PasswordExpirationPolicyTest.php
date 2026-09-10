<?php

namespace App\Tests\Security\Password;

use App\Entity\User;
use App\Security\Password\PasswordExpirationPolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * Expiration périodique des mots de passe (Reco-15) : comptes à privilèges uniquement
 * (ANSSI PG-078 R24/R25), 12 mois, avertissement dans les 30 derniers jours.
 */
class PasswordExpirationPolicyTest extends TestCase
{
    private const NOW = '2026-09-10 12:00:00';

    public function testUnProfilNonConcerneNExpireJamais(): void
    {
        $user = $this->user(['ROLE_CONSEILLER'], null);

        $this->assertFalse($this->policy()->appliesTo($user));
        $this->assertFalse($this->policy()->isExpired($user, $this->now()));
        $this->assertNull($this->policy()->getDaysBeforeExpiration($user, $this->now()));
    }

    public function testLaHierarchieDesRolesEstPriseEnCompte(): void
    {
        $this->assertTrue($this->policy()->appliesTo($this->user(['ROLE_SUPER_ADMIN'], null)));
    }

    public function testUnMotDePasseRecentNEstPasExpire(): void
    {
        $user = $this->user(['ROLE_ADMIN'], '2026-03-10 12:00:00');

        $this->assertFalse($this->policy()->isExpired($user, $this->now()));
        $this->assertSame('2027-03-10', $this->policy()->getExpirationDate($user)->format('Y-m-d'));
    }

    public function testUnMotDePasseDeDouzeMoisEstExpire(): void
    {
        $this->assertTrue($this->policy()->isExpired($this->user(['ROLE_CLIENT'], '2025-09-10 12:00:00'), $this->now()));
    }

    /**
     * Date inconnue : compte ancien non initialisé, ou expiration imposée par la commande R26.
     */
    public function testUnCompteConcerneSansDateConnueEstExpire(): void
    {
        $this->assertTrue($this->policy()->isExpired($this->user(['ROLE_ADMIN'], null), $this->now()));
    }

    public function testLAvertissementNApparaitQueDansLesTrenteDerniersJours(): void
    {
        $this->assertSame(15, $this->policy()->getDaysBeforeExpiration($this->user(['ROLE_ADMIN'], '2025-09-25 12:00:00'), $this->now()));
        $this->assertNull($this->policy()->getDaysBeforeExpiration($this->user(['ROLE_ADMIN'], '2026-01-01 12:00:00'), $this->now()));
        $this->assertNull($this->policy()->getDaysBeforeExpiration($this->user(['ROLE_ADMIN'], '2025-01-01 12:00:00'), $this->now()));
    }

    public function testUneDureeNulleDesactiveLExpiration(): void
    {
        $user = $this->user(['ROLE_ADMIN'], null);

        $this->assertFalse($this->policy(0)->appliesTo($user));
        $this->assertFalse($this->policy(0)->isExpired($user, $this->now()));
    }

    private function policy(int $months = 12): PasswordExpirationPolicy
    {
        return new PasswordExpirationPolicy(
            new RoleHierarchy(['ROLE_SUPER_ADMIN' => ['ROLE_ADMIN']]),
            ['ROLE_ADMIN', 'ROLE_CLIENT'],
            $months,
            30
        );
    }

    private function user(array $roles, ?string $passwordChangedAt): User
    {
        $user = new User();
        $user->setUsername('compte-de-test');
        $user->setRoles($roles);
        $user->setPasswordChangedAt(null === $passwordChangedAt ? null : new \DateTime($passwordChangedAt));

        return $user;
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }
}
