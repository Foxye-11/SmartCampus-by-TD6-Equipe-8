-- ============================================================
-- SmartCampus — Jeu de données de démonstration (à exécuter UNE fois)
-- À lancer APRÈS ScriptSQL.txt (qui crée la base et les tables).
-- Contenu :
--   • 5 enseignants
--   • 300 étudiants (répartis sur les 8 niveaux, leurs TD, 4 écoles, 4 départements)
--   • 40 cours (avec matière + créneau hebdomadaire « modèle »)
--   • Sessions de cours **datées** sur toute l'année scolaire 2025/2026
--     (une séance par semaine du semestre, du 1er sept. 2025 au 30 juin 2026)
--   • Inscriptions : chaque étudiant est inscrit à tous les cours visant son TD
--   • Notes (TP, contrôle, examen) avec valeurs pseudo-aléatoires
--   • Présences générées pour toutes les séances passées (avant aujourd'hui)
--   • Messages de démonstration entre enseignants et étudiants
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
-- 3) 40 COURS + SESSIONS DATÉES SUR TOUTE L'ANNÉE 2025-2026
--    Pour chaque cours, on crée :
--      - une fiche cours (matière, semestre, enseignant, capacité, …)
--      - une séance HEBDOMADAIRE datée par semaine du semestre
--        (date_specifique = jeudi 12/09/2025, jeudi 19/09/2025, …)
--      - l'affectation des TD (1 ou 4 selon amphi / salle classique)
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
    DECLARE v_date DATE;
    DECLARE v_sem_debut DATE;
    DECLARE v_sem_fin DATE;

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

        -- Génération des séances datées sur tout le semestre
        SELECT date_debut, date_fin INTO v_sem_debut, v_sem_fin
        FROM semestres WHERE id = v_sem;

        SET v_date = v_sem_debut;
        WHILE v_date <= v_sem_fin DO
            -- WEEKDAY : 0 = lundi ... 6 = dimanche ; jour_semaine : 1 = lundi ... 7 = dimanche
            IF (WEEKDAY(v_date) + 1) = v_jour THEN
                INSERT INTO sessions_cours (cours_id, salle_id, jour_semaine, heure_debut, heure_fin, date_specifique)
                VALUES (v_cid, v_sid, v_jour, v_hd, v_hf, v_date);
            END IF;
            SET v_date = DATE_ADD(v_date, INTERVAL 1 DAY);
        END WHILE;

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

-- ------------------------------------------------------------
-- 4) INSCRIPTIONS
--    Chaque étudiant est automatiquement inscrit à tous les cours
--    dont l'un des groupes TD correspond au sien.
-- ------------------------------------------------------------
INSERT INTO inscriptions (etudiant_id, cours_id, date_inscription, statut)
SELECT DISTINCT et.id, c.id, '2025-09-01 08:00:00', 'active'
FROM etudiants et
JOIN cours_groupes cg ON cg.groupe_td_id = et.groupe_td_id
JOIN cours c          ON c.id = cg.cours_id
WHERE et.annee_scolaire = '2025-2026';

