<?php

declare(strict_types=1);

namespace App\Tests\Security\Ip;

use App\Entity\User;
use App\Security\Ip\IpFilterPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Reco-18 : les interfaces d'administration ne sont accessibles qu'depuis les réseaux des
 * organismes concernés. Les partenaires externes ne sont pas filtrés.
 */
class IpFilterPolicyTest extends TestCase
{
    private const ROLES = [
        'administrateurs' => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'],
        'region'          => ['ROLE_CLIENT'],
        'docaposte'       => ['ROLE_INSTRUCTEUR', 'ROLE_INSTRUCTEUR_UP'],
    ];

    private const RESEAUX = [
        'administrateurs' => ['203.0.113.7'],
        'region'          => ['195.10.20.0/24'],
        'docaposte'       => ['198.51.100.0/24', '2001:db8:cafe::/48'],
    ];

    /**
     * @dataProvider provideAcces
     */
    public function testLAccesDependDuReseauEtDuProfil(string $role, ?string $ip, bool $attendu): void
    {
        self::assertSame($attendu, $this->politique()->autorise($this->compte($role), $ip));
    }

    public static function provideAcces(): iterable
    {
        yield 'administrateur depuis son adresse' => ['ROLE_ADMIN', '203.0.113.7', true];
        yield 'administrateur depuis ailleurs' => ['ROLE_ADMIN', '203.0.113.8', false];
        yield 'Région dans sa plage' => ['ROLE_CLIENT', '195.10.20.34', true];
        yield 'Région en limite haute de sa plage' => ['ROLE_CLIENT', '195.10.20.255', true];
        yield 'Région hors de sa plage' => ['ROLE_CLIENT', '195.10.21.1', false];
        yield 'Docaposte dans sa plage' => ['ROLE_INSTRUCTEUR', '198.51.100.12', true];
        yield 'Docaposte en IPv6' => ['ROLE_INSTRUCTEUR_UP', '2001:db8:cafe:1::5', true];
        yield 'Docaposte depuis la plage de la Région' => ['ROLE_INSTRUCTEUR', '195.10.20.34', false];
        yield 'adresse inconnue' => ['ROLE_ADMIN', null, false];
        yield 'adresse vide' => ['ROLE_ADMIN', '', false];
        // Profils externes : ils se connectent de n'importe où, le filtrage ne les concerne pas
        yield 'conseiller' => ['ROLE_CONSEILLER', '81.250.1.1', true];
        yield 'auditeur' => ['ROLE_AUDITEUR', '81.250.1.1', true];
        yield 'bénéficiaire' => ['ROLE_MEMBER', '81.250.1.1', true];
    }

    public function testUnCompteRelevantDeDeuxGroupesEstAccepteParLUnOuLAutre(): void
    {
        $compte = new User();
        $compte->setUsername('double');
        $compte->setRoles(['ROLE_ADMIN', 'ROLE_INSTRUCTEUR']);

        self::assertTrue($this->politique()->autorise($compte, '203.0.113.7'), 'Réseau des administrateurs');
        self::assertTrue($this->politique()->autorise($compte, '198.51.100.12'), 'Réseau de Docaposte');
        self::assertFalse($this->politique()->autorise($compte, '195.10.20.34'), 'Réseau de la Région');
        self::assertSame(['administrateurs', 'docaposte'], $this->politique()->groupes($compte));
    }

    public function testUneListeVideDesactiveLeFiltrageDuGroupe(): void
    {
        $politique = new IpFilterPolicy(
            ['administrateurs' => [], 'region' => ['195.10.20.0/24'], 'docaposte' => []],
            self::ROLES
        );

        $administrateur = $this->compte('ROLE_ADMIN');
        self::assertFalse($politique->estFiltre($administrateur), 'Aucun réseau configuré : pas de filtrage');
        self::assertTrue($politique->autorise($administrateur, '81.250.1.1'));

        self::assertTrue($politique->estFiltre($this->compte('ROLE_CLIENT')));
        self::assertFalse($politique->autorise($this->compte('ROLE_CLIENT'), '81.250.1.1'));
    }

    public function testSansAucuneConfigurationPersonneNEstEnferme(): void
    {
        $politique = new IpFilterPolicy([], self::ROLES);

        foreach (['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_CLIENT', 'ROLE_INSTRUCTEUR'] as $role) {
            self::assertTrue($politique->autorise($this->compte($role), '81.250.1.1'), $role);
        }
    }

    /**
     * Ce que le conteneur injecte réellement lorsque la variable d'environnement est vide.
     *
     * Le processeur « csv: » s'appuie sur str_getcsv(), qui rend [null] pour une chaîne vide.
     * Le cas n'était pas couvert : les tests ci-dessus construisent la politique avec de vrais
     * tableaux vides, que le conteneur ne produit jamais. En recette, le 06/11/2026, un
     * SECURITY_IP_FILTER_ADMINISTRATEURS vide levait « IpUtils::checkIp4(): Argument #2 ($ip)
     * must be of type string, null given ».
     *
     * @dataProvider provideListesVides
     *
     * @param list<string|null> $liste
     */
    public function testUneListeVidePeuImporteSaFormeDesactiveLeFiltrage(array $liste): void
    {
        $politique = new IpFilterPolicy(['administrateurs' => $liste], self::ROLES);
        $administrateur = $this->compte('ROLE_ADMIN');

        self::assertFalse($politique->estFiltre($administrateur));
        self::assertTrue($politique->autorise($administrateur, '81.250.1.1'));
    }

    public static function provideListesVides(): iterable
    {
        yield 'tableau vide' => [[]];
        yield 'variable vide via le processeur csv:' => [str_getcsv('')];
        yield 'chaîne vide' => [['']];
        yield 'espaces seuls' => [['   ']];
        yield 'virgule esseulée' => [str_getcsv(',')];
    }

    public function testLesEntreesVidesNEmpechentPasLesReseauxValablesDeFiltrer(): void
    {
        // Une liste mal saisie — « 203.0.113.7, » — ne doit ni lever d'erreur ni ouvrir l'accès.
        $politique = new IpFilterPolicy(['administrateurs' => str_getcsv('203.0.113.7,')], self::ROLES);
        $administrateur = $this->compte('ROLE_ADMIN');

        self::assertTrue($politique->estFiltre($administrateur));
        self::assertTrue($politique->autorise($administrateur, '203.0.113.7'));
        self::assertFalse($politique->autorise($administrateur, '81.250.1.1'));
    }

    private function politique(): IpFilterPolicy
    {
        return new IpFilterPolicy(self::RESEAUX, self::ROLES);
    }

    private function compte(string $role): User
    {
        $compte = new User();
        $compte->setUsername('test');
        $compte->setRoles([$role]);

        return $compte;
    }
}
