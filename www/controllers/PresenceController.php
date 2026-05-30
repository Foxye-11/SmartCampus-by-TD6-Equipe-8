<?php
// controllers/PresenceController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';

class PresenceController {

    private PDO  $pdo;
    // Seuil d'alerte : au-delà de ce % d'absences, une alerte est déclenchée
    private const SEUIL_ABSENCES = 30.0;

    public function __construct() {
        $this->pdo = getDB();
    }

    // -----------------------------------------------
    // Enregistrer la présence pour une session entière
    // (l'enseignant soumet la liste d'une session)
    // data = ['session_id' => int, 'presences' => [['inscription_id' => int, 'statut' => string], ...]]
    // -----------------------------------------------
    public function enregistrerSession(array $data): array {
        Auth::exiger('enseignant', 'admin');

        $sessionId = isset($data['session_id']) ? (int)$data['session_id'] : 0;
        $presences = $data['presences'] ?? [];

        if (!$sessionId || empty($presences)) {
            return ['succes' => false, 'erreur' => 'session_id et presences sont requis.'];
        }

        // Vérifier que la session appartient bien à un cours de cet enseignant
        if (Auth::getRole() === 'enseignant') {
            $stmt = $this->pdo->prepare(
                'SELECT sc.id
                 FROM sessions_cours sc
                 JOIN cours c ON c.id = sc.cours_id
                 JOIN enseignants e ON e.id = c.enseignant_id
                 WHERE sc.id = :sid AND e.utilisateur_id = :uid'
            );
            $stmt->execute([':sid' => $sessionId, ':uid' => $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                return ['succes' => false, 'erreur' => 'Session non autorisée.'];
            }
        }

        // Synchroniser les inscriptions implicites (cours_groupes) avant
        // l'upsert : le front a pu construire la liste a partir d'une vue
        // qui ne tenait pas compte des inscriptions auto-creees.
        $stmt = $this->pdo->prepare('SELECT cours_id FROM sessions_cours WHERE id = :id');
        $stmt->execute([':id' => $sessionId]);
        $rowC = $stmt->fetch();
        if ($rowC) {
            $this->syncInscriptionsDesGroupes((int)$rowC['cours_id']);
        }

        $statutsValides = ['present', 'absent', 'retard', 'excuse'];
        $erreurs        = [];
        $enregistres    = 0;

        $this->pdo->beginTransaction();
        try {
            foreach ($presences as $p) {
                $inscriptionId = isset($p['inscription_id']) ? (int)$p['inscription_id'] : 0;
                $statut        = $p['statut'] ?? 'present';

                if (!$inscriptionId || !in_array($statut, $statutsValides, true)) {
                    $erreurs[] = "Donnée invalide pour l'inscription $inscriptionId.";
                    continue;
                }

                // Upsert : insérer ou mettre à jour si déjà saisi
                $stmt = $this->pdo->prepare(
                    'INSERT INTO presences (inscription_id, session_id, statut)
                     VALUES (:iid, :sid, :statut)
                     ON DUPLICATE KEY UPDATE statut = VALUES(statut),
                                             date_enregistrement = NOW()'
                );
                $stmt->execute([
                    ':iid'    => $inscriptionId,
                    ':sid'    => $sessionId,
                    ':statut' => $statut,
                ]);
                $enregistres++;

                // Vérifier seuil d'alerte si absence
                if (in_array($statut, ['absent'], true)) {
                    $this->verifierSeuilAbsences($inscriptionId);
                }
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['succes' => false, 'erreur' => 'Erreur lors de l\'enregistrement.'];
        }

        return [
            'succes'      => true,
            'enregistres' => $enregistres,
            'erreurs'     => $erreurs,
        ];
    }

    // -----------------------------------------------
    // Modifier une présence individuelle
    // -----------------------------------------------
    public function modifier(int $presenceId, string $statut): array {
        Auth::exiger('enseignant', 'admin');

        $statutsValides = ['present', 'absent', 'retard', 'excuse'];
        if (!in_array($statut, $statutsValides, true)) {
            return ['succes' => false, 'erreur' => 'Statut invalide.'];
        }

        $stmt = $this->pdo->prepare('SELECT * FROM presences WHERE id = :id');
        $stmt->execute([':id' => $presenceId]);
        $presence = $stmt->fetch();

        if (!$presence) {
            return ['succes' => false, 'erreur' => 'Présence introuvable.'];
        }

        $this->pdo->prepare(
            'UPDATE presences SET statut = :statut, date_enregistrement = NOW()
             WHERE id = :id'
        )->execute([':statut' => $statut, ':id' => $presenceId]);

        if ($statut === 'absent') {
            $this->verifierSeuilAbsences((int)$presence['inscription_id']);
        }

        return ['succes' => true];
    }

    // -----------------------------------------------
    // Présences d'un étudiant (toutes ou par cours)
    // -----------------------------------------------
    public function presencesEtudiant(int $etudiantId, ?int $coursId = null): array {
        Auth::exiger('etudiant', 'enseignant', 'admin');

        if (Auth::getRole() === 'etudiant' && $_SESSION['etudiant_id'] !== $etudiantId) {
            return [];
        }

        $sql = 'SELECT p.id, p.statut, p.date_enregistrement,
                       sc.jour_semaine, sc.heure_debut, sc.heure_fin, sc.date_specifique,
                       c.code, c.intitule AS cours,
                       s.nom AS salle
                FROM presences p
                JOIN inscriptions i ON i.id = p.inscription_id
                JOIN sessions_cours sc ON sc.id = p.session_id
                JOIN cours c ON c.id = i.cours_id
                LEFT JOIN salles s ON s.id = sc.salle_id
                WHERE i.etudiant_id = :eid';

        $params = [':eid' => $etudiantId];

        if ($coursId !== null) {
            $sql .= ' AND i.cours_id = :cid';
            $params[':cid'] = $coursId;
        }

        $sql .= ' ORDER BY c.intitule, sc.date_specifique, sc.heure_debut';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Détail complet d'un cours pour un étudiant :
    // TOUTES les séances (passées + à venir) chronologiquement,
    // avec le statut de présence si elle a déjà été enregistrée.
    // Pour les séances futures (ou non encore appelées), p.statut = NULL.
    // -----------------------------------------------
    public function seancesEtudiantParCours(int $etudiantId, int $coursId): array {
        Auth::exiger('etudiant', 'enseignant', 'admin');

        if (Auth::getRole() === 'etudiant' && $_SESSION['etudiant_id'] !== $etudiantId) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT sc.id              AS session_id,
                    sc.date_specifique AS date,
                    sc.jour_semaine,
                    sc.heure_debut,
                    sc.heure_fin,
                    s.nom              AS salle,
                    s.batiment,
                    p.id               AS presence_id,
                    p.statut,
                    p.date_enregistrement
             FROM inscriptions i
             JOIN sessions_cours sc ON sc.cours_id = i.cours_id
             LEFT JOIN salles s   ON s.id = sc.salle_id
             LEFT JOIN presences p ON p.session_id = sc.id
                                  AND p.inscription_id = i.id
             WHERE i.etudiant_id = :eid
               AND i.cours_id = :cid
               AND i.statut = "active"
               AND sc.date_specifique IS NOT NULL
             ORDER BY sc.date_specifique, sc.heure_debut'
        );
        $stmt->execute([':eid' => $etudiantId, ':cid' => $coursId]);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Présences pour une session donnée (liste enseignant)
    //
    // Un cours peut etre rattache a un groupe TD via 'cours_groupes'
    // sans qu'il existe forcement une ligne 'inscriptions' pour chaque
    // etudiant du groupe. Pour que la liste de prise d'appel reste
    // complete, on materialise (lazily) ces inscriptions implicites
    // avant de lire la table 'presences'.
    // -----------------------------------------------
    public function presencesSession(int $sessionId): array {
        Auth::exiger('enseignant', 'admin');

        // 1) Recuperer le cours de la seance
        $stmt = $this->pdo->prepare('SELECT cours_id FROM sessions_cours WHERE id = :id');
        $stmt->execute([':id' => $sessionId]);
        $row = $stmt->fetch();
        if (!$row) return [];
        $coursId = (int)$row['cours_id'];

        // 2) Synchroniser les inscriptions implicites (via cours_groupes).
        //    Idempotent grace a la cle UNIQUE (etudiant_id, cours_id).
        $this->syncInscriptionsDesGroupes($coursId);

        // 3) Lire les inscrits + statut de presence (LEFT JOIN sur presences)
        $stmt = $this->pdo->prepare(
            'SELECT p.id AS presence_id, p.statut,
                    et.numero_etudiant,
                    CONCAT(u.prenom, " ", u.nom) AS etudiant,
                    i.id AS inscription_id
             FROM inscriptions i
             JOIN etudiants et ON et.id = i.etudiant_id
             JOIN utilisateurs u ON u.id = et.utilisateur_id
             LEFT JOIN presences p ON p.inscription_id = i.id AND p.session_id = :sid
             WHERE i.cours_id = :cid AND i.statut = "active"
             ORDER BY u.nom, u.prenom'
        );
        $stmt->execute([':sid' => $sessionId, ':cid' => $coursId]);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Materialise les "inscriptions implicites" : pour chaque etudiant
    // dont le groupe TD est affecte au cours via cours_groupes, on cree
    // (si elle n'existe pas) la ligne 'inscriptions' correspondante.
    // L'UNIQUE KEY (etudiant_id, cours_id) garantit l'idempotence.
    // -----------------------------------------------
    private function syncInscriptionsDesGroupes(int $coursId): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inscriptions (etudiant_id, cours_id, statut)
             SELECT DISTINCT et.id, :cid_select, "active"
             FROM etudiants et
             JOIN cours_groupes cg ON cg.groupe_td_id = et.groupe_td_id
             JOIN utilisateurs u ON u.id = et.utilisateur_id
             WHERE cg.cours_id = :cid_where AND u.actif = 1
             ON DUPLICATE KEY UPDATE inscriptions.id = inscriptions.id'
        );
        $stmt->execute([':cid_select' => $coursId, ':cid_where' => $coursId]);
    }

    // -----------------------------------------------
    // Résumé des absences d'un étudiant par cours
    // -----------------------------------------------
    public function resumeAbsences(int $etudiantId): array {
        Auth::exiger('etudiant', 'enseignant', 'admin');

        if (Auth::getRole() === 'etudiant' && $_SESSION['etudiant_id'] !== $etudiantId) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT c.id AS cours_id, c.code, c.intitule,
                    COUNT(p.id)                                         AS total_seances,
                    SUM(p.statut = "present")                           AS presents,
                    SUM(p.statut = "absent")                            AS absents,
                    SUM(p.statut = "retard")                            AS retards,
                    SUM(p.statut = "excuse")                            AS excuses,
                    ROUND(SUM(p.statut = "absent") * 100.0 / COUNT(p.id), 1) AS taux_absence
             FROM presences p
             JOIN inscriptions i ON i.id = p.inscription_id
             JOIN cours c ON c.id = i.cours_id
             WHERE i.etudiant_id = :eid AND i.statut = "active"
             GROUP BY c.id, c.code, c.intitule
             ORDER BY c.intitule'
        );
        $stmt->execute([':eid' => $etudiantId]);
        $resume = $stmt->fetchAll();

        // Ajouter un flag d'alerte si seuil dépassé
        foreach ($resume as &$r) {
            $r['alerte'] = (float)$r['taux_absence'] >= self::SEUIL_ABSENCES;
        }
        unset($r);

        return $resume;
    }

    // -----------------------------------------------
    // Étudiants en alerte d'absences pour un cours
    // -----------------------------------------------
    public function etudiantsEnAlerte(int $coursId): array {
        Auth::exiger('enseignant', 'admin');

        $stmt = $this->pdo->prepare(
            'SELECT et.numero_etudiant,
                    CONCAT(u.prenom, " ", u.nom) AS etudiant,
                    u.email,
                    COUNT(p.id) AS total_seances,
                    SUM(p.statut = "absent") AS absents,
                    ROUND(SUM(p.statut = "absent") * 100.0 / COUNT(p.id), 1) AS taux_absence
             FROM presences p
             JOIN inscriptions i ON i.id = p.inscription_id
             JOIN etudiants et ON et.id = i.etudiant_id
             JOIN utilisateurs u ON u.id = et.utilisateur_id
             WHERE i.cours_id = :cid AND i.statut = "active"
             GROUP BY et.id, et.numero_etudiant, u.prenom, u.nom, u.email
             HAVING taux_absence >= :seuil
             ORDER BY taux_absence DESC'
        );
        $stmt->execute([
            ':cid'   => $coursId,
            ':seuil' => self::SEUIL_ABSENCES,
        ]);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Étudiants en alerte d'absences pour TOUS les cours
    // d'un enseignant (utilisé par le dashboard).
    // -----------------------------------------------
    public function alertesEnseignant(int $enseignantId): array {
        Auth::exiger('enseignant', 'admin');

        $stmt = $this->pdo->prepare(
            'SELECT c.id AS cours_id, c.code, c.intitule AS cours,
                    et.id AS etudiant_id, et.numero_etudiant,
                    CONCAT(u.prenom, " ", u.nom) AS etudiant,
                    u.email,
                    COUNT(p.id) AS total_seances,
                    SUM(p.statut = "absent") AS absents,
                    ROUND(SUM(p.statut = "absent") * 100.0 / COUNT(p.id), 1) AS taux_absence
             FROM presences p
             JOIN inscriptions i ON i.id = p.inscription_id
             JOIN etudiants et   ON et.id = i.etudiant_id
             JOIN utilisateurs u ON u.id = et.utilisateur_id
             JOIN cours c        ON c.id = i.cours_id
             WHERE c.enseignant_id = :eid AND i.statut = "active"
             GROUP BY c.id, c.code, c.intitule, et.id, et.numero_etudiant, u.prenom, u.nom, u.email
             HAVING taux_absence >= :seuil
             ORDER BY taux_absence DESC, c.intitule, u.nom, u.prenom'
        );
        $stmt->execute([
            ':eid'   => $enseignantId,
            ':seuil' => self::SEUIL_ABSENCES,
        ]);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // RÈGLE MÉTIER : vérification du seuil d'absences
    // Crée une notification si le seuil est dépassé
    // -----------------------------------------------
    private function verifierSeuilAbsences(int $inscriptionId): void {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total,
                    SUM(statut = "absent") AS absents
             FROM presences
             WHERE inscription_id = :iid'
        );
        $stmt->execute([':iid' => $inscriptionId]);
        $row = $stmt->fetch();

        if (!$row || (int)$row['total'] === 0) return;

        $taux = ((float)$row['absents'] / (float)$row['total']) * 100;
        if ($taux < self::SEUIL_ABSENCES) return;

        // Récupérer l'étudiant (avec son nom complet) et le cours
        $stmt = $this->pdo->prepare(
            'SELECT et.utilisateur_id, et.numero_etudiant,
                    CONCAT(u.prenom, " ", u.nom) AS etudiant_nom,
                    c.intitule AS cours, c.enseignant_id,
                    ens.utilisateur_id AS enseignant_uid
             FROM inscriptions i
             JOIN etudiants et    ON et.id = i.etudiant_id
             JOIN utilisateurs u  ON u.id = et.utilisateur_id
             JOIN cours c         ON c.id = i.cours_id
             LEFT JOIN enseignants ens ON ens.id = c.enseignant_id
             WHERE i.id = :iid'
        );
        $stmt->execute([':iid' => $inscriptionId]);
        $info = $stmt->fetch();
        if (!$info) return;

        $contenu = sprintf(
            'Alerte : votre taux d\'absence en "%s" a atteint %.1f%% (seuil : %d%%).',
            $info['cours'], $taux, self::SEUIL_ABSENCES
        );

        // Notifier l'étudiant
        $this->insererNotification($info['utilisateur_id'], 'alerte_absence', $contenu);

        // Notifier l'enseignant — on nomme explicitement l'étudiant pour qu'il
        // puisse identifier de qui il s'agit sans avoir à chercher.
        if ($info['enseignant_uid']) {
            $this->insererNotification(
                $info['enseignant_uid'],
                'alerte_absence',
                sprintf(
                    '%s (%s) dépasse le seuil d\'absences en "%s" : %.1f%%.',
                    $info['etudiant_nom'], $info['numero_etudiant'],
                    $info['cours'], $taux
                )
            );
        }
    }

    private function insererNotification(int $utilisateurId, string $type, string $contenu): void {
        // Éviter les doublons de notifications le même jour
        $stmt = $this->pdo->prepare(
            'SELECT id FROM notifications
             WHERE utilisateur_id = :uid AND type = :type
               AND contenu = :contenu
               AND DATE(date_creation) = CURDATE()'
        );
        $stmt->execute([':uid' => $utilisateurId, ':type' => $type, ':contenu' => $contenu]);
        if ($stmt->fetch()) return;

        $this->pdo->prepare(
            'INSERT INTO notifications (utilisateur_id, type, contenu)
             VALUES (:uid, :type, :contenu)'
        )->execute([':uid' => $utilisateurId, ':type' => $type, ':contenu' => $contenu]);
    }
}
