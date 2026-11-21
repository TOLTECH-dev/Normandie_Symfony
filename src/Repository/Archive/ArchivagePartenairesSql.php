<?php

declare(strict_types=1);

namespace App\Repository\Archive;

/**
 * Qui prévenir avant d'archiver (Reco-03, étape « annonce »).
 *
 * Le courriel Région du 13/05/2026 demande d'avertir conseillers, auditeurs et
 * rénovateurs « de la nécessité de réaliser un export complet avant mise en
 * place de l'archivage ». Il faut donc, pour chaque dossier du lot, retrouver
 * les professionnels qui y sont rattachés, puis les comptes qui leur
 * correspondent.
 *
 * Un dossier porte ses professionnels à trois endroits différents selon son
 * type, d'où l'union ci-dessous :
 *
 *   demande_ ─┬─ demande_travaux ........... structure_id, conseiller_id
 *             │      └─ demande_travaux_devis .. auditeur_id, renovateur_id
 *             ├─ demande_audit_energie ...... structure_id, conseiller_id, auditeur_id
 *             └─ demande_audit_numerique .... structure_id, conseiller_id, auditeur_id
 *
 * La liaison entre un professionnel et son compte passe par la convention de
 * nom de compte, faute de colonne de liaison : « C » + identifiant de
 * structure_conseiller, « A » et « R » + identifiant de partenaire_ (même
 * convention que App\Security\Perimetre\Perimetre). Vérifié le 21/11/2026 sur
 * la copie de production : 291 comptes C, 33 A et 214 R se résolvent tous.
 *
 * Le préfixe fait partie de la jointure, et ce n'est pas un détail : un
 * conseiller n° 7 et un partenaire n° 7 existent tous les deux, et les
 * confondre enverrait à l'un les dossiers de l'autre.
 *
 * Le seul compte hors convention est « Toltech » (notre support, ROLE_CONSEILLER) :
 * son suffixe n'est pas numérique, il vaut 0 à la conversion, et aucun
 * identifiant 0 n'existe — il est donc écarté sans traitement particulier.
 */
final class ArchivagePartenairesSql
{
    /**
     * Les rattachements professionnels des dossiers d'un lot.
     *
     * UNION et non UNION ALL : un même professionnel peut être rattaché au même
     * dossier par deux chemins (l'auditeur d'un devis et celui de l'audit
     * énergétique), et il ne doit apparaître qu'une fois.
     */
    private const RATTACHEMENTS = <<<'SQL'
        WITH dossiers_du_lot AS (
            SELECT
                d.id                           AS demande_id,
                d.type                         AS demande_type,
                d.demande_travaux_id,
                d.demande_audit_energie_id,
                d.demande_audit_numerique_id
              FROM archive_lot_beneficiaire alb
              JOIN beneficiaire b ON b.user_id = alb.user_id
              JOIN demande_ d     ON d.beneficiaire_id = b.id
             WHERE alb.lot_id = :lot_id
               AND alb.etat = :etat
        ),
        rattachements AS (
            -- Conseillers : les trois types de demande en portent un
            SELECT dl.demande_id, dl.demande_type, 'C' AS prefixe, dt.conseiller_id AS professionnel_id
              FROM dossiers_du_lot dl
              JOIN demande_travaux dt ON dt.id = dl.demande_travaux_id
             WHERE dt.conseiller_id IS NOT NULL
            UNION
            SELECT dl.demande_id, dl.demande_type, 'C', dae.conseiller_id
              FROM dossiers_du_lot dl
              JOIN demande_audit_energie dae ON dae.id = dl.demande_audit_energie_id
             WHERE dae.conseiller_id IS NOT NULL
            UNION
            SELECT dl.demande_id, dl.demande_type, 'C', dan.conseiller_id
              FROM dossiers_du_lot dl
              JOIN demande_audit_numerique dan ON dan.id = dl.demande_audit_numerique_id
             WHERE dan.conseiller_id IS NOT NULL
            UNION
            -- Auditeurs : sur les audits, et sur le devis des dossiers travaux
            SELECT dl.demande_id, dl.demande_type, 'A', dae.auditeur_id
              FROM dossiers_du_lot dl
              JOIN demande_audit_energie dae ON dae.id = dl.demande_audit_energie_id
             WHERE dae.auditeur_id IS NOT NULL
            UNION
            SELECT dl.demande_id, dl.demande_type, 'A', dan.auditeur_id
              FROM dossiers_du_lot dl
              JOIN demande_audit_numerique dan ON dan.id = dl.demande_audit_numerique_id
             WHERE dan.auditeur_id IS NOT NULL
            UNION
            SELECT dl.demande_id, dl.demande_type, 'A', dtd.auditeur_id
              FROM dossiers_du_lot dl
              JOIN demande_travaux dt        ON dt.id = dl.demande_travaux_id
              JOIN demande_travaux_devis dtd ON dtd.id = dt.travaux_devis_id
             WHERE dtd.auditeur_id IS NOT NULL
            UNION
            -- Rénovateurs : uniquement sur le devis des dossiers travaux
            SELECT dl.demande_id, dl.demande_type, 'R', dtd.renovateur_id
              FROM dossiers_du_lot dl
              JOIN demande_travaux dt        ON dt.id = dl.demande_travaux_id
              JOIN demande_travaux_devis dtd ON dtd.id = dt.travaux_devis_id
             WHERE dtd.renovateur_id IS NOT NULL
        )
        SQL;

