-- =============================================================
-- Script de mise en conformité des comptes de test
-- =============================================================

-- =============================================================
-- 1. CONSEILLERS : créer les structure_conseiller + lien structure
-- =============================================================

-- Trouver l'id de la structure STRT1306
-- SELECT s.id FROM structure_ s
-- INNER JOIN structure_identification si ON s.structure_identification_id = si.id
-- WHERE si.code = 'STRT1306';
-- => Remplacer @structure_id par le résultat

SET @structure_id = (
    SELECT s.id FROM structure_ s
    INNER JOIN structure_identification si ON s.structure_identification_id = si.id
    WHERE si.code = 'STRT1306'
);

-- Conseiller 1 : gtiger (user id 15415)
INSERT INTO structure_conseiller (nom, prenom, telephone, email, enabled)
VALUES ('Tiger', 'G', NULL, 'gtiger+conseiller@almond.eu', 1);
SET @conseiller1_id = LAST_INSERT_ID();

INSERT INTO structure__structure_conseiller (structure__id, structure_conseiller_id)
VALUES (@structure_id, @conseiller1_id);

UPDATE user SET username = CONCAT('C', LPAD(@conseiller1_id, 5, '0'))
WHERE id = 15415;

-- Conseiller 2 : ggauvrit (user id 15422)
INSERT INTO structure_conseiller (nom, prenom, telephone, email, enabled)
VALUES ('Gauvrit', 'G', NULL, 'ggauvrit+conseiller@almond.eu', 1);
SET @conseiller2_id = LAST_INSERT_ID();

INSERT INTO structure__structure_conseiller (structure__id, structure_conseiller_id)
VALUES (@structure_id, @conseiller2_id);

UPDATE user SET username = CONCAT('C', LPAD(@conseiller2_id, 5, '0'))
WHERE id = 15422;


-- =============================================================
-- 2. AUDITEUR : créer 1 partenaire_ avec 2 contacts
-- =============================================================

-- Identification
INSERT INTO partenaire_identification (raison_sociale, thematique, siret)
VALUES ('Auditeur Test Almond', '0 | auditeur', '00000000000000');
SET @aud_identification_id = LAST_INSERT_ID();

-- Adresse
INSERT INTO partenaire_adresse (adresse1, code_postal, ville, email)
VALUES ('Adresse test', '75000', 'Paris', 'gtiger+auditeur@almond.eu');
SET @aud_adresse_id = LAST_INSERT_ID();

-- Statut
INSERT INTO partenaire_statut (date_rattachement, enabled)
VALUES (CURDATE(), 1);
SET @aud_statut_id = LAST_INSERT_ID();

-- Partenaire auditeur
INSERT INTO partenaire_ (date_creation, auteur_creation, date_modif, auteur_modif, type, partenaire_identification_id, partenaire_adresse_id, partenaire_statut_id)
VALUES (NOW(), 'SCRIPT_TEST', NOW(), 'SCRIPT_TEST', '0 | auditeur', @aud_identification_id, @aud_adresse_id, @aud_statut_id);
SET @aud_partenaire_id = LAST_INSERT_ID();

-- Contact 1 : gtiger
INSERT INTO partenaire_contact (civilite, nom, prenom, email, telephone)
VALUES (NULL, 'Tiger', 'G', 'gtiger+auditeur@almond.eu', NULL);
SET @aud_contact1_id = LAST_INSERT_ID();

INSERT INTO partenaire__partenaire_contact (partenaire__id, partenaire_contact_id)
VALUES (@aud_partenaire_id, @aud_contact1_id);

-- Contact 2 : ggauvrit
INSERT INTO partenaire_contact (civilite, nom, prenom, email, telephone)
VALUES (NULL, 'Gauvrit', 'G', 'ggauvrit+auditeur@almond.eu', NULL);
SET @aud_contact2_id = LAST_INSERT_ID();

INSERT INTO partenaire__partenaire_contact (partenaire__id, partenaire_contact_id)
VALUES (@aud_partenaire_id, @aud_contact2_id);

-- Mise à jour des logins auditeur (les 2 users pointent vers le même partenaire)
UPDATE user SET username = CONCAT('A', LPAD(@aud_partenaire_id, 5, '0'))
WHERE id = 15416;

UPDATE user SET username = CONCAT('A', LPAD(@aud_partenaire_id, 5, '0'))
WHERE id = 15423;


-- =============================================================
-- 3. RENOVATEUR : créer 1 partenaire_ avec 2 contacts
-- =============================================================

-- Identification
INSERT INTO partenaire_identification (raison_sociale, thematique, siret)
VALUES ('Renovateur Test Almond', '1 | renovateur', '00000000000000');
SET @ren_identification_id = LAST_INSERT_ID();

-- Adresse
INSERT INTO partenaire_adresse (adresse1, code_postal, ville, email)
VALUES ('Adresse test', '75000', 'Paris', 'gtiger+renovateur@almond.eu');
SET @ren_adresse_id = LAST_INSERT_ID();

-- Statut
INSERT INTO partenaire_statut (date_rattachement, enabled)
VALUES (CURDATE(), 1);
SET @ren_statut_id = LAST_INSERT_ID();

-- Partenaire rénovateur
INSERT INTO partenaire_ (date_creation, auteur_creation, date_modif, auteur_modif, type, partenaire_identification_id, partenaire_adresse_id, partenaire_statut_id)
VALUES (NOW(), 'SCRIPT_TEST', NOW(), 'SCRIPT_TEST', '1 | renovateur', @ren_identification_id, @ren_adresse_id, @ren_statut_id);
SET @ren_partenaire_id = LAST_INSERT_ID();

-- Contact 1 : gtiger
INSERT INTO partenaire_contact (civilite, nom, prenom, email, telephone)
VALUES (NULL, 'Tiger', 'G', 'gtiger+renovateur@almond.eu', NULL);
SET @ren_contact1_id = LAST_INSERT_ID();

INSERT INTO partenaire__partenaire_contact (partenaire__id, partenaire_contact_id)
VALUES (@ren_partenaire_id, @ren_contact1_id);

-- Contact 2 : ggauvrit
INSERT INTO partenaire_contact (civilite, nom, prenom, email, telephone)
VALUES (NULL, 'Gauvrit', 'G', 'ggauvrit+renovateur@almond.eu', NULL);
SET @ren_contact2_id = LAST_INSERT_ID();

INSERT INTO partenaire__partenaire_contact (partenaire__id, partenaire_contact_id)
VALUES (@ren_partenaire_id, @ren_contact2_id);

-- Mise à jour des logins rénovateur (les 2 users pointent vers le même partenaire)
UPDATE user SET username = CONCAT('R', LPAD(@ren_partenaire_id, 5, '0'))
WHERE id = 15417;

UPDATE user SET username = CONCAT('R', LPAD(@ren_partenaire_id, 5, '0'))
WHERE id = 15424;


-- =============================================================
-- 4. VERIFICATION
-- =============================================================
SELECT id, username, email, roles FROM user WHERE id IN (15415, 15416, 15417, 15422, 15423, 15424);
