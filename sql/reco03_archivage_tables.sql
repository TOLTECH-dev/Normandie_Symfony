-- =====================================================================
-- Reco-03 (plan de traitement des risques CEE) : registre des lots
-- d'archivage et photo des données d'export.
--
-- Trois tables, qui portent tout l'état de la procédure annuelle :
--
-- 1. archive_lot ................ une campagne annuelle, et son état.
--    L'état est le garde-fou central : aucune destruction n'est possible
--    avant « reception_validee », c'est-à-dire avant que les Archives de
--    la Région aient confirmé la récupération des archives.
--
-- 2. archive_lot_beneficiaire ... une ligne par COMPTE bénéficiaire du
--    lot. L'unité d'archivage est le compte (user ROLE_MEMBER), et non la
--    ligne « beneficiaire » : 197 comptes en portent plusieurs, et un zip
--    doit rassembler les dossiers de toutes les lignes du compte.
--
-- 3. archive_demande_export ..... la photo de l'export, une ligne par
--    dossier archivé. C'est elle, et elle seule, qui survit à la
--    destruction : l'export de la consultation des dossiers devient
--    l'union des dossiers vivants et de cette table, ce qui préserve les
--    statistiques depuis 2018.
--    INVARIANT : elle est écrite DÉJÀ ANONYMISÉE. Les six colonnes
--    nominatives (nom, prénom, e-mail, les deux téléphones et la
--    description du projet du particulier) y sont vides dès l'insertion.
--    L'identité complète ne figure que dans le zip versé aux Archives.
--    Ainsi, même si la destruction échoue en cours de route, la photo ne
--    contient aucune donnée nominative.
--
-- A exécuter sur la recette d'abord, puis la production, AVANT le
-- déploiement du code.
--
-- Rejouable : les trois CREATE TABLE sont en IF NOT EXISTS et aucune
-- donnée existante n'est modifiée.
--
-- Jeu de caractères aligné sur les 87 tables existantes (utf8mb3 /
-- utf8_unicode_ci) pour éviter toute divergence de collation.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Les lots
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS archive_lot (
    id INT AUTO_INCREMENT NOT NULL,

    -- Référence lisible de la campagne, par année civile : « ARCH-2027 »
    reference VARCHAR(32) NOT NULL,

    -- annonce | constitue | verse | reception_validee | detruit | atteste | abandonne
    etat VARCHAR(32) NOT NULL,

    -- Date à laquelle l'éligibilité a été calculée. Conservée pour que la
    -- sélection soit reproductible : rejouer la commande avec cette date
    -- doit redonner la même liste.
    date_reference DATETIME NOT NULL,

    date_creation DATETIME NOT NULL,

    -- Préavis donné aux conseillers, auditeurs et rénovateurs. Le préavis
    -- court à partir de l'envoi RÉEL du courriel, d'où deux dates : celle
    -- de l'envoi, et celle du terme qui en découle.
    date_annonce DATETIME DEFAULT NULL,
    date_fin_preavis DATETIME DEFAULT NULL,

    date_constitution DATETIME DEFAULT NULL,
    date_versement DATETIME DEFAULT NULL,
    date_reception_validee DATETIME DEFAULT NULL,
    date_destruction DATETIME DEFAULT NULL,
    date_attestation DATETIME DEFAULT NULL,

    -- Date à laquelle les archives déposées ont été retirées du serveur.
    -- Le répertoire de dépôt est une zone de transit : une fois les archives
    -- récupérées par les Archives de la Région et l'attestation produite, en
    -- garder une copie reviendrait à conserver, sous une autre forme, les
    -- données dont on vient d'attester la destruction.
    date_purge_depot DATETIME DEFAULT NULL,

    -- Qui a lancé la campagne (identifiant de connexion, ou « Automate »
    -- pour le déclenchement planifié de janvier).
    auteur VARCHAR(100) NOT NULL,

    nb_comptes INT NOT NULL DEFAULT 0,
    nb_dossiers INT NOT NULL DEFAULT 0,

    commentaire TEXT DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE INDEX uniq_archive_lot_reference (reference),
    INDEX idx_archive_lot_etat (etat)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB;


-- ---------------------------------------------------------------------
-- 2. Les comptes de chaque lot
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS archive_lot_beneficiaire (
    id INT AUTO_INCREMENT NOT NULL,
    lot_id INT NOT NULL,

    -- Compte bénéficiaire. Volontairement SANS clé étrangère vers user :
    -- le registre doit rester lisible et attestable même si le compte
    -- disparaît un jour, alors qu'une contrainte le ferait disparaître
    -- avec lui.
    user_id INT NOT NULL,

    -- Déclencheur retenu, parmi les cinq de la politique d'archivage.
    declencheur VARCHAR(64) NOT NULL,

    -- Date à laquelle le compte est devenu archivable : la plus tardive
    -- de ses dossiers. NULL pour un compte ouvert sans aucune demande.
    date_eligibilite DATETIME DEFAULT NULL,

    nb_dossiers INT NOT NULL DEFAULT 0,

    -- a_produire | produite | supprimee | retiree | en_erreur
    -- « retiree » : compte redevenu non éligible pendant le préavis.
    etat VARCHAR(32) NOT NULL,

    zip_nom VARCHAR(255) DEFAULT NULL,
    zip_taille BIGINT DEFAULT NULL,
    zip_sha256 CHAR(64) DEFAULT NULL,

    -- Pièces attendues, et celles qui étaient référencées en base mais
    -- absentes du disque. Déclarées plutôt que passées sous silence : c'est ce qui rend
    -- l'attestation de destruction honnête.
    nb_pieces INT DEFAULT NULL,
    nb_pieces_manquantes INT DEFAULT NULL,

    date_production DATETIME DEFAULT NULL,
    date_suppression DATETIME DEFAULT NULL,

    message_erreur TEXT DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE INDEX uniq_archive_lot_beneficiaire (lot_id, user_id),
    INDEX idx_archive_lot_beneficiaire_etat (etat),
    INDEX idx_archive_lot_beneficiaire_user (user_id),
    CONSTRAINT fk_archive_lot_beneficiaire_lot
        FOREIGN KEY (lot_id) REFERENCES archive_lot (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB;


-- ---------------------------------------------------------------------
-- 3. La photo de l'export
--
-- La ligne d'export complète est dans `donnees`, au format JSON, avec les
-- six colonnes nominatives déjà vides. Le JSON évite un ALTER TABLE à
-- chaque évolution de l'export (la liste des colonnes change dès ce lot :
-- retrait du revenu fiscal) et garde chaque photo fidèle à ce qu'était
-- l'export l'année de l'archivage.
--
-- Les cinq colonnes nommées sont extraites du JSON pour pouvoir trier et
-- filtrer sans le lire. Elles se remplissent EXACTEMENT depuis l'export,
-- ce qui n'est pas le cas de tous ses champs : les clés de filtre de la
-- consultation (DemandeListCriteria::FILTER_FIELDS) et les libellés de
-- colonnes de l'export ne se correspondent pas un pour un. Plutôt que de
-- remplir approximativement, on ne nomme que ce qui est fidèle ; un
-- filtrage plus fin lit le JSON.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS archive_demande_export (
    id INT AUTO_INCREMENT NOT NULL,
    lot_id INT NOT NULL,
    user_id INT NOT NULL,

    -- Identifiant du dossier supprimé. Conservé tel quel : les
    -- auto-incréments ne sont pas réattribués, la référence reste donc
    -- unique dans le temps.
    demande_id INT NOT NULL,

    -- « Thématique » de l'export, sous forme de libellé et non de code :
    -- c'est ce que porte le CSV, et c'est ce que lit la Région.
    thematique VARCHAR(100) DEFAULT NULL,

    demande_date DATE DEFAULT NULL,
    demande_statut VARCHAR(100) DEFAULT NULL,
    commission_date DATE DEFAULT NULL,
    date_rmh DATE DEFAULT NULL,

    -- Ligne d'export complète, colonnes nominatives déjà vidées.
    donnees JSON NOT NULL,

    date_creation DATETIME NOT NULL,

    PRIMARY KEY (id),
    UNIQUE INDEX uniq_archive_demande_export_demande (demande_id),
    INDEX idx_archive_demande_export_lot (lot_id),
    INDEX idx_archive_demande_export_user (user_id),
    INDEX idx_archive_demande_export_statut (demande_statut),
    INDEX idx_archive_demande_export_date (demande_date),
    CONSTRAINT fk_archive_demande_export_lot
        FOREIGN KEY (lot_id) REFERENCES archive_lot (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB;


-- ---------------------------------------------------------------------
-- Rattrapage pour une installation où archive_lot existe déjà sans la
-- colonne de purge du dépôt. Sans effet si elle est présente.
-- ---------------------------------------------------------------------
ALTER TABLE archive_lot
    ADD COLUMN IF NOT EXISTS date_purge_depot DATETIME DEFAULT NULL AFTER date_attestation;


-- =====================================================================
-- Contrôle après exécution
-- =====================================================================
-- SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
--  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'archive%';
