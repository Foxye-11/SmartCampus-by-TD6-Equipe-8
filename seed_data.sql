-- ============================================================
-- SmartCampus — Jeu de données de test
-- 5 enseignants + 300 étudiants.
-- À exécuter APRÈS migration_v2.sql (tables groupes_td, ecole, etc.).
-- Mot de passe de TOUS les comptes créés : Admin1234!
-- Idempotence : à exécuter une seule fois (emails uniques).
-- ============================================================

USE SmartCampusDB;

-- Hash bcrypt correspondant au mot de passe « Admin1234! » (réutilisé pour tous les comptes de test)
SET @hash := '$2b$10$Y5JwhAqPhxDQ7R0qUqOiKuWFEUAXFbodcnX34DD34yqfTF5OI0ZS.';

-- ------------------------------------------------------------
-- 5 ENSEIGNANTS
-- ------------------------------------------------------------
INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role_id, actif)
VALUES ('Dubois','Marie','prof1@smartcampus.fr',@hash,(SELECT id FROM roles WHERE nom='enseignant'),1);
INSERT INTO enseignants (utilisateur_id, grade, departement_id, ecole)
VALUES (LAST_INSERT_ID(),'Professeur',(SELECT id FROM departements WHERE code='INFO'),'École 1');

INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role_id, actif)
VALUES ('Bernard','Julien','prof2@smartcampus.fr',@hash,(SELECT id FROM roles WHERE nom='enseignant'),1);
INSERT INTO enseignants (utilisateur_id, grade, departement_id, ecole)
VALUES (LAST_INSERT_ID(),'Maître de conférences',(SELECT id FROM departements WHERE code='MATH'),'École 2');

INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role_id, actif)
VALUES ('Lefebvre','Sophie','prof3@smartcampus.fr',@hash,(SELECT id FROM roles WHERE nom='enseignant'),1);
INSERT INTO enseignants (utilisateur_id, grade, departement_id, ecole)
VALUES (LAST_INSERT_ID(),'Maître assistant',(SELECT id FROM departements WHERE code='GC'),'École 3');

INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role_id, actif)
VALUES ('Moreau','Pierre','prof4@smartcampus.fr',@hash,(SELECT id FROM roles WHERE nom='enseignant'),1);
INSERT INTO enseignants (utilisateur_id, grade, departement_id, ecole)
VALUES (LAST_INSERT_ID(),'ATER',(SELECT id FROM departements WHERE code='PHY'),'École 4');

INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role_id, actif)
VALUES ('Garcia','Laura','prof5@smartcampus.fr',@hash,(SELECT id FROM roles WHERE nom='enseignant'),1);
INSERT INTO enseignants (utilisateur_id, grade, departement_id, ecole)
VALUES (LAST_INSERT_ID(),'Vacataire',(SELECT id FROM departements WHERE code='INFO'),'École 1');

-- ------------------------------------------------------------
-- 300 ÉTUDIANTS (procédure stockée avec boucle)
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS seed_etudiants;
DELIMITER $$
CREATE PROCEDURE seed_etudiants()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE v_uid INT;
    DECLARE v_niveau VARCHAR(20);
    DECLARE v_tdcount INT;
    DECLARE v_td VARCHAR(10);
    DECLARE v_gid INT;
    DECLARE v_role INT;
    DECLARE v_nom VARCHAR(100);
    DECLARE v_prenom VARCHAR(100);

    SET v_role = (SELECT id FROM roles WHERE nom = 'etudiant');

    WHILE i <= 300 DO
        -- Niveau réparti sur 8 niveaux
        SET v_niveau = ELT(1 + (i % 8), 'ING1','ING2','ING3','L1','L2','L3','M1','M2');

        -- Nombre de groupes de TD disponibles pour ce niveau (cf. migration_v2.sql)
        SET v_tdcount = CASE v_niveau
                            WHEN 'ING1' THEN 4
                            WHEN 'ING2' THEN 4
                            WHEN 'ING3' THEN 2
                            WHEN 'L1'   THEN 2
                            WHEN 'L2'   THEN 2
                            WHEN 'L3'   THEN 2
                            ELSE 1
                        END;
        SET v_td  = CONCAT('TD0', 1 + (i % v_tdcount));
        SET v_gid = (SELECT id FROM groupes_td
                     WHERE niveau = v_niveau AND nom = v_td AND annee_scolaire = '2025-2026');

        -- Noms variés
        SET v_nom    = ELT(1 + (i % 15),
                           'Martin','Bernard','Thomas','Petit','Robert','Richard','Durand','Dubois',
                           'Moreau','Laurent','Simon','Michel','Lefebvre','Garcia','Roux');
        SET v_prenom = ELT(1 + (FLOOR(i / 15) % 15),
                           'Emma','Lucas','Léa','Hugo','Chloé','Louis','Manon','Jules',
                           'Camille','Nathan','Sarah','Tom','Inès','Théo','Lina');

        INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role_id, actif)
        VALUES (v_nom, v_prenom, CONCAT('etudiant', i, '@etu.smartcampus.fr'), @hash, v_role, 1);
        SET v_uid = LAST_INSERT_ID();

        INSERT INTO etudiants (utilisateur_id, numero_etudiant, departement_id, ecole, groupe_td_id, niveau, annee_scolaire)
        VALUES (v_uid,
                CONCAT('S26-', LPAD(i, 4, '0')),
                1 + (i % 4),                       -- département (ids 1..4)
                CONCAT('École ', 1 + (i % 4)),     -- école (attribut texte)
                v_gid,
                v_niveau,
                '2025-2026');

        SET i = i + 1;
    END WHILE;
END$$
DELIMITER ;

CALL seed_etudiants();
DROP PROCEDURE seed_etudiants;

-- ============================================================
-- Fin du jeu de données. Vérification rapide :
--   SELECT COUNT(*) FROM etudiants;     -- +300
--   SELECT COUNT(*) FROM enseignants;   -- +5
-- ============================================================
