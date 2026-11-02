<?php

declare(strict_types=1);

namespace App\Tests\Repository\Remboursement;

use App\Repository\Remboursement\RemboursementListCriteria;
use App\Repository\Remboursement\RemboursementListSql;
use PHPUnit\Framework\TestCase;

/**
 * Liste « Remboursement des aides » : les recherches, le tri et la pagination venaient de $_POST et
 * étaient concaténés au SQL. Ces tests fixent leur remplacement — listes blanches et paramètres
 * liés — et le regroupement des colonnes.
 */
class RemboursementListTest extends TestCase
{
    private const PAYLOAD = 'mar" OR "1"="1\' OR 1=1 -- ';

    /** Colonnes de la liste, telles que les déclare son script */
    private const COLONNES = [
        ['data' => 'demandeId', 'name' => 'dossier'],
        ['data' => 'beneficiaire', 'name' => 'beneficiaire'],
        ['data' => 'structureConseiller', 'name' => 'structureConseiller'],
        ['data' => 'partenaire', 'name' => 'partenaire'],
        ['data' => 'RMHDate', 'name' => 'RMHDate'],
        ['data' => 'action', 'name' => 'action'],
        ['data' => 'demandeType', 'name' => 'demandeType'],
        ['data' => 'statut', 'name' => 'statut'],
    ];

    /** Numéros d'opération des titres BBC, tels que les paramètres de l'application les donnent */
    private const BBC1 = 588;
    private const BBC2 = 589;

    public function testLitLesCriteresDataTables(): void
    {
        $criteria = RemboursementListCriteria::fromDataTables($this->post(
            ['order' => [['column' => '4', 'dir' => 'asc']], 'start' => '50', 'length' => '10'],
            ['dossier' => '201', 'beneficiaire' => 'martin']
        ));

        self::assertSame('RMHDate', $criteria->getSortColumn());
        self::assertSame('asc', $criteria->getSortDirection());
        self::assertSame(50, $criteria->getStart());
        self::assertSame(10, $criteria->getLength());
        self::assertSame(['dossier' => '201', 'beneficiaire' => 'martin'], $criteria->getFilters());
    }

    /**
     * @dataProvider provideTrisRefuses
     */
    public function testTriForgeRemplaceParLeTriParDefaut(int $colonne, mixed $sens): void
    {
        $post = $this->post(['order' => [['column' => (string) $colonne, 'dir' => $sens]]]);
        $post['columns'][0]['name'] = 'd.id; DROP TABLE user; --';
        $post['columns'][0]['data'] = 'd.id; DROP TABLE user; --';

        $criteria = RemboursementListCriteria::fromDataTables($post);

        self::assertSame('demandeId', $criteria->getSortColumn());
        self::assertSame('desc', $criteria->getSortDirection());
        self::assertStringContainsString('ORDER BY d.id DESC', RemboursementListSql::orderBy($criteria));
    }

    public static function provideTrisRefuses(): iterable
    {
        yield 'colonne forgée' => [0, 'asc'];
        yield 'colonne inexistante' => [99, 'asc'];
        yield 'colonne Action' => [5, 'asc'];
    }

    public function testSensDeTriEnListeBlanche(): void
    {
        $asc = RemboursementListCriteria::fromDataTables($this->post(['order' => [['column' => '1', 'dir' => 'ASC']]]));
        $force = RemboursementListCriteria::fromDataTables(
            $this->post(['order' => [['column' => '1', 'dir' => 'asc, (SELECT SLEEP(5))']]])
        );

        self::assertStringContainsString('ORDER BY b.nom ASC', RemboursementListSql::orderBy($asc));
        self::assertStringContainsString('ORDER BY b.nom DESC', RemboursementListSql::orderBy($force));
        self::assertStringNotContainsString('SLEEP', RemboursementListSql::orderBy($force));
    }

    public function testPaginationBornee(): void
    {
        $criteria = RemboursementListCriteria::fromDataTables($this->post(['start' => '0 OR 1=1', 'length' => '100000']));

        self::assertSame(' LIMIT 0, ' . RemboursementListCriteria::MAX_LENGTH, RemboursementListSql::limit($criteria));
    }

    /**
     * @dataProvider provideFiltresTexte
     *
     * @param list<string> $expressions
     */
    public function testLaSaisieNestJamaisEcriteDansLeSql(string $field, array $expressions): void
    {
        $criteria = new RemboursementListCriteria([$field => self::PAYLOAD]);
        [$where, $params] = RemboursementListSql::conditions($criteria, self::BBC1, self::BBC2);

        self::assertStringNotContainsString('OR "1"="1', $where);
        self::assertStringNotContainsString('mar', $where);
        self::assertSame(['filtre_' . $field => '%' . self::PAYLOAD . '%'], $params);

        $tests = array_map(static fn (string $e): string => $e . ' LIKE :filtre_' . $field, $expressions);
        self::assertStringContainsString('(' . implode(' OR ', $tests) . ')', $where);
    }

