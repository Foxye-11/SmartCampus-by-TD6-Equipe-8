<?php
// controllers/CoursController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/EmploiDuTempsController.php';

class CoursController {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    public function lister(?int $semestreId = null, ?int $departementId = null, ?int $groupeTdId = null): array {
        $sql = 'SELECT c.id, c.code, c.intitule, c.credits, c.capacite_max,
                       c.notes_verrouillees,
                       s.libelle AS semestre, s.id AS semestre_id,
                       d.nom AS departement,
                       CONCAT(u.prenom," ",u.nom) AS enseignant,
                       e.id AS enseignant_id,
                       (SELECT COUNT(*) FROM inscriptions i
                        WHERE i.cours_id = c.id AND i.statut="active") AS inscrits,
                       (SELECT GROUP_CONCAT(CONCAT(g.niveau, " ", g.nom) ORDER BY g.niveau, g.nom SEPARATOR ", ")
                        FROM cours_groupes cg JOIN groupes_td g ON g.id = cg.groupe_td_id
                        WHERE cg.cours_id = c.id) AS groupes
                FROM cours c
                JOIN semestres s ON s.id = c.semestre_id
                LEFT JOIN departements d ON d.id = c.departement_id
                LEFT JOIN enseignants e ON e.id = c.enseignant_id
                LEFT JOIN utilisateurs u ON u.id = e.utilisateur_id
                WHERE 1=1';
        $params = [];
        if ($semestreId) { $sql .= ' AND c.semestre_id = :sid'; $params[':sid'] = $semestreId; }
        if ($departementId) { $sql .= ' AND c.departement_id = :did'; $params[':did'] = $departementId; }
        if ($groupeTdId) {
            $sql .= ' AND c.id IN (SELECT cours_id FROM cours_groupes WHERE groupe_td_id = :gtd)';
            $params[':gtd'] = $groupeTdId;
        }
        $sql .= ' ORDER BY s.libelle, c.intitule';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtenir(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, s.libelle AS semestre, d.nom AS departement,
                    CONCAT(u.prenom," ",u.nom) AS enseignant,
                    e.id AS enseignant_id
             FROM cours c
             JOIN semestres s ON s.id = c.semestre_id
             LEFT JOIN departements d ON d.id = c.departement_id
             LEFT JOIN enseignants e ON e.id = c.enseignant_id
             LEFT JOIN utilisateurs u ON u.id = e.utilisateur_id
             WHERE c.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function creer(array $data): array {
        Auth::exiger('admin');
        $erreurs = $this->valider($data);
        if (!empty($erreurs)) return ['succes' => false, 'erreurs' => $erreurs];

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO cours (code, intitule, credits, capacite_max, semestre_id,
                                    departement_id, enseignant_id, description)
                 VALUES (:code, :intitule, :credits, :capacite_max, :semestre_id,
                         :departement_id, :enseignant_id, :description)'
            );
            $stmt->execute([
                ':code'           => trim($data['code']),
                ':intitule'       => trim($data['intitule']),
                ':credits'        => (int)($data['credits'] ?? 3),
                ':capacite_max'   => (int)($data['capacite_max'] ?? 30),
                ':semestre_id'    => (int)$data['semestre_id'],
                ':departement_id' => !empty($data['departement_id']) ? (int)$data['departement_id'] : null,
                ':enseignant_id'  => !empty($data['enseignant_id']) ? (int)$data['enseignant_id'] : null,
                ':description'    => isset($data['description']) ? trim($data['description']) : null,
            ]);
            $coursId = (int)$this->pdo->lastInsertId();

            // 1) Inscription des groupes de TD (classe entière) + étudiants individuels
            $this->inscrireGroupesEtEtudiants($coursId, $data);

            // 2) Planification optionnelle d'une première séance (salle + date/jour + heures)
            //    Les étudiants étant déjà inscrits, la détection de conflits les prend en compte.
            $avertissement = null;
            if (!empty($data['heure_debut']) && !empty($data['heure_fin'])
                && (!empty($data['date_specifique']) || !empty($data['jour_semaine']))) {

                $jour = !empty($data['jour_semaine'])
                    ? (int)$data['jour_semaine']
                    : (int)date('N', strtotime($data['date_specifique'])); // 1=Lundi … 7=Dimanche

                $edt = new EmploiDuTempsController();
                $sessionData = [
                    'cours_id'        => $coursId,
                    'jour_semaine'    => $jour,
                    'heure_debut'     => $data['heure_debut'],
                    'heure_fin'       => $data['heure_fin'],
                    'salle_id'        => !empty($data['salle_id']) ? (int)$data['salle_id'] : null,
                    'date_specifique' => !empty($data['date_specifique']) ? $data['date_specifique'] : null,
                ];
                $res = $edt->creerSession($sessionData);
                if (!$res['succes']) {
                    // On n'annule pas le cours : la séance pourra être ajoutée plus tard
                    // depuis l'emploi du temps. On remonte un avertissement.
                    $avertissement = 'Cours créé, mais la séance n\'a pas pu être planifiée : '
                                     . implode(' ', $res['erreurs']);
                }
            }

            $this->pdo->commit();
            $reponse = ['succes' => true, 'id' => $coursId];
            if ($avertissement) $reponse['avertissement'] = $avertissement;
            return $reponse;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['succes' => false, 'erreurs' => ['Erreur lors de la création du cours.']];
        }
    }

