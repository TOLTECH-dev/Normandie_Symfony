-- =====================================================================
-- Reco-15 (plan de traitement des risques CEE) : expiration périodique
-- des mots de passe et historique des derniers mots de passe
--
-- 1. user.password_changed_at : date du dernier mot de passe choisi par
--    l'utilisateur, point de départ de l'expiration. Initialisée à la date
--    d'exécution du script : aucun compte n'expire à la mise en production,
--    les premières échéances tombent SECURITY_PASSWORD_EXPIRATION_MONTHS
--    mois plus tard.
-- 2. user_password_history : empreintes des derniers mots de passe, pour en
--    interdire la réutilisation (ANSSI PG-078 R27). Jamais de mot de passe
--    en clair.
--
-- A exécuter sur la recette d'abord, puis la production, AVANT le
-- déploiement du code (Doctrine lit la nouvelle colonne dès le chargement
-- d'un compte).
--
-- A exécuter UNE SEULE FOIS : la commande normandie:expirePasswords remet
-- password_changed_at à NULL pour imposer un renouvellement immédiat ;
-- relancer l'UPDATE annulerait cette expiration.
-- =====================================================================

ALTER TABLE user
    ADD COLUMN IF NOT EXISTS password_changed_at DATETIME DEFAULT NULL;

UPDATE user
SET password_changed_at = NOW()
WHERE password_changed_at IS NULL;

CREATE TABLE IF NOT EXISTS user_password_history (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_user_password_history_user (user_id),
    PRIMARY KEY (id),
    CONSTRAINT fk_user_password_history_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB;

-- =====================================================================
-- Vérification post-exécution
-- =====================================================================
-- SELECT COUNT(*) FROM user WHERE password_changed_at IS NULL;   -- attendu : 0
-- SHOW CREATE TABLE user_password_history;
