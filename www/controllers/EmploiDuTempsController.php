<?php
// controllers/EmploiDuTempsController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';

class EmploiDuTempsController {

    private PDO $pdo;

    public function __construct() { $this->pdo = getDB(); }

    public function emploiEtudiant(int $etudiantId): array {
        $stmt = $this->pdo->prepare(
            'SELECT sc.id AS session_id, sc.jour_semaine, sc.heure_debut, sc.heure_fin,
                    sc.date_specifique,
                    c.id AS cours_id, c.code, c.intitule AS cours,
                    s.nom AS salle, s.batiment,
                    CONCAT(u.prenom, " ", u.nom) AS enseignant
             FROM sessions_cours sc
             JOIN cours c ON c.id = sc.cours_id
             JOIN inscriptions i ON i.cours_id = c.id
             LEFT JOIN salles s ON s.id = sc.salle_id
             LEFT JOIN enseignants e ON e.id = c.enseignant_id
             LEFT JOIN utilisateurs u ON u.id = e.utilisateur_id
             WHERE i.etudiant_id = :eid AND i.statut = "active"
             ORDER BY sc.jour_semaine, sc.heure_debut'
        );
        $stmt->execute([':eid' => $etudiantId]);
        return $stmt->fetchAll();
    }

    public function emploiEnseignant(int $enseignantId): array {
        $stmt = $this->pdo->prepare(
            'SELECT sc.id AS session_id, sc.jour_semaine, sc.heure_debut, sc.heure_fin,
                    sc.date_specifique,
                    c.id AS cours_id, c.code, c.intitule AS cours,
                    s.nom AS salle, s.batiment,
                    (SELECT COUNT(*) FROM inscriptions i
                     WHERE i.cours_id = c.id AND i.statut = "active") AS inscrits
             FROM sessions_cours sc
             JOIN cours c ON c.id = sc.cours_id
             LEFT JOIN salles s ON s.id = sc.salle_id
             WHERE c.enseignant_id = :eid
             ORDER BY sc.jour_semaine, sc.heure_debut'
        );
        $stmt->execute([':eid' => $enseignantId]);
        return $stmt->fetchAll();
    }

    public function emploiComplet(): array {
        Auth::exiger('admin');
        $stmt = $this->pdo->query(
            'SELECT sc.id AS session_id, sc.jour_semaine, sc.heure_debut, sc.heure_fin,
                    sc.date_specifique,
                    c.code, c.intitule AS cours,
                    s.nom AS salle, s.batiment,
                    CONCAT(u.prenom, " ", u.nom) AS enseignant
             FROM sessions_cours sc
             JOIN cours c ON c.id = sc.cours_id
             LEFT JOIN salles s ON s.id = sc.salle_id
             LEFT JOIN enseignants e ON e.id = c.enseignant_id
             LEFT JOIN utilisateurs u ON u.id = e.utilisateur_id
             ORDER BY sc.jour_semaine, sc.heure_debut'
        );
        return $stmt->fetchAll();
    }

    public function creerSession(array $data): array {
        Auth::exiger('admin');
        $erreurs = $this->validerSession($data);
        if (!empty($erreurs)) return ['succes' => false, 'erreurs' => $erreurs];

        if (!empty($data['salle_id'])) {
            $conflit = $this->verifierConflitSalle(
                (int)$data['salle_id'],
                (int)$data['jour_semaine'],
                $data['heure_debut'],
                $data['heure_fin']
            );
            if ($conflit) return ['succes' => false, 'erreurs' => ['Conflit de salle : ' . $conflit]];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions_cours (cours_id, jour_semaine, heure_debut, heure_fin,
                                         salle_id, date_specifique)
             VALUES (:cours_id, :jour, :debut, :fin, :salle_id, :date)'
        );
        $stmt->execute([
            ':cours_id'  => (int)$data['cours_id'],
            ':jour'      => (int)$data['jour_semaine'],
            ':debut'     => $data['heure_debut'],
            ':fin'       => $data['heure_fin'],
            ':salle_id'  => !empty($data['salle_id']) ? (int)$data['salle_id'] : null,
            ':date'      => $data['date_specifique'] ?? null,
        ]);
        return ['succes' => true, 'id' => (int)$this->pdo->lastInsertId()];
    }

    public function supprimerSession(int $id): array {
        Auth::exiger('admin');
        $this->pdo->prepare('DELETE FROM sessions_cours WHERE id = :id')
                  ->execute([':id' => $id]);
        return ['succes' => true];
    }

    private function verifierConflitSalle(int $salleId, int $jour, string $debut, string $fin): ?string {
        $stmt = $this->pdo->prepare(
            'SELECT c.intitule FROM sessions_cours sc
             JOIN cours c ON c.id = sc.cours_id
             WHERE sc.salle_id = :sid AND sc.jour_semaine = :jour
               AND sc.heure_debut < :fin AND sc.heure_fin > :debut
             LIMIT 1'
        );
        $stmt->execute([':sid' => $salleId, ':jour' => $jour, ':debut' => $debut, ':fin' => $fin]);
        $row = $stmt->fetch();
        return $row ? $row['intitule'] : null;
    }

    private function validerSession(array $data): array {
        $erreurs = [];
        if (empty($data['cours_id'])) $erreurs[] = 'Cours requis.';
        if (!isset($data['jour_semaine']) || $data['jour_semaine'] < 1 || $data['jour_semaine'] > 7)
            $erreurs[] = 'Jour invalide (1=Lundi … 7=Dimanche).';
        if (empty($data['heure_debut']) || empty($data['heure_fin']))
            $erreurs[] = 'Heures de début et fin requises.';
        elseif ($data['heure_debut'] >= $data['heure_fin'])
            $erreurs[] = 'L\'heure de fin doit être après le début.';
        return $erreurs;
    }
}