<?php

declare(strict_types=1);

namespace App\Archive;

/**
 * Où sont les pièces d'un dossier, sur le disque et en base (Reco-03).
 *
 * Rien dans la base ne porte le chemin d'un fichier : la colonne `*_url` ne
 * contient que l'EXTENSION (« pdf »), et le chemin est entièrement
 * conventionnel :
 *
 *     <app_root_dossier_data_symfony>uploads/<dossier><id><suffixe><extension>
 *
 * où `<id>` est l'identifiant de la LIGNE QUI PORTE LA COLONNE — et non celui
 * du dossier. Convention vérifiée sur les gabarits qui construisent les liens
 * de téléchargement (route `demande_document_bo`).
 *
 * Les deux tables de correspondance ci-dessous existent aussi, écrites en dur,
 * dans App\Controller\DocumentController (`$mapTypeDemandeToFolder` et
 * `findSuffixFileByTypeFile`). Elles sont reprises ici plutôt que partagées
 * pour ne pas toucher au téléchargement en cours de lot ; un lot ultérieur
 * devrait faire lire le contrôleur depuis cette classe, et le test
 * InventairePiecesTest vérifie en attendant qu'elles ne divergent pas.
 *
 * PIÈGE : `fiche_technique_field.ficheTechnique_document_url` correspond au
 * suffixe `_xml_document.` (type 4), et non à un suffixe dérivé de son nom.
 * Déduire le suffixe du nom de colonne marche pour les 31 autres pièces et
 * échoue sur celle-là.
 *
 * NON RÉSOLU : `instruction_travaux_devis_fiche_technique_field` (561 lignes,
 * colonne `ficheTechnique_document_url`) n'est référencée par aucune colonne
 * d'aucune table — elle n'est atteignable que par la logique applicative. Ses
 * pièces ne sont donc pas collectées. À trancher avant la mise en service :
 * soit le lien est retrouvé, soit ces 561 lignes sont déclarées hors périmètre.
 */
final class InventairePieces
{
    /** Répertoires de dépôt, par type de document (cf. DocumentController). */
    final public const DOSSIERS = [
        0 => 'demande_auditEnergie/',
        1 => 'demande_travaux/',
        2 => 'demande_travaux_devis/',
        3 => 'fiche_technique/',
        4 => 'remboursement/auditEnergie_depot/',
        5 => 'remboursement/auditEnergie_instruction/',
        6 => 'partenaire/auditeur/',
        7 => 'remboursement/auditNumerique_depot/',
        8 => 'remboursement/auditNumerique_instruction/',
        9 => 'remboursement/travaux_instruction/',
        10 => 'remboursement/travaux_instruction/conformite/',
    ];

    /** Suffixes de nom de fichier, par type de pièce (cf. DocumentController). */
    final public const SUFFIXES = [
        0 => '_justificatif_propriete.',
        1 => '_piece_complement.',
        2 => '_avis_imposition.',
        3 => '_audit.',
        4 => '_xml_document.',
        5 => '_devis_document.',
        6 => '_rib.',
        7 => '_facture.',
        8 => '_recto_cheque.',
        9 => '_verso_cheque.',
        10 => '_document.',
        11 => '_avis_imposition_conjoint.',
        12 => '_fiche_travaux.',
        13 => '_infiltrometrie_document.',
        14 => '_ventilation_document.',
        15 => '_acte_engagement.',
        17 => '_audit_apres_travaux_document.',
        18 => '_mandat.',
    ];

    /**
     * Table porteuse derrière chaque alias employé par les emplacements.
     *
     * Déclarée ici et non déduite : c'est elle qui permet de confronter
     * l'inventaire au schéma réel (`normandie:archivage:pieces --couverture`).
     *
     * @var array<string, string>
     */
    final public const TABLES_PAR_ALIAS = [
        'dae' => 'demande_audit_energie',
        'dt' => 'demande_travaux',
        'dtd' => 'demande_travaux_devis',
        'dtdu' => 'demande_travaux_devis_upload',
        'ftf' => 'fiche_technique_field',
        'raed' => 'remboursement_audit_energie_depot',
        'raei' => 'remboursement_audit_energie_instruction',
        'rand' => 'remboursement_audit_numerique_depot',
        'rani' => 'remboursement_audit_numerique_instruction',
        'rti' => 'remboursement_travaux_instruction',
        'rtic' => 'remboursement_travaux_instruction_conformite',
    ];

