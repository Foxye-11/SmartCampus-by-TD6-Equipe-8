-- ============================================================
-- SmartCampus — Jeu de données : 40 cours (+ 1 séance chacun)
-- À exécuter APRÈS migration_v2.sql ET seed_data.sql
-- (nécessite les 5 enseignants prof1..5, les semestres S1/S2 et les salles).
-- Chaque cours a une séance hebdomadaire de 1 à 2h, répartie de façon
-- à éviter les conflits de salle et d'enseignant.
-- ============================================================

USE SmartCampusDB;

DROP PROCEDURE IF EXISTS seed_cours;
DELIMITER $$
CREATE PROCEDURE seed_cours()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE p INT;            -- index prof 0..4
    DECLARE j INT;            -- index du cours pour ce prof 0..7
    DECLARE bloc INT;         -- 0 ou 1 (créneau matin)
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
    DECLARE v_int VARCHAR(150);

    WHILE i <= 40 DO
        SET p    = (i - 1) % 5;          -- 5 enseignants
        SET j    = (i - 1) DIV 5;        -- 0..7
        SET v_jour = 1 + (j % 5);        -- Lundi..Vendredi
        SET bloc = j DIV 5;              -- 0 ou 1
        SET v_hdeb = 8 + 2 * bloc;       -- 08h ou 10h
        SET v_dur  = 1 + (i % 2);        -- 1h ou 2h
        SET v_hd = MAKETIME(v_hdeb, 0, 0);
        SET v_hf = MAKETIME(v_hdeb + v_dur, 0, 0);

        -- Enseignant prof1..prof5 (créés dans seed_data.sql)
        SET v_eid = (SELECT e.id FROM enseignants e
                     JOIN utilisateurs u ON u.id = e.utilisateur_id
                     WHERE u.email = CONCAT('prof', p + 1, '@smartcampus.fr'));
        -- Salle distincte par enseignant (pas de conflit de salle sur un même créneau)
        SET v_sid = (SELECT id FROM salles
                     WHERE nom = ELT(p + 1, 'A101', 'A102', 'B201', 'B202', 'Amphi Curie'));
        -- Semestre alterné S1 / S2
        SET v_sem = (SELECT id FROM semestres
                     WHERE libelle = IF(i % 2 = 0, 'S2 2025-2026', 'S1 2025-2026'));
        SET v_dep  = 1 + (i % 4);        -- département (ids 1..4)
        SET v_code = CONCAT('C', LPAD(i, 3, '0'));
        SET v_int  = CONCAT(
            ELT(1 + (i % 20),
                'Algorithmique','Bases de données','Réseaux','Programmation Web','Mathématiques',
                'Physique','Statistiques','Systèmes d''exploitation','Intelligence artificielle','Cybersécurité',
                'Génie logiciel','Analyse','Probabilités','Mécanique','Électronique',
                'Anglais','Gestion de projet','Cloud computing','Data Science','Architecture logicielle'),
            ' (', v_code, ')');

        INSERT INTO cours (code, intitule, credits, capacite_max, semestre_id, departement_id, enseignant_id, description)
        VALUES (v_code, v_int, 3 + (i % 4), 30 + 10 * (i % 2), v_sem, v_dep, v_eid, NULL);
        SET v_cid = LAST_INSERT_ID();

        INSERT INTO sessions_cours (cours_id, salle_id, jour_semaine, heure_debut, heure_fin, date_specifique)
        VALUES (v_cid, v_sid, v_jour, v_hd, v_hf, NULL);

        SET i = i + 1;
    END WHILE;
END$$
DELIMITER ;

CALL seed_cours();
DROP PROCEDURE seed_cours;

-- ============================================================
-- Vérification :
--   SELECT COUNT(*) FROM cours;            -- +40
--   SELECT COUNT(*) FROM sessions_cours;   -- +40
-- ============================================================
