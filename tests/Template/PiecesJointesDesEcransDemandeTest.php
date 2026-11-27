<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;

/**
 * Les pièces jointes sur les écrans d'une demande (D26013).
 *
 * Trois défauts constatés en recette le 23/11/2026, tous sur la même famille
 * d'écrans, sont verrouillés ici.
 *
 *  1. UN LIEN DE TÉLÉCHARGEMENT SANS IDENTIFIANT. Les accesseurs des pièces
 *     renseignent `xxx_url` et `xxx_alt` dès qu'un fichier est transmis, bien
 *     avant l'enregistrement : au réaffichage d'un formulaire refusé, la
 *     demande porte le nom d'un fichier sans avoir encore d'identifiant. Les
 *     conditions qui ne regardaient que `xxx_alt` laissaient construire
 *     `path('demande_document_bo', {demandeId: null})`, ce que le routeur
 *     refuse : page d'erreur au lieu du message d'obligation.
 *
 *  2. L'ASTÉRISQUE DU MANDAT. Elle ne peut pas venir de l'option `required` du
 *     type de formulaire : le même formulaire sert à la modification, où le
 *     mandat n'est pas exigé, et l'attribut HTML bloquerait la reprise d'une
 *     demande antérieure.
 *
 *  3. LE MANDAT ABSENT DE LA CONSULTATION. Dépôt et modification le portaient,
 *     pas la consultation : le document devenait invisible une fois la demande
 *     enregistrée, côté bénéficiaire comme côté back-office.
 */
class PiecesJointesDesEcransDemandeTest extends TestCase
{
    private const RACINE = '/templates/FrontOffice/Demande/';

    private static function gabarit(string $relatif): string
    {
        return \dirname(__DIR__, 2) . self::RACINE . $relatif;
    }

    /**
     * Les quatre partiels servis par les écrans de dépôt et de modification,
     * côté bénéficiaire comme côté conseiller — ce sont les mêmes fichiers.
     *
     * @return array<string, array{0: string}>
     */
    public static function lesPartielsDeDepot(): array
    {
        return [
            'travaux — pièces' => [self::gabarit('Travaux/inc/form_travaux/audit.html.twig')],
            'travaux — revenus' => [self::gabarit('Travaux/inc/form_travaux/revenu.html.twig')],
            'audit énergétique — pièces' => [self::gabarit('AuditEnergie/inc/form_auditEnergie/document.html.twig')],
            'audit énergétique — revenus' => [self::gabarit('AuditEnergie/inc/form_auditEnergie/revenu.html.twig')],
        ];
    }

    /**
     * @dataProvider lesPartielsDeDepot
     */
    public function testChaqueLienDeTelechargementExigeUnIdentifiant(string $chemin): void
    {
        self::assertFileExists($chemin);

        $lignes = file($chemin, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lignes);

        $gardeCourante = null;
        $liensVerifies = 0;

        foreach ($lignes as $numero => $ligne) {
            if (preg_match('/\{%\s*if\s+(.+?)\s*%\}/', $ligne, $trouve)) {
                $gardeCourante = $trouve[1];
            }

            if (!str_contains($ligne, 'path(path_location')) {
                continue;
            }

            ++$liensVerifies;

            self::assertNotNull(
                $gardeCourante,
                sprintf('%s ligne %d : lien de téléchargement sans condition.', basename($chemin), $numero + 1)
            );

            self::assertMatchesRegularExpression(
                '/\.id(\s|$)/',
                $gardeCourante,
                sprintf(
                    "%s ligne %d : la condition « %s » ne vérifie pas l'identifiant de la demande. "
                    . "Un formulaire refusé porte le nom du fichier sans identifiant, et la page tombe en erreur.",
                    basename($chemin),
                    $numero + 1,
                    $gardeCourante
                )
            );
        }

        self::assertGreaterThan(0, $liensVerifies, 'Aucun lien de téléchargement trouvé : le partiel a-t-il changé de forme ?');
    }

    /**
     * @dataProvider lesEcransDeDepotPortantLeMandat
     */
    public function testLAsterisqueDuMandatSuitLaRegleDeLEntite(string $chemin, string $entite): void
    {
        $contenu = file_get_contents($chemin);
        self::assertIsString($contenu);

        self::assertStringContainsString(
            sprintf('{%% set mandatObligatoire = %s is null or %s.id is null %%}', $entite, $entite),
            $contenu,
            'La condition de l\'astérisque doit reprendre celle de la contrainte : demande non enregistrée.'
        );

        self::assertStringContainsString(
            "mandatObligatoire ? ' required' : ''",
            $contenu,
            'La classe « required », que layout_core.css transforme en astérisque, doit être conditionnelle.'
        );
    }

