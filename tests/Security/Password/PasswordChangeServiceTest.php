<?php

namespace App\Tests\Security\Password;

use App\Entity\User;
use App\Entity\UserPasswordHistory;
use App\Repository\UserPasswordHistoryRepository;
use App\Security\Password\PasswordChangeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

/**
 * Contrôles liés au compte lors du choix d'un mot de passe (Reco-15, ANSSI PG-078 R27) et
 * enregistrement : date, historique, abandon de l'ancien sel.
 *
 * Hachage réel, avec la configuration "auto" de security.yaml au coût minimal.
 */
class PasswordChangeServiceTest extends TestCase
{
    private PasswordHasherFactory $hasherFactory;

    protected function setUp(): void
    {
        $this->hasherFactory = new PasswordHasherFactory([
            User::class => [
                'algorithm'        => 'auto',
                'migrate_from'     => [],
                'hash_algorithm'   => 'sha512',
                'key_length'       => 40,
                'ignore_case'      => false,
                'encode_as_base64' => true,
                'iterations'       => 5000,
                'cost'             => 4,
                'memory_cost'      => null,
                'time_cost'        => null,
            ],
        ]);
    }

    public function testUnMotDePasseContenantUneDonneePersonnelleEstRefuse(): void
    {
        $user = $this->user();

        foreach (['Mdp-JDupont-2026!', 'jean.dupont-2026!X', 'Robuste-Dupont-9!'] as $password) {
            $errors = $this->service()->validate($user, $password);

            $this->assertCount(1, $errors, $password);
            $this->assertStringContainsString('identifiant', $errors[0]);
        }
    }

    public function testUneDonneePersonnelleTropCourteNEstPasRecherchee(): void
    {
        $user = $this->user();
        $user->setFirstname('Al');

        $this->assertSame([], $this->service()->validate($user, 'Alpha-Robuste-2026!'));
    }

    public function testLeMotDePasseActuelNePeutPasEtreReutilise(): void
    {
        $user = $this->user();
        $user->setPassword($this->hash('Actuel-Robuste-2026!'));

        $errors = $this->service()->validate($user, 'Actuel-Robuste-2026!');

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('déjà été utilisé', $errors[0]);
    }

    /**
     * Comptes non encore migrés : empreinte SHA-512 héritée de FOSUserBundle, vérifiée avec le sel.
     */
    public function testUneAncienneEmpreinteAvecSelEstReconnue(): void
    {
        $user = $this->user();
        $user->setSalt('sel-herite-de-fosuser');
        $user->setPassword($this->legacyHash('Ancien-Robuste-2024!', 'sel-herite-de-fosuser'));

        $this->assertCount(1, $this->service()->validate($user, 'Ancien-Robuste-2024!'));
    }

    public function testUnMotDePasseDeLHistoriqueEstRefuseEtUnNouveauAccepte(): void
    {
        $user = $this->user();
        $user->setPassword($this->hash('Actuel-Robuste-2026!'));
        $service = $this->service([$this->hash('Ancien-Robuste-2025!')]);

        $this->assertCount(1, $service->validate($user, 'Ancien-Robuste-2025!'));
        $this->assertSame([], $service->validate($user, 'Nouveau-Robuste-2026!'));
    }

    public function testLeChangementHacheDateEtHistoriseLeMotDePasse(): void
    {
        $user = $this->user();
        $user->setSalt('sel-herite-de-fosuser');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->callback(
            static fn ($entity): bool => $entity instanceof UserPasswordHistory && $entity->getPassword() === $user->getPassword()
        ));
        $entityManager->expects($this->once())->method('flush');

        $repository = $this->createMock(UserPasswordHistoryRepository::class);
        $repository->expects($this->once())->method('purgeOlderEntries')->with($user, 5);

        (new PasswordChangeService($entityManager, $this->hasherFactory, $repository, 5))->change($user, 'Nouveau-Robuste-2026!');

        $this->assertNull($user->getSalt());
        $this->assertNotNull($user->getPasswordChangedAt());
        $this->assertStringNotContainsString('Nouveau-Robuste-2026!', $user->getPassword());
        $this->assertTrue($this->hasherFactory->getPasswordHasher($user)->verify($user->getPassword(), 'Nouveau-Robuste-2026!'));
    }

    public function testSansHistoriqueAucuneEmpreinteNEstConservee(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $repository = $this->createMock(UserPasswordHistoryRepository::class);

        (new PasswordChangeService($entityManager, $this->hasherFactory, $repository, 0))->change($this->user(), 'Nouveau-Robuste-2026!');

        $this->addToAssertionCount(1);
    }

    /**
     * @param string[] $history empreintes renvoyées par l'historique
     */
    private function service(array $history = []): PasswordChangeService
    {
        $repository = $this->createMock(UserPasswordHistoryRepository::class);
        $repository->method('findLatestPasswords')->willReturn($history);

        return new PasswordChangeService($this->createMock(EntityManagerInterface::class), $this->hasherFactory, $repository, 5);
    }

    private function user(): User
    {
        $user = new User();
        $user->setUsername('jdupont');
        $user->setEmail('jean.dupont@example.org');
        $user->setFirstname('Jean');
        $user->setLastname('Dupont');
        $user->setRoles(['ROLE_ADMIN']);

        return $user;
    }

    private function hash(string $plainPassword): string
    {
        return $this->hasherFactory->getPasswordHasher(User::class)->hash($plainPassword);
    }

    /**
     * Empreinte au format FOSUserBundle : SHA-512, 5000 itérations, base64.
     */
    private function legacyHash(string $plainPassword, string $salt): string
    {
        $salted = $plainPassword . '{' . $salt . '}';
        $digest = hash('sha512', $salted, true);
        for ($i = 1; $i < 5000; $i++) {
            $digest = hash('sha512', $digest . $salted, true);
        }

        return base64_encode($digest);
    }
}
