<?php

declare(strict_types=1);

namespace App\Tests\Template;

use App\Controller\HistoriqueController;
use App\Tests\Security\Authorization\AccessMatrix;
use PHPUnit\Framework\TestCase;

/**
 * Chronologie générale sur les deux écrans de liste (D26013).
 *
 * La liste des utilisateurs et celle des dossiers reçoivent un onglet
 * « Chronologie » qui montre la même matière — les 411 000 actions
 * enregistrées — l'une du point de vue des personnes, l'autre des dossiers.
 *
 * Deux exigences commandent toute la mise en œuvre :
 *
 *  1. RIEN N'EST LU TANT QU'ON NE CONSULTE PAS. Charger la chronologie avec la
 *     page ferait payer à chaque ouverture de liste le prix d'une table qu'on
 *     ne regardera peut-être pas.
 *
 *  2. LE COÛT D'UNE TRANCHE NE DÉPEND PAS DE SA PROFONDEUR. D'où un curseur
 *     plutôt qu'un décalage, et un tri sur la clé primaire plutôt que sur une
 *     date non indexée — mesure du 24/11/2026 : la version avec jointure et tri
 *     par date ne rendait pas la main.
 */
class ChronologieGeneraleTest extends TestCase
{
    private static function source(string $relatif): string
    {
        $chemin = \dirname(__DIR__, 2) . '/' . $relatif;

        self::assertFileExists($chemin);

        $contenu = file_get_contents($chemin);
        self::assertIsString($contenu);

        return $contenu;
    }

    /**
     * L'accès effectif est éprouvé par AccessMatrixKernelTest, qui confronte la
     * référence au conteneur réel. Ce contrôle-ci fixe l'INTENTION : la route
     * existe, et la référence ne l'ouvre qu'aux administrateurs. Si quelqu'un
     * élargit l'attribut du contrôleur, c'est l'autre test qui le dira.
     */
    public function testLaRouteEstReserveeAuxAdministrateurs(): void
    {
        $matrice = new AccessMatrix(\dirname(__DIR__, 2));

        self::assertArrayHasKey(
            'historique_timeline',
            $matrice->routes('backOffice'),
            'La route doit être déclarée.'
        );

        $reference = json_decode(self::source('tests/Security/Authorization/fixtures/matrice_acces.json'), true);

        self::assertSame(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], $reference['historique_timeline'] ?? null);
    }

    /**
     * La tranche est lue avec une ligne de plus : sa présence dit qu'il reste
     * quelque chose, sans compter 411 000 lignes à chaque appel.
     */
    public function testLaSuiteSeDevineSansCompterLaTableEntiere(): void
    {
        $source = self::source('src/Controller/HistoriqueController.php');

        self::assertSame(50, HistoriqueController::ACTIONS_PAR_TRANCHE);

        $position = strpos($source, 'public function timeline');
        self::assertIsInt($position);

        $corps = substr($source, $position);

        self::assertStringContainsString('self::ACTIONS_PAR_TRANCHE + 1', $corps);
        self::assertStringContainsString('array_slice($lignes, 0, self::ACTIONS_PAR_TRANCHE)', $corps);
        self::assertStringNotContainsString('COUNT', $corps);
        self::assertStringContainsString("#[Security(\"is_granted('ROLE_BO_SUIVI_COMPTES')\")]", substr($source, 0, $position));
    }

    /**
     * Curseur et non décalage, clé primaire et non date.
     */
    public function testLaLectureEstParCurseurEtSuitLaClePrimaire(): void
    {
        $source = self::source('src/Repository/Historique_Repository.php');

        $position = strpos($source, 'public function lesDernieresActions');
        self::assertIsInt($position);

        $corps = substr($source, $position, 1600);

        self::assertStringContainsString('WHERE id < :avant', $corps);
        self::assertStringContainsString('ORDER BY id DESC LIMIT :limite', $corps);
        self::assertStringNotContainsString('OFFSET', $corps);
        self::assertStringNotContainsString('ORDER BY date_creation', $corps);
    }

    /**
     * La jointure sur `user` est interdite : `username` n'est pas indexé, et
     * l'optimiseur y ajoutait un tri temporaire sur 403 000 lignes.
     */
    public function testLesAuteursNeSontPasResolusParJointure(): void
    {
        $source = self::source('src/Repository/Historique_Repository.php');

        self::assertStringNotContainsString(
            'LEFT JOIN user',
            $source,
            'Résoudre les auteurs par jointure rend la chronologie inutilisable : user.username n\'est pas indexé.'
        );

        self::assertStringContainsString('private function avecLesAuteurs(array $lignes): array', $source);
        self::assertStringContainsString('WHERE username IN (:logins)', $source);
    }

    /**
     * Le panneau part vide : la première tranche n'arrive qu'à l'ouverture de
     * l'onglet.
     *
     * @dataProvider lesEcransDeListe
     */
    public function testLaChronologieNEstPasChargeeAvecLaPage(string $gabarit): void
    {
        $source = self::source($gabarit);

        self::assertStringContainsString('timeline_panneau.html.twig', $source);
        self::assertStringNotContainsString(
            'timeline_tranche.html.twig',
            $source,
            'Aucune tranche ne doit être rendue avec la page.'
        );

        $panneau = self::source('templates/BackOffice/Historique/inc/timeline_panneau.html.twig');

        self::assertStringContainsString('<ol class="activite-timeline timeline-liste"></ol>', $panneau, 'La liste arrive vide.');
        self::assertStringNotContainsString('{% for', $panneau, 'Le panneau ne boucle sur rien : il n\'a rien à afficher.');
    }

    /**
     * @dataProvider lesEcransDeListe
     */
    public function testLOngletEtSonPanneauSontReservesAuxAdministrateurs(string $gabarit): void
    {
        $source = self::source($gabarit);

        self::assertSame(
            3,
            substr_count($source, "is_granted('ROLE_BO_SUIVI_COMPTES')"),
            'Trois emplois attendus : la barre d\'onglets, la classe du conteneur, et le panneau.'
        );

        $panneau = strpos($source, 'timeline_panneau.html.twig');
        self::assertIsInt($panneau);

        self::assertStringContainsString(
            "{% if is_granted('ROLE_BO_SUIVI_COMPTES') %}",
            substr($source, max(0, $panneau - 400), 400),
            'Le panneau doit être sous la garde.'
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function lesEcransDeListe(): array
    {
        return [
            'liste des utilisateurs' => ['templates/BackOffice/Admin/list.html.twig'],
            'liste des dossiers' => ['templates/BackOffice/Demande/listAll.html.twig'],
        ];
    }

    /**
     * Pas de gestionnaire en ligne : la politique de sécurité du contenu les
     * refuse. L'adresse passe par un attribut de données.
     */
    public function testLeChargementSeFaitSansScriptEnLigne(): void
    {
        $panneau = self::source('templates/BackOffice/Historique/inc/timeline_panneau.html.twig');

        self::assertStringContainsString("data-url=\"{{ path('historique_timeline') }}\"", $panneau);
        self::assertStringNotContainsString('onclick', $panneau);
        self::assertStringNotContainsString('<script', $panneau);

        $script = self::source('assets/js/backOffice/historique/timelineGlobale/index.js');

        self::assertStringContainsString("on('shown.bs.tab'", $script, 'La première tranche part à l\'ouverture de l\'onglet.');
        self::assertStringContainsString('premiereOuverture', $script, 'Une seule fois : rouvrir l\'onglet ne doit pas recharger.');
        self::assertStringContainsString("data('avant', reponse.suivant)", $script, 'Le curseur rendu par le serveur est reporté sur le bouton.');
    }
}
