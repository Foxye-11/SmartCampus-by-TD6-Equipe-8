<?php
// controllers/EmploiDuTempsController.php
//
// Emploi du temps - mode CALENDRIER (annee scolaire).
// Chaque seance est datee (sessions_cours.date_specifique). On ne raisonne
// plus en "semaine type" repetee, mais en vrai calendrier sur l'annee.
//
// L'API accepte les filtres :
//   - annee_scolaire (ex : "2025-2026" — par defaut)
//   - date_debut / date_fin (sous-intervalle pour vue jour/semaine/mois)
//   - cours_id, matiere, enseignant_id, etudiant_id (filtres metier)
//
// Pour l'instant, seule l'annee scolaire 2025-2026 est disponible.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';

class EmploiDuTempsController {

    private PDO $pdo;

    /** Annee scolaire par defaut + liste des annees actuellement disponibles. */
    public const ANNEE_DEFAUT      = '2025-2026';
    public const ANNEES_DISPO      = ['2025-2026'];

    public function __construct() { $this->pdo = getDB(); }

    // -----------------------------------------------
    // Liste des annees scolaires selectionnables dans
    // l'interface (calendrier). Seule 2025-2026 est
    // disponible pour l'instant.
    // -----------------------------------------------
    public function anneesDisponibles(): array {
        return self::ANNEES_DISPO;
    }

