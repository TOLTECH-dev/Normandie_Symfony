<?php

declare(strict_types=1);

namespace App\Tests\Archive;

use App\Archive\InventaireSuppression;
use App\Repository\Archive\ArchivageSuppressionRepository;
use PHPUnit\Framework\TestCase;

/**
 * Cohérence de l'inventaire de suppression (Reco-03).
 *
 * C'est l'inventaire le plus dangereux du projet : il décide de ce qui
 * disparaît définitivement. L'intégrité référentielle n'étant pas déclarée
 * dans cette base, rien ne rattrapera une erreur ici — ni une table oubliée,
 * qui laisserait des données personnelles derrière une destruction attestée,
 * ni un ensemble mal ordonné, qui supprimerait moins que prévu en silence.
 */
class InventaireSuppressionTest extends TestCase
{
    /**
     * La collecte est séquentielle : chaque requête ne peut s'appuyer que sur
     * des ensembles DÉJÀ relevés. Un ensemble cité trop tôt serait vide, la
     * requête serait passée, et la suppression porterait sur moins de lignes
     * que prévu — sans la moindre erreur visible.
     */
    public function testChaqueCollecteNeDependQueDEnsemblesDejaReleves(): void
    {
        $disponibles = ['beneficiaire' => true];

        foreach (InventaireSuppression::COLLECTES as $nom => $collecte) {
            foreach ($collecte['depend'] as $appui) {
                self::assertArrayHasKey(
                    $appui,
                    $disponibles,
                    sprintf('la collecte « %s » s\'appuie sur « %s », qui n\'est pas encore relevé', $nom, $appui)
                );
            }

            $disponibles[$nom] = true;
        }
    }

    /**
     * Toute substitution employée par une requête de collecte doit être
     * déclarée comme dépendance : sinon la requête la recevrait vide sans que
     * la règle précédente s'en aperçoive.
     */
    public function testChaqueSubstitutionEstDeclareeCommeDependance(): void
    {
        foreach (InventaireSuppression::COLLECTES as $nom => $collecte) {
            preg_match_all('/:([a-z_]+)/', $collecte['sql'], $trouvees);

            foreach (array_unique($trouvees[1]) as $substitution) {
                self::assertContains(
                    $substitution,
                    array_merge($collecte['depend'], ['beneficiaire']),
                    sprintf('la collecte « %s » utilise :%s sans le déclarer en dépendance', $nom, $substitution)
                );
            }
        }
    }

    public function testChaqueSuppressionSAppuieSurUnEnsembleCollecte(): void
    {
        $collectes = array_merge(array_keys(InventaireSuppression::COLLECTES), ['beneficiaire']);

        foreach (InventaireSuppression::SUPPRESSIONS as $suppression) {
            self::assertContains(
                $suppression['ensemble'],
                $collectes,
                sprintf(
                    'la suppression de « %s » s\'appuie sur l\'ensemble « %s », que rien ne collecte',
                    $suppression['table'],
                    $suppression['ensemble']
                )
            );
        }
    }

    public function testChaqueSuppressionEstUnDeleteParametre(): void
    {
        foreach (InventaireSuppression::SUPPRESSIONS as $suppression) {
            self::assertStringStartsWith('DELETE FROM', $suppression['sql'], $suppression['table']);
            self::assertStringContainsString(':ids', $suppression['sql'], $suppression['table']);
            self::assertStringContainsString(
                $suppression['table'],
                $suppression['sql'],
                'la requête doit porter sur la table annoncée'
            );
        }
    }

    public function testAucuneTableNestSupprimeeDeuxFois(): void
    {
        $tables = array_column(InventaireSuppression::SUPPRESSIONS, 'table');

        self::assertSame(
            array_unique($tables),
            $tables,
            'une table supprimée deux fois signale un inventaire recopié de travers'
        );
    }

