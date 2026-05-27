-- ============================================================
-- SmartCampus — Migration v2
-- À exécuter sur une base SmartCampusDB existante (phpMyAdmin / CLI).
-- Ajoute : groupes de TD, association cours<->groupes,
--          un attribut texte `ecole` sur étudiants et enseignants,
--          le groupe de TD des étudiants, et des données de référence
--          (semestres S1/S2, salles).
-- Idempotent autant que possible (IF NOT EXISTS / INSERT IGNORE).
-- Gère aussi le cas où une version précédente (table `ecoles` + ecole_id)
-- aurait déjà été appliquée : on bascule alors vers la colonne texte.
-- ============================================================

USE SmartCampusDB;

-- ------------------------------------------------------------
-- 1) Table des groupes de TD (ex : ING1 - TD03)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS groupes_td (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom            VARCHAR(50) NOT NULL,        -- ex : 'TD03'
    niveau         VARCHAR(20) NOT NULL,        -- ex : 'ING1'
    annee_scolaire VARCHAR(9)  NOT NULL,        -- ex : '2025-2026'
    UNIQUE KEY uq_groupe_td (niveau, nom, annee_scolaire)
);

INSERT IGNORE INTO groupes_td (niveau, nom, annee_scolaire) VALUES
    ('ING1','TD01','2025-2026'), ('ING1','TD02','2025-2026'),
    ('ING1','TD03','2025-2026'), ('ING1','TD04','2025-2026'),
    ('ING2','TD01','2025-2026'), ('ING2','TD02','2025-2026'),
    ('ING2','TD03','2025-2026'), ('ING2','TD04','2025-2026'),
    ('ING3','TD01','2025-2026'), ('ING3','TD02','2025-2026'),
    ('L1','TD01','2025-2026'),   ('L1','TD02','2025-2026'),
    ('L2','TD01','2025-2026'),   ('L2','TD02','2025-2026'),
    ('L3','TD01','2025-2026'),   ('L3','TD02','2025-2026'),
    ('M1','TD01','2025-2026'),   ('M2','TD01','2025-2026');

-- ------------------------------------------------------------
-- 2) Nettoyage d'une éventuelle version précédente
--    (suppression des clés étrangères vers `ecoles` puis de la table)
-- ------------------------------------------------------------
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etudiants'
              AND CONSTRAINT_NAME = 'fk_etudiant_ecole');
SET @sql := IF(@fk > 0, 'ALTER TABLE etudiants DROP FOREIGN KEY fk_etudiant_ecole', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enseignants'
              AND CONSTRAINT_NAME = 'fk_enseignant_ecole');
SET @sql := IF(@fk > 0, 'ALTER TABLE enseignants DROP FOREIGN KEY fk_enseignant_ecole', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etudiants' AND COLUMN_NAME = 'ecole_id');
SET @sql := IF(@col > 0, 'ALTER TABLE etudiants DROP COLUMN ecole_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enseignants' AND COLUMN_NAME = 'ecole_id');
SET @sql := IF(@col > 0, 'ALTER TABLE enseignants DROP COLUMN ecole_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

DROP TABLE IF EXISTS ecoles;

-- ------------------------------------------------------------
-- 3) Étudiants : ajout ecole (texte) + groupe_td_id
-- ------------------------------------------------------------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etudiants' AND COLUMN_NAME = 'ecole');
SET @sql := IF(@col = 0,
    'ALTER TABLE etudiants ADD COLUMN ecole VARCHAR(100) NULL AFTER departement_id',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etudiants' AND COLUMN_NAME = 'groupe_td_id');
SET @sql := IF(@col = 0,
    'ALTER TABLE etudiants ADD COLUMN groupe_td_id INT UNSIGNED NULL AFTER ecole',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etudiants'
              AND CONSTRAINT_NAME = 'fk_etudiant_groupe_td');
SET @sql := IF(@fk = 0,
    'ALTER TABLE etudiants ADD CONSTRAINT fk_etudiant_groupe_td FOREIGN KEY (groupe_td_id) REFERENCES groupes_td(id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ------------------------------------------------------------
-- 4) Enseignants : ajout ecole (texte)
-- ------------------------------------------------------------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enseignants' AND COLUMN_NAME = 'ecole');
SET @sql := IF(@col = 0,
    'ALTER TABLE enseignants ADD COLUMN ecole VARCHAR(100) NULL AFTER departement_id',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ------------------------------------------------------------
-- 5) Association cours <-> groupes de TD
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cours_groupes (
    cours_id     INT UNSIGNED NOT NULL,
    groupe_td_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (cours_id, groupe_td_id),
    CONSTRAINT fk_cg_cours  FOREIGN KEY (cours_id)     REFERENCES cours(id)       ON DELETE CASCADE,
    CONSTRAINT fk_cg_groupe FOREIGN KEY (groupe_td_id) REFERENCES groupes_td(id)  ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- 6) Semestres S1 / S2 (corrige le cas « semestre vide »)
-- ------------------------------------------------------------
INSERT IGNORE INTO semestres (libelle, annee_scolaire, numero, date_debut, date_fin) VALUES
    ('S1 2025-2026', '2025-2026', 1, '2025-09-01', '2026-01-31'),
    ('S2 2025-2026', '2025-2026', 2, '2026-02-01', '2026-06-30');

-- ------------------------------------------------------------
-- 7) Salles de démonstration (pour la recherche + liste déroulante)
-- ------------------------------------------------------------
INSERT IGNORE INTO salles (nom, capacite, batiment, type_salle, disponible) VALUES
    ('A101', 40, 'Bâtiment A', 'cours', 1),
    ('A102', 40, 'Bâtiment A', 'cours', 1),
    ('B201', 30, 'Bâtiment B', 'tp', 1),
    ('B202', 30, 'Bâtiment B', 'tp', 1),
    ('Amphi Curie', 200, 'Bâtiment C', 'amphi', 1),
    ('Amphi Newton', 150, 'Bâtiment C', 'amphi', 1),
    ('S301', 25, 'Bâtiment S', 'seminaire', 1);

-- ============================================================
-- Fin de la migration v2
-- ============================================================
