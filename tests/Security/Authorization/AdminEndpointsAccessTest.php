<?php

declare(strict_types=1);

namespace App\Tests\Security\Authorization;

use PHPUnit\Framework\TestCase;

/**
 * Vulnérabilité 6 / recommandation 10 de l'audit ALMOND : tout profil authentifié pouvait lister les
 * comptes (identifiant de connexion, nom, courriel, rôle) via le point d'entrée d'administration.
 *
 * Les attributs citent des regroupements : le test raisonne sur les profils réellement autorisés,
 * après résolution par la hiérarchie des rôles.
 */
class AdminEndpointsAccessTest extends TestCase
{
    /** Profils habilités à administrer les comptes */
    private const PROFILS_ADMINISTRATION = ['ROLE_ADMIN', 'ROLE_CLIENT', 'ROLE_SUPER_ADMIN'];

    private AccessMatrix $matrice;

    protected function setUp(): void
    {
        $this->matrice = new AccessMatrix(\dirname(__DIR__, 3));
    }

    /**
     * @dataProvider provideRoutesDAdministrationDesComptes
     */
    public function testLAdministrationDesComptesResteReserveeAuxProfilsHabilites(string $route, string $methode): void
    {
        $profils = $this->matrice->profilsAutorises($this->matrice->rolesExiges('App\Controller\AdminController', $methode));

        self::assertNotSame([], $profils, sprintf('La route %s n impose aucun contrôle de rôle.', $route));
        self::assertSame([], array_values(array_diff($profils, self::PROFILS_ADMINISTRATION)), sprintf(
            'La route %s est ouverte à des profils non habilités : %s',
            $route,
            implode(', ', array_diff($profils, self::PROFILS_ADMINISTRATION))
        ));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRoutesDAdministrationDesComptes(): iterable
    {
        foreach ((new AccessMatrix(\dirname(__DIR__, 3)))->routes('backOffice') as $route => [$classe, $methode]) {
            if (str_ends_with($classe, 'AdminController')) {
                yield $route => [$route, $methode];
            }
        }
    }

    /**
     * Le défaut relevé par l'audit : la liste au format JSON était ouverte aux huit profils du
     * back-office, alors que la page qui l'affiche ne l'est qu'aux profils habilités.
     */
    public function testLaListeJsonEtLaPageQuiLAfficheOntLesMemesDroits(): void
    {
        $classe = 'App\Controller\AdminController';

        self::assertSame(
            $this->matrice->profilsAutorises($this->matrice->rolesExiges($classe, 'list')),
            $this->matrice->profilsAutorises($this->matrice->rolesExiges($classe, 'listAjax'))
        );
    }
}
