<?php

declare(strict_types=1);

namespace App\Repository\Archive;

use App\Archive\InventairePieces;

/**
 * Parcours du graphe d'un dossier pour en recenser les pièces (Reco-03).
 *
 * La requête est GÉNÉRÉE depuis InventairePieces::EMPLACEMENTS plutôt
 * qu'écrite à la main : trente emplacements répartis sur onze tables, c'est
 * trop pour être recopié sans faute, et une ligne oubliée se traduirait par des
 * pièces absentes de l'archive — sans que rien ne le signale.
 *
 * Le graphe, tel qu'il ressort du schéma :
 *
 *   demande_ ─┬─ demande_audit_energie ................. 4 pièces
 *             ├─ demande_audit_numerique ............... aucune
 *             ├─ demande_travaux ....................... 4 pièces
 *             │    ├─ travaux_devis_id → demande_travaux_devis ......... 2
 *             │    │     └─ (liaison) → demande_travaux_devis_upload ... 1
 *             │    └─ fiche_technique_id → fiche_technique → 4 fiches .. 4
 *             └─ remboursement_ (demande_id)
 *                  ├─ remboursement_audit_energie → depot / instruction .. 1 + 4
 *                  ├─ remboursement_audit_numerique → depot / instruction  1 + 4
 *                  └─ remboursement_travaux
 *                       ├─ instruction → 4 pièces, + conformité (liaison) . 1
 *                       └─ fiche_technique_id → fiche_technique ......... 4
 *
 * Une fiche technique est atteignable par deux chemins (la demande de travaux
 * et le remboursement) et porte quatre étapes : les identifiants de ses lignes
 * sont donc réunis une fois pour toutes dans la vue `fiches_techniques`, et les
 * quatre colonnes de pièces ne sont écrites qu'une fois.
 */
final class ArchivagePiecesSql
{
    /**
     * Les branches du graphe : pour chaque source déclarée par l'inventaire,
     * les jointures qui y mènent depuis `d` (la demande).
     *
     * @var array<string, string>
     */
    private const BRANCHES = [
        'audit_energie' => 'JOIN demande_audit_energie dae ON dae.id = dl.demande_audit_energie_id',

        'travaux' => 'JOIN demande_travaux dt ON dt.id = dl.demande_travaux_id',

        'devis' => 'JOIN demande_travaux dt ON dt.id = dl.demande_travaux_id
                    JOIN demande_travaux_devis dtd ON dtd.id = dt.travaux_devis_id',

        'devis_upload' => 'JOIN demande_travaux dt ON dt.id = dl.demande_travaux_id
                           JOIN demande_travaux_devis dtd ON dtd.id = dt.travaux_devis_id
                           JOIN demande_travaux_devis_demande_travaux_devis_upload jdu
                             ON jdu.demande_travaux_devis_id = dtd.id
                           JOIN demande_travaux_devis_upload dtdu
                             ON dtdu.id = jdu.demande_travaux_devis_upload_id',

        'fiche_technique' => 'JOIN fiches_techniques fts ON fts.demande_id = dl.demande_id
                              JOIN fiche_technique_field ftf ON ftf.id = fts.champ_id',

        'rbt_audit_energie_depot' => 'JOIN remboursement_ r ON r.demande_id = dl.demande_id
                                      JOIN remboursement_audit_energie rae ON rae.id = r.remboursement_audit_energie_id
                                      JOIN remboursement_audit_energie_depot raed ON raed.id = rae.depot_id',

        'rbt_audit_energie_instruction' => 'JOIN remboursement_ r ON r.demande_id = dl.demande_id
                                            JOIN remboursement_audit_energie rae ON rae.id = r.remboursement_audit_energie_id
                                            JOIN remboursement_audit_energie_instruction raei ON raei.id = rae.instruction_id',

        'rbt_audit_numerique_depot' => 'JOIN remboursement_ r ON r.demande_id = dl.demande_id
                                        JOIN remboursement_audit_numerique ran ON ran.id = r.remboursement_audit_numerique_id
                                        JOIN remboursement_audit_numerique_depot rand ON rand.id = ran.depot_id',

        'rbt_audit_numerique_instruction' => 'JOIN remboursement_ r ON r.demande_id = dl.demande_id
                                              JOIN remboursement_audit_numerique ran ON ran.id = r.remboursement_audit_numerique_id
                                              JOIN remboursement_audit_numerique_instruction rani ON rani.id = ran.instruction_id',

        'rbt_travaux_instruction' => 'JOIN remboursement_ r ON r.demande_id = dl.demande_id
                                      JOIN remboursement_travaux rt ON rt.id = r.remboursement_travaux_id
                                      JOIN remboursement_travaux_instruction rti ON rti.id = rt.instruction_id',

        'rbt_travaux_conformite' => 'JOIN remboursement_ r ON r.demande_id = dl.demande_id
                                     JOIN remboursement_travaux rt ON rt.id = r.remboursement_travaux_id
                                     JOIN remboursement_travaux_instruction rti ON rti.id = rt.instruction_id
                                     JOIN remboursement_travaux_instruction__conformite jc
                                       ON jc.remboursement_travaux_instruction_id = rti.id
                                     JOIN remboursement_travaux_instruction_conformite rtic
                                       ON rtic.id = jc.remboursement_travaux_instruction_conformite_id',
    ];