    /**
     * Les erreurs du mandat se placent sous le champ, dans la colonne du
     * widget, et non en tête de bloc où rien ne les rattachait au bon champ.
     * La classe wrapper_error leur donne le rouge et la puce d'erreur.
     *
     * @dataProvider lesEcransDeDepotPortantLeMandat
     */
    public function testLesErreursDuMandatSontSousLeChampEtEnRouge(string $chemin, string $entite, string $champ): void
    {
        $contenu = file_get_contents($chemin);
        self::assertIsString($contenu);

        $position = strpos($contenu, 'form_widget(' . $champ . ')');
        self::assertIsInt($position, 'Champ du mandat introuvable.');

        $erreurs = strpos($contenu, 'form_errors(' . $champ . ')');
        self::assertIsInt($erreurs, 'Erreurs du mandat introuvables.');

        self::assertGreaterThan(
            $position,
            $erreurs,
            'Les erreurs du mandat doivent être rendues APRÈS le champ, sans quoi elles s\'affichent au-dessus du champ précédent.'
        );

        self::assertStringContainsString(
            'wrapper_error',
            substr($contenu, $position, $erreurs - $position + 200),
            'Les erreurs doivent être enveloppées dans wrapper_error, qui les met en rouge.'
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function lesEcransDeDepotPortantLeMandat(): array
    {
        return [
            'travaux' => [
                self::gabarit('Travaux/inc/form_travaux/audit.html.twig'),
                'travaux.demande_travaux',
                'form.demande_travaux.mandat',
            ],
            'audit énergétique' => [
                self::gabarit('AuditEnergie/inc/form_auditEnergie/document.html.twig'),
                'auditE.demande_auditEnergie',
                'form.demande_auditEnergie.mandat',
            ],
        ];
    }

    /**
     * Les écrans de consultation, partagés par l'espace bénéficiaire et le
     * back-office, doivent montrer le mandat comme les autres pièces.
     *
     * @dataProvider lesEcransDeConsultation
     */
    public function testLeMandatFigureSurLesEcransDeConsultation(string $chemin, string $cleIdentifiant, int $typeDemande): void
    {
        $contenu = file_get_contents($chemin);
        self::assertIsString($contenu);

        self::assertStringContainsString('demande.demandeMandatAlt', $contenu, 'La ligne du mandat manque à la consultation.');

        self::assertStringContainsString(
            sprintf(
                "{'demandeId': demande.%s, 'typeFile': 18, 'extension': demande.demandeMandatUrl, 'typeDemande': %d}",
                $cleIdentifiant,
                $typeDemande
            ),
            $contenu,
            'Le lien doit viser la pièce de type 18 (mandat) de la bonne nature de demande.'
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function lesEcransDeConsultation(): array
    {
        return [
            'travaux' => [self::gabarit('Travaux/inc/view_travaux/audit.html.twig'), 'travauxId', 1],
            'audit énergétique' => [self::gabarit('AuditEnergie/inc/view_auditEnergie/document.html.twig'), 'auditEId', 0],
        ];
    }

    /**
     * Ces écrans lisent un tableau produit par findByIdCustom, pas l'entité :
     * sans ces deux colonnes, la ligne du mandat resterait muette.
     *
     * @dataProvider lesDepotsDeConsultation
     */
    public function testLaRequeteDeConsultationRamenLesColonnesDuMandat(string $depot, string $alias): void
    {
        $contenu = file_get_contents(\dirname(__DIR__, 2) . '/src/Repository/' . $depot);
        self::assertIsString($contenu);

        self::assertStringContainsString($alias . '.mandat_alt AS demandeMandatAlt', $contenu);
        self::assertStringContainsString($alias . '.mandat_url AS demandeMandatUrl', $contenu);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function lesDepotsDeConsultation(): array
    {
        return [
            'travaux' => ['Demande_travauxRepository.php', 'dt'],
            'audit énergétique' => ['Demande_auditEnergieRepository.php', 'dae'],
        ];
    }
}
