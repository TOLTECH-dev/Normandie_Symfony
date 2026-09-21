<?php

declare(strict_types=1);

namespace App\Tests\Security\Authorization;

use PHPUnit\Framework\TestCase;

/**
 * Vulnérabilité 6 / recommandation 10 de l'audit ALMOND.
 *
 * Le pare-feu n'exige qu'un rôle back-office quelconque sur /admin : sans attribut, un écran est donc
 * ouvert à tous les profils. Ce test impose un contrôle explicite sur chaque écran, hors exceptions
 * justifiées ci-dessous, et vérifie qu'aucun rôle cité n'est inconnu (faute de frappe).
 */
class RouteAccessCoverageTest extends TestCase
{
    /** Écrans sans attribut, et la raison pour laquelle ils n'en portent pas */
    private const EXCEPTIONS = [
        'admin_user_login' => 'page de connexion, accessible avant authentification',
        'admin_password_change' => 'changement de son propre mot de passe, ouvert à tout compte connecté',
        'bo_dashboard' => 'servi par le contrôleur de l accueil public : un attribut y fermerait aussi le site public',
        'demande_document_bo' => 'accès vérifié dossier par dossier par DocumentAccessChecker',
    ];

    private AccessMatrix $matrice;

    protected function setUp(): void
    {
        $this->matrice = new AccessMatrix(\dirname(__DIR__, 3));
    }

    /**
     * @dataProvider provideRoutesBackOffice
     */
    public function testChaqueEcranDuBackOfficePorteUnControleExplicite(string $route, string $classe, string $methode): void
    {
        if (isset(self::EXCEPTIONS[$route])) {
            self::assertTrue(true);

            return;
        }

        self::assertNotSame([], $this->matrice->rolesExiges($classe, $methode), sprintf(
            'L écran %s (%s::%s) n impose aucun rôle : il est donc ouvert à tous les profils du back-office. '
            . 'Ajouter un attribut, ou l inscrire dans les exceptions avec sa justification.',
            $route,
            $classe,
            $methode
        ));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideRoutesBackOffice(): iterable
    {
        foreach ((new AccessMatrix(\dirname(__DIR__, 3)))->routes('backOffice') as $route => [$classe, $methode]) {
            yield $route => [$route, $classe, $methode];
        }
    }

    public function testLesExceptionsCorrespondentToutesAUneRouteExistante(): void
    {
        $routes = $this->matrice->routes('backOffice');

        self::assertSame([], array_diff(array_keys(self::EXCEPTIONS), array_keys($routes)));
    }

    /**
     * Un regroupement mal orthographié n'accorde aucun droit et passerait inaperçu.
     */
    public function testTousLesRolesCitesSontDeclaresDansLaHierarchie(): void
    {
        $connus = $this->matrice->rolesConnus();
        $inconnus = [];

        foreach (['backOffice', 'frontOffice', 'main'] as $fichier) {
            foreach ($this->matrice->routes($fichier) as $route => [$classe, $methode]) {
                if (!class_exists($classe)) {
                    continue; // route cassée, suivie par testAucuneRouteNePointeVersUnControleurInexistant
                }
                foreach ($this->matrice->rolesExiges($classe, $methode) as $role) {
                    if (!in_array($role, $connus, true)) {
                        $inconnus[] = $route . ' : ' . $role;
                    }
                }
            }
        }

        self::assertSame([], array_unique($inconnus));
    }

    /**
     * Les regroupements introduits pour le lot 2 doivent rester définis : les retirer ouvrirait ou
     * fermerait silencieusement des écrans.
     */
    public function testLesRegroupementsFonctionnelsExistent(): void
    {
        $connus = $this->matrice->rolesConnus();

        foreach (['ROLE_APP_UTILISATEUR', 'ROLE_BO_GESTION_REGION', 'ROLE_BO_ESPACE_CONSEILLER',
                     'ROLE_BO_INSTRUCTION', 'ROLE_BO_INSTRUCTION_ETENDUE', 'ROLE_BO_DOSSIER_CONSULTATION'] as $groupe) {
            self::assertContains($groupe, $connus);
        }
    }

    /**
     * Une route dont le contrôleur n'existe pas renvoie une erreur serveur. `fos_user_profile_show`
     * est un vestige de la migration FOSUserBundle, signalé au client le 16/09/2026 : à supprimer
     * ou à réimplémenter. Ce test échoue si une nouvelle route cassée apparaît.
     */
    public function testAucuneRouteNePointeVersUnControleurInexistant(): void
    {
        $cassees = [];
        foreach (['backOffice', 'frontOffice', 'main'] as $fichier) {
            foreach ($this->matrice->routes($fichier) as $route => [$classe, $methode]) {
                if (!class_exists($classe)) {
                    $cassees[$route] = $classe;
                }
            }
        }

        self::assertSame(['fos_user_profile_show' => 'App' . chr(92) . 'Controller' . chr(92) . 'UserController'], $cassees);
    }
}
