-- =====================================================================
-- Optimisation de la page de consultation des dossiers (back-office)
--
-- Ces index visent les colonnes FK utilisees en JOIN dans
-- Demande_Repository::findAllAjax() et Demande_Repository::countAll().
--
-- Avant d'appliquer : verifier qu'ils n'existent pas deja :
--   SHOW INDEX FROM demande_audit_energie WHERE Column_name = 'structure_id';
--   SHOW INDEX FROM demande_travaux       WHERE Column_name = 'structure_id';
--   SHOW INDEX FROM partenaire_           WHERE Column_name = 'partenaire_statut_id';
--   SHOW INDEX FROM historique_           WHERE Column_name = 'demande_id';
--
-- A executer sur la base de recette d'abord, puis prod (heure creuse).
-- Chaque ALTER pose un lock metadonnees court ; sur InnoDB la cration
-- d'index est ONLINE par defaut (ALGORITHM=INPLACE, LOCK=NONE).
-- =====================================================================

ALTER TABLE demande_audit_energie
    ADD INDEX IF NOT EXISTS idx_dae_structure_id (structure_id),
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE demande_travaux
    ADD INDEX IF NOT EXISTS idx_dt_structure_id (structure_id),
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE partenaire_
    ADD INDEX IF NOT EXISTS idx_p_partenaire_statut_id (partenaire_statut_id),
    ALGORITHM=INPLACE, LOCK=NONE;

-- Index composite pour la mini-requete des comptes de commentaires
-- (cf. Demande_Repository::countCommentairesByDemandeIds()).
-- 'action' est filtre par LOWER(...) = 'commentaire' : un index simple
-- sur (demande_id, action) permet quand meme une grosse selectivite
-- sur demande_id.
ALTER TABLE historique_
    ADD INDEX IF NOT EXISTS idx_h_demande_action (demande_id, action),
    ALGORITHM=INPLACE, LOCK=NONE;

-- =====================================================================
-- Verification post-execution
-- =====================================================================
-- SHOW INDEX FROM demande_audit_energie;
-- SHOW INDEX FROM demande_travaux;
-- SHOW INDEX FROM partenaire_;
-- SHOW INDEX FROM historique_;
--
-- Puis EXPLAIN sur la requete findAllAjax pour confirmer le type != ALL
-- sur les tables ci-dessus.