    // -----------------------------------------------
    // Inscrit les groupes de TD (toute la classe) et les étudiants
    // individuels passés au cours. Idempotent (INSERT IGNORE).
    // -----------------------------------------------
    private function inscrireGroupesEtEtudiants(int $coursId, array $data): void {
        $groupes  = $data['groupes_td'] ?? [];   // tableau d'IDs de groupes
        $etudiants = $data['etudiants'] ?? [];    // tableau d'IDs d'étudiants

        // Groupes de TD : enregistre l'association puis inscrit chaque étudiant actif du groupe
        foreach ($groupes as $gid) {
            $gid = (int)$gid;
            if ($gid <= 0) continue;
            $this->pdo->prepare(
                'INSERT IGNORE INTO cours_groupes (cours_id, groupe_td_id) VALUES (:c, :g)'
            )->execute([':c' => $coursId, ':g' => $gid]);

            $this->pdo->prepare(
                'INSERT IGNORE INTO inscriptions (etudiant_id, cours_id, statut)
                 SELECT et.id, :c, "active"
                 FROM etudiants et
                 JOIN utilisateurs u ON u.id = et.utilisateur_id
                 WHERE et.groupe_td_id = :g AND u.actif = 1'
            )->execute([':c' => $coursId, ':g' => $gid]);
        }

        // Étudiants individuels
        foreach ($etudiants as $eid) {
            $eid = (int)$eid;
            if ($eid <= 0) continue;
            $this->pdo->prepare(
                'INSERT IGNORE INTO inscriptions (etudiant_id, cours_id, statut)
                 VALUES (:e, :c, "active")'
            )->execute([':e' => $eid, ':c' => $coursId]);
        }
    }

    // Groupes de TD associés à un cours (pour l'édition / affichage)
    public function groupesDuCours(int $coursId): array {
        $stmt = $this->pdo->prepare(
            'SELECT g.id, CONCAT(g.niveau, " ", g.nom) AS libelle
             FROM cours_groupes cg JOIN groupes_td g ON g.id = cg.groupe_td_id
             WHERE cg.cours_id = :c ORDER BY g.niveau, g.nom'
        );
        $stmt->execute([':c' => $coursId]);
        return $stmt->fetchAll();
    }

    public function modifier(int $id, array $data): array {
        Auth::exiger('admin');
        if (!$this->obtenir($id)) return ['succes' => false, 'erreurs' => ['Cours introuvable.']];
        $erreurs = $this->valider($data, $id);
        if (!empty($erreurs)) return ['succes' => false, 'erreurs' => $erreurs];

        $stmt = $this->pdo->prepare(
            'UPDATE cours SET code=:code, intitule=:intitule, credits=:credits,
                              capacite_max=:capacite_max, semestre_id=:semestre_id,
                              departement_id=:departement_id, enseignant_id=:enseignant_id,
                              description=:description
             WHERE id=:id'
        );
        $stmt->execute([
            ':code'           => trim($data['code']),
            ':intitule'       => trim($data['intitule']),
            ':credits'        => (int)($data['credits'] ?? 3),
            ':capacite_max'   => (int)($data['capacite_max'] ?? 30),
            ':semestre_id'    => (int)$data['semestre_id'],
            ':departement_id' => isset($data['departement_id']) ? (int)$data['departement_id'] : null,
            ':enseignant_id'  => isset($data['enseignant_id']) ? (int)$data['enseignant_id'] : null,
            ':description'    => isset($data['description']) ? trim($data['description']) : null,
            ':id'             => $id,
        ]);
        return ['succes' => true];
    }

    public function supprimer(int $id): array {
        Auth::exiger('admin');
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS nb FROM inscriptions WHERE cours_id=:id AND statut="active"');
        $stmt->execute([':id' => $id]);
        if ($stmt->fetch()['nb'] > 0)
            return ['succes' => false, 'erreurs' => ['Des étudiants sont inscrits à ce cours.']];
        $this->pdo->prepare('DELETE FROM cours WHERE id=:id')->execute([':id' => $id]);
        return ['succes' => true];
    }

    public function semestres(): array {
        return $this->pdo->query('SELECT * FROM semestres ORDER BY annee_scolaire, numero')->fetchAll();
    }

    public function departements(): array {
        return $this->pdo->query('SELECT * FROM departements ORDER BY nom')->fetchAll();
    }

    public function sessionsParCours(int $coursId): array {
        $stmt = $this->pdo->prepare(
            'SELECT sc.*, s.nom AS salle_nom, s.batiment
             FROM sessions_cours sc
             LEFT JOIN salles s ON s.id = sc.salle_id
             WHERE sc.cours_id = :cid
             ORDER BY sc.jour_semaine, sc.heure_debut'
        );
        $stmt->execute([':cid' => $coursId]);
        return $stmt->fetchAll();
    }

    private function valider(array $data, ?int $excludeId = null): array {
        $erreurs = [];
        if (empty($data['code']) || strlen(trim($data['code'])) < 2)
            $erreurs[] = 'Code cours requis (min 2 caractères).';
        if (empty($data['intitule']) || strlen(trim($data['intitule'])) < 3)
            $erreurs[] = 'Intitulé requis (min 3 caractères).';
        if (empty($data['semestre_id']))
            $erreurs[] = 'Semestre requis.';
        // Unicité du code
        if (!empty($data['code'])) {
            $stmt = $this->pdo->prepare('SELECT id FROM cours WHERE code=:code' . ($excludeId ? ' AND id!=:id' : ''));
            $params = [':code' => trim($data['code'])];
            if ($excludeId) $params[':id'] = $excludeId;
            $stmt->execute($params);
            if ($stmt->fetch()) $erreurs[] = 'Ce code cours existe déjà.';
        }
        return $erreurs;
    }
}