-- =====================================================================
-- D26013 — mandat donné au conseiller.
--
-- Le bénéficiaire dépose un mandat au format PDF au moment de sa
-- demande, et l'instruction administrative se prononce sur sa
-- conformité, comme elle le fait déjà pour le justificatif de
-- propriété, le KBIS et l'avis d'imposition.
--
-- PÉRIMÈTRE : les deux types de demande. `demande_travaux` et
-- `demande_audit_energie` portent exactement les mêmes pièces, et
-- `instruction_travaux` et `instruction_audit_energie` exactement les
-- mêmes conformités. Le mandat autorise le conseiller à agir pour le
-- bénéficiaire : il ne dépend pas du type de dossier. N'en équiper
-- qu'un seul des deux aurait créé une asymétrie que rien ne justifie.
--
-- Les colonnes suivent les conventions en place, qui ne sont pas
-- évidentes et qu'il vaut mieux connaître :
--   - `*_url` ne contient PAS une URL mais l'EXTENSION du fichier
--     (« pdf ») ; le chemin est reconstruit par convention ;
--   - `*_alt` porte le nom d'origine du fichier déposé ;
--   - `*_reason` est un tableau sérialisé PHP (type Doctrine « array »)
--     qui contient les entités de motif elles-mêmes, pas leurs
--     identifiants.
--
-- A exécuter sur la recette d'abord, puis la production, AVANT le
-- déploiement du code.
--
-- Rejouable : ADD COLUMN IF NOT EXISTS.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Le dépôt du mandat, côté demande
-- ---------------------------------------------------------------------
ALTER TABLE demande_travaux
    ADD COLUMN IF NOT EXISTS mandat_url VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS mandat_alt VARCHAR(255) DEFAULT NULL;

ALTER TABLE demande_audit_energie
    ADD COLUMN IF NOT EXISTS mandat_url VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS mandat_alt VARCHAR(255) DEFAULT NULL;


-- ---------------------------------------------------------------------
-- 2. La conformité du mandat, côté instruction administrative
-- ---------------------------------------------------------------------
ALTER TABLE instruction_travaux
    ADD COLUMN IF NOT EXISTS mandat_conformite VARCHAR(20) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS mandat_reason LONGTEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS mandat_reason_autre LONGTEXT DEFAULT NULL;

ALTER TABLE instruction_audit_energie
    ADD COLUMN IF NOT EXISTS mandat_conformite VARCHAR(20) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS mandat_reason LONGTEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS mandat_reason_autre LONGTEXT DEFAULT NULL;


-- ---------------------------------------------------------------------
-- 3. Les motifs de non-conformité du mandat
--
-- La liste définitive est attendue de la Région. Ces motifs sont posés
-- pour que l'écran soit utilisable dès la recette ; ils se remplacent
-- par un simple UPDATE, ou se désactivent avec la notion actif/inactif
-- livrée par ailleurs dans ce même lot.
--
-- Le filtre « MANDAT » rejoint les filtres existants JP, KBIS et AI.
--
-- « Autre » EST OBLIGATOIRE, et doit rester le DERNIER motif de la
-- liste (position_last = 99, comme pour JP, KBIS et AI) : c'est en
-- repérant la dernière case à cocher du groupe que le script
-- displayOption.js décide d'afficher le champ de texte libre. Un motif
-- réel en dernière position prendrait sa place et déclencherait la
-- saisie libre à tort.
--
-- Rejouable : chaque motif est inséré s'il manque, à l'unité.
-- ---------------------------------------------------------------------
INSERT INTO instruction_reason (filtre, slug, actif, position_last)
SELECT nouveaux.* FROM (
              SELECT 'MANDAT' AS filtre, 'Le mandat n''est pas signé' AS slug, 1 AS actif, 1 AS position_last
    UNION ALL SELECT 'MANDAT', 'Le mandat n''est pas daté', 1, 2
    UNION ALL SELECT 'MANDAT', 'Le document déposé n''est pas un mandat', 1, 3
    UNION ALL SELECT 'MANDAT', 'Autre', 1, 99
) AS nouveaux
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT filtre, slug FROM instruction_reason) AS existants
     WHERE existants.filtre = nouveaux.filtre AND existants.slug = nouveaux.slug
);


-- =====================================================================
-- Contrôle après exécution
-- =====================================================================
-- SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
--  WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME LIKE 'mandat%'
--  ORDER BY TABLE_NAME, COLUMN_NAME;
--
-- Attendu : 10 colonnes, 2 par table de demande et 3 par table
-- d'instruction.
--
-- SELECT id, slug, actif FROM instruction_reason WHERE filtre = 'MANDAT';
