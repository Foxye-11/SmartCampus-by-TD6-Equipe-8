-- ============================================================
-- SmartCampus — Jeu de données de démonstration (à exécuter UNE fois)
-- À lancer APRÈS ScriptSQL.txt (qui crée la base et les tables).
-- Contenu :
--   • 5 enseignants
--   • 300 étudiants (répartis sur les 8 niveaux, leurs TD, 4 écoles, 4 départements)
--   • 40 cours (avec matière, 1 séance hebdomadaire de 1 à 2 h, affectation TD)
-- Mot de passe de TOUS les comptes : « 1234 »
-- ============================================================

USE SmartCampusDB;

-- Hash bcrypt correspondant au mot de passe « 1234 »
SET @hash := '$2b$10$Y5JwhAqPhxDQ7R0qUqOiKuWFEUAXFbodcnX34DD34yqfTF5OI0ZS.';

-- ------------------------------------------------------------
-- 1) 5 ENSEIGNANTS
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
-- 2) 300 ÉTUDIANTS
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
        SET v_niveau = ELT(1 + (i % 8), 'ING1','ING2','ING3','L1','L2','L3','M1','M2');

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
                1 + (i % 4),
                CONCAT('École ', 1 + (i % 4)),
                v_gid,
                v_niveau,
                '2025-2026');

        SET i = i + 1;
    END WHILE;
END$$
DELIMITER ;

CALL seed_etudiants();
DROP PROCEDURE seed_etudiants;

-- ------------------------------------------------------------
-- 3) 40 COURS (matière + 1 séance hebdo de 1 à 2 h + groupe(s) TD)
--    Règle TD : amphi -> 4 TD du même niveau (ING1 ou ING2)
--               salle normale -> 1 seul TD
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS seed_cours;
DELIMITER $$
CREATE PROCEDURE seed_cours()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE p INT;
    DECLARE j INT;
    DECLARE bloc INT;
    DECLARE v_jour INT;
    DECLARE v_hdeb INT;
    DECLARE v_dur INT;
    DECLARE v_hd TIME;
    DECLARE v_hf TIME;
    DECLARE v_cid INT;
    DECLARE v_eid INT;
    DECLARE v_sid INT;
    DECLARE v_sem INT;
    DECLARE v_dep INT;
    DECLARE v_code VARCHAR(20);
    DECLARE v_mat VARCHAR(100);
    DECLARE v_int VARCHAR(150);
    DECLARE v_niv VARCHAR(20);

    WHILE i <= 40 DO
        SET p    = (i - 1) % 5;
        SET j    = (i - 1) DIV 5;
        SET v_jour = 1 + (j % 5);
        SET bloc = j DIV 5;
        SET v_hdeb = 8 + 2 * bloc;
        SET v_dur  = 1 + (i % 2);
        SET v_hd = MAKETIME(v_hdeb, 0, 0);
        SET v_hf = MAKETIME(v_hdeb + v_dur, 0, 0);

        SET v_eid = (SELECT e.id FROM enseignants e
                     JOIN utilisateurs u ON u.id = e.utilisateur_id
                     WHERE u.email = CONCAT('prof', p + 1, '@smartcampus.fr'));
        SET v_sid = (SELECT id FROM salles
                     WHERE nom = ELT(p + 1, 'A101', 'A102', 'B201', 'B202', 'Amphi Curie'));
        SET v_sem = (SELECT id FROM semestres
                     WHERE libelle = IF(i % 2 = 0, 'S2 2025-2026', 'S1 2025-2026'));
        SET v_dep  = 1 + (i % 4);
        SET v_code = CONCAT('C', LPAD(i, 3, '0'));
        SET v_mat  = ELT(1 + (i % 20),
                         'Algorithmique','Bases de données','Réseaux','Programmation Web','Mathématiques',
                         'Physique','Statistiques','Systèmes d''exploitation','Intelligence artificielle','Cybersécurité',
                         'Génie logiciel','Analyse','Probabilités','Mécanique','Électronique',
                         'Anglais','Gestion de projet','Cloud computing','Data Science','Architecture logicielle');
        SET v_int  = CONCAT(v_mat, ' (', v_code, ')');

        INSERT INTO cours (code, intitule, matiere, credits, capacite_max, semestre_id, departement_id, enseignant_id, description)
        VALUES (v_code, v_int, v_mat, 3 + (i % 4), 30 + 10 * (i % 2), v_sem, v_dep, v_eid, NULL);
        SET v_cid = LAST_INSERT_ID();

        INSERT INTO sessions_cours (cours_id, salle_id, jour_semaine, heure_debut, heure_fin, date_specifique)
        VALUES (v_cid, v_sid, v_jour, v_hd, v_hf, NULL);

        -- Affectation TD
        IF p = 4 THEN
            -- Amphi : 4 TD du même niveau (ING1 ou ING2)
            SET v_niv = IF(i % 2 = 0, 'ING2', 'ING1');
            INSERT INTO cours_groupes (cours_id, groupe_td_id)
            SELECT v_cid, id FROM groupes_td
            WHERE niveau = v_niv AND annee_scolaire = '2025-2026'
              AND nom IN ('TD01','TD02','TD03','TD04');
        ELSE
            -- Autre salle : 1 seul TD (TD01 d'un niveau cyclique)
            SET v_niv = ELT(1 + (i % 6), 'ING1','ING2','ING3','L1','L2','L3');
            INSERT INTO cours_groupes (cours_id, groupe_td_id)
            SELECT v_cid, id FROM groupes_td
            WHERE niveau = v_niv AND annee_scolaire = '2025-2026' AND nom = 'TD01';
        END IF;

        SET i = i + 1;
    END WHILE;
END$$
DELIMITER ;

CALL seed_cours();
DROP PROCEDURE seed_cours;

-- ============================================================
-- Vérifications :
--   SELECT COUNT(*) FROM enseignants;     -- 5
--   SELECT COUNT(*) FROM etudiants;       -- 300
--   SELECT COUNT(*) FROM cours;           -- 40
--   SELECT COUNT(*) FROM sessions_cours;  -- 40
--   SELECT COUNT(*) FROM cours_groupes;   -- 40 cours × (4 ou 1)
-- ============================================================
