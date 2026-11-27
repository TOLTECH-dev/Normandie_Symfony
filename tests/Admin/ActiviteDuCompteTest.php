<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Tests\Security\Authorization\AccessMatrix;
use PHPUnit\Framework\TestCase;

/**
 * Onglet « Activité » de la fiche utilisateur (D26013, point 4).
 *
 * La Région veut lire, depuis la fiche d'un compte, tout ce que ce compte a
 * fait : la date, l'action, le dossier, l'état de ce dossier au moment de
 * l'action, et le remboursement éventuel. La table `historique_` porte déjà ces
 * colonnes.
 *
 * Trois choses doivent tenir dans le temps :
 *
 *  1. L'ONGLET EST RÉSERVÉ AUX ADMINISTRATEURS. L'écran reste ouvert aux autres
 *     profils de la Région, mais la chronologie retrace l'activité d'une
 *     personne : elle ne se montre pas à tout le monde.
 *
 *  2. L'HISTORIQUE N'EST PAS LU POUR LES AUTRES. Masquer l'onglet ne suffirait
 *     pas : deux requêtes partiraient pour rien sur une table de 411 000
 *     lignes, et les données seraient dans la page.
 *
 *  3. LA LECTURE EST PAGINÉE ET INDEXÉE. Un seul compte porte 57 000 actions.
 *     Sans pagination la page tombe ; sans l'index `auteur_date_idx` chaque
 *     affichage parcourt toute la table.
 */
class ActiviteDuCompteTest extends TestCase
{
    private static function racine(): string
    {
        return \dirname(__DIR__, 2);
    }

    private static function source(string $relatif): string
    {
        $chemin = self::racine() . '/' . $relatif;

        self::assertFileExists($chemin);

        $contenu = file_get_contents($chemin);
        self::assertIsString($contenu);

        return $contenu;
    }

    /**
     * Le regroupement de droits suit la convention posée par la VUL06 : l'écran
     * ne cite pas les profils, il cite le regroupement.
     */
    public function testLeSuiviDesComptesNEstAccordeQuAuxAdministrateurs(): void
    {
        $matrice = new AccessMatrix(self::racine());

        $autorises = [];

        foreach (AccessMatrix::PROFILS as $profil) {
            if (in_array('ROLE_BO_SUIVI_COMPTES', $matrice->rolesAtteignables($profil), true)) {
                $autorises[] = $profil;
            }
        }

        sort($autorises);

        self::assertSame(
            ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
            $autorises,
            'Seuls les administrateurs doivent atteindre ROLE_BO_SUIVI_COMPTES.'
        );
    }

    /**
     * Le profil Région consulte les comptes mais ne voit pas leur activité :
     * c'est la distinction que l'évolution introduit.
     */
    public function testLeProfilRegionConsulteLesComptesSansVoirLeurActivite(): void
    {
        $matrice = new AccessMatrix(self::racine());

        $atteignables = $matrice->rolesAtteignables('ROLE_CLIENT');

        self::assertContains('ROLE_BO_GESTION_REGION', $atteignables, 'Le profil Région garde l\'accès à la fiche.');
        self::assertNotContains('ROLE_BO_SUIVI_COMPTES', $atteignables);
    }

    public function testLHistoriqueNEstLuQuePourLesProfilsAutorises(): void
    {
        $source = self::source('src/Controller/AdminController.php');

        $position = strpos($source, 'public function view(');
        self::assertIsInt($position);

        $corps = substr($source, $position, 3000);

        $garde = strpos($corps, "isGranted('ROLE_BO_SUIVI_COMPTES')");
        $lecture = strpos($corps, 'compterLesActionsDe');

        self::assertIsInt($garde, 'La lecture doit être conditionnée au regroupement de droits.');
        self::assertIsInt($lecture);
        self::assertGreaterThan(
            $garde,
            $lecture,
            'L\'historique ne doit pas être interrogé avant d\'avoir vérifié le droit.'
        );
    }

    public function testLeGabaritNeRendLOngletQueSiLeSuiviEstAutorise(): void
    {
        $source = self::source('templates/BackOffice/Admin/view.html.twig');

        self::assertStringContainsString('{% if suivi_autorise %}', $source);

        $ouverture = strpos($source, '{% if suivi_autorise %}');
        $sinon = strpos($source, '{% else %}', $ouverture);
        $onglet = strpos($source, 'tab_activite');

        self::assertIsInt($ouverture);
        self::assertIsInt($sinon);
        self::assertIsInt($onglet);

        self::assertGreaterThan($ouverture, $onglet);
        self::assertLessThan($sinon, $onglet, 'L\'onglet doit être dans la branche autorisée, pas dans la branche de repli.');
    }

    /**
     * La branche de repli doit rendre la fiche telle qu'elle était : sans elle,
     * un profil Région verrait une page vide.
     */
    public function testLaFicheResteCompleteSansLOnglet(): void
    {
        $source = self::source('templates/BackOffice/Admin/view.html.twig');

        $sinon = strpos($source, '{% else %}');
        self::assertIsInt($sinon);

        $repli = substr($source, $sinon);

        self::assertStringContainsString('view_admin/identification.html.twig', $repli);
        self::assertStringContainsString('view_admin/profil.html.twig', $repli);
    }

    /**
     * Les cinq renseignements demandés par la Région.
     *
     * @dataProvider lesRenseignementsAttendus
     */
    public function testLaChronologiePorteCeQuiEstDemande(string $extrait, string $pourquoi): void
    {
        self::assertStringContainsString(
            $extrait,
            self::source('templates/BackOffice/Admin/inc/view_admin/activite.html.twig'),
            $pourquoi
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function lesRenseignementsAttendus(): array
    {
        return [
            'la date et l\'heure' => ['action.dateCreation|date(', 'La date de l\'action.'],
            'l\'action' => ['{{ action.action }}', 'Le libellé de l\'action.'],
            'le dossier' => ["path('demande_view', {'demandeId': action.demandeId})", 'Le dossier associé, cliquable.'],
            'l\'état du dossier' => ['{{ action.statutSlug }}', 'L\'état du dossier AU MOMENT de l\'action.'],
            'le remboursement' => ['action.remboursementId', 'Le remboursement éventuel.'],
        ];
    }

    /**
     * Du plus récent au plus ancien : c'est l'ordre demandé, et il n'est pas
     * garanti par la date seule — plusieurs actions tombent dans la même
     * seconde, l'identifiant les départage.
     */
    public function testLaLectureVaDuPlusRecentAuPlusAncienSansAmbiguite(): void
    {
        $source = self::source('src/Repository/Historique_Repository.php');

        $position = strpos($source, 'public function lesActionsDe');
        self::assertIsInt($position);

        $corps = substr($source, $position, 800);

        self::assertStringContainsString("orderBy('h.dateCreation', 'DESC')", $corps);
        self::assertStringContainsString("addOrderBy('h.id', 'DESC')", $corps);
        self::assertStringContainsString('setMaxResults($limite)', $corps);
        self::assertStringContainsString('setFirstResult($decalage)', $corps);
    }

    /**
     * L'index conditionne la tenue de l'écran : sans lui, chaque affichage
     * parcourt 411 000 lignes, deux fois.
     */
    public function testLIndexParAuteurEstLivreAvecLEcran(): void
    {
        $script = self::source('sql/d26013_historique_par_auteur.sql');

        self::assertStringContainsString('CREATE INDEX IF NOT EXISTS auteur_date_idx', $script);
        self::assertStringContainsString('historique_ (auteur_creation, date_creation)', $script);
    }
}
