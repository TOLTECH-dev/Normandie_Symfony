<?php

namespace App\Tests\Security\TwoFactor;

use App\Entity\User;
use App\Security\TwoFactor\MfaPolicy;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Périmètre du MFA préconisé par le rapport d'audit ALMOND NOR005-22 : profils
 * Administrateur, Instructeur et Client.
 *
 * Le service est lu dans le conteneur : le test porte sur la configuration réelle
 * (paramètre "security_mfa_roles" et hiérarchie des rôles de security.yaml).
 */
class MfaPolicyTest extends KernelTestCase
{
    /**
     * @dataProvider profilsSoumisAuMfa
     */
    public function testLesProfilsAdministrateurInstructeurEtClientSontSoumisAuMfa(string $role): void
    {
        $this->assertTrue($this->policy()->isRequiredFor($this->user([$role])));
    }

    public function profilsSoumisAuMfa(): iterable
    {
        yield 'Super administrateur' => [User::PARAM_ROLE_SUPER_ADMIN];
        yield 'Administrateur' => [User::PARAM_ROLE_ADMIN];
        yield 'Instructeur' => [User::PARAM_ROLE_INSTRUCTEUR];
        yield 'Instructeur UP' => [User::PARAM_ROLE_INSTRUCTEUR_UP];
        yield 'Client' => [User::PARAM_ROLE_CLIENT];
    }

    /**
     * @dataProvider profilsNonConcernes
     */
    public function testLesAutresProfilsNeSontPasSoumisAuMfa(string $role): void
    {
        $this->assertFalse($this->policy()->isRequiredFor($this->user([$role])));
    }

    public function profilsNonConcernes(): iterable
    {
        yield 'Bénéficiaire' => [User::PARAM_ROLE_MEMBER];
        yield 'Conseiller' => [User::PARAM_ROLE_CONSEILLER];
        yield 'Auditeur' => [User::PARAM_ROLE_AUDITEUR];
        yield 'Rénovateur' => [User::PARAM_ROLE_RENOVATEUR];
        yield 'EPCI' => [User::PARAM_ROLE_EPCI];
        yield 'Automate' => [User::PARAM_ROLE_AUTOMATE];
        yield 'Technique' => [User::PARAM_ROLE_TECHNIQUE];
    }

    public function testUnCompteCumulantPlusieursRolesEstSoumisDesQuUnSeulEstConcerne(): void
    {
        $this->assertTrue($this->policy()->isRequiredFor(
            $this->user([User::PARAM_ROLE_CONSEILLER, User::PARAM_ROLE_INSTRUCTEUR])
        ));
    }

    private function policy(): MfaPolicy
    {
        self::bootKernel();

        return self::getContainer()->get(MfaPolicy::class);
    }

    private function user(array $roles): User
    {
        $user = new User();
        $user->setUsername('compte-de-test');
        $user->setRoles($roles);

        return $user;
    }
}
