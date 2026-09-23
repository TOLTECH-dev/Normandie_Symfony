<?php

declare(strict_types=1);

namespace App\Tests\Repository\Demande;

use App\Repository\Demande\DemandeListCriteria;
use PHPUnit\Framework\TestCase;

class DemandeListCriteriaTest extends TestCase
{
    /**
     * Colonnes de la liste, telles que les déclare appel_datatables_ajax.js : « data » désigne la
     * donnée affichée, « name » le nom logique reconnu par le serveur. Une colonne regroupée (dossier,
     * bénéficiaire, échéances) porte donc un nom différent de sa donnée principale.
     */
    private const COLONNES = [
        ['data' => 'demandeId', 'name' => 'dossier'],
        ['data' => 'beneficiaireIdentifiant', 'name' => 'beneficiaire'],
        ['data' => 'structureConseiller', 'name' => 'structureConseiller'],
        ['data' => 'partenaire', 'name' => 'partenaire'],
        ['data' => 'commissionDate', 'name' => 'echeances'],
        ['data' => 'action', 'name' => 'action'],
        // Colonnes masquées : affichées dans la cellule « Dossier », mais recherchables à part
        ['data' => 'demandeType', 'name' => 'demandeType'],
        ['data' => 'demandeStatutSlug', 'name' => 'demandeStatutSlug'],
    ];

    public function testLitLesCriteresDataTables(): void
    {
        $criteria = DemandeListCriteria::fromDataTables($this->post([
            'order'  => [['column' => '1', 'dir' => 'asc']],
            'start'  => '50',
            'length' => '10',
        ], ['beneficiaire' => 'mar', 'demandeType' => '31']));

        self::assertSame('beneficiaire', $criteria->getSortColumn());
        self::assertSame('asc', $criteria->getSortDirection());
        self::assertSame(50, $criteria->getStart());
        self::assertSame(10, $criteria->getLength());
        self::assertSame(['beneficiaire' => 'mar', 'demandeType' => '31'], $criteria->getFilters());
        self::assertTrue($criteria->hasFilters());
    }

    /** Une colonne regroupée est reconnue par son nom logique, pas par la donnée qu'elle affiche */
    public function testUtiliseLeNomDeColonnePlutotQueLaDonnee(): void
    {
        $criteria = DemandeListCriteria::fromDataTables($this->post(
            ['order' => [['column' => '4', 'dir' => 'asc']]],
            ['dossier' => '1234', 'echeances' => '12/03/2026']
        ));

        self::assertSame('echeances', $criteria->getSortColumn());
        self::assertSame(['dossier' => '1234', 'echeances' => '12/03/2026'], $criteria->getFilters());
    }

    public function testColonneDeTriForgeeRemplaceeParLeTriParDefaut(): void
    {
        $post = $this->post(['order' => [['column' => '0', 'dir' => 'asc']]]);
        $post['columns'][0]['name'] = 'd.id; DROP TABLE user; --';
        $post['columns'][0]['data'] = 'd.id; DROP TABLE user; --';

        $criteria = DemandeListCriteria::fromDataTables($post);

        self::assertSame('demandeId', $criteria->getSortColumn());
        self::assertSame('desc', $criteria->getSortDirection());
    }

    public function testColonneNonTriableRemplaceeParLeTriParDefaut(): void
    {
        // Colonne 5 : « Action »
        $criteria = DemandeListCriteria::fromDataTables($this->post(['order' => [['column' => '5', 'dir' => 'asc']]]));

        self::assertSame('demandeId', $criteria->getSortColumn());
        self::assertSame('desc', $criteria->getSortDirection());
    }

