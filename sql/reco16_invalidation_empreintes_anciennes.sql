-- ============================================================================================
--  Reco-16 — neutralisation des empreintes héritées de l'ancienne application
--
--  Objet : retirer de la base les empreintes SHA-512 + sel (88 caractères, format FOSUser), que
--          le plan de traitement des risques demande d'abandonner. Elles restent exploitables
--          hors ligne par qui obtiendrait une copie de la base : 5 000 tours de SHA-512 se
--          cassent sans difficulté sur du matériel courant, et les mots de passe ainsi retrouvés
--          valent souvent sur d'autres services.
--
--  Effet pour les utilisateurs : AUCUN. Ces empreintes ne sont déjà plus vérifiables par
--  l'application — PHP ne reconnaît pas ce format — et aucun mot de passe ne permet donc de s'en
--  servir. Le script ne fait que rendre explicite un état de fait, en supprimant la valeur
--  sensible. Les comptes RESTENT ACTIFS : leurs titulaires reprennent la main par le parcours
--  « mot de passe oublié », qui les réenregistre en Argon2id.
--
--  Ce qui n'est PAS touché :
--    - les empreintes bcrypt ($2y$) : elles restent valables et basculent seules en Argon2id à la
--      prochaine connexion (migrate_from, cf. config/packages/security.yaml) ;
--    - les empreintes Argon2id déjà en place ;
--    - l'état d'activation des comptes, leurs rôles et leurs données.
--
--  Nature     : ÉCRITURE. À passer APRÈS la sauvegarde et APRÈS le déploiement du code, de sorte
--               que le parcours de réinitialisation enregistre bien en Argon2id.
--  Réexécution : sans effet au second passage (la clause WHERE exclut la valeur déjà posée).
--  Retour arrière : impossible sans restauration de la sauvegarde — les empreintes sont perdues.
--               C'est voulu : c'est l'objet du script.
--  Durée      : une seule écriture sur environ 10 500 lignes, de l'ordre de la centaine de
--               millisecondes. Aucune interruption de service.
--
--  Exécution  : mysql -u <compte> -p <base> < sql/reco16_invalidation_empreintes_anciennes.sql
-- ============================================================================================

-- Valeur posée à la place de l'empreinte. Elle ne peut être produite par aucun algorithme de
-- hachage et n'est reconnue par aucun vérificateur : toute tentative de connexion échoue.
-- Elle est lisible, ce qui permet de reconnaître l'opération dans la base par la suite.
SET @marqueur := '!reco16-empreinte-neutralisee';


-- --------------------------------------------------------------------------------------------
--  1. État avant l'opération
-- --------------------------------------------------------------------------------------------
SELECT
    CASE
        WHEN password = @marqueur                THEN 'deja neutralisee'
        WHEN password LIKE '$argon2id$%'         THEN 'argon2id'
        WHEN password LIKE '$2y$%'               THEN 'bcrypt'
        ELSE                                          'ancienne (a neutraliser)'
    END                                                            AS empreinte,
    SUM(CASE WHEN enabled = 1 THEN 1 ELSE 0 END)                   AS comptes_actifs,
    COUNT(*)                                                       AS total
FROM user
GROUP BY empreinte
ORDER BY total DESC;


-- --------------------------------------------------------------------------------------------
--  2. Neutralisation
--
--     Le sel est vidé dans le même mouvement : il n'a de sens que pour l'ancien format, et le
--     conserver laisserait en base un élément secret devenu inutile.
-- --------------------------------------------------------------------------------------------
START TRANSACTION;

UPDATE user
SET password = @marqueur,
    salt     = NULL
WHERE password NOT LIKE '$2y$%'
  AND password NOT LIKE '$argon2id$%'
  AND password <> @marqueur;

COMMIT;


-- --------------------------------------------------------------------------------------------
--  3. État après l'opération
--
--     La ligne « ancienne (a neutraliser) » doit avoir disparu. Les effectifs bcrypt et argon2id
--     doivent être inchangés par rapport à l'étape 1 : le script n'a pas à les toucher.
-- --------------------------------------------------------------------------------------------
SELECT
    CASE
        WHEN password = @marqueur                THEN 'neutralisee'
        WHEN password LIKE '$argon2id$%'         THEN 'argon2id'
        WHEN password LIKE '$2y$%'               THEN 'bcrypt'
        ELSE                                          'ANCIENNE RESTANTE — ANOMALIE'
    END                                                            AS empreinte,
    SUM(CASE WHEN enabled = 1 THEN 1 ELSE 0 END)                   AS comptes_actifs,
    COUNT(*)                                                       AS total
FROM user
GROUP BY empreinte
ORDER BY total DESC;

-- Sels restants : ceux des comptes bcrypt, que le script ne touche pas. Bcrypt porte son sel
-- dans l'empreinte elle-même, cette colonne n'y sert donc à rien — elle sera vidée d'elle-même au
-- passage en Argon2id. Un reliquat de quelques centaines de lignes est normal ; ce qui ne le
-- serait pas, c'est un chiffre proche du parc entier.
SELECT COUNT(*) AS sels_restants FROM user WHERE salt IS NOT NULL AND salt <> '';
