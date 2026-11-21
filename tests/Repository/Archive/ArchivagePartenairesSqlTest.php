<?php

declare(strict_types=1);

namespace App\Tests\Repository\Archive;

use App\Repository\Archive\ArchivagePartenairesSql;
use PHPUnit\Framework\TestCase;

/**
 * Garde-fous sur la résolution des destinataires de l'annonce d'archivage.
 *
 * Le danger que ces tests surveillent est discret : faute de colonne de
 * liaison, un professionnel est rattaché à son compte par la convention de nom
 * (« C » + identifiant de conseiller, « A » et « R » + identifiant de
 * partenaire). Or un conseiller n° 7 et un partenaire n° 7 existent tous les
 * deux. Si le préfixe disparaissait de la jointure, l'un recevrait la liste
 * des dossiers de l'autre — un courriel parti chez le mauvais professionnel ne
 * se rattrape pas.
 *
 * La résolution elle-même a été vérifiée sur la copie de production le
 * 21/11/2026 : 291 comptes « C », 33 « A » et 214 « R » se résolvent tous.
 */
class ArchivagePartenairesSqlTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function fournirLesDeuxRequetes(): iterable
    {
        yield 'destinataires' => [ArchivagePartenairesSql::destinatairesEtDossiers()];
        yield 'dossiers sans destinataire' => [ArchivagePartenairesSql::dossiersSansDestinataire()];
    }

    /**
     * @dataProvider fournirLesDeuxRequetes
     */
    public function testLaJointureQualifieLIdentifiantParLePrefixe(string $sql): void
    {
        self::assertStringContainsString(
            'LEFT(u.username, 1) = r.prefixe',
            $sql,
            'le préfixe doit faire partie de la jointure, sinon les profils se confondent'
        );
        self::assertStringContainsString(
            'CAST(SUBSTRING(u.username, 2) AS UNSIGNED) = r.professionnel_id',
            $sql
        );
    }

    /**
     * @dataProvider fournirLesDeuxRequetes
     */
    public function testChaquePrefixeExigeSonRole(string $sql): void
    {
        self::assertStringContainsString("'C' = r.prefixe AND u.roles LIKE '%ROLE_CONSEILLER%'", $sql);
        self::assertStringContainsString("'A' = r.prefixe AND u.roles LIKE '%ROLE_AUDITEUR%'", $sql);
        self::assertStringContainsString("'R' = r.prefixe AND u.roles LIKE '%ROLE_RENOVATEUR%'", $sql);
    }

    /**
     * Un compte désactivé ou sans adresse ne peut pas exporter : l'annoncer
     * n'aurait pas d'objet, et gonflerait le nombre d'envois en échec.
     *
     * @dataProvider fournirLesDeuxRequetes
     */
    public function testLesComptesInjoignablesSontEcartes(string $sql): void
    {
        self::assertStringContainsString('u.enabled = 1', $sql);
        self::assertStringContainsString('u.email IS NOT NULL', $sql);
        self::assertStringContainsString("u.email <> ''", $sql);
    }

    /**
     * @dataProvider fournirLesDeuxRequetes
     */
    public function testLeLotEtLEtatSontDesParametres(string $sql): void
    {
        self::assertStringContainsString('alb.lot_id = :lot_id', $sql);
        self::assertStringContainsString('alb.etat = :etat', $sql);
    }

    /**
     * UNION et non UNION ALL : un auditeur peut être rattaché deux fois au même
     * dossier (par l'audit énergétique et par le devis travaux), et son dossier
     * ne doit figurer qu'une fois dans son courriel.
     */
    public function testLesRattachementsSontDedoublonnes(): void
    {
        $sql = ArchivagePartenairesSql::destinatairesEtDossiers();

        self::assertStringNotContainsString('UNION ALL', $sql);
        self::assertGreaterThanOrEqual(
            6,
            substr_count($sql, 'UNION'),
            'les sept chemins de rattachement doivent tous être présents'
        );
    }

    /**
     * Les trois chemins d'un auditeur et le chemin du rénovateur passent par
     * des tables différentes : si l'un disparaissait, des professionnels ne
     * seraient pas prévenus sans que rien ne le signale.
     */
    public function testLesQuatreTablesPorteusesSontInterrogees(): void
    {
        $sql = ArchivagePartenairesSql::destinatairesEtDossiers();

        foreach (['demande_travaux', 'demande_travaux_devis', 'demande_audit_energie', 'demande_audit_numerique'] as $table) {
            self::assertStringContainsString($table, $sql, sprintf('la table %s doit être interrogée', $table));
        }

        self::assertStringContainsString('dtd.renovateur_id', $sql);
        self::assertStringContainsString('dtd.auditeur_id', $sql);
        self::assertStringContainsString('dae.auditeur_id', $sql);
        self::assertStringContainsString('dan.auditeur_id', $sql);
    }
}
