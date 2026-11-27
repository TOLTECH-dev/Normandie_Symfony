<?php

declare(strict_types=1);

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;

/**
 * L'export des dossiers ne porte plus l'identité du bénéficiaire (24/11/2026).
 *
 * Le nom, le prénom et le courriel ont été retirés : le fichier circule hors de
 * la plateforme, et le numéro de dossier suffit à retrouver la personne dans
 * l'application.
 *
 * Le retrait se joue à trois endroits qu'il faut garder cohérents — deux
 * tableaux parallèles (en-têtes et données) et six listes par profil. En
 * oublier un ne produit aucune erreur : les en-têtes et les lignes se
 * décaleraient simplement d'une colonne, ce qui ne se voit qu'en ouvrant le
 * fichier.
 *
 * Les exports ADEME (A03 et A05), eux, conservent ces colonnes : ce sont des
 * transmissions réglementaires avec leur propre format.
 */
class ExportSansIdentiteTest extends TestCase
{
    private static function service(): string
    {
        $chemin = \dirname(__DIR__, 2) . '/src/Service/DemandeServiceBO.php';

        self::assertFileExists($chemin);

        $contenu = file_get_contents($chemin);
        self::assertIsString($contenu);

        return $contenu;
    }

    private static function corpsDeLExportDossiers(): string
    {
        $source = self::service();

        $debut = strpos($source, 'public function fputTitleAndContentExport');
        $fin = strpos($source, 'public function fputCSVContentExportADEMEA03');

        self::assertIsInt($debut);
        self::assertIsInt($fin);
        self::assertGreaterThan($debut, $fin);

        return substr($source, $debut, $fin - $debut);
    }

    /**
     * @dataProvider lesColonnesRetirees
     */
    public function testLaColonneNeFigurePlusDansLExportDesDossiers(string $cle, string $libelle): void
    {
        $corps = self::corpsDeLExportDossiers();

        self::assertStringNotContainsString(
            "'" . $cle . "'",
            $corps,
            sprintf('La clé « %s » ne doit plus apparaître, ni en en-tête, ni en donnée, ni dans les listes par profil.', $cle)
        );

        self::assertStringNotContainsString("'" . $libelle . "'", $corps);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function lesColonnesRetirees(): array
    {
        return [
            'nom' => ['nom', 'Nom'],
            'prénom' => ['prenom', 'Prénom'],
            'courriel' => ['email', 'Email'],
            'téléphone 1' => ['telephone1', 'Téléphone1'],
            'téléphone 2' => ['telephone2', 'Téléphone2'],
        ];
    }

    /**
     * Les deux tableaux sont écrits l'un après l'autre dans le même fichier :
     * s'ils cessent d'avoir les mêmes clés, les colonnes se décalent.
     */
    public function testLesEntetesEtLesDonneesPortentLesMemesColonnes(): void
    {
        $corps = self::corpsDeLExportDossiers();

        $cles = static function (string $debutTableau) use ($corps): array {
            $debut = strpos($corps, $debutTableau);
            self::assertIsInt($debut);

            $fin = strpos($corps, '];', $debut);
            self::assertIsInt($fin);

            preg_match_all("/'([A-Za-z0-9_]+)'\s*=>/", substr($corps, $debut, $fin - $debut), $trouve);

            return $trouve[1];
        };

        self::assertSame(
            $cles('$fieldsHeader = ['),
            $cles('$fieldsData = ['),
            'En-têtes et données doivent décrire exactement les mêmes colonnes, dans le même ordre.'
        );
    }

    /**
     * La donnée n'est même plus lue : elle ne peut donc pas réapparaître par
     * une modification distraite du tableau des données.
     */
    public function testLaRequeteNeLitPlusLIdentite(): void
    {
        $source = file_get_contents(\dirname(__DIR__, 2) . '/src/Repository/Demande_Repository.php');
        self::assertIsString($source);

        $debut = strpos($source, 'public function findDataExportResult');
        self::assertIsInt($debut);

        $fin = strpos($source, 'public function', $debut + 50);
        self::assertIsInt($fin);

        $corps = substr($source, $debut, $fin - $debut);

        foreach ([
            'b.nom AS beneficiaireNom',
            'b.prenom AS beneficiairePrenom',
            'b.email AS email',
            'b.tel_1 AS tel_1',
            'b.tel_2 AS tel_2',
        ] as $colonne) {
            self::assertStringNotContainsString($colonne, $corps);
        }
    }

    /**
     * Garde-fou : les transmissions réglementaires à l'ADEME gardent leur
     * format. Les toucher serait une autre décision, qui ne nous appartient pas.
     */
    public function testLesExportsADEMEConserventLeurFormat(): void
    {
        $source = self::service();

        $debut = strpos($source, 'public function fputCSVContentExportADEMEA03');
        self::assertIsInt($debut);

        $corps = substr($source, $debut);

        self::assertStringContainsString("'nom'", $corps);
        self::assertStringContainsString("'prenom'", $corps);
        self::assertStringContainsString("'email'", $corps);
    }
}
