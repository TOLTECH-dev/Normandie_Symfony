<?php

declare(strict_types=1);

namespace App\Tests\Archive;

use App\Archive\InventairePieces;
use App\Repository\Archive\ArchivagePiecesSql;
use PHPUnit\Framework\TestCase;

/**
 * Cohérence de l'inventaire des pièces (Reco-03).
 *
 * L'inventaire décide quels fichiers partent dans les archives versées aux
 * Archives de la Région, et quels fichiers seront ensuite supprimés. Une entrée
 * fausse ne provoque aucune erreur visible : elle produit simplement une
 * archive incomplète, et la pièce disparaît quand même à la destruction. D'où
 * ces vérifications, qui portent sur la forme de l'inventaire.
 *
 * Ce qu'elles ne peuvent PAS vérifier : que l'inventaire couvre toutes les
 * colonnes du schéma. Cela demande la vraie base, et c'est le rôle de
 * `normandie:archivage:pieces --couverture`, à passer en recette avant chaque
 * campagne.
 */
class InventairePiecesTest extends TestCase
{
    public function testChaqueEmplacementDesigneUnDossierEtUnSuffixeConnus(): void
    {
        foreach (InventairePieces::EMPLACEMENTS as $emplacement) {
            self::assertArrayHasKey(
                $emplacement['dossier'],
                InventairePieces::DOSSIERS,
                sprintf('%s.%s : répertoire %d inconnu', $emplacement['alias'], $emplacement['colonne'], $emplacement['dossier'])
            );
            self::assertArrayHasKey(
                $emplacement['type'],
                InventairePieces::SUFFIXES,
                sprintf('%s.%s : type de pièce %d inconnu', $emplacement['alias'], $emplacement['colonne'], $emplacement['type'])
            );
        }
    }

    public function testChaqueAliasEstRattacheAUneTable(): void
    {
        foreach (InventairePieces::EMPLACEMENTS as $emplacement) {
            self::assertArrayHasKey(
                $emplacement['alias'],
                InventairePieces::TABLES_PAR_ALIAS,
                sprintf('alias « %s » sans table déclarée', $emplacement['alias'])
            );
        }
    }

    public function testChaqueColonneEstUneColonneDeDocument(): void
    {
        foreach (InventairePieces::EMPLACEMENTS as $emplacement) {
            self::assertStringEndsWith(
                '_url',
                $emplacement['colonne'],
                'une pièce est désignée par une colonne *_url, qui porte son extension'
            );
        }
    }

    /**
     * Le couple (table, colonne) doit être unique : deux entrées identiques
     * verseraient deux fois la même pièce et fausseraient les compteurs du
     * registre, donc la volumétrie portée par l'attestation de destruction.
     */
    public function testAucunEmplacementNestDeclareDeuxFois(): void
    {
        $vus = [];

        foreach (InventairePieces::EMPLACEMENTS as $emplacement) {
            $cle = InventairePieces::TABLES_PAR_ALIAS[$emplacement['alias']] . '.' . $emplacement['colonne'];

            self::assertArrayNotHasKey($cle, $vus, sprintf('emplacement déclaré deux fois : %s', $cle));
            $vus[$cle] = true;
        }
    }

    /**
     * Le libellé est interpolé dans la requête générée : une apostrophe y
     * casserait le SQL. Les libellés sont nos propres constantes, mais autant
     * que la contrainte soit écrite quelque part.
     */
    public function testAucunLibelleNeContientDeQuoteNiDeBarreObliques(): void
    {
        foreach (InventairePieces::EMPLACEMENTS as $emplacement) {
            self::assertDoesNotMatchRegularExpression(
                "#['\"\\\\/]#",
                $emplacement['libelle'],
                sprintf('libellé « %s » : ni apostrophe, ni guillemet, ni barre oblique', $emplacement['libelle'])
            );
            self::assertNotSame('', trim($emplacement['libelle']));
        }
    }

    public function testLeCheminSuitLaConventionDeNommage(): void
    {
        $chemin = InventairePieces::cheminRelatif(
            ['dossier' => 9, 'type' => 6],
            12345,
            'pdf'
        );

        self::assertSame('remboursement/travaux_instruction/12345_rib.pdf', $chemin);
    }

    /**
     * Le piège relevé lors de la cartographie : le document de fiche technique
     * est stocké sous le suffixe « _xml_document. », et non sous un suffixe
     * dérivé du nom de sa colonne. Déduire le suffixe du nom de colonne marche
     * partout ailleurs et échoue ici.
     */
    public function testLeDocumentDeFicheTechniqueGardeSonSuffixeHistorique(): void
    {
        $emplacements = array_values(array_filter(
            InventairePieces::EMPLACEMENTS,
            static fn (array $e): bool => 'ftf' === $e['alias'] && 'ficheTechnique_document_url' === $e['colonne']
        ));

        self::assertCount(1, $emplacements);
        self::assertSame('_xml_document.', InventairePieces::SUFFIXES[$emplacements[0]['type']]);
    }

    public function testLeNomDansArchiveEstLisibleEtUnique(): void
    {
        self::assertSame(
            'devis-ou-facture-4321.pdf',
            InventairePieces::nomDansArchive('devis ou facture', 4321, 'pdf')
        );
    }

    /**
     * La requête est générée depuis l'inventaire : toute source citée par un
     * emplacement doit exister comme branche du graphe, sans quoi la génération
     * lève — mieux vaut un échec franc qu'une pièce silencieusement oubliée.
     */
    public function testLaRequeteSeGenereEtCiteChaqueEmplacement(): void
    {
        $sql = ArchivagePiecesSql::piecesDesDossiers();

        foreach (InventairePieces::EMPLACEMENTS as $emplacement) {
            self::assertStringContainsString(
                $emplacement['alias'] . '.' . $emplacement['colonne'],
                $sql,
                sprintf('%s.%s absent de la requête générée', $emplacement['alias'], $emplacement['colonne'])
            );
        }

        self::assertStringContainsString('alb.lot_id = :lot_id', $sql);
        self::assertStringContainsString('alb.etat = :etat', $sql);
    }

    /**
     * Les onze tables porteuses doivent toutes être atteintes par la requête :
     * si une branche du graphe disparaissait, ses pièces ne seraient pas
     * versées, et la destruction les emporterait quand même.
     */
    public function testChaqueTablePorteuseEstAtteinteParLaRequete(): void
    {
        $sql = ArchivagePiecesSql::piecesDesDossiers();
        $alias = array_unique(array_column(InventairePieces::EMPLACEMENTS, 'alias'));

        foreach ($alias as $unAlias) {
            $table = InventairePieces::TABLES_PAR_ALIAS[$unAlias];

            self::assertMatchesRegularExpression(
                sprintf('/\b%s\s+%s\b/', preg_quote($table, '/'), preg_quote($unAlias, '/')),
                $sql,
                sprintf('la table %s doit être jointe sous son alias %s', $table, $unAlias)
            );
        }
    }
}
