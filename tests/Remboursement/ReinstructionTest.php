<?php

declare(strict_types=1);

namespace App\Tests\Remboursement;

use App\Entity\Remboursement_statut;
use App\Remboursement\Reinstruction;
use PHPUnit\Framework\TestCase;

/**
 * Reprise d'un remboursement refusé (D26013, point 1-B).
 *
 * Demande de support : « Accès à un dossier par auditeur pour modification d'un
 * dépôt de fichiers. Actuellement ce n'est pas possible. » Le refus était un
 * terminus — le partenaire ne pouvait plus remplacer la pièce qu'on lui
 * reprochait, et le dossier disparaissait même de sa liste.
 *
 * Les règles vivent dans du SQL écrit à la main et dans un fichier JavaScript,
 * qu'aucun test d'intégration ne peut atteindre sans base. Les contrôles
 * portent donc sur la règle elle-même et sur les trois endroits qui
 * l'appliquent, pour qu'un retour en arrière se voie.
 */
class ReinstructionTest extends TestCase
{
    private static function source(string $relatif): string
    {
        $chemin = \dirname(__DIR__, 2) . '/' . $relatif;

        self::assertFileExists($chemin);

        $contenu = file_get_contents($chemin);
        self::assertIsString($contenu);

        return $contenu;
    }

    public function testLeStatutDeRepriseExisteEtNeRecouvreAucunAutre(): void
    {
        self::assertSame(31, Remboursement_statut::STATUS_31);
        self::assertNotSame(Remboursement_statut::STATUS_20, Remboursement_statut::STATUS_31);
        self::assertNotSame(Remboursement_statut::STATUS_22, Remboursement_statut::STATUS_31);
    }

    /**
     * Seul le 22 ferme définitivement le dépôt. Le 20 n'y figure plus : c'est
     * tout l'objet de l'évolution.
     */
    public function testSeulLeRemboursementPayeFermeDefinitivementLeDepot(): void
    {
        self::assertSame([Remboursement_statut::STATUS_22], Reinstruction::STATUTS_DEPOT_FERME);
        self::assertNotContains(Remboursement_statut::STATUS_20, Reinstruction::STATUTS_DEPOT_FERME);
    }

    /**
     * @dataProvider lesProfils
     *
     * @param list<string> $roles
     */
    public function testSeulsLesProfilsQuiDisposentDuDepotRevoientLesRefuses(array $roles, bool $attendu): void
    {
        self::assertSame($attendu, Reinstruction::profilPeutReprendre($roles));
    }

    /**
     * @return array<string, array{0: list<string>, 1: bool}>
     */
    public static function lesProfils(): array
    {
        return [
            // Ceux qui ont le bouton de dépôt : le partenaire qui redépose et
            // les comptes Région qui l'accompagnent.
            'auditeur' => [['ROLE_AUDITEUR'], true],
            'client Région' => [['ROLE_CLIENT'], true],
            'administrateur' => [['ROLE_ADMIN'], true],
            'super administrateur' => [['ROLE_SUPER_ADMIN'], true],
            // Les autres gardent la liste d'aujourd'hui : ils reverront le
            // dossier une fois repris, au statut « à réinstruire ».
            'conseiller' => [['ROLE_CONSEILLER'], false],
            'instructeur' => [['ROLE_INSTRUCTEUR'], false],
            'instructeur étendu' => [['ROLE_INSTRUCTEUR_UP'], false],
            'rénovateur' => [['ROLE_RENOVATEUR'], false],
            'EPCI' => [['ROLE_EPCI'], false],
            'bénéficiaire' => [['ROLE_MEMBER'], false],
            'aucun rôle' => [[], false],
        ];
    }

    /**
     * La reprise s'arrête au dossier anonymisé ou archivé : ses pièces
     * n'existent plus, rouvrir le dépôt laisserait croire le contraire.
     */
    public function testLaConditionCouvreLAnonymisationEtLArchivage(): void
    {
        $condition = Reinstruction::conditionDossierVivant();

        self::assertStringContainsString('r.rgpd <> 1', $condition);
        self::assertStringContainsString('d.rgpd <> 1', $condition);
        self::assertStringContainsString('archive_demande_export', $condition);
        self::assertStringContainsString('ade.demande_id = d.id', $condition);
    }

    public function testLaConditionSuitLesAliasDeLaRequete(): void
    {
        $condition = Reinstruction::conditionDossierVivant('remb', 'dem');

        self::assertStringContainsString('remb.rgpd <> 1', $condition);
        self::assertStringContainsString('dem.rgpd <> 1', $condition);
        self::assertStringContainsString('ade.demande_id = dem.id', $condition);
        self::assertStringNotContainsString(' r.rgpd', $condition);
    }

