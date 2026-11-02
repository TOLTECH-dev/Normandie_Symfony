<?php

declare(strict_types=1);

namespace App\Tests\Repository\Beneficiaire;

use App\Repository\Beneficiaire\BeneficiaireListCriteria;
use App\Repository\Beneficiaire\BeneficiaireListSql;
use PHPUnit\Framework\TestCase;

final class BeneficiaireListTest extends TestCase
{
    private const CHARGE = "mar' OR 1=1 -- ";

    /**
     * @param array<mixed> $extra
     *
     * @return array<mixed>
     */
    private static function payload(array $extra = []): array
    {
        return array_replace([
            'draw' => 1,
            'columns' => array_map(
                static fn (string $c): array => ['data' => $c, 'search' => ['value' => '']],
                BeneficiaireListCriteria::COLONNES_LISTE
            ),
            'order' => [['column' => 0, 'dir' => 'asc']],
            'start' => 0,
            'length' => 25,
            'search' => ['value' => ''],
        ], $extra);
    }

    public function testLaSaisieNestJamaisEcriteDansLeSql(): void
    {
        $criteria = BeneficiaireListCriteria::pourListe(self::payload(['search' => ['value' => self::CHARGE]]));
        [$sql, $parametres] = BeneficiaireListSql::conditions($criteria);

        self::assertStringNotContainsString('OR 1=1', $sql);
        self::assertStringNotContainsString('mar', $sql);
        self::assertStringContainsString(':recherche_0', $sql);

        // La charge est découpée en mots, comme n'importe quelle recherche : chacun devient un
        // paramètre lié, et aucun n'atteint la requête.
        self::assertSame(["%mar'%", '%or%', '%1=1%', '%--%'], array_values($parametres));
    }

    /**
     * Chaque mot doit correspondre, les colonnes étant liées par OR : « dupont caen » ne retient
     * que les bénéficiaires satisfaisant les deux termes, comme avant la correction.
     */
    public function testChaqueMotDonneSonPropreParametre(): void
    {
        $criteria = BeneficiaireListCriteria::pourListe(self::payload(['search' => ['value' => 'dupont caen']]));
        [$sql, $parametres] = BeneficiaireListSql::conditions($criteria);

        self::assertSame(['recherche_0' => '%dupont%', 'recherche_1' => '%caen%'], $parametres);
        // Deux groupes de colonnes, donc une seule jonction « AND » entre eux.
        self::assertSame(1, substr_count($sql, ') AND ('));
        self::assertStringContainsString('LCASE(b.nom) LIKE :recherche_0', $sql);
    }

    public function testLaRechercheEstRamenéeEnMinuscules(): void
    {
        $criteria = BeneficiaireListCriteria::pourListe(self::payload(['search' => ['value' => 'DUPONT']]));
        [, $parametres] = BeneficiaireListSql::conditions($criteria);

        self::assertSame(['recherche_0' => '%dupont%'], $parametres);
    }

    public function testSansRechercheAucuneConditionNiParametre(): void
    {
        $criteria = BeneficiaireListCriteria::pourListe(self::payload());

        self::assertSame(['', []], BeneficiaireListSql::conditions($criteria));
    }

    /**
     * @dataProvider provideTrisForges
     */
    public function testUneColonneDeTriInconnueRamèneAuTriParDefaut(string $colonne): void
    {
        $payload = self::payload();
        $payload['columns'][0]['data'] = $colonne;

        $criteria = BeneficiaireListCriteria::pourListe($payload);

        self::assertSame(BeneficiaireListCriteria::TRI_DEFAUT, $criteria->getColonneTri());
        self::assertSame(' ORDER BY beneficiaireId DESC', BeneficiaireListSql::orderBy($criteria));
    }

    public static function provideTrisForges(): iterable
    {
        yield 'champ non affiché' => ['b.email'];
        yield 'sous-requête' => ['b.nom, (SELECT password FROM user LIMIT 1)'];
        yield 'parenthèse' => ['b.nom) OR (1'];
        yield 'colonne vide' => [''];
    }

    public function testChaqueColonneDeLEcranEstTriable(): void
    {
        foreach (BeneficiaireListCriteria::COLONNES_LISTE as $rang => $colonne) {
            $criteria = BeneficiaireListCriteria::pourListe(self::payload(['order' => [['column' => $rang, 'dir' => 'asc']]]));

            self::assertSame($colonne, $criteria->getColonneTri());
            self::assertMatchesRegularExpression('/^ ORDER BY [a-zA-Z]+ ASC$/', BeneficiaireListSql::orderBy($criteria));
        }
    }