    /**
     * Jointure d'un rattachement vers les comptes correspondants.
     *
     * Comptes désactivés et comptes sans adresse exclus : l'annonce sert à
     * permettre un export, ce qu'un compte qui ne peut pas se connecter ne
     * fera pas. Le rôle est vérifié en plus du préfixe, pour qu'un compte
     * renommé ne reçoive pas les dossiers d'un autre.
     */
    private const JOINTURE_COMPTES = <<<'SQL'
        JOIN user u
          ON LEFT(u.username, 1) = r.prefixe
         AND CAST(SUBSTRING(u.username, 2) AS UNSIGNED) = r.professionnel_id
         AND u.enabled = 1
         AND u.email IS NOT NULL
         AND u.email <> ''
         AND (
              ('C' = r.prefixe AND u.roles LIKE '%ROLE_CONSEILLER%')
           OR ('A' = r.prefixe AND u.roles LIKE '%ROLE_AUDITEUR%')
           OR ('R' = r.prefixe AND u.roles LIKE '%ROLE_RENOVATEUR%')
         )
        SQL;

    /**
     * Une ligne par couple (compte destinataire, dossier).
     *
     * Le regroupement par destinataire est fait en PHP : un courriel par
     * professionnel, portant la liste de ses dossiers, et non un courriel par
     * dossier.
     */
    public static function destinatairesEtDossiers(): string
    {
        return self::RATTACHEMENTS . "\n" . <<<'SQL'
            SELECT
                u.id        AS user_id,
                u.username,
                u.email,
                u.firstname,
                u.lastname,
                r.prefixe,
                r.demande_id,
                r.demande_type
              FROM rattachements r
            SQL
            . "\n" . self::JOINTURE_COMPTES . "\n ORDER BY u.username, r.demande_id";
    }

    /**
     * Les dossiers du lot que personne ne sera prévenu de voir disparaître.
     *
     * Deux cas, et il faut les distinguer : le dossier n'a aucun professionnel
     * rattaché, ou il en a mais aucun compte joignable (désactivé, sans
     * adresse). Ce n'est pas un défaut à corriger dans le code : c'est une
     * information à remonter à la Région, qui peut vouloir prévenir ces
     * professionnels autrement.
     */
    public static function dossiersSansDestinataire(): string
    {
        return self::RATTACHEMENTS . "\n" . <<<'SQL'
            , joignables AS (
                SELECT DISTINCT r.demande_id
                  FROM rattachements r
            SQL
            . "\n" . self::JOINTURE_COMPTES . "\n" . <<<'SQL'
            ),
            rattaches AS (
                SELECT DISTINCT demande_id FROM rattachements
            )
            SELECT
                dl.demande_id,
                dl.demande_type,
                CASE WHEN ra.demande_id IS NULL
                     THEN 'aucun professionnel rattache'
                     ELSE 'professionnel sans compte joignable' END AS motif
              FROM dossiers_du_lot dl
              LEFT JOIN rattaches  ra ON ra.demande_id = dl.demande_id
              LEFT JOIN joignables jo ON jo.demande_id = dl.demande_id
             WHERE jo.demande_id IS NULL
             ORDER BY motif, dl.demande_id
            SQL;
    }
}