    /**
     * Le piège polymorphe : `admin_coordonnee` porte la géolocalisation des
     * logements — l'adresse exacte du bénéficiaire — et s'y rattache par
     * (object_id, type). Sans le filtre sur le type, la suppression
     * emporterait aussi les coordonnées des agences de partenaires et des
     * permanences, qui n'ont rien à voir avec ce bénéficiaire.
     */
    public function testLaSuppressionDesCoordonneesFiltreSurLeTypePolymorphe(): void
    {
        $entrees = array_values(array_filter(
            InventaireSuppression::SUPPRESSIONS,
            static fn (array $s): bool => 'admin_coordonnee' === $s['table']
        ));

        self::assertCount(1, $entrees, 'la géolocalisation des logements doit être supprimée');
        self::assertSame('logement', $entrees[0]['ensemble']);
        self::assertStringContainsString("type LIKE '%logement'", $entrees[0]['sql']);
    }

    /**
     * Le compte lui-même n'est jamais supprimé : les Archives refusent la
     * suppression directe des comptes, ils sont anonymisés.
     */
    public function testLeCompteNestJamaisSupprime(): void
    {
        foreach (InventaireSuppression::SUPPRESSIONS as $suppression) {
            self::assertNotSame('user', $suppression['table'], 'le compte est anonymisé, pas supprimé');
        }
    }

    /**
     * L'historique et ses filles doivent disparaître : le compte rendu du
     * 17/11/2026 tranche que commentaires et historique sont versés à
     * l'archive puis supprimés de la base.
     */
    public function testLHistoriqueEtSesFillesSontSupprimes(): void
    {
        $tables = array_column(InventaireSuppression::SUPPRESSIONS, 'table');

        foreach (['historique_', 'historique_post', 'historique_email', 'historique_data'] as $table) {
            self::assertContains($table, $tables, sprintf('%s doit être supprimée', $table));
        }
    }

    public function testLesTablesHorsPerimetreNeSontPasSupprimees(): void
    {
        $tables = array_column(InventaireSuppression::SUPPRESSIONS, 'table');

        foreach (array_keys(InventaireSuppression::HORS_PERIMETRE) as $table) {
            self::assertNotContains(
                $table,
                $tables,
                'une table déclarée hors périmètre ne peut pas être supprimée en même temps'
            );
        }

        self::assertNotEmpty(
            InventaireSuppression::HORS_PERIMETRE,
            'les tables non rattachables doivent rester déclarées, avec leur raison'
        );
    }

    /**
     * La garantie contre l'injection : quelle que soit la valeur reçue, ce qui
     * sort de listeDIdentifiants() est une liste d'entiers. Les requêtes de
     * suppression n'emploient pas de paramètres liés — c'est cette fonction,
     * et elle seule, qui tient la règle.
     *
     * @dataProvider fournirValeursHostiles
     */
    public function testLaListeDIdentifiantsNeRendQueDesEntiers(mixed $valeur): void
    {
        $liste = ArchivageSuppressionRepository::listeDIdentifiants([$valeur]);

        self::assertMatchesRegularExpression(
            '/^-?\d+$/',
            $liste,
            sprintf('« %s » doit ressortir en entier', var_export($valeur, true))
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function fournirValeursHostiles(): iterable
    {
        yield 'injection classique' => ['1 OR 1=1'];
        yield 'commentaire sql' => ['1; DROP TABLE user --'];
        yield 'apostrophe' => ["1'"];
        yield 'texte' => ['abc'];
        yield 'vide' => [''];
        yield 'flottant' => [12.9];
        yield 'entier' => [42];
        yield 'entier en chaine' => ['42'];
    }

    public function testUnEnsembleVideNeCorrespondARien(): void
    {
        self::assertSame(
            'NULL',
            ArchivageSuppressionRepository::listeDIdentifiants([]),
            "un IN () vide serait du SQL invalide ; IN (NULL) ne correspond à rien"
        );
    }

    public function testPlusieursIdentifiantsSontSeparesParDesVirgules(): void
    {
        self::assertSame('1,2,3', ArchivageSuppressionRepository::listeDIdentifiants([1, 2, 3]));
    }
}
