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
        // Le statut n'y figure pas : il se choisit dans une liste, donc s'écrit en égalité stricte
        foreach (['dossier', 'beneficiaire', 'echeances',
                     'demandeId', 'beneficiaireIdentifiant', 'logement', 'demandeDateCreation',
                     'structureConseiller', 'partenaire', 'commissionDate', 'remboursementDate'] as $field) {
            yield $field => [$field];
        }
    }

    /**
     * Une colonne regroupée interroge tous les champs de son groupe, avec un seul paramètre lié.
     *
     * @dataProvider provideGroupedFilters
     *
     * @param list<string> $expressions
     */
    public function testColonneRegroupeeInterrogeTousSesChamps(string $field, array $expressions): void
    {
        $sql = $this->listSql()->filterBy([$field => 'mar'])->countQuery();

        $tests = array_map(static fn (string $e): string => $e . ' LIKE :filtre_' . $field, $expressions);
        self::assertStringContainsString('(' . implode(' OR ', $tests) . ')', $sql);

        // Le groupe entier ne consomme qu'un paramètre, répété dans chaque test
        self::assertSame(['filtre_' . $field => '%mar%'], $this->listSql()->filterBy([$field => 'mar'])->getParams());
    }

    public static function provideGroupedFilters(): iterable
    {
        yield 'dossier' => ['dossier', ['d.id', "DATE_FORMAT(d.date_creation, '%d/%m/%Y')"]];
        yield 'bénéficiaire' => ['beneficiaire', ['b.nom', 'b.prenom', 'b.nom_SCI', 'l.code_postal', 'l.ville']];
        yield 'échéances' => ['echeances', ["DATE_FORMAT(dCP.date_CP, '%d/%m/%Y')", "DATE_FORMAT(dRMH.date_RMH, '%d/%m/%Y')"]];
    }

    public function testRechercheGeneraleLieeEnParametre(): void
    {
        $listSql = $this->listSql()->searchEverywhere(self::PAYLOAD);

        foreach ([$listSql->countQuery(), $listSql->pageQuery('demandeId', 'desc', 0, 25)] as $sql) {
            self::assertStringNotContainsString('OR "1"="1', $sql);
            self::assertStringNotContainsString('mar', $sql);
            self::assertStringContainsString(':recherche_generale', $sql);
        }
        // La recherche générale passe par DemandeListCriteria::normalizeSearch : espaces de bord ôtés
        self::assertSame(['recherche_generale' => '%' . trim(self::PAYLOAD) . '%'], $listSql->getParams());
    }

    public function testRechercheGeneraleBalayeToutesLesColonnesAffichees(): void
    {
        $sql = $this->listSql()->searchEverywhere('mar')->countQuery();

        foreach (['d.id', "DATE_FORMAT(d.date_creation, '%d/%m/%Y')", 'b.nom', 'b.prenom', 'b.nom_SCI',
                     'l.code_postal', 'l.ville', "DATE_FORMAT(dCP.date_CP, '%d/%m/%Y')",
                     "DATE_FORMAT(dRMH.date_RMH, '%d/%m/%Y')", 'si_dae.nom', 'sc_dt.nom', 'pi_dtd.raison_sociale',
                     'IF(rs.slug IS NOT NULL, rs.slug, ds.slug)'] as $expression) {
            self::assertStringContainsString($expression . ' LIKE :recherche_generale', $sql);
        }

        // Les tables lues par ces champs sont bien jointes
        foreach (['LEFT JOIN date_CP dCP', 'LEFT JOIN date_RMH dRMH', 'LEFT JOIN structure_identification si_dae',
                     'LEFT JOIN partenaire_identification pi_dtd', 'LEFT JOIN remboursement_statut rs'] as $join) {
            self::assertStringContainsString($join, $sql);
        }
    }

    public function testRechercheGeneraleVideSansEffet(): void
    {
        $listSql = $this->listSql()->searchEverywhere('   ');

        self::assertSame($this->listSql()->countQuery(), $listSql->countQuery());
        self::assertSame([], $listSql->getParams());
    }

    /** Recherche générale et recherches par colonne se cumulent, chacune avec son paramètre */
    public function testRechercheGeneraleEtFiltresSeCumulent(): void
    {
        $listSql = $this->listSql()->filterBy(['beneficiaire' => 'martin'])->searchEverywhere('76000');

        self::assertSame([
            'filtre_beneficiaire' => '%martin%',
            'recherche_generale'  => '%76000%',
        ], $listSql->getParams());
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
        $listSql = $this->listSql()->filterBy(['demandeStatutSlug' => 'Demande refusée']);
        [$conditions, $params] = DemandeListSql::exportFilterConditions(['demandeStatutSlug' => 'Demande refusée']);

        self::assertStringContainsString(
            'IF(rs.slug IS NOT NULL, rs.slug IN (:filtre_demandeStatutSlug_0), ds.slug IN (:filtre_demandeStatutSlug_0))',
            $listSql->countQuery()
        );
        self::assertSame(['filtre_demandeStatutSlug_0' => 'Demande refusée'], $listSql->getParams());
        self::assertSame(
            ['(ds.slug IN (:filtre_demandeStatutSlug_0) OR rs.slug IN (:filtre_demandeStatutSlug_0))'],
            $conditions
        );
        self::assertSame(['filtre_demandeStatutSlug_0' => 'Demande refusée'], $params);
    }

    /** Plusieurs statuts choisis : une valeur liée par statut, jamais de texte dans le SQL */
    public function testPlusieursStatutsChoisis(): void
    {
        $valeur = 'Demande refusée|Demande incomplète|' . self::PAYLOAD;
        $listSql = $this->listSql()->filterBy(['demandeStatutSlug' => $valeur]);
        $sql = $listSql->countQuery();

        self::assertStringContainsString(
            'IN (:filtre_demandeStatutSlug_0, :filtre_demandeStatutSlug_1, :filtre_demandeStatutSlug_2)',
            $sql
        );
        self::assertStringNotContainsString('Demande refusée', $sql);
        self::assertStringNotContainsString('OR "1"="1', $sql);
        self::assertSame([
            'filtre_demandeStatutSlug_0' => 'Demande refusée',
            'filtre_demandeStatutSlug_1' => 'Demande incomplète',
            'filtre_demandeStatutSlug_2' => trim(self::PAYLOAD),
        ], $listSql->getParams());
    }

    /** Plusieurs thématiques choisies : chaque code passe par la liste blanche, les autres tombent */
    public function testPlusieursThematiquesChoisies(): void
    {
        $sql = $this->listSql()->filterBy(['demandeType' => '1|31|99|1 OR 1=1'])->countQuery();

        self::assertStringContainsString(
            "((d.type = 1 AND dtd.niveau IS NULL) OR (d.type = 3 AND SUBSTRING(dtd.niveau, 1, 1) = '1'))",
            $sql
        );
        self::assertStringNotContainsString('1 OR 1=1', $sql);
        self::assertSame([], $this->listSql()->filterBy(['demandeType' => '1|31'])->getParams());
    }

    /** Aucun code reconnu : la recherche n'ajoute aucune condition */
    public function testThematiquesToutesInconnues(): void
    {
        $listSql = $this->listSql()->filterBy(['demandeType' => '99|abc']);

        self::assertSame($this->listSql()->countQuery(), $listSql->countQuery());
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
