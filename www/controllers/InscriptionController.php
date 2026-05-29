<?php
// controllers/InscriptionController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';

class InscriptionController {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    // -----------------------------------------------
    // Inscrire un étudiant à un cours
    // Règles métier :
    //   1. Pas d'inscription double
    //   2. Capacité maximale du cours
    //   3. Prérequis académiques
    //   4. Conflit d'emploi du temps
    //   5. Semestre non archivé
    // -----------------------------------------------
    public function inscrire(int $etudiantId, int $coursId): array {
        Auth::exiger('etudiant', 'admin');

        // Un étudiant ne peut s'inscrire que pour lui-même (sauf admin)
        if (Auth::getRole() === 'etudiant') {
            if ($_SESSION['etudiant_id'] !== $etudiantId) {
                return ['succes' => false, 'erreur' => 'Action non autorisée.'];
            }
        }

        // Vérifier existence du cours
        $cours = $this->getCours($coursId);
        if (!$cours) {
            return ['succes' => false, 'erreur' => 'Cours introuvable.'];
        }

        // Règle 5 : semestre non archivé
        $stmt = $this->pdo->prepare(
            'SELECT archive FROM semestres WHERE id = :id'
        );
        $stmt->execute([':id' => $cours['semestre_id']]);
        $semestre = $stmt->fetch();
        if ($semestre && $semestre['archive']) {
            return ['succes' => false, 'erreur' => 'Les inscriptions pour ce semestre sont closes.'];
        }

        // Règle 1 : pas d'inscription double
        $stmt = $this->pdo->prepare(
            'SELECT id, statut FROM inscriptions
             WHERE etudiant_id = :eid AND cours_id = :cid'
        );
        $stmt->execute([':eid' => $etudiantId, ':cid' => $coursId]);
        $existant = $stmt->fetch();

        if ($existant) {
            if ($existant['statut'] === 'active') {
                return ['succes' => false, 'erreur' => 'Vous êtes déjà inscrit à ce cours.'];
            }
            // Réactiver une inscription annulée
            $stmt = $this->pdo->prepare(
                'UPDATE inscriptions SET statut = "active", date_inscription = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([':id' => $existant['id']]);
            return ['succes' => true, 'message' => 'Inscription réactivée.', 'id' => $existant['id']];
        }

        // Règle 2 : capacité maximale
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS nb FROM inscriptions
             WHERE cours_id = :cid AND statut = "active"'
        );
        $stmt->execute([':cid' => $coursId]);
        $inscrits = $stmt->fetch()['nb'];

        if ($inscrits >= $cours['capacite_max']) {
            return ['succes' => false, 'erreur' => 'Ce cours est complet (capacité maximale atteinte).'];
        }

        // Règle 3 : prérequis
        $prerequisManquants = $this->verifierPrerequis($etudiantId, $coursId);
        if (!empty($prerequisManquants)) {
            return [
                'succes' => false,
                'erreur' => 'Prérequis non validés : ' . implode(', ', $prerequisManquants),
            ];
        }

        // Règle 4 : conflit d'emploi du temps
        $conflit = $this->detecterConflitEmploiDuTemps($etudiantId, $coursId);
        if ($conflit) {
            return [
                'succes' => false,
                'erreur' => 'Conflit d\'emploi du temps avec le cours : ' . $conflit,
            ];
        }

        // Tout est valide : insérer
        $stmt = $this->pdo->prepare(
            'INSERT INTO inscriptions (etudiant_id, cours_id, statut)
             VALUES (:eid, :cid, "active")'
        );
        $stmt->execute([':eid' => $etudiantId, ':cid' => $coursId]);

        $inscriptionId = (int) $this->pdo->lastInsertId();

        // Notification à l'enseignant
        $this->notifierEnseignantNouvelInscrit($cours, $etudiantId);

        return ['succes' => true, 'message' => 'Inscription réussie.', 'id' => $inscriptionId];
    }

    // -----------------------------------------------
    // Annuler une inscription
    // -----------------------------------------------
    public function annuler(int $inscriptionId): array {
        Auth::exiger('etudiant', 'admin');

        $stmt = $this->pdo->prepare(
            'SELECT i.*, c.notes_verrouillees
             FROM inscriptions i
             JOIN cours c ON c.id = i.cours_id
             WHERE i.id = :id'
        );
        $stmt->execute([':id' => $inscriptionId]);
        $inscription = $stmt->fetch();

        if (!$inscription) {
            return ['succes' => false, 'erreur' => 'Inscription introuvable.'];
        }

        // Un étudiant ne peut annuler que ses propres inscriptions
        if (Auth::getRole() === 'etudiant') {
            if ($_SESSION['etudiant_id'] !== (int)$inscription['etudiant_id']) {
                return ['succes' => false, 'erreur' => 'Action non autorisée.'];
            }
        }

        // Impossible d'annuler si les notes sont verrouillées
        if ($inscription['notes_verrouillees']) {
            return [
                'succes' => false,
                'erreur' => 'Impossible d\'annuler : les notes de ce cours sont validées.',
            ];
        }

        $stmt = $this->pdo->prepare(
            'UPDATE inscriptions SET statut = "annulee" WHERE id = :id'
        );
        $stmt->execute([':id' => $inscriptionId]);

        return ['succes' => true, 'message' => 'Inscription annulée.'];
    }

