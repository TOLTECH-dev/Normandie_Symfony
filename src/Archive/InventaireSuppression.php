<?php

declare(strict_types=1);

namespace App\Archive;

/**
 * Ce qu'il faut supprimer pour qu'un dossier disparaisse (Reco-03).
 *
 * L'intégrité référentielle n'est PAS déclarée dans cette base : une seule clé
 * étrangère pointe vers `demande_`, aucune vers `beneficiaire`, pour 63 clés
 * sur 99 tables. Rien ne cascade, et rien ne bloque non plus. La suppression
 * repose donc entièrement sur cet inventaire : une table oubliée ici, et des
 * données personnelles survivent à une destruction qu'on aura pourtant
 * attestée.
 *
 * La suppression se fait en DEUX TEMPS :
 *
 *  1. COLLECTE — on relève d'abord tous les identifiants à supprimer, table par
 *     table, pendant que le graphe est encore intact ;
 *  2. SUPPRESSION — on supprime ensuite par identifiant, des feuilles vers la
 *     racine.
 *
 * Ce découpage n'est pas une élégance : une suppression en chaîne de jointures
 * casserait au fur et à mesure que les tables intermédiaires se vident, et le
 * reste passerait silencieusement. Il donne en prime des comptages exacts pour
 * la simulation et pour l'attestation de destruction.
 *
 * PIÈGE POLYMORPHE : `admin_coordonnee` porte la géolocalisation des logements
 * — soit l'adresse exacte du bénéficiaire — et s'y rattache par le couple
 * (`object_id`, `type`), sans colonne nommée. Un inventaire bâti en suivant les
 * colonnes `*_id` l'aurait manquée : 21 498 lignes de type « logement » au
 * 21/11/2026.
 *
 * HORS PÉRIMÈTRE, faute de lien : `instruction_travaux_devis_fiche_technique`
 * (187 lignes) et `instruction_travaux_devis_fiche_technique_field`
 * (561 lignes) ne sont référencées par aucune colonne d'aucune table. Elles ne
 * peuvent donc pas être rattachées à un dossier, ni supprimées avec lui. À
 * trancher avant la mise en service : soit le lien applicatif est retrouvé,
 * soit ces lignes sont déclarées sans objet et purgées globalement.
 */