    /**
     * @dataProvider provideDirections
     */
    public function testSensDeTriEnListeBlanche(mixed $dir, string $expected): void
    {
        $criteria = DemandeListCriteria::fromDataTables($this->post(['order' => [['column' => '2', 'dir' => $dir]]]));

        self::assertSame('structureConseiller', $criteria->getSortColumn());
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
            'dossier'             => '',
            'demandeType'         => 31,
            'beneficiaire'        => ['76'],
            'auditEId'            => '12',
            'action'              => 'x',
            'b.nom) OR (1'        => 'x',
            'partenaire'          => 'SAS " OR "1"="1',
            'echeances'           => null,
        ]);

        self::assertSame(['demandeType' => '31', 'partenaire' => 'SAS " OR "1"="1'], $filters);
    }

    /** Les noms atomiques restent acceptés : les exports enregistrés avant le regroupement les utilisent */
    public function testAccepteEncoreLesAnciensNomsDeColonnes(): void
    {
        $filters = DemandeListCriteria::normalizeFilters([
            'beneficiaireIdentifiant' => 'martin',
            'logement'                => '76000',
            'demandeDateCreation'     => '12/03/2026',
            'remboursementDate'       => '30/06/2026',
        ]);

        self::assertSame([
            'beneficiaireIdentifiant' => 'martin',
            'logement'                => '76000',
            'demandeDateCreation'     => '12/03/2026',
            'remboursementDate'       => '30/06/2026',
        ], $filters);
    }

    public function testRechercheGeneraleLueEtTronquee(): void
    {
        $post = $this->post();
        $post['search'] = ['value' => '  ' . str_repeat('a', DemandeListCriteria::MAX_SEARCH_LENGTH + 50) . '  ', 'regex' => 'false'];

        $criteria = DemandeListCriteria::fromDataTables($post);

        self::assertTrue($criteria->hasGlobalSearch());
        self::assertSame(DemandeListCriteria::MAX_SEARCH_LENGTH, mb_strlen($criteria->getGlobalSearch()));
    }

    public function testRechercheGeneraleAbsenteOuInattendue(): void
    {
        $post = $this->post();
        $post['search'] = ['value' => ['x']];

        self::assertFalse(DemandeListCriteria::fromDataTables($post)->hasGlobalSearch());
        self::assertSame('', DemandeListCriteria::fromDataTables($this->post())->getGlobalSearch());
    }

    /** Une recherche par colonne est bornée comme la recherche générale */
    public function testRechercheParColonneTronquee(): void
    {
        $filters = DemandeListCriteria::normalizeFilters([
            'beneficiaire' => str_repeat('b', DemandeListCriteria::MAX_SEARCH_LENGTH + 10),
        ]);

        self::assertSame(DemandeListCriteria::MAX_SEARCH_LENGTH, mb_strlen($filters['beneficiaire']));
    }

    /** Choix multiple : la sélection arrive séparée par des barres verticales */
    public function testChoixMultipleDecoupe(): void
    {
        self::assertSame(
            ['Demande refusée', 'Demande incomplète'],
            DemandeListCriteria::splitValues(' Demande refusée | Demande incomplète |  | Demande refusée ')
        );
        self::assertSame([], DemandeListCriteria::splitValues(' | '));
        self::assertCount(
            DemandeListCriteria::MAX_MULTI_VALUES,
            DemandeListCriteria::splitValues(implode('|', range(1, DemandeListCriteria::MAX_MULTI_VALUES + 20)))
        );
    }

    /** Une liste de statuts dépasse forcément la borne d'une recherche libre : elle a la sienne */
    public function testChoixMultipleBornePlusLargement(): void
    {
        $statuts = implode('|', array_fill(0, 30, 'Demande en attente instruction Prestataire'));

        $filters = DemandeListCriteria::normalizeFilters([
            'demandeStatutSlug' => $statuts,
            'beneficiaire'      => $statuts,
        ]);

        self::assertSame($statuts, $filters['demandeStatutSlug']);
        self::assertSame(DemandeListCriteria::MAX_SEARCH_LENGTH, mb_strlen($filters['beneficiaire']));
    }

    /** Envoi de formulaire : un champ à choix multiple peut arriver sous forme de tableau */
    public function testChoixMultipleEnvoyeCommeTableau(): void
    {
        $filters = DemandeListCriteria::normalizeFilters([
            'demandeStatutSlug' => ['Demande refusée', 'Demande incomplète'],
            'demandeType'       => ['1', '31'],
            'beneficiaire'      => ['martin'],
        ]);

        self::assertSame('Demande refusée|Demande incomplète', $filters['demandeStatutSlug']);
        self::assertSame('1|31', $filters['demandeType']);
        self::assertArrayNotHasKey('beneficiaire', $filters);
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
