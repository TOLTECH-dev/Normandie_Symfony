<?php

declare(strict_types=1);

namespace App\Tests\Repository\Titre;

use App\Repository\Titre\TitreListSort;
use PHPUnit\Framework\TestCase;

final class TitreListSortTest extends TestCase
{
    /**
     * @param array<mixed> $colonnes
     *
     * @return array<mixed>
     */
    private static function payload(array $colonnes, int $index = 0, string $sens = 'asc'): array
    {
        return [
            'columns' => array_map(static fn (string $c): array => ['data' => $c], $colonnes),
            'order' => [['column' => $index, 'dir' => $sens]],
        ];
    }

    /**
     * @dataProvider provideColonnesDeLEcran
     */
    public function testLesColonnesDeLEcranSontTriables(string $colonne, string $expression): void
    {
        $tri = TitreListSort::depuisDataTables(self::payload([$colonne]));

        self::assertSame($expression, $tri->expression());
        self::assertSame('ASC', $tri->sens());
    }

    public static function provideColonnesDeLEcran(): iterable
    {
        yield 'thématique' => ['demandeType', 'd.type'];
        yield 'bénéficiaire' => ['beneficiaire', 'beneficiaire'];
        yield 'dossier' => ['demandeId', 't.demandeId'];
        yield 'production' => ['productionId', 't.productionId'];
        yield 'numéro de chèque' => ['numeroCheque', 't.numeroCheque'];
        yield 'montant' => ['valeurTitre', 't.valeurTitre'];
        yield 'émission' => ['dateEmission', 't.dateEmission'];
        yield 'validité' => ['dateValidite', 't.dateValidite'];
    }

    /**
     * Le défaut corrigé : la colonne atteignait `QueryBuilder::orderBy()`, donc le DQL. Un champ
     * non affiché, comme l'adresse de courriel, servait alors d'oracle.
     *
     * @dataProvider provideColonnesForgees
     */
    public function testToutAutreTriRamèneALaColonneParDefaut(string $colonne): void
    {
        $tri = TitreListSort::depuisDataTables(self::payload([$colonne]));

        self::assertSame('t.demandeId', $tri->expression());
    }

    public static function provideColonnesForgees(): iterable
    {
        yield 'champ non affiché' => ['b.email'];
        yield 'entité non jointe' => ['u.password'];
        yield 'sous-requête' => ['t.id, (SELECT u.password FROM App\Entity\User u)'];
        yield 'parenthèse' => ['d.type) OR (1=1'];
        yield 'colonne vide' => [''];
        yield 'colonne inconnue' => ['colonneInventee'];
    }

    /**
     * @dataProvider provideSensDeTri
     */
    public function testLeSensEstToujoursAscOuDesc(string $saisi, string $attendu): void
    {
        $tri = TitreListSort::depuisDataTables(self::payload(['demandeId'], 0, $saisi));

        self::assertSame($attendu, $tri->sens());
    }

    public static function provideSensDeTri(): iterable
    {
        yield 'ascendant' => ['asc', 'ASC'];
        yield 'ascendant en majuscules' => ['ASC', 'ASC'];
        yield 'espaces autour' => ['  asc  ', 'ASC'];
        yield 'descendant' => ['desc', 'DESC'];
        yield 'colonne ajoutée' => ['ASC, b.email', 'DESC'];
        yield 'commande ajoutée' => ['asc; DROP TABLE titre', 'DESC'];
        yield 'vide' => ['', 'DESC'];
    }

    public function testUnIndexDeColonneAberrantRamèneAuDefaut(): void
    {
        foreach ([99, -1, 'deux', null] as $index) {
            $payload = self::payload(['demandeId', 'numeroCheque']);
            $payload['order'][0]['column'] = $index;

            $tri = TitreListSort::depuisDataTables($payload);

            self::assertSame('t.demandeId', $tri->expression(), sprintf('index %s', var_export($index, true)));
        }
    }

    public function testUneRequeteSansTriRamèneAuDefaut(): void
    {
        $tri = TitreListSort::depuisDataTables([]);

        self::assertSame('t.demandeId', $tri->expression());
        self::assertSame('DESC', $tri->sens());
    }
}