    /**
     * Le contrôle d'accès au dépôt est le verrou qui compte : masquer le bouton
     * ne suffirait pas, l'adresse reste atteignable.
     */
    public function testLAccesAuDepotNeFermePlusSurLeRefusSeul(): void
    {
        $source = self::source('src/Repository/Remboursement_Repository.php');

        $position = strpos($source, 'public function findOneForDepot');
        self::assertIsInt($position);

        $corps = substr($source, $position, 2000);

        self::assertStringContainsString(
            'Reinstruction::conditionDossierVivant()',
            $corps,
            "L'accès au dépôt doit être subordonné à un dossier vivant, et non fermé sur le seul statut refusé."
        );

        self::assertStringNotContainsString(
            'NOT IN (" . Remboursement_statut::STATUS_20 . ", " . Remboursement_statut::STATUS_22 . ")',
            $corps,
            'Le refus ne doit plus fermer le dépôt de la même façon que le paiement.'
        );
    }

    /**
     * Le dossier doit réapparaître dans la liste du partenaire : sans chemin
     * pour y accéder, rouvrir le dépôt ne servirait à rien.
     */
    public function testLaListeRendLesRefusesAuxProfilsQuiPeuventReprendre(): void
    {
        $source = self::source('src/Repository/Remboursement_Repository.php');

        self::assertSame(
            2,
            substr_count($source, 'Reinstruction::profilPeutReprendre($roles)'),
            'Les deux requêtes de la liste — données et comptage — doivent appliquer la même règle, sinon la pagination ment.'
        );
    }

    /**
     * Le dépôt était enfermé avec la fiche technique et les commentaires dans
     * une condition commune excluant 20 et 22. Lui seul s'ouvre au refus.
     */
    public function testLeBoutonDeDepotEstSortiDeLaConditionDesAutresBlocs(): void
    {
        $source = self::source('assets/js/backOffice/remboursement/list/appel_datatables_ajax.js');

        $depot = strpos($source, '// ---------- DEPOT ----------');
        $finDepot = strpos($source, '// ---------- FIN DEPOT ----------');
        $ficheTechnique = strpos($source, '// ---------- FICHE TECHNIQUE ----------');

        self::assertIsInt($depot);
        self::assertIsInt($finDepot);
        self::assertIsInt($ficheTechnique);

        $avantDepot = substr($source, 0, $depot);
        $conditionDuDepot = substr($avantDepot, strrpos($avantDepot, 'if ('));

        self::assertStringNotContainsString(
            "['20', '22']",
            $conditionDuDepot,
            'Le dépôt ne doit plus être fermé au statut refusé.'
        );

        $entreLesDeux = substr($source, $finDepot, $ficheTechnique - $finDepot);

        self::assertStringContainsString(
            "['20', '22']",
            $entreLesDeux,
            'La fiche technique et les commentaires gardent leur règle : fermés au refus.'
        );
    }

    /**
     * @dataProvider lesControleursDeDepot
     */
    public function testLeRedepotMarqueLaRepriseEtEfaceLeReproche(string $controleur): void
    {
        $source = self::source('src/Controller/' . $controleur);

        $position = strpos($source, 'public function editDepot');
        self::assertIsInt($position);

        $corps = substr($source, $position);

        self::assertStringContainsString(
            'if (Remboursement_statut::STATUS_20 === $remboursement->getStatutId()) {',
            $corps
        );
        self::assertStringContainsString(
            '$remboursement->setStatutId(Remboursement_statut::STATUS_31);',
            $corps
        );
        self::assertStringContainsString(
            '$remboursement->setMotifRefus(null);',
            $corps,
            'Le motif de refus vise une pièce qui vient d\'être remplacée : il n\'a plus lieu d\'être.'
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function lesControleursDeDepot(): array
    {
        return [
            'audit énergétique' => ['RemboursementAuditEnergieController.php'],
            'audit numérique' => ['RemboursementAuditNumeriqueController.php'],
        ];
    }

    /**
     * addDepot ne concerne que les remboursements sans dépôt, qui n'ont donc
     * jamais été instruits : la marque de reprise n'y a pas sa place.
     */
    public function testLaCreationDUnDepotNeMarquePasDeReprise(): void
    {
        foreach (self::lesControleursDeDepot() as [$controleur]) {
            $source = self::source('src/Controller/' . $controleur);

            $debut = strpos($source, 'public function addDepot');
            $fin = strpos($source, 'public function editDepot');

            self::assertIsInt($debut);
            self::assertIsInt($fin);
            self::assertGreaterThan($debut, $fin);

            self::assertStringNotContainsString(
                'STATUS_31',
                substr($source, $debut, $fin - $debut),
                $controleur . ' : addDepot ne doit pas marquer de reprise.'
            );
        }
    }
}