-- ------------------------------------------------------------
-- 5) NOTES
--    Pour chaque inscription, on genere 2 notes "passees" (TP +
--    controle continu) si la date de creation est anterieure a
--    aujourd'hui, plus 1 note d'examen lorsque le semestre est
--    termine. Les valeurs sont pseudo-aleatoires entre 7 et 19.
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS seed_notes;
DELIMITER $$
CREATE PROCEDURE seed_notes()
BEGIN
    -- TP (note de TP, coef 1, semaine ~4 du semestre)
    INSERT INTO notes (inscription_id, type_evaluation, valeur, coefficient, commentaire, date_saisie)
    SELECT i.id, 'tp',
           ROUND(8 + RAND() * 11, 2),
           1.00,
           ELT(1 + FLOOR(RAND() * 5),
               'Travail soigné.','Peut mieux faire.','Bonne progression.',
               'Manque de rigueur.', NULL),
           DATE_ADD(s.date_debut, INTERVAL 30 DAY)
    FROM inscriptions i
    JOIN cours c     ON c.id = i.cours_id
    JOIN semestres s ON s.id = c.semestre_id
    WHERE i.statut = 'active'
      AND DATE_ADD(s.date_debut, INTERVAL 30 DAY) < CURDATE();

    -- Contrôle continu (coef 1, semaine ~8 du semestre)
    INSERT INTO notes (inscription_id, type_evaluation, valeur, coefficient, commentaire, date_saisie)
    SELECT i.id, 'controle',
           ROUND(8 + RAND() * 11, 2),
           1.00,
           NULL,
           DATE_ADD(s.date_debut, INTERVAL 60 DAY)
    FROM inscriptions i
    JOIN cours c     ON c.id = i.cours_id
    JOIN semestres s ON s.id = c.semestre_id
    WHERE i.statut = 'active'
      AND DATE_ADD(s.date_debut, INTERVAL 60 DAY) < CURDATE();

    -- Examen final (coef 2, date_fin du semestre) si semestre terminé
    INSERT INTO notes (inscription_id, type_evaluation, valeur, coefficient, commentaire, date_saisie)
    SELECT i.id, 'examen',
           ROUND(7 + RAND() * 12, 2),
           2.00,
           NULL,
           s.date_fin
    FROM inscriptions i
    JOIN cours c     ON c.id = i.cours_id
    JOIN semestres s ON s.id = c.semestre_id
    WHERE i.statut = 'active'
      AND s.date_fin < CURDATE();

    -- Projet (coef 2) sur quelques cours seulement (1 sur 4)
    INSERT INTO notes (inscription_id, type_evaluation, valeur, coefficient, commentaire, date_saisie)
    SELECT i.id, 'projet',
           ROUND(10 + RAND() * 9, 2),
           2.00,
           ELT(1 + FLOOR(RAND() * 4),
               'Bon projet.', 'Soutenance solide.', 'À approfondir.', NULL),
           DATE_ADD(s.date_debut, INTERVAL 90 DAY)
    FROM inscriptions i
    JOIN cours c     ON c.id = i.cours_id
    JOIN semestres s ON s.id = c.semestre_id
    WHERE i.statut = 'active'
      AND DATE_ADD(s.date_debut, INTERVAL 90 DAY) < CURDATE()
      AND (c.id % 4) = 0;
END$$
DELIMITER ;

CALL seed_notes();
DROP PROCEDURE seed_notes;

-- ------------------------------------------------------------
-- 6) PRÉSENCES
--    Pour chaque inscription, on génère une présence pour chaque
--    séance passée du cours. Statut aléatoire :
--      - 80 % présent
--      - 12 % absent
--      -  4 % retard
--      -  4 % excusé
-- ------------------------------------------------------------
INSERT INTO presences (inscription_id, session_id, statut, date_enregistrement)
SELECT i.id,
       sc.id,
       CASE
         WHEN RAND() < 0.80 THEN 'present'
         WHEN RAND() < 0.60 THEN 'absent'
         WHEN RAND() < 0.50 THEN 'retard'
         ELSE 'excuse'
       END,
       sc.date_specifique
FROM inscriptions i
JOIN sessions_cours sc ON sc.cours_id = i.cours_id
WHERE i.statut = 'active'
  AND sc.date_specifique IS NOT NULL
  AND sc.date_specifique < CURDATE();

