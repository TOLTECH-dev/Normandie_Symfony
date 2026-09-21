<?php

declare(strict_types=1);

namespace App\Tests\Repository\User;

use App\Repository\User\UserListCriteria;
use PHPUnit\Framework\TestCase;

class UserListCriteriaTest extends TestCase
{
    public function testLesCriteresDeLaPageSontRepris(): void
    {
        $criteria = UserListCriteria::fromDataTables($this->post(
            ['order' => [['column' => '1', 'dir' => 'asc']], 'start' => '50', 'length' => '10'],
            ['username' => 'dupont', 'email' => '@normandie.fr']
        ));

        self::assertSame('username', $criteria->getSortColumn());
        self::assertSame('asc', $criteria->getSortDirection());
        self::assertSame(50, $criteria->getStart());
        self::assertSame(10, $criteria->getLength());
        self::assertSame(['username' => 'dupont', 'email' => '@normandie.fr'], $criteria->getFilters());
    }

    /**
     * Le défaut corrigé : le nom de colonne venait du client et servait à bâtir « u.<colonne> LIKE ».
     * Filtrer sur le mot de passe ou le jeton de confirmation permettait de les extraire.
     *
     * @dataProvider provideColonnesInterdites
     */
    public function testUneColonneHorsDeLaPageEstIgnoree(string $colonne): void
    {
        $criteria = UserListCriteria::fromDataTables($this->post([], [$colonne => '$2y$13$a%']));

        self::assertSame([], $criteria->getFilters());
    }

    public static function provideColonnesInterdites(): iterable
    {
        yield 'mot de passe' => ['password'];
        yield 'jeton de confirmation' => ['confirmationToken'];
        yield 'sel' => ['salt'];
        yield 'expression forgée' => ['id) OR (1=1'];
    }

    /**
     * @dataProvider provideTrisForges
     */
    public function testUnTriForgeRevientAuTriParDefaut(string $colonne, string $sens): void
    {
        $post = $this->post(['order' => [['column' => '0', 'dir' => $sens]]]);
        $post['columns'][0]['data'] = $colonne;

        $criteria = UserListCriteria::fromDataTables($post);

        self::assertSame(UserListCriteria::DEFAULT_SORT, $criteria->getSortColumn());
        self::assertSame('desc', $criteria->getSortDirection());
    }

    public static function provideTrisForges(): iterable
    {
        yield 'tri sur le mot de passe' => ['password', 'asc'];
        yield 'tri sur une expression' => ['u.id, u.password', 'asc'];
        yield 'sens forgé' => ['id', 'asc, u.password'];
    }

    public function testPaginationBornee(): void
    {
        $criteria = UserListCriteria::fromDataTables($this->post(['start' => '-5', 'length' => '100000']));

        self::assertSame(0, $criteria->getStart());
        self::assertSame(UserListCriteria::MAX_LENGTH, $criteria->getLength());
    }

    public function testLaRechercheGlobaleEstConserveeTelleQuelle(): void
    {
        $criteria = UserListCriteria::fromDataTables($this->post(['search' => ['value' => "o'brien"]]));

        self::assertTrue($criteria->hasSearch());
        self::assertSame("o'brien", $criteria->getSearch());
        self::assertFalse($criteria->hasFilters());
    }

    public function testRefuseUnTriInconnuALaConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UserListCriteria([], '', 'password');
    }

    /**
     * @param array<string, mixed>  $overrides
     * @param array<string, string> $recherches
     *
     * @return array<string, mixed>
     */
    private function post(array $overrides = [], array $recherches = []): array
    {
        $columns = [];
        foreach ([...UserListCriteria::COLUMNS, 'action'] as $data) {
            $columns[] = ['data' => $data, 'search' => ['value' => $recherches[$data] ?? '']];
        }

        return $overrides + [
            'draw'    => '1',
            'columns' => $columns,
            'order'   => [['column' => '0', 'dir' => 'desc']],
            'start'   => '0',
            'length'  => '25',
            'search'  => ['value' => ''],
        ];
    }
}