    /**
     * Emplacements volontairement HORS périmètre, avec leur raison. Le contrôle
     * de couverture les attend, et ne les signale donc pas.
     *
     * @var array<string, string>
     */
    final public const HORS_PERIMETRE = [
        'instruction_travaux_devis_fiche_technique_field.ficheTechnique_document_url' =>
            "aucune table ne reference cette table : lien applicatif non retrouve (23 pieces au 21/11/2026)",
    ];

    /**
     * Les emplacements de pièces, un par couple (table porteuse, colonne).
     *
     * `source` désigne une des branches du graphe du dossier déclarées par
     * ArchivagePiecesSql ; `alias` est celui de la table porteuse dans cette
     * branche. `libelle` est le nom lisible donné à la pièce dans l'archive et
     * dans le manifeste — c'est lui que lira un archiviste, pas le nom de
     * colonne.
     *
     * @var list<array{source: string, alias: string, colonne: string, dossier: int, type: int, libelle: string}>
     */
    final public const EMPLACEMENTS = [
        // Demande d'audit énergétique
        ['source' => 'audit_energie', 'alias' => 'dae', 'colonne' => 'justificatif_propriete_url', 'dossier' => 0, 'type' => 0, 'libelle' => 'justificatif de propriete'],
        ['source' => 'audit_energie', 'alias' => 'dae', 'colonne' => 'piece_complement_url', 'dossier' => 0, 'type' => 1, 'libelle' => 'piece complementaire'],
        ['source' => 'audit_energie', 'alias' => 'dae', 'colonne' => 'avis_imposition_url', 'dossier' => 0, 'type' => 2, 'libelle' => 'avis d imposition'],
        ['source' => 'audit_energie', 'alias' => 'dae', 'colonne' => 'avis_imposition_conjoint_url', 'dossier' => 0, 'type' => 11, 'libelle' => 'avis d imposition du conjoint'],
        ['source' => 'audit_energie', 'alias' => 'dae', 'colonne' => 'mandat_url', 'dossier' => 0, 'type' => 18, 'libelle' => 'mandat donne au conseiller'],

        // Demande de travaux
        ['source' => 'travaux', 'alias' => 'dt', 'colonne' => 'justificatif_propriete_url', 'dossier' => 1, 'type' => 0, 'libelle' => 'justificatif de propriete'],
        ['source' => 'travaux', 'alias' => 'dt', 'colonne' => 'piece_complement_url', 'dossier' => 1, 'type' => 1, 'libelle' => 'piece complementaire'],
        ['source' => 'travaux', 'alias' => 'dt', 'colonne' => 'avis_imposition_url', 'dossier' => 1, 'type' => 2, 'libelle' => 'avis d imposition'],
        ['source' => 'travaux', 'alias' => 'dt', 'colonne' => 'avis_imposition_conjoint_url', 'dossier' => 1, 'type' => 11, 'libelle' => 'avis d imposition du conjoint'],
        ['source' => 'travaux', 'alias' => 'dt', 'colonne' => 'mandat_url', 'dossier' => 1, 'type' => 18, 'libelle' => 'mandat donne au conseiller'],

        // Devis de travaux
        ['source' => 'devis', 'alias' => 'dtd', 'colonne' => 'audit_url', 'dossier' => 2, 'type' => 3, 'libelle' => 'rapport d audit'],
        ['source' => 'devis', 'alias' => 'dtd', 'colonne' => 'acte_engagement_url', 'dossier' => 2, 'type' => 15, 'libelle' => 'acte d engagement'],

        // Pièces déposées avec les devis
        ['source' => 'devis_upload', 'alias' => 'dtdu', 'colonne' => 'devis_document_url', 'dossier' => 2, 'type' => 5, 'libelle' => 'devis ou facture'],

        // Fiches techniques (toutes étapes confondues)
        ['source' => 'fiche_technique', 'alias' => 'ftf', 'colonne' => 'ficheTechnique_document_url', 'dossier' => 3, 'type' => 4, 'libelle' => 'fiche technique'],
        ['source' => 'fiche_technique', 'alias' => 'ftf', 'colonne' => 'infiltrometrie_document_url', 'dossier' => 3, 'type' => 13, 'libelle' => 'test d infiltrometrie'],
        ['source' => 'fiche_technique', 'alias' => 'ftf', 'colonne' => 'ventilation_document_url', 'dossier' => 3, 'type' => 14, 'libelle' => 'test de ventilation'],
        ['source' => 'fiche_technique', 'alias' => 'ftf', 'colonne' => 'audit_apres_travaux_document_url', 'dossier' => 3, 'type' => 17, 'libelle' => 'audit apres travaux'],

        // Remboursement d'un chèque audit énergétique
        ['source' => 'rbt_audit_energie_depot', 'alias' => 'raed', 'colonne' => 'audit_url', 'dossier' => 4, 'type' => 3, 'libelle' => 'rapport d audit depose'],
        ['source' => 'rbt_audit_energie_instruction', 'alias' => 'raei', 'colonne' => 'recto_cheque_url', 'dossier' => 5, 'type' => 8, 'libelle' => 'cheque eco recto'],
        ['source' => 'rbt_audit_energie_instruction', 'alias' => 'raei', 'colonne' => 'verso_cheque_url', 'dossier' => 5, 'type' => 9, 'libelle' => 'cheque eco verso'],
        ['source' => 'rbt_audit_energie_instruction', 'alias' => 'raei', 'colonne' => 'facture_url', 'dossier' => 5, 'type' => 7, 'libelle' => 'facture'],
        ['source' => 'rbt_audit_energie_instruction', 'alias' => 'raei', 'colonne' => 'rib_url', 'dossier' => 5, 'type' => 6, 'libelle' => 'RIB'],

        // Remboursement d'un chèque audit numérique
        ['source' => 'rbt_audit_numerique_depot', 'alias' => 'rand', 'colonne' => 'audit_url', 'dossier' => 7, 'type' => 3, 'libelle' => 'rapport d audit depose'],
        ['source' => 'rbt_audit_numerique_instruction', 'alias' => 'rani', 'colonne' => 'recto_cheque_url', 'dossier' => 8, 'type' => 8, 'libelle' => 'cheque eco recto'],
        ['source' => 'rbt_audit_numerique_instruction', 'alias' => 'rani', 'colonne' => 'verso_cheque_url', 'dossier' => 8, 'type' => 9, 'libelle' => 'cheque eco verso'],
        ['source' => 'rbt_audit_numerique_instruction', 'alias' => 'rani', 'colonne' => 'facture_url', 'dossier' => 8, 'type' => 7, 'libelle' => 'facture'],
        ['source' => 'rbt_audit_numerique_instruction', 'alias' => 'rani', 'colonne' => 'rib_url', 'dossier' => 8, 'type' => 6, 'libelle' => 'RIB'],

        // Remboursement d'un chèque travaux
        ['source' => 'rbt_travaux_instruction', 'alias' => 'rti', 'colonne' => 'recto_cheque_url', 'dossier' => 9, 'type' => 8, 'libelle' => 'cheque eco recto'],
        ['source' => 'rbt_travaux_instruction', 'alias' => 'rti', 'colonne' => 'verso_cheque_url', 'dossier' => 9, 'type' => 9, 'libelle' => 'cheque eco verso'],
        ['source' => 'rbt_travaux_instruction', 'alias' => 'rti', 'colonne' => 'fiche_travaux_url', 'dossier' => 9, 'type' => 12, 'libelle' => 'fiche de liaison'],
        ['source' => 'rbt_travaux_instruction', 'alias' => 'rti', 'colonne' => 'rib_url', 'dossier' => 9, 'type' => 6, 'libelle' => 'RIB'],
        ['source' => 'rbt_travaux_conformite', 'alias' => 'rtic', 'colonne' => 'document_url', 'dossier' => 10, 'type' => 10, 'libelle' => 'piece de conformite'],
    ];

    /**
     * Nom du fichier d'une pièce sur le disque, relatif à `uploads/`.
     *
     * @param array{dossier: int, type: int} $emplacement
     */
    public static function cheminRelatif(array $emplacement, int $id, string $extension): string
    {
        return self::DOSSIERS[$emplacement['dossier']]
            . $id
            . self::SUFFIXES[$emplacement['type']]
            . $extension;
    }

    /**
     * Nom donné à la pièce DANS l'archive : lisible par un archiviste, et
     * unique au sein d'un dossier même quand plusieurs pièces partagent un
     * libellé (plusieurs devis, par exemple).
     */
    public static function nomDansArchive(string $libelle, int $id, string $extension): string
    {
        $base = str_replace(' ', '-', $libelle);

        return sprintf('%s-%d.%s', $base, $id, $extension);
    }
}