-- ------------------------------------------------------------
-- 7) MESSAGES
--    Quelques échanges démo : enseignants → étudiants, étudiants →
--    enseignants, et un message d'admin à tout le monde.
-- ------------------------------------------------------------
-- 7a) Messages d'enseignants vers leurs étudiants (50)
INSERT INTO messages (expediteur_id, destinataire_id, sujet, contenu, lu, date_envoi)
SELECT * FROM (
    SELECT
        e.utilisateur_id AS expediteur_id,
        et.utilisateur_id AS destinataire_id,
        ELT(1 + (et.id % 6),
            'Rendu attendu',
            'Question sur le TP',
            'Rappel cours',
            'Note publiée',
            'Convocation',
            'Suivi pédagogique') AS sujet,
        ELT(1 + (et.id % 6),
            'Bonjour, merci de me rendre le TP avant la fin de la semaine.',
            'Pourriez-vous reformuler votre question, elle n''était pas claire ?',
            'N''oubliez pas le cours de demain à 10h, salle A101.',
            'Votre note a été publiée dans votre espace, n''hésitez pas à me contacter en cas de question.',
            'Vous êtes convoqué(e) à un entretien individuel vendredi à 14h.',
            'Je vous propose un point pédagogique en début de semaine prochaine.') AS contenu,
        IF(et.id % 3 = 0, 0, 1) AS lu,
        DATE_SUB(CURDATE(), INTERVAL (et.id % 30) DAY) AS date_envoi
    FROM etudiants et
    JOIN cours_groupes cg ON cg.groupe_td_id = et.groupe_td_id
    JOIN cours c          ON c.id = cg.cours_id
    JOIN enseignants e    ON e.id = c.enseignant_id
    WHERE et.id <= 50
    ORDER BY et.id, e.id
    LIMIT 50
) m;

-- 7b) Messages d'étudiants vers leurs enseignants (50)
INSERT INTO messages (expediteur_id, destinataire_id, sujet, contenu, lu, date_envoi)
SELECT * FROM (
    SELECT
        et.utilisateur_id AS expediteur_id,
        e.utilisateur_id  AS destinataire_id,
        ELT(1 + (et.id % 5),
            'Question sur le cours',
            'Demande d''absence',
            'Précision sur le TP',
            'Rendez-vous possible ?',
            'Lien support de cours') AS sujet,
        ELT(1 + (et.id % 5),
            'Bonjour, j''ai une question concernant la notion vue lors du dernier cours, pourriez-vous m''aider ?',
            'Bonjour, je vais être absent(e) la semaine prochaine pour raison médicale, je vous transmettrai un justificatif.',
            'Bonjour, pourriez-vous préciser ce qui est attendu pour le rendu du TP ?',
            'Bonjour, serait-il possible de prendre un rendez-vous pour échanger sur mes notes ?',
            'Bonjour, le lien vers le support de cours semble ne pas fonctionner, pouvez-vous le renvoyer ?') AS contenu,
        IF(et.id % 4 = 0, 0, 1) AS lu,
        DATE_SUB(CURDATE(), INTERVAL ((et.id * 2) % 25) DAY) AS date_envoi
    FROM etudiants et
    JOIN cours_groupes cg ON cg.groupe_td_id = et.groupe_td_id
    JOIN cours c          ON c.id = cg.cours_id
    JOIN enseignants e    ON e.id = c.enseignant_id
    WHERE et.id BETWEEN 51 AND 100
    ORDER BY et.id, e.id
    LIMIT 50
) m;

-- 7c) Annonce de l'admin à tous les enseignants
INSERT INTO messages (expediteur_id, destinataire_id, sujet, contenu, lu, date_envoi)
SELECT
    (SELECT id FROM utilisateurs WHERE email = 'admin@smartcampus.fr'),
    e.utilisateur_id,
    'Verrouillage des notes du S1',
    'Bonjour, pour information les notes du semestre 1 2025-2026 seront verrouillées le 15 février 2026. Merci de finaliser vos saisies avant cette date.',
    0,
    '2026-02-01 09:00:00'
FROM enseignants e;

-- ============================================================
-- Vérifications :
--   SELECT COUNT(*) FROM enseignants;     -- 5
--   SELECT COUNT(*) FROM etudiants;       -- 300
--   SELECT COUNT(*) FROM cours;           -- 40
--   SELECT COUNT(*) FROM sessions_cours;  -- ~600 (40 cours × ~15 sem.)
--   SELECT COUNT(*) FROM inscriptions;    -- plusieurs milliers
--   SELECT COUNT(*) FROM notes;           -- ~3 notes × inscriptions
--   SELECT COUNT(*) FROM presences;       -- ~ inscriptions × séances passées
--   SELECT COUNT(*) FROM messages;        -- ~105
-- ============================================================
