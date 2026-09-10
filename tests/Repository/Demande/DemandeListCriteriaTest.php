<?php

declare(strict_types=1);

namespace App\Tests\Repository\Demande;

use App\Repository\Demande\DemandeListCriteria;
use PHPUnit\Framework\TestCase;

class DemandeListCriteriaTest extends TestCase
{
    public function testLitLesCriteresDataTables(): void
    {
        $criteria = DemandeListCriteria::fromDataTables($this->post([
            'order'  => [['column' => '2', 'dir' => 'asc']],
            'start'  => '50',
            'length' => '10',
        ], ['beneficiaireIdentifiant' => 'mar', 'logement' => '76']));

        self::assertSame('beneficiaireIdentifiant', $criteria->getSortColumn());
        self::assertSame('asc', $criteria->getSortDirection());
        self::assertSame(50, $criteria->getStart());
        self::assertSame(10, $criteria->getLength());
        self::assertSame(['beneficiaireIdentifiant' => 'mar', 'logement' => '76'], $criteria->getFilters());
        self::assertTrue($criteria->hasFilters());
    }

    public function testColonneDeTriForgeeRemplaceeParLeTriParDefaut(): void
    {
        $post = $this->post(['order' => [['column' => '0', 'dir' => 'asc']]]);
        $post['columns'][0]['data'] = 'd.id; DROP TABLE user; --';

        $criteria = DemandeListCriteria::fromDataTables($post);

        self::assertSame('demandeId', $criteria->getSortColumn());
        self::assertSame('desc', $criteria->getSortDirection());
    }

    public function testColonneNonTriableRemplaceeParLeTriParDefaut(): void
    {
        $criteria = DemandeListCriteria::fromDataTables($this->post(['order' => [['column' => '11', 'dir' => 'asc']]]));

        self::assertSame('demandeId', $criteria->getSortColumn());
        self::assertSame('desc', $criteria->getSortDirection());
    }

    /**
     * @dataProvider provideDirections
     */
    public function testSensDeTriEnListeBlanche(mixed $dir, string $expected): void
    {
        $criteria = DemandeListCriteria::fromDataTables($this->post(['order' => [['column' => '4', 'dir' => $dir]]]));

        self::assertSame('demandeDateCreation', $criteria->getSortColumn());
        self::assertSame($expected, $criteria->getSortDirection());
    }

    public static function provideDirections(): iterable
    {
        yield 'asc' => ['asc', 'asc'];
        yield 'majuscules' => ['ASC', 'asc'];
        yield 'desc' => ['desc', 'desc'];
        yield 'injection' => ['asc, (SELECT SLEEP(5))', 'desc'];
        yield 'tableau' => [['asc'], 'desc'];
    }

    /**
     * @dataProvider providePagination
     */
    public function testPaginationBornee(mixed $start, mixed $length, int $expectedStart, int $expectedLength): void
    {
        $criteria = DemandeListCriteria::fromDataTables($this->post(['start' => $start, 'length' => $length]));

        self::assertSame($expectedStart, $criteria->getStart());
        self::assertSame($expectedLength, $criteria->getLength());
    }

    public static function providePagination(): iterable
    {
        yield 'valeurs normales' => ['25', '25', 25, 25];
        yield 'toutes les lignes (-1)' => ['0', '-1', 0, DemandeListCriteria::DEFAULT_LENGTH];
        yield 'trop de lignes' => ['0', '100000', 0, DemandeListCriteria::MAX_LENGTH];
        yield 'début négatif' => ['-5', '10', 0, 10];
        yield 'texte' => ['0 OR 1=1', '10; --', 0, DemandeListCriteria::DEFAULT_LENGTH];
        yield 'absents' => [null, null, 0, DemandeListCriteria::DEFAULT_LENGTH];
    }

    public function testNeGardeQueLesRecherchesConnuesEtNonVides(): void
    {
        $filters = DemandeListCriteria::normalizeFilters([
            'demandeId'           => '',
            'demandeType'         => 31,
            'logement'            => ['76'],
            'auditEId'            => '12',
            'action'              => 'x',
            'b.nom) OR (1'        => 'x',
            'partenaire'          => 'SAS " OR "1"="1',
            'commissionDate'      => null,
        ]);

        self::assertSame(['demandeType' => '31', 'partenaire' => 'SAS " OR "1"="1'], $filters);
    }

    public function testRefuseUnTriInconnuALaConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DemandeListCriteria([], 'b.nom');
    }

    /**
     * Requête DataTables reproduisant les colonnes de la liste (appel_datatables_ajax.js).
     *
     * @param array<string, mixed>  $overrides
     * @param array<string, string> $searches
     *
     * @return array<string, mixed>
     */
    private function post(array $overrides = [], array $searches = []): array
    {
        $columns = [];
        foreach ([...DemandeListCriteria::SORT_COLUMNS, 'action'] as $data) {
            $columns[] = ['data' => $data, 'search' => ['value' => $searches[$data] ?? '', 'regex' => 'false']];
        }

        return $overrides + [
            'draw'    => '1',
            'columns' => $columns,
            'order'   => [['column' => '0', 'dir' => 'desc']],
            'start'   => '0',
            'length'  => '25',
        ];
    }
}