    /**
     * Les dossiers du lot, et les lignes de fiche technique qui leur sont
     * rattachées par l'un ou l'autre des deux chemins.
     */
    private const VUES = <<<'SQL'
        WITH dossiers_du_lot AS (
            SELECT
                alb.user_id,
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
        -- Les quatre étapes d'une fiche technique, atteintes par la demande de
        -- travaux comme par le remboursement. DISTINCT : les deux chemins mènent
        -- souvent à la même fiche.
        fiches_techniques AS (
            SELECT DISTINCT dl.demande_id, champ.champ_id
              FROM dossiers_du_lot dl
              JOIN demande_travaux dt ON dt.id = dl.demande_travaux_id
              JOIN fiche_technique ft ON ft.id = dt.fiche_technique_id
              JOIN (
                    SELECT id, fiche_technique_initial_id     AS champ_id FROM fiche_technique
              UNION SELECT id, fiche_technique_bbc_id                     FROM fiche_technique
              UNION SELECT id, fiche_technique_prescription_id            FROM fiche_technique
              UNION SELECT id, fiche_technique_fin_chantier_id            FROM fiche_technique
              ) champ ON champ.id = ft.id
             WHERE champ.champ_id IS NOT NULL
            UNION
            SELECT DISTINCT dl.demande_id, champ.champ_id
              FROM dossiers_du_lot dl
              JOIN remboursement_ r         ON r.demande_id = dl.demande_id
              JOIN remboursement_travaux rt ON rt.id = r.remboursement_travaux_id
              JOIN fiche_technique ft       ON ft.id = rt.fiche_technique_id
              JOIN (
                    SELECT id, fiche_technique_initial_id     AS champ_id FROM fiche_technique
              UNION SELECT id, fiche_technique_bbc_id                     FROM fiche_technique
              UNION SELECT id, fiche_technique_prescription_id            FROM fiche_technique
              UNION SELECT id, fiche_technique_fin_chantier_id            FROM fiche_technique
              ) champ ON champ.id = ft.id
             WHERE champ.champ_id IS NOT NULL
        )
        SQL;

    /**
     * Une ligne par pièce : le dossier, le compte, le type, l'identifiant
     * porteur et l'extension. Le chemin sur le disque est assemblé en PHP par
     * InventairePieces, qui détient la convention.
     */
    public static function piecesDesDossiers(): string
    {
        $branches = [];

        foreach (InventairePieces::EMPLACEMENTS as $emplacement) {
            $jointures = self::BRANCHES[$emplacement['source']]
                ?? throw new \LogicException(sprintf(
                    'Emplacement de pièce « %s.%s » rattaché à la source inconnue « %s ».',
                    $emplacement['alias'],
                    $emplacement['colonne'],
                    $emplacement['source']
                ));

            $branches[] = sprintf(
                "SELECT dl.user_id, dl.demande_id, dl.demande_type, %s AS porteur_id,\n"
                . "       %s AS extension, %d AS dossier_type, %d AS piece_type, %s AS libelle\n"
                . "  FROM dossiers_du_lot dl\n  %s\n"
                . " WHERE %s.%s IS NOT NULL AND %s.%s <> ''",
                $emplacement['alias'] . '.id',
                $emplacement['alias'] . '.' . $emplacement['colonne'],
                $emplacement['dossier'],
                $emplacement['type'],
                "'" . $emplacement['libelle'] . "'",
                trim(preg_replace('/\s+/', ' ', $jointures) ?? ''),
                $emplacement['alias'],
                $emplacement['colonne'],
                $emplacement['alias'],
                $emplacement['colonne']
            );
        }

        // UNION ALL : deux emplacements distincts ne peuvent pas produire la même
        // ligne, et dédoublonner 30 branches coûterait un tri inutile.
        return self::VUES . "\n" . implode("\nUNION ALL\n", $branches)
            . "\n ORDER BY user_id, demande_id, piece_type, porteur_id";
    }

    /**
     * Comptage des pièces par type, sans les identifiants : de quoi annoncer un
     * volume sans manipuler de donnée nominative.
     */
    public static function syntheseParType(): string
    {
        return 'SELECT libelle, dossier_type, piece_type, extension, COUNT(*) AS pieces, '
            . "COUNT(DISTINCT demande_id) AS dossiers\n"
            . '  FROM (' . self::piecesDesDossiers() . ") AS p\n"
            . ' GROUP BY libelle, dossier_type, piece_type, extension'
            . ' ORDER BY pieces DESC';
    }
}
