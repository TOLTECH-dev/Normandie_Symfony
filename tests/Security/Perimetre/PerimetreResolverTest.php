<?php

declare(strict_types=1);

namespace App\Tests\Security\Perimetre;

use App\Entity\User;
use App\Repository\EPCI_Repository;
use App\Repository\Structure_Repository;
use App\Security\Perimetre\Perimetre;
use App\Security\Perimetre\PerimetreResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Vulnérabilité 6 / recommandation 10 : le périmètre d'un compte est déduit en un seul endroit.
 * Ces tests figent les règles historiques de déduction, reprises à l'identique.
 */
class PerimetreResolverTest extends TestCase
{
    /**
     * @dataProvider provideProfils
     *
     * @param list<string> $roles
     */
    public function testLeProfilSuitLOrdreHistoriqueDesRegles(array $roles, ?string $attendu): void
    {
        self::assertSame($attendu, (new Perimetre($roles, 'C00008', null, null))->profil());
    }

    public static function provideProfils(): iterable
    {
        yield 'conseiller' => [['ROLE_CONSEILLER', 'ROLE_USER'], 'ROLE_CONSEILLER'];
        yield 'conseiller et auditeur : le conseiller l emporte' => [['ROLE_AUDITEUR', 'ROLE_CONSEILLER'], 'ROLE_CONSEILLER'];
        yield 'auditeur et rénovateur : l auditeur l emporte' => [['ROLE_RENOVATEUR', 'ROLE_AUDITEUR'], 'ROLE_AUDITEUR'];
        yield 'EPCI et technique : l EPCI l emporte' => [['ROLE_TECHNIQUE', 'ROLE_EPCI'], 'ROLE_EPCI'];
        yield 'technique' => [['ROLE_TECHNIQUE'], 'ROLE_TECHNIQUE'];
        yield 'instructeur : aucun périmètre' => [['ROLE_INSTRUCTEUR'], null];
        yield 'administrateur : aucun périmètre' => [['ROLE_ADMIN'], null];
    }

    /**
     * @dataProvider provideIdentifiants
     */
    public function testLIdentifiantEstLeSuffixeDuNomDeCompte(string $username, int $identifiant, ?int $numerique): void
    {
        $perimetre = new Perimetre(['ROLE_AUDITEUR'], $username, null, null);

        self::assertSame($identifiant, $perimetre->identifiant());
        self::assertSame($numerique, $perimetre->identifiantNumerique());
    }

    public static function provideIdentifiants(): iterable
    {
        yield 'conseiller C00008' => ['C00008', 8, 8];
        yield 'auditeur A00245' => ['A00245', 245, 245];
        // Comptes du profil technique : suffixe non numérique, 0 ou aucun périmètre selon la règle
        yield 'nom sans suffixe numérique' => ['gleroux', 0, null];
        yield 'nom vide' => ['', 0, null];
    }

    public function testLaStructureDuConseillerEstResolueUneSeuleFois(): void
    {
        $structures = $this->createMock(Structure_Repository::class);
        $structures->expects(self::once())->method('findByConseillerId')->with(8)->willReturn(['id' => 12]);
        $epci = $this->createMock(EPCI_Repository::class);
        $epci->expects(self::never())->method('findByContactId');

        $resolver = new PerimetreResolver($structures, $epci, new TokenStorage());
        $perimetre = $resolver->pour(['ROLE_CONSEILLER'], 'C00008');

        self::assertSame(12, $perimetre->structureId());
        self::assertNull($perimetre->epciId());
        self::assertSame($perimetre, $resolver->pour(['ROLE_CONSEILLER'], 'C00008'), 'Résolution mémorisée');
    }

    public function testLEpciDuContactEstResolu(): void
    {
        $structures = $this->createMock(Structure_Repository::class);
        $structures->expects(self::never())->method('findByConseillerId');
        $epci = $this->createMock(EPCI_Repository::class);
        $epci->method('findByContactId')->with(2)->willReturn(['id' => 73]);

        $perimetre = (new PerimetreResolver($structures, $epci, new TokenStorage()))->pour(['ROLE_EPCI'], 'E00002');

        self::assertSame(73, $perimetre->epciId());
        self::assertNull($perimetre->structureId());
    }

    public function testUnConseillerSansStructureNaAucuneStructure(): void
    {
        $structures = $this->createMock(Structure_Repository::class);
        $structures->method('findByConseillerId')->willReturn(false);

        $perimetre = (new PerimetreResolver($structures, $this->createMock(EPCI_Repository::class), new TokenStorage()))
            ->pour(['ROLE_CONSEILLER'], 'C00999');

        self::assertNull($perimetre->structureId());
    }

    public function testLePerimetreDuCompteConnecteEstCeluiDuJeton(): void
    {
        $utilisateur = new User();
        $utilisateur->setUsername('R00015');
        $utilisateur->setRoles(['ROLE_RENOVATEUR']);
        $jetons = new TokenStorage();
        $jetons->setToken(new UsernamePasswordToken($utilisateur, 'backoffice', $utilisateur->getRoles()));

        $perimetre = (new PerimetreResolver($this->createMock(Structure_Repository::class), $this->createMock(EPCI_Repository::class), $jetons))
            ->pourUtilisateurConnecte();

        self::assertSame('ROLE_RENOVATEUR', $perimetre->profil());
        self::assertSame(15, $perimetre->identifiant());
    }

    public function testSansCompteConnecteLePerimetreEstVide(): void
    {
        $perimetre = (new PerimetreResolver($this->createMock(Structure_Repository::class), $this->createMock(EPCI_Repository::class), new TokenStorage()))
            ->pourUtilisateurConnecte();

        self::assertNull($perimetre->profil());
        self::assertSame(0, $perimetre->identifiant());
    }
}
