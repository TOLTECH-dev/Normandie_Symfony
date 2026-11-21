<?php

declare(strict_types=1);

namespace App\Repository\Archive;

/**
 * La requête d'éligibilité à l'archivage (Reco-03), isolée dans sa propre
 * classe comme DemandeListSql : elle est longue, elle porte toute la politique
 * d'archivage, et elle doit rester lisible à côté de la spécification.
 *
 * Reprise de `evolutions/securite/29_reco03_eligibilite_archivage_v2.sql`, dont
 * elle partage mot pour mot les vues de travail. Deux différences voulues :
 *
 *  - la date de comparaison est un PARAMÈTRE (`:date_reference`) et non NOW(),
 *    pour que la sélection soit reproductible et testable ;
 *  - la sortie est une ligne par compte éligible, là où le fichier SQL ne
 *    produisait que des comptages.
 *
 * Les cinq déclencheurs, tels qu'ils ressortent du courriel Région du
 * 13/05/2026 :
 *   1  compte créé sans aucune demande ........ création du compte + 2 ans
 *   2  chèque travaux payé .................... date RMH travaux + 1 an
 *   3  chèque audit payé, sans chèque travaux . date RMH audit + 5 ans
 *   4  dossier refusé ......................... passage au statut refusé + 1 an
 *   5  demande non finalisée ................... création de la demande + 2 ans
 *
 * L'unité d'archivage est le COMPTE : il n'est éligible que lorsque TOUS ses
 * dossiers le sont, et la date retenue est la plus tardive d'entre eux.
 */
final class ArchivageEligibiliteSql
{
    /** Statut d'une demande refusée. */
    private const STATUT_DEMANDE_REFUSEE = 15;

    /** Statut d'une demande dont le chèque a été envoyé. */
    private const STATUT_CHEQUE_ENVOYE = 14;

    /** Statut d'un remboursement refusé. */
    private const STATUT_REMBOURSEMENT_REFUSE = 20;

    /** Statut d'un remboursement terminé, c'est-à-dire payé. */
    private const STATUT_REMBOURSEMENT_TERMINE = 22;

    /** Type de demande « travaux ». */
    private const TYPE_TRAVAUX = 3;

