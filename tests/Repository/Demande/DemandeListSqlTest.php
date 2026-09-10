<?php

declare(strict_types=1);

namespace App\Tests\Repository\Demande;

use App\Repository\Demande\DemandeListSql;
use PHPUnit\Framework\TestCase;

class DemandeListSqlTest extends TestCase
{
    private const PAYLOAD = 'mar" OR "1"="1\' OR 1=1 -- ';

    /**
     * @dataProvider provideTextFilters
     */
    public function testLaSaisieNestJamaisEcriteDansLeSql(string $field): void
    {
        $listSql = $this->listSql()->filterBy([$field => self::PAYLOAD]);

        foreach ([$listSql->countQuery(), $listSql->pageQuery('demandeId', 'desc', 0, 25)] as $sql) {
            self::assertStringNotContainsString('OR "1"="1', $sql);
            self::assertStringNotContainsString('mar', $sql);
            self::assertStringContainsString(':filtre_' . $field, $sql);
        }
        self::assertSame(['filtre_' . $field => '%' . self::PAYLOAD . '%'], $listSql->getParams());

        [$conditions, $params] = DemandeListSql::exportFilterConditions([$field => self::PAYLOAD]);
        self::assertStringNotContainsString('mar', implode(' ', $conditions));
        self::assertSame(['filtre_' . $field => '%' . self::PAYLOAD . '%'], $params);
    }

    public static function provideTextFilters(): iterable
    {
        foreach (['demandeId', 'beneficiaireIdentifiant', 'logement', 'demandeDateCreation', 'demandeStatutSlug',
                     'structureConseiller', 'partenaire', 'commissionDate', 'remboursementDate'] as $field) {
            yield $field => [$field];
        }
    }

    public function testColonnesInconnuesIgnorees(): void
    {
        $listSql = $this->listSql()->filterBy(['b.nom) OR (1' => 'x', 'auditEId' => '1']);
        [$conditions, $params] = DemandeListSql::exportFilterConditions(['d.id) OR (1' => 'x']);

        self::assertSame($this->listSql()->countQuery(), $listSql->countQuery());
        self::assertSame([], $listSql->getParams());
        self::assertSame([[], []], [$conditions, $params]);
    }

    /**
     * @dataProvider provideDemandeTypes
     */
    public function testThematiqueEnListeBlanche(string $value, ?string $expected): void
    {
        $sql = $this->listSql()->filterBy(['demandeType' => $value])->countQuery();

        if (null === $expected) {
            self::assertSame($this->listSql()->countQuery(), $sql);
        } else {
            self::assertStringContainsString($expected, $sql);
            self::assertStringContainsString('LEFT JOIN demande_travaux_devis dtd', $sql);
        }
    }

    public static function provideDemandeTypes(): iterable
    {
        yield 'audit énergétique' => ['1', '(d.type = 1 AND dtd.niveau IS NULL)'];
        yield 'travaux à définir' => ['3', '(d.type = 3 AND dtd.niveau IS NULL)'];
        yield 'travaux niveau 2' => ['31', "(d.type = 3 AND SUBSTRING(dtd.niveau, 1, 1) = '1')"];
        yield 'rénovation globale BBC' => ['39', "(d.type = 3 AND SUBSTRING(dtd.niveau, 1, 1) = '9')"];
        yield 'code inconnu' => ['99', null];
        yield 'injection' => ['1 OR 1=1', null];
    }

    public function testStatutAfficheDansLaListeEtDemandeOuRemboursementDansLExport(): void
    {
        $listSql = $this->listSql()->filterBy(['demandeStatutSlug' => 'valid']);
        [$conditions] = DemandeListSql::exportFilterConditions(['demandeStatutSlug' => 'valid']);

        self::assertStringContainsString(
            'IF(rs.slug IS NOT NULL, rs.slug LIKE :filtre_demandeStatutSlug, ds.slug LIKE :filtre_demandeStatutSlug)',
            $listSql->countQuery()
        );
        self::assertSame(['(ds.slug LIKE :filtre_demandeStatutSlug OR rs.slug LIKE :filtre_demandeStatutSlug)'], $conditions);
    }

    public function testSansRechercheSeulesLesJointuresNecessairesSontEcrites(): void
    {
        $sql = $this->listSql()->pageQuery('demandeId', 'desc', 0, 25);

        foreach (['INNER JOIN beneficiaire b', 'INNER JOIN logement l', 'INNER JOIN demande_statut ds',
                     'LEFT JOIN titre t2', 'LEFT JOIN remboursement_ r2', 'LEFT JOIN remboursement_ r '] as $join) {
            self::assertStringContainsString($join, $sql);
        }
        foreach (['structure_identification', 'partenaire_identification', 'date_CP', 'date_RMH', 'dAuditE'] as $absent) {
            self::assertStringNotContainsString($absent, $sql);
        }
        self::assertStringEndsWith('GROUP BY d.id, r.id ORDER BY d.id DESC, r.id ASC LIMIT 0, 25', $sql);
    }

    public function testLesJointuresParentesPrecedentLeursEnfants(): void
    {
        $sql = $this->listSql()->filterBy(['partenaire' => 'SAS'])->countQuery();

        $positions = array_map(static fn (string $join): int|false => strpos($sql, $join), [
            'LEFT JOIN demande_travaux dt ',
            'LEFT JOIN demande_travaux_devis dtd ',
            'LEFT JOIN partenaire_ p_dtd ',
            'LEFT JOIN partenaire_identification pi_dtd ',
        ]);

        self::assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        self::assertSame($sorted, $positions);
    }

    public function testTriSurUneColonneCalculee(): void
    {
        $sql = $this->listSql()->pageQuery('beneficiaireIdentifiant', 'asc', 75, 25);

        self::assertStringContainsString("ORDER BY MAX(CASE WHEN ('1 | sci' != b.type)", $sql);
        self::assertStringEndsWith('ASC, d.id DESC, r.id ASC LIMIT 75, 25', $sql);
    }

    public function testTriSurLAuditEnergetiqueApresRegroupement(): void
    {
        $sql = $this->listSql()->pageQuery('auditEId', 'desc', 0, 25);

        self::assertStringContainsString(') AS lignes ORDER BY (SELECT dAuditE.id FROM demande_ dAuditE', $sql);
        self::assertStringContainsString('dAuditE.logement_id = lignes.logementId AND dAuditE.id <> lignes.demandeId', $sql);
    }

    public function testPerimetreAuditeur(): void
    {
        $sql = $this->listSql()->restrictToAuditeur(42)->countQuery();

        self::assertStringContainsString("(p_dae.id = 42 OR p_dan.id = 42 OR (p_dtd_aud.id = 42 AND dt.audit = '1'))", $sql);
        self::assertStringContainsString('LEFT JOIN partenaire_ p_dtd_aud ON dtd.auditeur_id = p_dtd_aud.id', $sql);
    }

    public function testDetailLimiteAuxDossiersDeLaPage(): void
    {
        $sql = DemandeListSql::detailQuery();

        self::assertStringContainsString('AS auditEId', $sql);
        self::assertStringEndsWith('WHERE d.id IN (:ids) GROUP BY d.id, r.id', $sql);
    }

    private function listSql(): DemandeListSql
    {
        return new DemandeListSql(588, 589);
    }
}
