-- =====================================================================
-- Reco-04 (plan de traitement des risques CEE) : journal des violations
-- de la politique de sécurité du contenu.
--
-- POURQUOI AGRÉGER. Un navigateur envoie un rapport PAR violation et PAR
-- chargement de page. Une seule page fautive, vue mille fois par jour,
-- produirait mille lignes identiques : la table deviendrait illisible et
-- coûteuse, et la mesure serait noyée. On conserve donc une ligne par
-- (directive, source bloquée, page), avec un compteur.
--
-- CE QUI N'EST PAS CONSERVÉ, volontairement :
--   - la chaîne de requête des URL. Un rapport porte l'adresse de la page
--     où la violation s'est produite ; sur une page de réinitialisation,
--     cette adresse contient le JETON de réinitialisation. On ne garde
--     que le chemin ;
--   - le référent, l'adresse IP et l'agent utilisateur : ils
--     n'apprendraient rien sur la politique et transformeraient ce
--     journal technique en traçage de navigation.
--
-- A exécuter sur la recette d'abord, puis la production, AVANT le
-- déploiement du code.
--
-- Rejouable : CREATE TABLE IF NOT EXISTS.
-- =====================================================================

CREATE TABLE IF NOT EXISTS csp_violation (
    id INT AUTO_INCREMENT NOT NULL,

    -- Clé d'agrégation : empreinte de (directive, source bloquée, page).
    -- Une empreinte plutôt que les trois colonnes en index unique, parce
    -- que des URL de 500 caractères dépassent la taille maximale d'un
    -- index.
    empreinte CHAR(64) NOT NULL,

    -- Directive qui a refusé la ressource : style-src, script-src...
    directive VARCHAR(64) NOT NULL,

    -- Ce qui a été refusé. Vaut « inline » ou « eval » pour les
    -- violations de code embarqué, une origine sinon.
    source_bloquee VARCHAR(500) NOT NULL,

    -- Page concernée, SANS sa chaîne de requête.
    page VARCHAR(500) NOT NULL,

    -- 1 : violation de la politique EN VIGUEUR, donc une ressource
    --     réellement bloquée pour l'utilisateur, à traiter en priorité.
    -- 0 : violation de la politique en OBSERVATION, donc ce que le
    --     durcissement casserait. C'est la mesure attendue.
    politique_appliquee TINYINT(1) NOT NULL DEFAULT 0,

    occurrences INT NOT NULL DEFAULT 1,
    premiere_vue DATETIME NOT NULL,
    derniere_vue DATETIME NOT NULL,

    PRIMARY KEY (id),
    UNIQUE INDEX uniq_csp_violation_empreinte (empreinte),
    INDEX idx_csp_violation_directive (directive),
    INDEX idx_csp_violation_appliquee (politique_appliquee, derniere_vue),
    INDEX idx_csp_violation_derniere (derniere_vue)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB;


-- =====================================================================
-- Contrôle après exécution
-- =====================================================================
-- SELECT politique_appliquee, directive, COUNT(*) AS cas,
--        SUM(occurrences) AS occurrences
--   FROM csp_violation
--  GROUP BY politique_appliquee, directive
--  ORDER BY occurrences DESC;