    public static function provideFiltresTexte(): iterable
    {
        yield 'dossier' => ['dossier', ['d.id', 't.numero_cheque']];
        yield 'bénéficiaire' => ['beneficiaire', ['b.nom', 'b.prenom', 'b.nom_SCI', 'l.code_postal', 'l.ville']];
        yield 'date RMH' => ['RMHDate', ["DATE_FORMAT(dRMH.date_RMH, '%d/%m/%Y')"]];
        yield 'professionnel' => ['partenaire', ['pi_dae.raison_sociale', 'pi_dan.raison_sociale', 'pi_dtd.raison_sociale']];
    }

    /**
     * Le filtre de statut de la liste est une saisie libre : la recherche reste partielle et porte
     * sur les deux familles de statut, celle de la demande et celle du remboursement, comme avant
     * la correction. Seule la façon d'y faire entrer la valeur change : elle est liée, et non plus
     * écrite dans la requête.
     */
    public function testStatutRechercheEnPartielSurLesDeuxFamilles(): void
    {
        $criteria = new RemboursementListCriteria(['statut' => self::PAYLOAD]);
        [$where, $params] = RemboursementListSql::conditions($criteria, self::BBC1, self::BBC2);

        self::assertSame(' AND (ds.slug LIKE :filtre_statut OR rs.slug LIKE :filtre_statut)', $where);
        self::assertStringNotContainsString('OR 1=1', $where);
        // La valeur est reprise telle quelle, espaces compris : c'est le filtre d'une colonne, que
        // la correction ne cherche pas à réécrire — elle se borne à l'empêcher d'atteindre le SQL.
        self::assertSame(['filtre_statut' => '%' . self::PAYLOAD . '%'], $params);
    }

    /**
     * @dataProvider provideThematiques
     */
    public function testThematiquesEnListeBlanche(string $valeur, ?string $attendu): void
    {
        $criteria = new RemboursementListCriteria(['demandeType' => $valeur]);
        [$where, $params] = RemboursementListSql::conditions($criteria, self::BBC1, self::BBC2);

        self::assertSame([], $params, 'Une thématique est un code repris de la liste blanche');

        if (null === $attendu) {
            self::assertSame('', $where);
        } else {
            self::assertStringContainsString($attendu, $where);
        }
    }

    public static function provideThematiques(): iterable
    {
        yield 'audit énergétique' => ['1', '(d.type = 1 AND dtd.niveau IS NULL)'];
        yield 'travaux niveau 2' => ['31', "(d.type = 3 AND SUBSTRING(dtd.niveau, 1, 1) = '1')"];
        yield 'BBC rénovateur (1/2)' => ['331', "SUBSTRING(dtd.niveau, 1, 1) = '3' AND t.numero_operation = 588"];
        yield 'BBC rénovateur (2/2)' => ['332', "SUBSTRING(dtd.niveau, 1, 1) = '3' AND t.numero_operation = 589"];
        yield 'plusieurs codes' => ['1|31', '(d.type = 1 AND dtd.niveau IS NULL) OR (d.type = 3'];
        yield 'code inconnu' => ['99999', null];
        yield 'injection' => ['1 OR 1=1', null];
    }

    public function testRechercheGeneraleLieeEnParametre(): void
    {
        $criteria = new RemboursementListCriteria([], 'demandeId', 'desc', 0, 25, self::PAYLOAD);
        [$where, $params] = RemboursementListSql::conditions($criteria, self::BBC1, self::BBC2);

        self::assertStringNotContainsString('mar', $where);
        self::assertSame(['recherche_generale' => '%' . trim(self::PAYLOAD) . '%'], $params);

        foreach (['d.id', 't.numero_cheque', 'b.nom', 'l.ville', 'IF(r.id, rs.slug, ds.slug)'] as $expression) {
            self::assertStringContainsString($expression . ' LIKE :recherche_generale', $where);
        }
    }

    public function testSansCritereAucuneCondition(): void
    {
        [$where, $params] = RemboursementListSql::conditions(new RemboursementListCriteria(), self::BBC1, self::BBC2);

        self::assertSame('', $where);
        self::assertSame([], $params);
    }

    public function testColonnesInconnuesIgnorees(): void
    {
        $criteria = new RemboursementListCriteria(['b.nom) OR (1' => 'x', 'action' => 'y']);

        self::assertSame([], $criteria->getFilters());
        self::assertFalse($criteria->hasFilters());
    }

    /**
     * Requête DataTables reproduisant les colonnes de la liste.
     *
     * @param array<string, mixed>  $overrides
     * @param array<string, string> $searches recherches par nom logique de colonne
     *
     * @return array<string, mixed>
     */
    private function post(array $overrides = [], array $searches = []): array
    {
        $columns = [];
        foreach (self::COLONNES as $colonne) {
            $columns[] = $colonne + ['search' => ['value' => $searches[$colonne['name']] ?? '', 'regex' => 'false']];
        }

        return $overrides + [
            'draw'    => '1',
            'columns' => $columns,
            'order'   => [['column' => '0', 'dir' => 'desc']],
            'start'   => '0',
            'length'  => '25',
            'search'  => ['value' => '', 'regex' => 'false'],
        ];
    }
}
