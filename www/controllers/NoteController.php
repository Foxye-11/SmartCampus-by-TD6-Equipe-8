<?php
// controllers/NoteController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';

class NoteController {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    // -----------------------------------------------
    // Saisir une note (enseignant / admin)
    // -----------------------------------------------
    public function saisir(array $data): array {
        Auth::exiger('enseignant', 'admin');

        $erreurs = $this->validerNote($data);
        if (!empty($erreurs)) {
            return ['succes' => false, 'erreurs' => $erreurs];
        }

        $inscriptionId  = (int) $data['inscription_id'];
        $typeEvaluation = trim($data['type_evaluation']);
        $valeur         = (float) $data['valeur'];
        $coefficient    = (float) ($data['coefficient'] ?? 1.0);
        $commentaire    = isset($data['commentaire']) ? trim($data['commentaire']) : null;

        // Vérifier existence de l'inscription
        $inscription = $this->getInscription($inscriptionId);
        if (!$inscription) {
            return ['succes' => false, 'erreurs' => ['Inscription introuvable.']];
        }

        // RÈGLE MÉTIER : notes verrouillées
        if ($inscription['notes_verrouillees']) {
            return [
                'succes'  => false,
                'erreurs' => ['Les notes de ce cours sont verrouillées et ne peuvent plus être modifiées.'],
            ];
        }

        // Vérifier que l'enseignant est responsable du cours
        if (Auth::getRole() === 'enseignant') {
            if ((int)$inscription['enseignant_utilisateur_id'] !== (int)$_SESSION['user_id']) {
                return ['succes' => false, 'erreurs' => ['Vous n\'êtes pas responsable de ce cours.']];
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO notes (inscription_id, type_evaluation, valeur, coefficient, commentaire)
             VALUES (:iid, :type, :valeur, :coeff, :commentaire)'
        );
        $stmt->execute([
            ':iid'         => $inscriptionId,
            ':type'        => $typeEvaluation,
            ':valeur'      => $valeur,
            ':coeff'       => $coefficient,
            ':commentaire' => $commentaire,
        ]);

        $noteId = (int) $this->pdo->lastInsertId();

        // Notifier l'étudiant
        $this->notifierEtudiant($inscription, $typeEvaluation, $valeur);

        return ['succes' => true, 'id' => $noteId];
    }

    // -----------------------------------------------
    // Modifier une note (enseignant / admin)
    // -----------------------------------------------
    public function modifier(int $noteId, array $data): array {
        Auth::exiger('enseignant', 'admin');

        $note = $this->getNote($noteId);
        if (!$note) {
            return ['succes' => false, 'erreurs' => ['Note introuvable.']];
        }

        $inscription = $this->getInscription($note['inscription_id']);

        // RÈGLE MÉTIER : notes verrouillées
        if ($inscription && $inscription['notes_verrouillees']) {
            return [
                'succes'  => false,
                'erreurs' => ['Impossible de modifier : les notes de ce cours sont verrouillées.'],
            ];
        }

        if (Auth::getRole() === 'enseignant') {
            if ((int)$inscription['enseignant_utilisateur_id'] !== (int)$_SESSION['user_id']) {
                return ['succes' => false, 'erreurs' => ['Vous n\'êtes pas responsable de ce cours.']];
            }
        }

        $erreurs = $this->validerNote($data);
        if (!empty($erreurs)) {
            return ['succes' => false, 'erreurs' => $erreurs];
        }

        $stmt = $this->pdo->prepare(
            'UPDATE notes
             SET type_evaluation = :type,
                 valeur = :valeur,
                 coefficient = :coeff,
                 commentaire = :commentaire
             WHERE id = :id'
        );
        $stmt->execute([
            ':type'        => trim($data['type_evaluation']),
            ':valeur'      => (float) $data['valeur'],
            ':coeff'       => (float) ($data['coefficient'] ?? 1.0),
            ':commentaire' => isset($data['commentaire']) ? trim($data['commentaire']) : null,
            ':id'          => $noteId,
        ]);

        return ['succes' => true];
    }

    // -----------------------------------------------
    // Supprimer une note
    // -----------------------------------------------
    public function supprimer(int $noteId): array {
        Auth::exiger('enseignant', 'admin');

        $note = $this->getNote($noteId);
        if (!$note) {
            return ['succes' => false, 'erreurs' => ['Note introuvable.']];
        }

        $inscription = $this->getInscription($note['inscription_id']);
        if ($inscription && $inscription['notes_verrouillees']) {
            return ['succes' => false, 'erreurs' => ['Notes verrouillées : suppression impossible.']];
        }

        $this->pdo->prepare('DELETE FROM notes WHERE id = :id')
                  ->execute([':id' => $noteId]);

        return ['succes' => true];
    }

    // -----------------------------------------------
    // Notes d'un étudiant (toutes ou par cours)
    // -----------------------------------------------
    public function notesEtudiant(int $etudiantId, ?int $coursId = null): array {
        Auth::exiger('etudiant', 'enseignant', 'admin');

        // Un étudiant ne consulte que ses propres notes
        if (Auth::getRole() === 'etudiant') {
            if ($_SESSION['etudiant_id'] !== $etudiantId) {
                return [];
            }
        }

        $sql = 'SELECT n.id, n.type_evaluation, n.valeur, n.coefficient,
                       n.commentaire, n.date_saisie,
                       c.code, c.intitule AS cours,
                       s.libelle AS semestre
                FROM notes n
                JOIN inscriptions i ON i.id = n.inscription_id
                JOIN cours c ON c.id = i.cours_id
                JOIN semestres s ON s.id = c.semestre_id
                WHERE i.etudiant_id = :eid AND i.statut = "active"';

        $params = [':eid' => $etudiantId];

        if ($coursId !== null) {
            $sql .= ' AND i.cours_id = :cid';
            $params[':cid'] = $coursId;
        }

        $sql .= ' ORDER BY s.libelle, c.intitule, n.date_saisie';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Notes d'un cours (enseignant / admin)
    // -----------------------------------------------
    public function notesDuCours(int $coursId): array {
        Auth::exiger('enseignant', 'admin');

        $stmt = $this->pdo->prepare(
            'SELECT n.id, n.type_evaluation, n.valeur, n.coefficient,
                    n.commentaire, n.date_saisie,
                    et.numero_etudiant,
                    CONCAT(u.prenom, " ", u.nom) AS etudiant
             FROM notes n
             JOIN inscriptions i ON i.id = n.inscription_id
             JOIN etudiants et ON et.id = i.etudiant_id
             JOIN utilisateurs u ON u.id = et.utilisateur_id
             WHERE i.cours_id = :cid AND i.statut = "active"
             ORDER BY u.nom, u.prenom, n.type_evaluation'
        );
        $stmt->execute([':cid' => $coursId]);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Notes de tous les cours partageant une matière donnée
    // (enseignant / admin)
    // -----------------------------------------------
    public function notesParMatiere(string $matiere): array {
        Auth::exiger('enseignant', 'admin');

        // RESTRICTION ENSEIGNANT : ne voir que les notes des cours dont
        // il est responsable, meme si la matiere est partagee.
        $sql = 'SELECT n.id, n.type_evaluation, n.valeur, n.coefficient,
                       n.commentaire, n.date_saisie,
                       et.numero_etudiant,
                       CONCAT(u.prenom, " ", u.nom) AS etudiant,
                       c.code AS cours_code, c.intitule AS cours_intitule
                FROM notes n
                JOIN inscriptions i ON i.id = n.inscription_id
                JOIN cours c        ON c.id = i.cours_id
                JOIN etudiants et   ON et.id = i.etudiant_id
                JOIN utilisateurs u ON u.id = et.utilisateur_id
                WHERE c.matiere = :mat AND i.statut = "active"';
        $params = [':mat' => $matiere];

        if (Auth::getRole() === 'enseignant') {
            $sql .= ' AND c.enseignant_id = (
                SELECT id FROM enseignants WHERE utilisateur_id = :tuid LIMIT 1
            )';
            $params[':tuid'] = $_SESSION['user_id'];
        }

        $sql .= ' ORDER BY c.code, u.nom, u.prenom, n.type_evaluation';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Calcul de la moyenne d'un étudiant dans un cours
    // -----------------------------------------------
    public function calculerMoyenne(int $etudiantId, int $coursId): array {
        $stmt = $this->pdo->prepare(
            'SELECT n.valeur, n.coefficient
             FROM notes n
             JOIN inscriptions i ON i.id = n.inscription_id
             WHERE i.etudiant_id = :eid AND i.cours_id = :cid AND i.statut = "active"'
        );
        $stmt->execute([':eid' => $etudiantId, ':cid' => $coursId]);
        $notes = $stmt->fetchAll();

        if (empty($notes)) {
            return ['moyenne' => null, 'nb_notes' => 0];
        }

        $somme      = 0.0;
        $totalCoeff = 0.0;
        foreach ($notes as $n) {
            $somme      += (float)$n['valeur'] * (float)$n['coefficient'];
            $totalCoeff += (float)$n['coefficient'];
        }

        $moyenne = $totalCoeff > 0 ? round($somme / $totalCoeff, 2) : null;

        return [
            'moyenne'  => $moyenne,
            'nb_notes' => count($notes),
            'mention'  => $this->calculerMention($moyenne),
        ];
    }

    // -----------------------------------------------
    // Bulletin complet d'un étudiant (par semestre)
    // -----------------------------------------------
    public function bulletin(int $etudiantId, int $semestreId): array {
        Auth::exiger('etudiant', 'enseignant', 'admin');

        if (Auth::getRole() === 'etudiant' && $_SESSION['etudiant_id'] !== $etudiantId) {
            return [];
        }

        // Cours du semestre où l'étudiant est inscrit
        $stmt = $this->pdo->prepare(
            'SELECT c.id AS cours_id, c.code, c.intitule, c.credits
             FROM inscriptions i
             JOIN cours c ON c.id = i.cours_id
             WHERE i.etudiant_id = :eid
               AND c.semestre_id = :sid
               AND i.statut = "active"
             ORDER BY c.intitule'
        );
        $stmt->execute([':eid' => $etudiantId, ':sid' => $semestreId]);
        $coursSemestre = $stmt->fetchAll();

        $bulletin      = [];
        $moyenneGlobale = 0.0;
        $totalCredits  = 0;

        // Requête détail (toutes les évaluations) que l'on annexe à chaque cours
        $stmtNotes = $this->pdo->prepare(
            'SELECT n.id, n.type_evaluation, n.valeur, n.coefficient,
                    n.commentaire, n.date_saisie
             FROM notes n
             JOIN inscriptions i ON i.id = n.inscription_id
             WHERE i.etudiant_id = :eid
               AND i.cours_id = :cid
               AND i.statut = "active"
             ORDER BY n.date_saisie, n.type_evaluation'
        );

        foreach ($coursSemestre as $c) {
            $moy = $this->calculerMoyenne($etudiantId, $c['cours_id']);
            $stmtNotes->execute([':eid' => $etudiantId, ':cid' => $c['cours_id']]);
            $detail = $stmtNotes->fetchAll();

            $bulletin[] = array_merge($c, $moy, ['notes' => $detail]);

            if ($moy['moyenne'] !== null) {
                $moyenneGlobale += $moy['moyenne'] * $c['credits'];
                $totalCredits   += $c['credits'];
            }
        }

        $moyenneFinale = $totalCredits > 0
                          ? round($moyenneGlobale / $totalCredits, 2)
                          : null;

        return [
            'cours'             => $bulletin,
            'moyenne_generale'  => $moyenneFinale,
            'mention_generale'  => $this->calculerMention($moyenneFinale),
        ];
    }

    // -----------------------------------------------
    // Verrouiller les notes d'un cours (admin)
    // -----------------------------------------------
    public function verrouiller(int $coursId): array {
        Auth::exiger('admin');

        $stmt = $this->pdo->prepare(
            'UPDATE cours SET notes_verrouillees = 1 WHERE id = :id'
        );
        $stmt->execute([':id' => $coursId]);

        return ['succes' => true, 'message' => 'Notes verrouillées définitivement.'];
    }

    // -----------------------------------------------
    // Helpers privés
    // -----------------------------------------------
    private function getNote(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM notes WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function getInscription(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT i.*, c.notes_verrouillees, c.enseignant_id,
                    e.utilisateur_id AS enseignant_utilisateur_id,
                    et.utilisateur_id AS etudiant_utilisateur_id
             FROM inscriptions i
             JOIN cours c ON c.id = i.cours_id
             LEFT JOIN enseignants e ON e.id = c.enseignant_id
             JOIN etudiants et ON et.id = i.etudiant_id
             WHERE i.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function validerNote(array $data): array {
        $erreurs        = [];
        $typesValides   = ['examen', 'partiel', 'tp', 'projet', 'controle'];

        if (empty($data['inscription_id']) || (int)$data['inscription_id'] < 1) {
            $erreurs[] = 'Inscription invalide.';
        }
        if (empty($data['type_evaluation']) ||
            !in_array(trim($data['type_evaluation']), $typesValides, true)) {
            $erreurs[] = 'Type d\'évaluation invalide (' . implode(', ', $typesValides) . ').';
        }
        if (!isset($data['valeur']) || !is_numeric($data['valeur']) ||
            (float)$data['valeur'] < 0 || (float)$data['valeur'] > 20) {
            $erreurs[] = 'La note doit être comprise entre 0 et 20.';
        }
        if (isset($data['coefficient']) &&
            (!is_numeric($data['coefficient']) || (float)$data['coefficient'] <= 0)) {
            $erreurs[] = 'Le coefficient doit être un nombre positif.';
        }
        return $erreurs;
    }

    private function calculerMention(?float $moyenne): string {
        if ($moyenne === null) return '-';
        if ($moyenne >= 16) return 'Très Bien';
        if ($moyenne >= 14) return 'Bien';
        if ($moyenne >= 12) return 'Assez Bien';
        if ($moyenne >= 10) return 'Passable';
        return 'Insuffisant';
    }

    private function notifierEtudiant(array $inscription, string $type, float $valeur): void {
        $this->pdo->prepare(
            'INSERT INTO notifications (utilisateur_id, type, contenu)
             VALUES (:uid, "note_publiee", :contenu)'
        )->execute([
            ':uid'     => $inscription['etudiant_utilisateur_id'],
            ':contenu' => sprintf(
                'Nouvelle note publiée : %s — %.2f/20 (%s)',
                $type, $valeur, $type
            ),
        ]);
    }
}
