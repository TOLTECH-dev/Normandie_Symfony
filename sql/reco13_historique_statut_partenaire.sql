-- =====================================================================
-- Reco-13 (plan de traitement des risques CEE) : historique des périodes
-- d'activité des partenaires auditeurs et rénovateurs.
--
-- POURQUOI. La recommandation demande « un historique précis des comptes
-- actifs / inactifs (un compte peut être temporairement inactif) ». Or
-- `partenaire_statut` est en relation UN POUR UN avec le partenaire :
-- elle ne retient que la DERNIÈRE désactivation. Un partenaire désactivé,
-- réactivé, puis désactivé de nouveau perd ses périodes antérieures.
--
-- CE QUE CETTE TABLE EST. Un journal en ajout seul : une ligne par
-- changement d'état, jamais modifiée ni supprimée. Les périodes se
-- reconstituent en lisant les changements dans l'ordre. Un journal est
-- plus sûr qu'une table de périodes à tenir à jour : rien à corriger
-- après coup, donc rien à corriger de travers.
--
-- CE QU'ELLE N'EST PAS. Une reconstitution du passé. Les changements
-- antérieurs à sa mise en service n'ont jamais été enregistrés nulle
-- part et sont définitivement perdus. L'amorçage ci-dessous inscrit
-- seulement ce que la base sait encore : la date de rattachement, et la
-- dernière désactivation quand le partenaire est inactif. Ces lignes
-- portent `origine = 'reconstitution'` pour qu'on ne les confonde jamais
-- avec un changement réellement observé.
--
-- A exécuter sur la recette d'abord, puis la production, AVANT le
-- déploiement du code.
--
-- Rejouable : le CREATE est en IF NOT EXISTS, et l'amorçage ne s'exécute
-- que si la table est vide.
-- =====================================================================

CREATE TABLE IF NOT EXISTS partenaire_statut_historique (
    id INT AUTO_INCREMENT NOT NULL,

    partenaire_id INT NOT NULL,

    -- État APRÈS le changement : 1 actif, 0 inactif.
    enabled TINYINT(1) NOT NULL,

    -- Date de l'événement au sens métier (celle que porte la fiche), qui
    -- peut différer de la date d'enregistrement : un gestionnaire saisit
    -- parfois une désactivation avec une date antérieure.
    date_evenement DATE DEFAULT NULL,

    -- Date d'enregistrement réelle, qui fait foi pour la traçabilité.
    date_creation DATETIME NOT NULL,

    auteur VARCHAR(100) DEFAULT NULL,

    -- 'observe'         : changement constaté par l'application
    -- 'reconstitution'  : déduit de l'état connu lors de la mise en service
    origine VARCHAR(20) NOT NULL DEFAULT 'observe',

    PRIMARY KEY (id),
    INDEX idx_partenaire_statut_historique_partenaire (partenaire_id, date_creation),
    INDEX idx_partenaire_statut_historique_etat (enabled)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB;


-- ---------------------------------------------------------------------
-- Amorçage : ce que la base sait encore de l'état des partenaires.
--
-- Deux lignes au plus par partenaire :
--   1. son rattachement, donc son activation initiale ;
--   2. sa dernière désactivation, s'il est inactif aujourd'hui.
--
-- La sentinelle '1900-01-01' que portent les partenaires actifs n'est pas
-- une date de désactivation : elle est écartée.
-- ---------------------------------------------------------------------

INSERT INTO partenaire_statut_historique
    (partenaire_id, enabled, date_evenement, date_creation, auteur, origine)
SELECT p.id, 1, ps.date_rattachement, NOW(), 'Reco-13 mise en service', 'reconstitution'
  FROM partenaire_ p
  JOIN partenaire_statut ps ON ps.id = p.partenaire_statut_id
 WHERE ps.date_rattachement IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM partenaire_statut_historique);

INSERT INTO partenaire_statut_historique
    (partenaire_id, enabled, date_evenement, date_creation, auteur, origine)
SELECT p.id, 0, ps.date_inactif, NOW(), 'Reco-13 mise en service', 'reconstitution'
  FROM partenaire_ p
  JOIN partenaire_statut ps ON ps.id = p.partenaire_statut_id
 WHERE ps.enabled = 0
   AND ps.date_inactif IS NOT NULL
   AND ps.date_inactif <> '1900-01-01'
   AND (SELECT COUNT(*) FROM partenaire_statut_historique h WHERE h.origine = 'observe') = 0
   AND NOT EXISTS (
        SELECT 1 FROM (SELECT * FROM partenaire_statut_historique) h
         WHERE h.partenaire_id = p.id AND h.enabled = 0
   );


-- =====================================================================
-- Contrôle après exécution
-- =====================================================================
-- SELECT origine, enabled, COUNT(*) AS lignes,
--        MIN(date_evenement) AS plus_ancienne, MAX(date_evenement) AS plus_recente
--   FROM partenaire_statut_historique
--  GROUP BY origine, enabled;
--
-- Attendu à la mise en service : 246 lignes « actif » et 84 lignes
-- « inactif », toutes en origine « reconstitution ».