    // -----------------------------------------------
    // Vue calendrier pour un etudiant
    // -----------------------------------------------
    public function emploiEtudiant(int $etudiantId, array $filtres = []): array {
        $annee   = $filtres['annee_scolaire'] ?? self::ANNEE_DEFAUT;
        $debut   = $filtres['date_debut'] ?? null;
        $fin     = $filtres['date_fin']   ?? null;

        $sql = 'SELECT sc.id AS session_id, sc.date_specifique AS date,
                       sc.jour_semaine, sc.heure_debut, sc.heure_fin,
                       c.id AS cours_id, c.code, c.intitule AS cours, c.matiere,
                       s.nom AS salle, s.batiment,
                       sem.libelle AS semestre, sem.annee_scolaire,
                       CONCAT(u.prenom, " ", u.nom) AS enseignant
                FROM sessions_cours sc
                JOIN cours c        ON c.id = sc.cours_id
                JOIN semestres sem  ON sem.id = c.semestre_id
                JOIN inscriptions i ON i.cours_id = c.id
                LEFT JOIN salles s        ON s.id = sc.salle_id
                LEFT JOIN enseignants e   ON e.id = c.enseignant_id
                LEFT JOIN utilisateurs u  ON u.id = e.utilisateur_id
                WHERE i.etudiant_id = :eid
                  AND i.statut = "active"
                  AND sc.date_specifique IS NOT NULL
                  AND sem.annee_scolaire = :annee';
        $params = [':eid' => $etudiantId, ':annee' => $annee];

        if ($debut) { $sql .= ' AND sc.date_specifique >= :d1'; $params[':d1'] = $debut; }
        if ($fin)   { $sql .= ' AND sc.date_specifique <= :d2'; $params[':d2'] = $fin; }

        $sql .= ' ORDER BY sc.date_specifique, sc.heure_debut';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Vue calendrier pour un enseignant
    // -----------------------------------------------
    public function emploiEnseignant(int $enseignantId, array $filtres = []): array {
        $annee   = $filtres['annee_scolaire'] ?? self::ANNEE_DEFAUT;
        $debut   = $filtres['date_debut'] ?? null;
        $fin     = $filtres['date_fin']   ?? null;

        $sql = 'SELECT sc.id AS session_id, sc.date_specifique AS date,
                       sc.jour_semaine, sc.heure_debut, sc.heure_fin,
                       c.id AS cours_id, c.code, c.intitule AS cours, c.matiere,
                       s.nom AS salle, s.batiment,
                       sem.libelle AS semestre, sem.annee_scolaire,
                       (SELECT COUNT(*) FROM inscriptions i
                        WHERE i.cours_id = c.id AND i.statut = "active") AS inscrits
                FROM sessions_cours sc
                JOIN cours c       ON c.id = sc.cours_id
                JOIN semestres sem ON sem.id = c.semestre_id
                LEFT JOIN salles s ON s.id = sc.salle_id
                WHERE c.enseignant_id = :eid
                  AND sc.date_specifique IS NOT NULL
                  AND sem.annee_scolaire = :annee';
        $params = [':eid' => $enseignantId, ':annee' => $annee];

        if ($debut) { $sql .= ' AND sc.date_specifique >= :d1'; $params[':d1'] = $debut; }
        if ($fin)   { $sql .= ' AND sc.date_specifique <= :d2'; $params[':d2'] = $fin; }

        $sql .= ' ORDER BY sc.date_specifique, sc.heure_debut';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Vue calendrier complete (admin) + filtres
    // -----------------------------------------------
    public function emploiComplet(array $filtres = []): array {
        Auth::exiger('admin');

        $annee = $filtres['annee_scolaire'] ?? self::ANNEE_DEFAUT;
        $debut = $filtres['date_debut'] ?? null;
        $fin   = $filtres['date_fin']   ?? null;

        $sql =
            'SELECT sc.id AS session_id, sc.date_specifique AS date,
                    sc.jour_semaine, sc.heure_debut, sc.heure_fin,
                    c.id AS cours_id, c.code, c.intitule AS cours, c.matiere,
                    s.nom AS salle, s.batiment,
                    sem.libelle AS semestre, sem.annee_scolaire,
                    CONCAT(u.prenom, " ", u.nom) AS enseignant,
                    e.id AS enseignant_id
             FROM sessions_cours sc
             JOIN cours c       ON c.id = sc.cours_id
             JOIN semestres sem ON sem.id = c.semestre_id
             LEFT JOIN salles s        ON s.id = sc.salle_id
             LEFT JOIN enseignants e   ON e.id = c.enseignant_id
             LEFT JOIN utilisateurs u  ON u.id = e.utilisateur_id
             WHERE sc.date_specifique IS NOT NULL
               AND sem.annee_scolaire = :annee';
        $params = [':annee' => $annee];

        if ($debut) { $sql .= ' AND sc.date_specifique >= :d1'; $params[':d1'] = $debut; }
        if ($fin)   { $sql .= ' AND sc.date_specifique <= :d2'; $params[':d2'] = $fin; }

        if (!empty($filtres['cours_id'])) {
            $sql .= ' AND c.id = :cid';
            $params[':cid'] = (int)$filtres['cours_id'];
        }
        if (!empty($filtres['matiere'])) {
            $sql .= ' AND c.matiere = :mat';
            $params[':mat'] = $filtres['matiere'];
        }
        if (!empty($filtres['enseignant_id'])) {
            $sql .= ' AND c.enseignant_id = :eid';
            $params[':eid'] = (int)$filtres['enseignant_id'];
        }
        if (!empty($filtres['etudiant_id'])) {
            $sql .= ' AND c.id IN (SELECT i.cours_id FROM inscriptions i
                                   WHERE i.etudiant_id = :etu AND i.statut = "active")';
            $params[':etu'] = (int)$filtres['etudiant_id'];
        }

        $sql .= ' ORDER BY sc.date_specifique, sc.heure_debut';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Creer une seance : date_specifique est OBLIGATOIRE
    // (on est en mode calendrier, plus en semaine type).
    // -----------------------------------------------
    public function creerSession(array $data): array {
        Auth::exiger('admin');

        $erreurs = $this->validerSession($data);
        if (!empty($erreurs)) return ['succes' => false, 'erreurs' => $erreurs];

        // jour_semaine est deduit de la date pour rester coherent.
        $jour = (int) date('N', strtotime($data['date_specifique']));

        // Detection centralisee des conflits (salle / enseignant / etudiants)
        $conflits = $this->detecterConflits(array_merge($data,
                       ['jour_semaine' => $jour]));
        if (!empty($conflits)) return ['succes' => false, 'erreurs' => $conflits];

        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions_cours (cours_id, jour_semaine, heure_debut, heure_fin,
                                         salle_id, date_specifique)
             VALUES (:cours_id, :jour, :debut, :fin, :salle_id, :date)'
        );
        $stmt->execute([
            ':cours_id'  => (int)$data['cours_id'],
            ':jour'      => $jour,
            ':debut'     => $data['heure_debut'],
            ':fin'       => $data['heure_fin'],
            ':salle_id'  => !empty($data['salle_id']) ? (int)$data['salle_id'] : null,
            ':date'      => $data['date_specifique'],
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
    // Detection des conflits d'une seance datee.
    // Verifie : salle occupee, enseignant occupe, etudiants occupes.
    // Les comparaisons utilisent maintenant date_specifique.
    // -----------------------------------------------
    public function detecterConflits(array $data, ?int $excludeId = null): array {
        $conflits = [];
        $debut   = $data['heure_debut'];
        $fin     = $data['heure_fin'];
        $coursId = (int)$data['cours_id'];
        $date    = $data['date_specifique'] ?? null;

        if (!$date) return ['Date de la seance requise pour la detection de conflit.'];

        // 1) Conflit de salle
        if (!empty($data['salle_id'])) {
            $c = $this->verifierConflitSalle((int)$data['salle_id'], $date, $debut, $fin, $excludeId);
            if ($c) $conflits[] = 'Salle deja occupee a ce creneau par : ' . $c;
        }

        // 2) Conflit d'enseignant
        $c = $this->verifierConflitEnseignant($coursId, $date, $debut, $fin, $excludeId);
        if ($c) $conflits[] = 'Enseignant deja occupe a ce creneau par : ' . $c;

        // 3) Conflit d'etudiants
        $c = $this->verifierConflitEtudiants($coursId, $date, $debut, $fin, $excludeId);
        if ($c) $conflits[] = 'Au moins un etudiant inscrit a deja cours a ce creneau : ' . $c;

        return $conflits;
    }

    private function verifierConflitSalle(int $salleId, string $date, string $debut, string $fin, ?int $excludeId = null): ?string {
        $sql = 'SELECT c.intitule FROM sessions_cours sc
                JOIN cours c ON c.id = sc.cours_id
                WHERE sc.salle_id = :sid
                  AND sc.date_specifique = :date
                  AND sc.heure_debut < :fin AND sc.heure_fin > :debut';
        $params = [':sid' => $salleId, ':date' => $date, ':debut' => $debut, ':fin' => $fin];
        if ($excludeId) { $sql .= ' AND sc.id != :ex'; $params[':ex'] = $excludeId; }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? $row['intitule'] : null;
    }

    private function verifierConflitEnseignant(int $coursId, string $date, string $debut, string $fin, ?int $excludeId = null): ?string {
        $stmt = $this->pdo->prepare('SELECT enseignant_id FROM cours WHERE id = :cid');
        $stmt->execute([':cid' => $coursId]);
        $row = $stmt->fetch();
        if (!$row || empty($row['enseignant_id'])) return null;
        $enseignantId = (int)$row['enseignant_id'];

        $sql = 'SELECT c.intitule FROM sessions_cours sc
                JOIN cours c ON c.id = sc.cours_id
                WHERE c.enseignant_id = :ens AND sc.cours_id != :cid
                  AND sc.date_specifique = :date
                  AND sc.heure_debut < :fin AND sc.heure_fin > :debut';
        $params = [':ens' => $enseignantId, ':cid' => $coursId, ':date' => $date, ':debut' => $debut, ':fin' => $fin];
        if ($excludeId) { $sql .= ' AND sc.id != :ex'; $params[':ex'] = $excludeId; }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $r = $stmt->fetch();
        return $r ? $r['intitule'] : null;
    }

    private function verifierConflitEtudiants(int $coursId, string $date, string $debut, string $fin, ?int $excludeId = null): ?string {
        $sql = 'SELECT c2.intitule FROM sessions_cours sc
                JOIN cours c2          ON c2.id = sc.cours_id
                JOIN inscriptions i2   ON i2.cours_id = sc.cours_id AND i2.statut = "active"
                WHERE sc.cours_id != :cid
                  AND sc.date_specifique = :date
                  AND sc.heure_debut < :fin AND sc.heure_fin > :debut
                  AND i2.etudiant_id IN (
                        SELECT etudiant_id FROM inscriptions
                        WHERE cours_id = :cid2 AND statut = "active"
                  )';
        $params = [':cid' => $coursId, ':cid2' => $coursId, ':date' => $date, ':debut' => $debut, ':fin' => $fin];
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
        if (empty($data['date_specifique'])) {
            $erreurs[] = 'Date de la seance requise (mode calendrier).';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date_specifique'])) {
            $erreurs[] = 'Format de date invalide (YYYY-MM-DD attendu).';
        } else {
            // Verifier que la date est bien dans une annee scolaire disponible.
            $annee = $this->anneeScolaireDeLaDate($data['date_specifique']);
            if (!in_array($annee, self::ANNEES_DISPO, true)) {
                $erreurs[] = 'Date hors annee scolaire selectionnable (seule ' . self::ANNEE_DEFAUT . ' est disponible).';
            }
        }
        if (empty($data['heure_debut']) || empty($data['heure_fin']))
            $erreurs[] = 'Heures de debut et fin requises.';
        elseif ($data['heure_debut'] >= $data['heure_fin'])
            $erreurs[] = 'L\'heure de fin doit etre apres le debut.';
        return $erreurs;
    }

    // Retourne l'annee scolaire (ex "2025-2026") d'une date donnee.
    // Convention : septembre N -> juin N+1 = annee N-(N+1).
    private function anneeScolaireDeLaDate(string $date): string {
        $t = strtotime($date);
        $m = (int) date('n', $t);
        $y = (int) date('Y', $t);
        if ($m >= 9) {
            return $y . '-' . ($y + 1);
        }
        return ($y - 1) . '-' . $y;
    }
}