final class InventaireSuppression
{
    /**
     * Les ensembles d'identifiants collectés, dans l'ordre où ils doivent être
     * relevés. Chaque requête reçoit les ensembles déjà collectés en paramètre.
     *
     * `depend` nomme les ensembles dont la requête a besoin ; une requête dont
     * une dépendance est vide est simplement passée.
     *
     * @var array<string, array{depend: list<string>, sql: string}>
     */
    final public const COLLECTES = [
        // --- le dossier et ses branches -----------------------------------
        'demande' => [
            'depend' => ['beneficiaire'],
            'sql' => 'SELECT id FROM demande_ WHERE beneficiaire_id IN (:beneficiaire)',
        ],
        'demande_travaux' => [
            'depend' => ['demande'],
            'sql' => 'SELECT demande_travaux_id FROM demande_ WHERE id IN (:demande) AND demande_travaux_id IS NOT NULL',
        ],
        'demande_audit_energie' => [
            'depend' => ['demande'],
            'sql' => 'SELECT demande_audit_energie_id FROM demande_ WHERE id IN (:demande) AND demande_audit_energie_id IS NOT NULL',
        ],
        'demande_audit_numerique' => [
            'depend' => ['demande'],
            'sql' => 'SELECT demande_audit_numerique_id FROM demande_ WHERE id IN (:demande) AND demande_audit_numerique_id IS NOT NULL',
        ],
        'demande_travaux_devis' => [
            'depend' => ['demande_travaux'],
            'sql' => 'SELECT travaux_devis_id FROM demande_travaux WHERE id IN (:demande_travaux) AND travaux_devis_id IS NOT NULL',
        ],
        'demande_travaux_devis_upload' => [
            'depend' => ['demande_travaux_devis'],
            'sql' => 'SELECT demande_travaux_devis_upload_id FROM demande_travaux_devis_demande_travaux_devis_upload
                       WHERE demande_travaux_devis_id IN (:demande_travaux_devis)',
        ],

        // --- remboursement -------------------------------------------------
        'remboursement' => [
            'depend' => ['demande'],
            'sql' => 'SELECT id FROM remboursement_ WHERE demande_id IN (:demande)',
        ],
        'remboursement_travaux' => [
            'depend' => ['remboursement'],
            'sql' => 'SELECT remboursement_travaux_id FROM remboursement_ WHERE id IN (:remboursement) AND remboursement_travaux_id IS NOT NULL',
        ],
        'remboursement_audit_energie' => [
            'depend' => ['remboursement'],
            'sql' => 'SELECT remboursement_audit_energie_id FROM remboursement_ WHERE id IN (:remboursement) AND remboursement_audit_energie_id IS NOT NULL',
        ],
        'remboursement_audit_numerique' => [
            'depend' => ['remboursement'],
            'sql' => 'SELECT remboursement_audit_numerique_id FROM remboursement_ WHERE id IN (:remboursement) AND remboursement_audit_numerique_id IS NOT NULL',
        ],
        'remboursement_travaux_instruction' => [
            'depend' => ['remboursement_travaux'],
            'sql' => 'SELECT instruction_id FROM remboursement_travaux WHERE id IN (:remboursement_travaux) AND instruction_id IS NOT NULL',
        ],
        'remboursement_travaux_instruction_conformite' => [
            'depend' => ['remboursement_travaux_instruction'],
            'sql' => 'SELECT remboursement_travaux_instruction_conformite_id
                        FROM remboursement_travaux_instruction__conformite
                       WHERE remboursement_travaux_instruction_id IN (:remboursement_travaux_instruction)',
        ],
        'remboursement_audit_energie_depot' => [
            'depend' => ['remboursement_audit_energie'],
            'sql' => 'SELECT depot_id FROM remboursement_audit_energie WHERE id IN (:remboursement_audit_energie) AND depot_id IS NOT NULL',
        ],
        'remboursement_audit_energie_instruction' => [
            'depend' => ['remboursement_audit_energie'],
            'sql' => 'SELECT instruction_id FROM remboursement_audit_energie WHERE id IN (:remboursement_audit_energie) AND instruction_id IS NOT NULL',
        ],
        'remboursement_audit_energie_validation' => [
            'depend' => ['remboursement_audit_energie'],
            'sql' => 'SELECT validation_id FROM remboursement_audit_energie WHERE id IN (:remboursement_audit_energie) AND validation_id IS NOT NULL',
        ],
        'remboursement_audit_numerique_depot' => [
            'depend' => ['remboursement_audit_numerique'],
            'sql' => 'SELECT depot_id FROM remboursement_audit_numerique WHERE id IN (:remboursement_audit_numerique) AND depot_id IS NOT NULL',
        ],
        'remboursement_audit_numerique_instruction' => [
            'depend' => ['remboursement_audit_numerique'],
            'sql' => 'SELECT instruction_id FROM remboursement_audit_numerique WHERE id IN (:remboursement_audit_numerique) AND instruction_id IS NOT NULL',
        ],

        // Les avis de satisfaction sont référencés par DEUX chemins : le
        // remboursement de travaux, et la validation d'un remboursement
        // d'audit énergétique. N'en suivre qu'un laisserait des avis derrière.
        'rating' => [
            'depend' => ['remboursement_travaux', 'remboursement_audit_energie_validation'],
            'sql' => 'SELECT rating_id FROM remboursement_travaux
                       WHERE id IN (:remboursement_travaux) AND rating_id IS NOT NULL
                      UNION
                      SELECT rating_id FROM remboursement_audit_energie_validation
                       WHERE id IN (:remboursement_audit_energie_validation) AND rating_id IS NOT NULL',
        ],

        // --- fiches techniques, atteintes par deux chemins -----------------
        'fiche_technique' => [
            'depend' => ['demande_travaux', 'remboursement_travaux'],
            'sql' => 'SELECT fiche_technique_id FROM demande_travaux WHERE id IN (:demande_travaux) AND fiche_technique_id IS NOT NULL
                      UNION
                      SELECT fiche_technique_id FROM remboursement_travaux WHERE id IN (:remboursement_travaux) AND fiche_technique_id IS NOT NULL',
        ],
        'fiche_technique_field' => [
            'depend' => ['fiche_technique'],
            'sql' => 'SELECT fiche_technique_initial_id     FROM fiche_technique WHERE id IN (:fiche_technique) AND fiche_technique_initial_id IS NOT NULL
                      UNION SELECT fiche_technique_bbc_id          FROM fiche_technique WHERE id IN (:fiche_technique) AND fiche_technique_bbc_id IS NOT NULL
                      UNION SELECT fiche_technique_prescription_id FROM fiche_technique WHERE id IN (:fiche_technique) AND fiche_technique_prescription_id IS NOT NULL
                      UNION SELECT fiche_technique_fin_chantier_id FROM fiche_technique WHERE id IN (:fiche_technique) AND fiche_technique_fin_chantier_id IS NOT NULL',
        ],

        // --- instruction ---------------------------------------------------
        'instruction' => [
            'depend' => ['demande'],
            'sql' => 'SELECT id FROM instruction_ WHERE demande_id IN (:demande)',
        ],
        'instruction_travaux' => [
            'depend' => ['instruction'],
            'sql' => 'SELECT instruction_travaux_id FROM instruction_ WHERE id IN (:instruction) AND instruction_travaux_id IS NOT NULL',
        ],
        'instruction_audit_energie' => [
            'depend' => ['instruction'],
            'sql' => 'SELECT instruction_audit_energie_id FROM instruction_ WHERE id IN (:instruction) AND instruction_audit_energie_id IS NOT NULL',
        ],

        // --- titre et historique --------------------------------------------
        'titre' => [
            'depend' => ['demande'],
            'sql' => 'SELECT id FROM titre WHERE demande_id IN (:demande)',
        ],
        'historique' => [
            'depend' => ['demande'],
            'sql' => 'SELECT id FROM historique_ WHERE demande_id IN (:demande)',
        ],

        // --- le compte et son logement ---------------------------------------
        'logement' => [
            'depend' => ['beneficiaire'],
            'sql' => 'SELECT id FROM logement WHERE beneficiaire_id IN (:beneficiaire)',
        ],
    ];