    /**
     * Les vues de travail, communes à la sélection et à la synthèse.
     *
     * Volontairement en une seule chaîne : les CTE ne survivent pas à la fin de
     * l'instruction, et les dupliquer dans deux requêtes inviterait aux
     * divergences — c'est précisément l'erreur qu'avait commise la v1.
     */
    private static function vuesDeTravail(): string
    {
        return sprintf(
            <<<'SQL'
            WITH refus_demande AS (
                SELECT demande_id, MAX(date_creation) AS date_refus
                  FROM historique_
                 WHERE statut_slug = 'Demande refusée'
                 GROUP BY demande_id
            ),
            refus_remboursement AS (
                SELECT remboursement_id, MAX(date_creation) AS date_refus
                  FROM historique_
                 WHERE statut_slug = 'Remboursement refusé' AND remboursement_id IS NOT NULL
                 GROUP BY remboursement_id
            ),
            dossier AS (
                SELECT
                    d.id                                                   AS demande_id,
                    d.beneficiaire_id,
                    d.type,
                    d.statut_id,
                    d.date_creation,
                    MAX(CASE WHEN r.statut_id = %1$d THEN dr.date_RMH END) AS date_paiement,
                    MAX(CASE WHEN r.statut_id = %2$d THEN rr.date_refus END) AS date_refus_remboursement,
                    MAX(rd.date_refus)                                     AS date_refus_demande,
                    -- Un remboursement engagé mais ni terminé ni refusé maintient le dossier en vie.
                    MAX(CASE WHEN r.id IS NOT NULL AND r.statut_id NOT IN (%2$d, %1$d) THEN 1 ELSE 0 END)
                                                                           AS remboursement_en_cours
                  FROM demande_ d
                  LEFT JOIN titre t                ON t.demande_id = d.id
                  LEFT JOIN remboursement_ r       ON r.titre_id = t.id
                  LEFT JOIN date_RMH dr            ON dr.id = r.dateRMH_id
                  LEFT JOIN refus_remboursement rr ON rr.remboursement_id = r.id
                  LEFT JOIN refus_demande rd       ON rd.demande_id = d.id
                 GROUP BY d.id, d.beneficiaire_id, d.type, d.statut_id, d.date_creation
            ),
            -- Un chèque travaux payé « libère » les chèques audit du même compte,
            -- qui suivent alors le délai d'un an et non de cinq.
            travaux_paye AS (
                SELECT beneficiaire_id
                  FROM dossier
                 WHERE type = %3$d AND date_paiement IS NOT NULL
                 GROUP BY beneficiaire_id
            ),
            dossier_classe AS (
                SELECT
                    ds.*,
                    CASE
                        WHEN %4$d = ds.statut_id                     THEN 'demande refusee'
                        WHEN ds.date_refus_remboursement IS NOT NULL THEN 'remboursement refuse'
                        WHEN ds.date_paiement IS NOT NULL AND %3$d = ds.type THEN 'travaux paye'
                        WHEN ds.date_paiement IS NOT NULL            THEN 'audit paye'
                        WHEN 1 = ds.remboursement_en_cours           THEN 'remboursement en cours'
                        WHEN %5$d = ds.statut_id                     THEN 'cheque envoye, remboursement non engage'
                        ELSE                                              'demande non finalisee'
                    END                                                    AS situation,
                    CASE
                        -- Règle 4 : refus de la demande
                        WHEN %4$d = ds.statut_id
                            THEN DATE_ADD(ds.date_refus_demande, INTERVAL 1 YEAR)
                        -- Règle 4 : refus du remboursement
                        WHEN ds.date_refus_remboursement IS NOT NULL
                            THEN DATE_ADD(ds.date_refus_remboursement, INTERVAL 1 YEAR)
                        -- Règle 2 : chèque travaux payé
                        WHEN ds.date_paiement IS NOT NULL AND %3$d = ds.type
                            THEN DATE_ADD(ds.date_paiement, INTERVAL 1 YEAR)
                        -- Règle 3 : chèque audit payé, cinq ans ou un an
                        WHEN ds.date_paiement IS NOT NULL
                            THEN DATE_ADD(ds.date_paiement, INTERVAL IF(tp.beneficiaire_id IS NULL, 5, 1) YEAR)
                        -- Règle 5 : demande jamais finalisée
                        WHEN 0 = ds.remboursement_en_cours AND %5$d <> ds.statut_id
                            THEN DATE_ADD(ds.date_creation, INTERVAL 2 YEAR)
                        -- Dossier encore en vie : aucune date d'éligibilité
                        ELSE NULL
                    END                                                    AS date_eligibilite
                  FROM dossier ds
                  LEFT JOIN travaux_paye tp ON tp.beneficiaire_id = ds.beneficiaire_id
            ),
            compte AS (
                SELECT
                    u.id                                                   AS user_id,
                    u.date_creation                                        AS creation_compte,
                    u.enabled,
                    COUNT(dc.demande_id)                                   AS nb_dossiers,
                    SUM(dc.date_eligibilite IS NULL)                       AS nb_dossiers_en_vie,
                    MAX(dc.date_eligibilite)                               AS date_eligibilite,
                    -- Situation du dossier le plus tardif, qui qualifie le déclencheur
                    SUBSTRING_INDEX(
                        GROUP_CONCAT(dc.situation ORDER BY dc.date_eligibilite DESC SEPARATOR '||'),
                        '||', 1
                    )                                                      AS situation_determinante
                  FROM user u
                  LEFT JOIN beneficiaire b    ON b.user_id = u.id
                  LEFT JOIN dossier_classe dc ON dc.beneficiaire_id = b.id
                 WHERE u.roles LIKE '%%ROLE_MEMBER%%'
                 GROUP BY u.id, u.date_creation, u.enabled
            )
            SQL,
            self::STATUT_REMBOURSEMENT_TERMINE,
            self::STATUT_REMBOURSEMENT_REFUSE,
            self::TYPE_TRAVAUX,
            self::STATUT_DEMANDE_REFUSEE,
            self::STATUT_CHEQUE_ENVOYE
        );
    }

