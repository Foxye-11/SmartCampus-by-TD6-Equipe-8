<?php
// controllers/CoursController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';

class CoursController {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    public function lister(?int $semestreId = null, ?int $departementId = null): array {
        $sql = 'SELECT c.id, c.code, c.intitule, c.credits, c.capacite_max,
                       c.notes_verrouillees,
                       s.libelle AS semestre, s.id AS semestre_id,
                       d.nom AS departement,
                       CONCAT(u.prenom," ",u.nom) AS enseignant,
                       e.id AS enseignant_id,
                       (SELECT COUNT(*) FROM inscriptions i
                        WHERE i.cours_id = c.id AND i.statut="active") AS inscrits
                FROM cours c
                JOIN semestres s ON s.id = c.semestre_id
                LEFT JOIN departements d ON d.id = c.departement_id
                LEFT JOIN enseignants e ON e.id = c.enseignant_id
                LEFT JOIN utilisateurs u ON u.id = e.utilisateur_id
                WHERE 1=1';
        $params = [];
        if ($semestreId) { $sql .= ' AND c.semestre_id = :sid'; $params[':sid'] = $semestreId; }
        if ($departementId) { $sql .= ' AND c.departement_id = :did'; $params[':did'] = $departementId; }
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
            ':departement_id' => isset($data['departement_id']) ? (int)$data['departement_id'] : null,
            ':enseignant_id'  => isset($data['enseignant_id']) ? (int)$data['enseignant_id'] : null,
            ':description'    => isset($data['description']) ? trim($data['description']) : null,
        ]);
        return ['succes' => true, 'id' => (int)$this->pdo->lastInsertId()];
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