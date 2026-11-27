-- =====================================================================
-- D26013 — historique des états d'un compte utilisateur.
--
-- « Adaptation de la notion d'état des comptes utilisateurs avec la date
-- heure de chaque changement d'état (création, désactivation,
-- réactivation, suppression). »
--
-- La table `user` ne peut pas y répondre : elle ne porte qu'un booléen
-- `enabled`, une `date_creation`, et une `date_inactif` qui n'est PAS une
-- date de désactivation (voir plus bas). Une réactivation n'y laisse
-- aucune trace, une suppression efface la ligne.
--
-- D'où une table d'événements, sur le modèle de
-- `partenaire_statut_historique` (Reco-13) : même vocabulaire, même
-- distinction entre ce que l'application a CONSTATÉ et ce qu'on a
-- RECONSTITUÉ à la mise en service.
--
-- Pas de clé étrangère vers `user`, volontairement : l'historique doit
-- survivre à la suppression du compte, qui est précisément l'un des
-- événements qu'il enregistre. Le login est donc recopié.
--
-- A exécuter sur la recette d'abord, puis la production, AVANT le
-- déploiement du code.
--
-- Rejouable : CREATE TABLE IF NOT EXISTS, et les reconstitutions sont
-- conditionnées à l'absence de lignes de même origine.
-- =====================================================================

CREATE TABLE IF NOT EXISTS user_etat_historique (
    id             INT(11)      NOT NULL AUTO_INCREMENT,
    user_id        INT(11)      NOT NULL,
    username       VARCHAR(255) NOT NULL,
    etat           VARCHAR(20)  NOT NULL,
    date_evenement DATETIME     NOT NULL,
    auteur         VARCHAR(100) DEFAULT NULL,
    origine        VARCHAR(20)  NOT NULL,
    PRIMARY KEY (id),
    KEY user_etat_historique_user_idx (user_id, date_evenement),
    KEY user_etat_historique_username_idx (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


-- =====================================================================
-- Reconstitution 1 : la création de chaque compte existant
-- =====================================================================
-- `date_creation` est fiable et renseignée pour les 11 186 comptes.

INSERT INTO user_etat_historique (user_id, username, etat, date_evenement, auteur, origine)
SELECT u.id, u.username, 'creation', u.date_creation, u.auteur_creation, 'reconstitue'
FROM user u
WHERE NOT EXISTS (
    SELECT 1 FROM user_etat_historique h
    WHERE h.user_id = u.id AND h.etat = 'creation'
);


-- =====================================================================
-- Reconstitution 2 : les désactivations datables
-- =====================================================================
-- ATTENTION — `user.date_inactif` ne veut pas dire ce que son nom laisse
-- croire. Sur 9 240 comptes qui la portent, 8 796 ont exactement la date
-- de création : la colonne a servi à l'import initial. Seul
-- `AdminController::enabled(0)` y écrit une vraie date de désactivation.
--
-- On ne reconstitue donc que les lignes où les deux dates DIFFÈRENT (444
-- comptes) : ce sont les désactivations réellement opérées depuis
-- l'application. Les 127 comptes désactivés dont la date_inactif vaut la
-- date de création ne sont pas datables — leur désactivation est réelle,
-- mais sa date est perdue, et l'inventer serait pire que l'omettre.
--
-- Les RÉACTIVATIONS ne sont pas reconstituables du tout : rien ne les
-- enregistre aujourd'hui. L'écran affiche donc l'état courant du compte
-- à côté de l'historique, pour qu'on ne lise pas une désactivation
-- ancienne comme l'état actuel.

INSERT INTO user_etat_historique (user_id, username, etat, date_evenement, auteur, origine)
SELECT u.id, u.username, 'desactivation', u.date_inactif, u.auteur_modif, 'reconstitue'
FROM user u
WHERE u.date_inactif IS NOT NULL
    AND DATE(u.date_inactif) <> DATE(u.date_creation)
    AND NOT EXISTS (
        SELECT 1 FROM user_etat_historique h
        WHERE h.user_id = u.id AND h.etat = 'desactivation'
    );


-- =====================================================================
-- Contrôle
-- =====================================================================
-- SELECT etat, origine, COUNT(*) FROM user_etat_historique
-- GROUP BY etat, origine ORDER BY etat, origine;
--   -> creation / reconstitue      : 11 186
--      desactivation / reconstitue :    444
--
-- SELECT * FROM user_etat_historique WHERE user_id = <id> ORDER BY date_evenement DESC;