    /**
     * Condition d'éligibilité d'un compte, à la date `:date_reference`.
     *
     * Deux cas, et deux seulement :
     *  - aucun dossier, et le compte a plus de deux ans ;
     *  - au moins un dossier, aucun encore en vie, et la date d'archivage du
     *    plus tardif est échue.
     */
    private const CONDITION_ELIGIBLE = <<<'SQL'
        (0 = nb_dossiers AND creation_compte <= DATE_SUB(:date_reference, INTERVAL 2 YEAR))
        OR (nb_dossiers > 0 AND 0 = nb_dossiers_en_vie AND date_eligibilite <= :date_reference)
        SQL;

    /**
     * Une ligne par compte éligible : de quoi constituer le lot.
     *
     * `situation_determinante` n'est pas traduite en déclencheur ici : la
     * correspondance appartient à ArchiveLotBeneficiaire, qui lève une erreur
     * sur une situation inconnue plutôt que d'enregistrer un motif faux.
     */
    public static function comptesEligibles(bool $limiteAuxComptesDonnes = false): string
    {
        $select = <<<'SQL'
            SELECT
                user_id,
                enabled,
                nb_dossiers,
                date_eligibilite,
                situation_determinante
              FROM compte
            SQL;

        return self::vuesDeTravail() . "\n" . $select . "\n" . self::clauseWhere($limiteAuxComptesDonnes)
            . "\n ORDER BY user_id";
    }

    /**
     * La clause WHERE, avec la condition d'éligibilité entre parenthèses.
     *
     * Les parenthèses ne sont pas cosmétiques : la condition est une
     * disjonction, et un `AND` ajouté sans elles ne porterait que sur sa
     * seconde branche.
     */
    /**
     * Les comptes déjà détruits par une campagne précédente.
     *
     * Sans cette exclusion, un compte archivé puis anonymisé serait repris à
     * chaque campagne suivante : vidé de ses dossiers, il retombe exactement
     * dans le cas « compte sans demande depuis plus de deux ans ». On
     * produirait indéfiniment des archives vides pour les mêmes comptes, et
     * l'attestation les redéclarerait détruits chaque année.
     *
     * Constaté en recette le 21/11/2026, sur des comptes détruits la veille.
     *
     * Seul l'état « supprimee » exclut : un compte retiré d'un lot — parce
     * qu'il était redevenu actif — doit pouvoir revenir dans une campagne
     * ultérieure.
     */
    private const DEJA_ARCHIVES = <<<'SQL'
        user_id NOT IN (
            SELECT user_id FROM archive_lot_beneficiaire WHERE etat = 'supprimee'
        )
        SQL;

    private static function clauseWhere(bool $limiteAuxComptesDonnes): string
    {
        $where = " WHERE (\n" . self::CONDITION_ELIGIBLE . "\n )\n   AND " . self::DEJA_ARCHIVES;

        if ($limiteAuxComptesDonnes) {
            $where .= "\n   AND user_id IN (:user_ids)";
        }

        return $where;
    }

    /**
     * Synthèse par déclencheur, sans aucune donnée nominative : ce qui est
     * annoncé à la Région avant de constituer quoi que ce soit.
     */
    public static function syntheseParDeclencheur(): string
    {
        return self::vuesDeTravail() . "\n" . <<<'SQL'
            SELECT
                CASE
                    WHEN 0 = nb_dossiers                       THEN 'compte sans demande (+ 2 ans)'
                    WHEN 'travaux paye' = situation_determinante         THEN 'cheque travaux paye (RMH + 1 an)'
                    WHEN 'audit paye' = situation_determinante           THEN 'cheque audit paye (RMH + 5 ans)'
                    WHEN 'demande refusee' = situation_determinante      THEN 'demande refusee (+ 1 an)'
                    WHEN 'remboursement refuse' = situation_determinante THEN 'remboursement refuse (+ 1 an)'
                    WHEN 'demande non finalisee' = situation_determinante THEN 'demande non finalisee (+ 2 ans)'
                    ELSE                                                      'situation inattendue'
                END                                            AS declencheur,
                COUNT(*)                                       AS comptes,
                SUM(CASE WHEN enabled = 1 THEN 1 ELSE 0 END)   AS comptes_actifs,
                SUM(nb_dossiers)                               AS dossiers
              FROM compte
            SQL
            . "\n" . self::clauseWhere(false) . "\n GROUP BY declencheur ORDER BY comptes DESC";
    }
}
