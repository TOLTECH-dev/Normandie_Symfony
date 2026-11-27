-- =====================================================================
-- D26013 — motifs de non-conformité actifs / inactifs.
--
-- La Région veut pouvoir retirer un motif devenu obsolète des listes de
-- choix, SANS toucher aux dossiers déjà instruits avec ce motif.
--
-- Un motif inactif n'est plus PROPOSÉ ; il reste choisi là où il l'a
-- déjà été. C'est possible sans précaution particulière sur l'existant :
-- les colonnes `*_reason` des tables d'instruction ne stockent pas des
-- identifiants mais les entités SÉRIALISÉES, libellé compris. Un dossier
-- ancien affiche donc le texte figé au moment de sa saisie, que le motif
-- soit depuis désactivé ou renommé.
--
-- Valeur par défaut à 1 : à la mise en service, rien ne change — les 35
-- motifs d'instruction et les 34 de remboursement restent proposés.
--
-- A exécuter sur la recette d'abord, puis la production, AVANT le
-- déploiement du code (Doctrine lit la nouvelle colonne dès le
-- chargement d'un motif).
--
-- Rejouable : ADD COLUMN IF NOT EXISTS.
-- =====================================================================

ALTER TABLE instruction_reason
    ADD COLUMN IF NOT EXISTS actif TINYINT(1) NOT NULL DEFAULT 1 AFTER slug;

ALTER TABLE remboursement_reason
    ADD COLUMN IF NOT EXISTS actif TINYINT(1) NOT NULL DEFAULT 1 AFTER slug;


-- =====================================================================
-- Désactiver un motif
-- =====================================================================
-- Il n'existe pas d'écran pour cela : la demande porte sur la notion et
-- sa prise en compte à l'affichage, pas sur une interface de gestion.
-- La bascule se fait donc en base, par exemple :
--
--   UPDATE instruction_reason   SET actif = 0 WHERE id = 12;
--   UPDATE remboursement_reason SET actif = 0 WHERE id = 4;
--
-- Pour retrouver un motif à partir de son texte :
--
--   SELECT id, filtre, actif, slug FROM instruction_reason
--    WHERE slug LIKE '%taxe foncière%' ORDER BY filtre, position_last;


-- =====================================================================
-- Contrôle après exécution
-- =====================================================================
-- SELECT 'instruction' AS origine, filtre, SUM(actif) AS actifs, COUNT(*) AS total
--   FROM instruction_reason GROUP BY filtre
-- UNION ALL
-- SELECT 'remboursement', filtre, SUM(actif), COUNT(*)
--   FROM remboursement_reason GROUP BY filtre;
--
-- Attendu à la mise en service : actifs = total partout.