    // -----------------------------------------------
    // Cours disponibles pour un étudiant (non inscrit)
    // -----------------------------------------------
    public function coursDisponibles(int $etudiantId): array {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.code, c.intitule, c.credits, c.capacite_max,
                    s.libelle AS semestre,
                    d.nom AS departement,
                    CONCAT(u.prenom, " ", u.nom) AS enseignant,
                    (SELECT COUNT(*) FROM inscriptions i2
                     WHERE i2.cours_id = c.id AND i2.statut = "active") AS inscrits,
                    (SELECT GROUP_CONCAT(DISTINCT
                              CONCAT(
                                ELT(sc.jour_semaine,
                                    "Lun.","Mar.","Mer.","Jeu.","Ven.","Sam.","Dim."),
                                " ",
                                TIME_FORMAT(sc.heure_debut, "%H:%i"),
                                "–",
                                TIME_FORMAT(sc.heure_fin,   "%H:%i"))
                              ORDER BY sc.jour_semaine, sc.heure_debut
                              SEPARATOR ", ")
                     FROM sessions_cours sc
                     WHERE sc.cours_id = c.id) AS creneau
             FROM cours c
             JOIN semestres s ON s.id = c.semestre_id
             LEFT JOIN departements d ON d.id = c.departement_id
             LEFT JOIN enseignants e ON e.id = c.enseignant_id
             LEFT JOIN utilisateurs u ON u.id = e.utilisateur_id
             WHERE s.archive = 0
               AND c.id NOT IN (
                   SELECT cours_id FROM inscriptions
                   WHERE etudiant_id = :eid AND statut = "active"
               )
             ORDER BY s.libelle, c.intitule'
        );
        $stmt->execute([':eid' => $etudiantId]);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Cours suivis par un étudiant
    // -----------------------------------------------
    public function coursSuivis(int $etudiantId): array {
        Auth::exiger('etudiant', 'admin', 'enseignant');

        $stmt = $this->pdo->prepare(
            'SELECT i.id AS inscription_id, i.date_inscription, i.statut,
                    c.code, c.intitule, c.credits,
                    s.libelle AS semestre,
                    CONCAT(u.prenom, " ", u.nom) AS enseignant,
                    (SELECT GROUP_CONCAT(DISTINCT
                              CONCAT(
                                ELT(sc.jour_semaine,
                                    "Lun.","Mar.","Mer.","Jeu.","Ven.","Sam.","Dim."),
                                " ",
                                TIME_FORMAT(sc.heure_debut, "%H:%i"),
                                "–",
                                TIME_FORMAT(sc.heure_fin,   "%H:%i"))
                              ORDER BY sc.jour_semaine, sc.heure_debut
                              SEPARATOR ", ")
                     FROM sessions_cours sc
                     WHERE sc.cours_id = c.id) AS creneau
             FROM inscriptions i
             JOIN cours c ON c.id = i.cours_id
             JOIN semestres s ON s.id = c.semestre_id
             LEFT JOIN enseignants e ON e.id = c.enseignant_id
             LEFT JOIN utilisateurs u ON u.id = e.utilisateur_id
             WHERE i.etudiant_id = :eid
             ORDER BY s.libelle, c.intitule'
        );
        $stmt->execute([':eid' => $etudiantId]);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Étudiants inscrits à un cours (enseignant / admin)
    // -----------------------------------------------
    public function etudiantsDuCours(int $coursId): array {
        Auth::exiger('enseignant', 'admin');

        $stmt = $this->pdo->prepare(
            'SELECT i.id AS inscription_id, i.date_inscription,
                    et.numero_etudiant, et.niveau,
                    CONCAT(u.prenom, " ", u.nom) AS etudiant,
                    u.email
             FROM inscriptions i
             JOIN etudiants et ON et.id = i.etudiant_id
             JOIN utilisateurs u ON u.id = et.utilisateur_id
             WHERE i.cours_id = :cid AND i.statut = "active"
             ORDER BY u.nom, u.prenom'
        );
        $stmt->execute([':cid' => $coursId]);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // RÈGLE 3 : vérification des prérequis
    // -----------------------------------------------
    private function verifierPrerequis(int $etudiantId, int $coursId): array {
        // Récupérer les prérequis du cours
        $stmt = $this->pdo->prepare(
            'SELECT p.prerequis_id, c.intitule
             FROM prerequis p
             JOIN cours c ON c.id = p.prerequis_id
             WHERE p.cours_id = :cid'
        );
        $stmt->execute([':cid' => $coursId]);
        $prerequis = $stmt->fetchAll();

        if (empty($prerequis)) return [];

        $manquants = [];
        foreach ($prerequis as $pr) {
            // Vérifier que l'étudiant a été inscrit et a une note moyenne >= 10
            $stmt = $this->pdo->prepare(
                'SELECT AVG(n.valeur * n.coefficient) / AVG(n.coefficient) AS moyenne
                 FROM inscriptions i
                 JOIN notes n ON n.inscription_id = i.id
                 WHERE i.etudiant_id = :eid
                   AND i.cours_id = :cid
                   AND i.statut = "active"'
            );
            $stmt->execute([':eid' => $etudiantId, ':cid' => $pr['prerequis_id']]);
            $row = $stmt->fetch();

            if (!$row || $row['moyenne'] === null || (float)$row['moyenne'] < 10.0) {
                $manquants[] = $pr['intitule'];
            }
        }
        return $manquants;
    }

    // -----------------------------------------------
    // RÈGLE 4 : détection de conflit d'emploi du temps
    // -----------------------------------------------
    private function detecterConflitEmploiDuTemps(int $etudiantId, int $coursId): ?string {
        // Sessions du cours demandé
        $stmtNouv = $this->pdo->prepare(
            'SELECT jour_semaine, heure_debut, heure_fin
             FROM sessions_cours WHERE cours_id = :cid'
        );
        $stmtNouv->execute([':cid' => $coursId]);
        $sessionsNouveau = $stmtNouv->fetchAll();

        if (empty($sessionsNouveau)) return null;

        // Sessions des cours auxquels l'étudiant est déjà inscrit
        $stmtExist = $this->pdo->prepare(
            'SELECT sc.jour_semaine, sc.heure_debut, sc.heure_fin, c.intitule
             FROM sessions_cours sc
             JOIN cours c ON c.id = sc.cours_id
             JOIN inscriptions i ON i.cours_id = c.id
             WHERE i.etudiant_id = :eid AND i.statut = "active"'
        );
        $stmtExist->execute([':eid' => $etudiantId]);
        $sessionsExistantes = $stmtExist->fetchAll();

        foreach ($sessionsNouveau as $sn) {
            foreach ($sessionsExistantes as $se) {
                if ($sn['jour_semaine'] !== $se['jour_semaine']) continue;
                // Chevauchement temporel
                if ($sn['heure_debut'] < $se['heure_fin'] &&
                    $sn['heure_fin']   > $se['heure_debut']) {
                    return $se['intitule'];
                }
            }
        }
        return null;
    }

    // -----------------------------------------------
    // Notification à l'enseignant
    // -----------------------------------------------
    private function notifierEnseignantNouvelInscrit(array $cours, int $etudiantId): void {
        if (!$cours['enseignant_id']) return;

        $stmtE = $this->pdo->prepare(
            'SELECT utilisateur_id FROM enseignants WHERE id = :id'
        );
        $stmtE->execute([':id' => $cours['enseignant_id']]);
        $ens = $stmtE->fetch();
        if (!$ens) return;

        $stmtU = $this->pdo->prepare(
            'SELECT CONCAT(u.prenom," ",u.nom) AS nom_complet
             FROM etudiants et JOIN utilisateurs u ON u.id = et.utilisateur_id
             WHERE et.id = :eid'
        );
        $stmtU->execute([':eid' => $etudiantId]);
        $etudiant = $stmtU->fetch();

        $this->pdo->prepare(
            'INSERT INTO notifications (utilisateur_id, type, contenu)
             VALUES (:uid, "nouveau_inscrit",
             :contenu)'
        )->execute([
            ':uid'     => $ens['utilisateur_id'],
            ':contenu' => ($etudiant['nom_complet'] ?? 'Un étudiant')
                          . ' s\'est inscrit au cours ' . $cours['intitule'],
        ]);
    }

    // -----------------------------------------------
    // Récupérer un cours avec ses métadonnées
    // -----------------------------------------------
    private function getCours(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, s.archive
             FROM cours c
             JOIN semestres s ON s.id = c.semestre_id
             WHERE c.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
