-- =====================================================================
-- D26013 — index de l'historique par auteur.
--
-- L'onglet « Activité » de la fiche utilisateur lit `historique_` filtré
-- sur `auteur_creation` et trié par date décroissante. La table n'avait
-- d'index que sur `demande_id` et `remboursement_id` : chaque affichage
-- aurait parcouru les 411 000 lignes, deux fois (comptage + page).
--
-- L'index composite sert les deux à la fois : le filtre par l'auteur,
-- puis le tri par date sans table temporaire.
--
-- Coût : `auteur_creation` est un VARCHAR(255) en utf8mb3, soit 765
-- octets au plus, plus 5 pour la date — loin de la limite InnoDB de
-- 3072 octets, aucun préfixe n'est nécessaire.
--
-- A exécuter sur la recette d'abord, puis la production. Sans effet sur
-- le code : à défaut, l'écran fonctionnerait, mais lentement.
--
-- Création d'index sur une table de 411 000 lignes : quelques secondes,
-- en ligne (ALGORITHM=INPLACE par défaut sous MariaDB 10.x/11.x).
--
-- Rejouable : CREATE INDEX IF NOT EXISTS.
-- =====================================================================

CREATE INDEX IF NOT EXISTS auteur_date_idx
    ON historique_ (auteur_creation, date_creation);


-- =====================================================================
-- Contrôle
-- =====================================================================
-- SHOW INDEX FROM historique_ WHERE Key_name = 'auteur_date_idx';
--
-- EXPLAIN SELECT * FROM historique_
-- WHERE auteur_creation = 'tthuillier'
-- ORDER BY date_creation DESC
-- LIMIT 50;
--   -> doit annoncer « Using index condition » sur auteur_date_idx,
--      sans « Using filesort ».
