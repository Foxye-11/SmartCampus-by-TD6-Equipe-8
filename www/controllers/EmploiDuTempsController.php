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

    public function emploiComplet(array $filtres = []): array {
        Auth::exiger('admin');
        $sql =
            'SELECT sc.id AS session_id, sc.jour_semaine, sc.heure_debut, sc.heure_fin,
                    sc.date_specifique,
                    c.id AS cours_id, c.code, c.intitule AS cours,
                    s.nom AS salle, s.batiment,
                    CONCAT(u.prenom, " ", u.nom) AS enseignant,
                    e.id AS enseignant_id
             FROM sessions_cours sc
             JOIN cours c ON c.id = sc.cours_id
             LEFT JOIN salles s ON s.id = sc.salle_id
             LEFT JOIN enseignants e ON e.id = c.enseignant_id
             LEFT JOIN utilisateurs u ON u.id = e.utilisateur_id
             WHERE 1=1';
        $params = [];
        // Filtre par matière (cours)
        if (!empty($filtres['cours_id'])) {
            $sql .= ' AND c.id = :cid';
            $params[':cid'] = (int)$filtres['cours_id'];
        }
        // Filtre par enseignant (personne = prof)
        if (!empty($filtres['enseignant_id'])) {
            $sql .= ' AND c.enseignant_id = :eid';
            $params[':eid'] = (int)$filtres['enseignant_id'];
        }
        // Filtre par étudiant (personne = élève) : séances des cours auxquels il est inscrit
        if (!empty($filtres['etudiant_id'])) {
            $sql .= ' AND c.id IN (SELECT i.cours_id FROM inscriptions i
                                   WHERE i.etudiant_id = :etu AND i.statut = "active")';
            $params[':etu'] = (int)$filtres['etudiant_id'];
        }
        $sql .= ' ORDER BY sc.jour_semaine, sc.heure_debut';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function creerSession(array $data): array {
        Auth::exiger('admin');
        $erreurs = $this->validerSession($data);
        if (!empty($erreurs)) return ['succes' => false, 'erreurs' => $erreurs];

        // Détection centralisée des conflits (salle / enseignant / groupe d'étudiants)
        $conflits = $this->detecterConflits($data);
        if (!empty($conflits)) return ['succes' => false, 'erreurs' => $conflits];

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

    // -----------------------------------------------
    // Détection des conflits d'une séance à créer.
    // Vérifie : salle occupée, enseignant occupé, groupe/étudiant occupé.
    // Retourne un tableau de messages (vide si aucun conflit).
    // Le paramètre $excludeId permet d'ignorer une séance (édition).
    // -----------------------------------------------
    public function detecterConflits(array $data, ?int $excludeId = null): array {
        $conflits = [];
        $jour  = (int)$data['jour_semaine'];
        $debut = $data['heure_debut'];
        $fin   = $data['heure_fin'];
        $coursId = (int)$data['cours_id'];

        // 1) Conflit de salle
        if (!empty($data['salle_id'])) {
            $c = $this->verifierConflitSalle((int)$data['salle_id'], $jour, $debut, $fin, $excludeId);
            if ($c) $conflits[] = 'Salle déjà occupée à ce créneau par : ' . $c;
        }

        // 2) Conflit d'enseignant : l'enseignant du cours a déjà une séance qui chevauche
        $c = $this->verifierConflitEnseignant($coursId, $jour, $debut, $fin, $excludeId);
        if ($c) $conflits[] = 'Enseignant déjà occupé à ce créneau par : ' . $c;

        // 3) Conflit de groupe / étudiant : un étudiant inscrit à ce cours a déjà une séance qui chevauche
        $c = $this->verifierConflitEtudiants($coursId, $jour, $debut, $fin, $excludeId);
        if ($c) $conflits[] = 'Au moins un étudiant/groupe inscrit a déjà cours à ce créneau : ' . $c;

        return $conflits;
    }

    private function verifierConflitSalle(int $salleId, int $jour, string $debut, string $fin, ?int $excludeId = null): ?string {
        $sql = 'SELECT c.intitule FROM sessions_cours sc
                JOIN cours c ON c.id = sc.cours_id
                WHERE sc.salle_id = :sid AND sc.jour_semaine = :jour
                  AND sc.heure_debut < :fin AND sc.heure_fin > :debut';
        $params = [':sid' => $salleId, ':jour' => $jour, ':debut' => $debut, ':fin' => $fin];
        if ($excludeId) { $sql .= ' AND sc.id != :ex'; $params[':ex'] = $excludeId; }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? $row['intitule'] : null;
    }

    private function verifierConflitEnseignant(int $coursId, int $jour, string $debut, string $fin, ?int $excludeId = null): ?string {
        // Enseignant du cours concerné
        $stmt = $this->pdo->prepare('SELECT enseignant_id FROM cours WHERE id = :cid');
        $stmt->execute([':cid' => $coursId]);
        $row = $stmt->fetch();
        if (!$row || empty($row['enseignant_id'])) return null;
        $enseignantId = (int)$row['enseignant_id'];

        $sql = 'SELECT c.intitule FROM sessions_cours sc
                JOIN cours c ON c.id = sc.cours_id
                WHERE c.enseignant_id = :ens AND sc.cours_id != :cid
                  AND sc.jour_semaine = :jour
                  AND sc.heure_debut < :fin AND sc.heure_fin > :debut';
        $params = [':ens' => $enseignantId, ':cid' => $coursId, ':jour' => $jour, ':debut' => $debut, ':fin' => $fin];
        if ($excludeId) { $sql .= ' AND sc.id != :ex'; $params[':ex'] = $excludeId; }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $r = $stmt->fetch();
        return $r ? $r['intitule'] : null;
    }

    private function verifierConflitEtudiants(int $coursId, int $jour, string $debut, string $fin, ?int $excludeId = null): ?string {
        // Un étudiant inscrit (statut active) à ce cours est-il aussi inscrit à un autre
        // cours dont une séance chevauche ce créneau ?
        $sql = 'SELECT c2.intitule FROM sessions_cours sc
                JOIN cours c2 ON c2.id = sc.cours_id
                JOIN inscriptions i2 ON i2.cours_id = sc.cours_id AND i2.statut = "active"
                WHERE sc.cours_id != :cid
                  AND sc.jour_semaine = :jour
                  AND sc.heure_debut < :fin AND sc.heure_fin > :debut
                  AND i2.etudiant_id IN (
                        SELECT etudiant_id FROM inscriptions
                        WHERE cours_id = :cid2 AND statut = "active"
                  )';
        $params = [':cid' => $coursId, ':cid2' => $coursId, ':jour' => $jour, ':debut' => $debut, ':fin' => $fin];
        if ($excludeId) { $sql .= ' AND sc.id != :ex'; $params[':ex'] = $excludeId; }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $r = $stmt->fetch();
        return $r ? $r['intitule'] : null;
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