    public function testLeSensDeTriEstReduitADeuxValeurs(): void
    {
        foreach (['ASC, b.email' => 'desc', 'asc; DROP TABLE user' => 'desc', 'asc' => 'asc', 'DESC' => 'desc'] as $saisi => $attendu) {
            $criteria = BeneficiaireListCriteria::pourListe(self::payload(['order' => [['column' => 0, 'dir' => $saisi]]]));
            self::assertSame($attendu, $criteria->getSensTri(), sprintf('sens « %s »', $saisi));
        }
    }

    /**
     * Une borne qui n'est pas un entier propre n'est pas « nettoyée » mais rejetée : « 10 UNION
     * SELECT 1 » vaut zéro, et non dix. Rien de ce qui suit le nombre ne peut donc subsister.
     */
    public function testLaPaginationEstBorneeEtEntiere(): void
    {
        $criteria = BeneficiaireListCriteria::pourListe(self::payload([
            'start' => '10 UNION SELECT 1',
            'length' => '99999',
        ]));

        self::assertSame(0, $criteria->getDebut());
        self::assertSame(BeneficiaireListCriteria::LONGUEUR_MAX, $criteria->getLongueur());
        self::assertSame(' LIMIT 0, 100', BeneficiaireListSql::limit($criteria));
    }

    public function testUnePaginationLegitimeEstConservee(): void
    {
        $criteria = BeneficiaireListCriteria::pourListe(self::payload(['start' => '50', 'length' => '10']));

        self::assertSame(' LIMIT 50, 10', BeneficiaireListSql::limit($criteria));
    }

    public function testLaPaginationNegativeEstRamenéeAZero(): void
    {
        $criteria = BeneficiaireListCriteria::pourListe(self::payload(['start' => -50, 'length' => -1]));

        self::assertSame(0, $criteria->getDebut());
        self::assertSame(BeneficiaireListCriteria::LONGUEUR_DEFAUT, $criteria->getLongueur());
    }

    /**
     * L'écran d'assistance ne montre ni la commune ni la structure : sa recherche ne doit pas y
     * porter non plus, sous peine d'élargir silencieusement ce que voit un conseiller.
     */
    public function testLAssistanceChercheSurSesSeulesColonnes(): void
    {
        $criteria = BeneficiaireListCriteria::pourAssistance(self::payload(['search' => ['value' => 'dupont']]));
        [$sql] = BeneficiaireListSql::conditions($criteria);

        self::assertStringContainsString('b.auteur_creation', $sql);
        self::assertStringNotContainsString('b.ville', $sql);
        self::assertStringNotContainsString('scr.nom', $sql);
    }

    public function testLePerimetreDeStructureEstLieEtNonConcatene(): void
    {
        $criteria = BeneficiaireListCriteria::pourAssistance(self::payload())->pourStructure(42);
        [$sql, $parametres] = BeneficiaireListSql::conditions($criteria);

        self::assertSame(' AND b.structure_rattachement_id = :structure_rattachement', $sql);
        self::assertSame(['structure_rattachement' => '42'], $parametres);
        self::assertStringNotContainsString('42', str_replace(':structure_rattachement', '', $sql));
    }

    public function testLePerimetreSAjouteALaRecherche(): void
    {
        $criteria = BeneficiaireListCriteria::pourAssistance(self::payload(['search' => ['value' => 'dupont']]))
            ->pourStructure(7);
        [$sql, $parametres] = BeneficiaireListSql::conditions($criteria);

        self::assertStringStartsWith(' AND b.structure_rattachement_id = :structure_rattachement AND (', $sql);
        self::assertSame(['structure_rattachement' => '7', 'recherche_0' => '%dupont%'], $parametres);
    }

    /**
     * Les critères sont immuables : restreindre le périmètre ne doit pas modifier l'objet d'origine,
     * sous peine de faire fuiter un périmètre d'un écran à l'autre.
     */
    public function testLaRestrictionDePerimetreNeModifiePasLOriginal(): void
    {
        $criteria = BeneficiaireListCriteria::pourAssistance(self::payload());
        $restreint = $criteria->pourStructure(7);

        self::assertNull($criteria->getStructureRattachement());
        self::assertSame(7, $restreint->getStructureRattachement());
    }

    public function testUneRechercheTresDecoupeeEstBornee(): void
    {
        $criteria = BeneficiaireListCriteria::pourListe(self::payload([
            'search' => ['value' => implode(' ', array_fill(0, 50, 'mot'))],
        ]));

        self::assertCount(10, $criteria->motsRecherches());
    }
}