    /**
     * L'ordre de suppression.
     *
     * LA RÈGLE, et elle n'est pas celle qu'on croit : une table qui PORTE une
     * clé étrangère se supprime AVANT celle qu'elle référence. Ici,
     * `remboursement_travaux.instruction_id` pointe vers
     * `remboursement_travaux_instruction` : c'est donc `remboursement_travaux`
     * qui part en premier, bien qu'il soit « au-dessus » dans le graphe du
     * dossier.
     *
     * Cet ordre a été construit en relevant les clés étrangères réelles du
     * schéma, après qu'un ordre déduit du graphe métier a échoué à
     * l'exécution. `normandie:archivage:detruire --verifier-ordre` le
     * confronte au schéma et doit être passé en recette avant chaque campagne.
     *
     * @var list<array{table: string, ensemble: string, sql: string}>
     */
    final public const SUPPRESSIONS = [
        // Historique : les filles portent la clé, elles partent en premier
        ['table' => 'historique_email', 'ensemble' => 'historique', 'sql' => 'DELETE FROM historique_email WHERE historique_id IN (:ids)'],
        ['table' => 'historique_post', 'ensemble' => 'historique', 'sql' => 'DELETE FROM historique_post WHERE historique_id IN (:ids)'],
        ['table' => 'historique_data', 'ensemble' => 'historique', 'sql' => 'DELETE FROM historique_data WHERE historique_id IN (:ids)'],
        ['table' => 'historique_', 'ensemble' => 'historique', 'sql' => 'DELETE FROM historique_ WHERE id IN (:ids)'],

        // La table de liaison porte deux clés : avant ses deux extrémités
        ['table' => 'remboursement_travaux_instruction__conformite', 'ensemble' => 'remboursement_travaux_instruction', 'sql' => 'DELETE FROM remboursement_travaux_instruction__conformite WHERE remboursement_travaux_instruction_id IN (:ids)'],

        // remboursement_ porte les clés vers ses trois branches : il part avant elles
        ['table' => 'remboursement_', 'ensemble' => 'remboursement', 'sql' => 'DELETE FROM remboursement_ WHERE id IN (:ids)'],

        // Chaque branche porte les clés vers son dépôt, son instruction et sa validation
        ['table' => 'remboursement_travaux', 'ensemble' => 'remboursement_travaux', 'sql' => 'DELETE FROM remboursement_travaux WHERE id IN (:ids)'],
        ['table' => 'remboursement_audit_energie', 'ensemble' => 'remboursement_audit_energie', 'sql' => 'DELETE FROM remboursement_audit_energie WHERE id IN (:ids)'],
        ['table' => 'remboursement_audit_numerique', 'ensemble' => 'remboursement_audit_numerique', 'sql' => 'DELETE FROM remboursement_audit_numerique WHERE id IN (:ids)'],

        ['table' => 'remboursement_travaux_instruction', 'ensemble' => 'remboursement_travaux_instruction', 'sql' => 'DELETE FROM remboursement_travaux_instruction WHERE id IN (:ids)'],
        ['table' => 'remboursement_travaux_instruction_conformite', 'ensemble' => 'remboursement_travaux_instruction_conformite', 'sql' => 'DELETE FROM remboursement_travaux_instruction_conformite WHERE id IN (:ids)'],
        ['table' => 'remboursement_audit_energie_validation', 'ensemble' => 'remboursement_audit_energie_validation', 'sql' => 'DELETE FROM remboursement_audit_energie_validation WHERE id IN (:ids)'],
        ['table' => 'remboursement_audit_energie_depot', 'ensemble' => 'remboursement_audit_energie_depot', 'sql' => 'DELETE FROM remboursement_audit_energie_depot WHERE id IN (:ids)'],
        ['table' => 'remboursement_audit_energie_instruction', 'ensemble' => 'remboursement_audit_energie_instruction', 'sql' => 'DELETE FROM remboursement_audit_energie_instruction WHERE id IN (:ids)'],
        ['table' => 'remboursement_audit_numerique_depot', 'ensemble' => 'remboursement_audit_numerique_depot', 'sql' => 'DELETE FROM remboursement_audit_numerique_depot WHERE id IN (:ids)'],
        ['table' => 'remboursement_audit_numerique_instruction', 'ensemble' => 'remboursement_audit_numerique_instruction', 'sql' => 'DELETE FROM remboursement_audit_numerique_instruction WHERE id IN (:ids)'],

        // Les avis : référencés par remboursement_travaux ET par la validation,
        // donc après les deux
        ['table' => 'rating', 'ensemble' => 'rating', 'sql' => 'DELETE FROM rating WHERE id IN (:ids)'],

        // Titre et production
        ['table' => 'production__demande_', 'ensemble' => 'demande', 'sql' => 'DELETE FROM production__demande_ WHERE demande__id IN (:ids)'],
        ['table' => 'titre', 'ensemble' => 'titre', 'sql' => 'DELETE FROM titre WHERE id IN (:ids)'],

        // instruction_ porte les clés vers ses deux branches
        ['table' => 'instruction_', 'ensemble' => 'instruction', 'sql' => 'DELETE FROM instruction_ WHERE id IN (:ids)'],
        ['table' => 'instruction_travaux', 'ensemble' => 'instruction_travaux', 'sql' => 'DELETE FROM instruction_travaux WHERE id IN (:ids)'],
        ['table' => 'instruction_audit_energie', 'ensemble' => 'instruction_audit_energie', 'sql' => 'DELETE FROM instruction_audit_energie WHERE id IN (:ids)'],

        // demande_ porte les clés vers ses trois branches
        ['table' => 'demande_', 'ensemble' => 'demande', 'sql' => 'DELETE FROM demande_ WHERE id IN (:ids)'],
        ['table' => 'demande_travaux', 'ensemble' => 'demande_travaux', 'sql' => 'DELETE FROM demande_travaux WHERE id IN (:ids)'],
        ['table' => 'demande_audit_energie', 'ensemble' => 'demande_audit_energie', 'sql' => 'DELETE FROM demande_audit_energie WHERE id IN (:ids)'],
        ['table' => 'demande_audit_numerique', 'ensemble' => 'demande_audit_numerique', 'sql' => 'DELETE FROM demande_audit_numerique WHERE id IN (:ids)'],

        // Devis : la liaison porte deux clés, donc avant ses deux extrémités
        ['table' => 'demande_travaux_devis_demande_travaux_devis_upload', 'ensemble' => 'demande_travaux_devis', 'sql' => 'DELETE FROM demande_travaux_devis_demande_travaux_devis_upload WHERE demande_travaux_devis_id IN (:ids)'],
        ['table' => 'demande_travaux_devis_upload', 'ensemble' => 'demande_travaux_devis_upload', 'sql' => 'DELETE FROM demande_travaux_devis_upload WHERE id IN (:ids)'],
        ['table' => 'demande_travaux_devis', 'ensemble' => 'demande_travaux_devis', 'sql' => 'DELETE FROM demande_travaux_devis WHERE id IN (:ids)'],

        // fiche_technique porte les quatre clés vers ses étapes, et est
        // elle-même référencée par remboursement_travaux, déjà supprimé
        ['table' => 'fiche_technique', 'ensemble' => 'fiche_technique', 'sql' => 'DELETE FROM fiche_technique WHERE id IN (:ids)'],
        ['table' => 'fiche_technique_field', 'ensemble' => 'fiche_technique_field', 'sql' => 'DELETE FROM fiche_technique_field WHERE id IN (:ids)'],

        // Le logement et sa géolocalisation : lien POLYMORPHE, pas une clé nommée
        ['table' => 'admin_coordonnee', 'ensemble' => 'logement', 'sql' => "DELETE FROM admin_coordonnee WHERE object_id IN (:ids) AND type LIKE '%logement'"],
        ['table' => 'logement', 'ensemble' => 'logement', 'sql' => 'DELETE FROM logement WHERE id IN (:ids)'],

        // La fiche du bénéficiaire. Le compte `user`, lui, n'est pas supprimé :
        // les Archives refusent la suppression directe des comptes, il est
        // anonymisé (cf. ArchivageSuppressionRepository::anonymiserLeCompte).
        ['table' => 'beneficiaire', 'ensemble' => 'beneficiaire', 'sql' => 'DELETE FROM beneficiaire WHERE id IN (:ids)'],
    ];

    /**
     * Tables porteuses de données de dossier que l'inventaire ne sait pas
     * rattacher, avec leur raison.
     *
     * @var array<string, string>
     */
    final public const HORS_PERIMETRE = [
        'instruction_travaux_devis_fiche_technique' =>
            'aucune table ne la reference : lien applicatif non retrouve (187 lignes au 21/11/2026)',
        'instruction_travaux_devis_fiche_technique_field' =>
            'aucune table ne la reference : lien applicatif non retrouve (561 lignes au 21/11/2026)',
    ];
}
