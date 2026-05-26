<?php
// controllers/MessageController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';

class MessageController {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    // -----------------------------------------------
    // Envoyer un message
    // -----------------------------------------------
    public function envoyer(array $data): array {
        Auth::exiger();

        $expediteurId   = (int) $_SESSION['user_id'];
        $destinataireId = isset($data['destinataire_id']) ? (int)$data['destinataire_id'] : 0;
        $sujet          = isset($data['sujet']) ? trim($data['sujet']) : '';
        $contenu        = isset($data['contenu']) ? trim($data['contenu']) : '';

        // Validation
        $erreurs = [];
        if (!$destinataireId) {
            $erreurs[] = 'Destinataire invalide.';
        }
        if ($expediteurId === $destinataireId) {
            $erreurs[] = 'Vous ne pouvez pas vous envoyer un message à vous-même.';
        }
        if (strlen($sujet) < 2 || strlen($sujet) > 200) {
            $erreurs[] = 'Le sujet doit contenir entre 2 et 200 caractères.';
        }
        if (strlen($contenu) < 5) {
            $erreurs[] = 'Le contenu est trop court.';
        }
        if (!empty($erreurs)) {
            return ['succes' => false, 'erreurs' => $erreurs];
        }

        // Vérifier que le destinataire existe et est actif
        $stmt = $this->pdo->prepare(
            'SELECT id FROM utilisateurs WHERE id = :id AND actif = 1'
        );
        $stmt->execute([':id' => $destinataireId]);
        if (!$stmt->fetch()) {
            return ['succes' => false, 'erreurs' => ['Destinataire introuvable.']];
        }

        // Restriction : un étudiant ne peut écrire qu'à ses enseignants ou à l'admin
        if (Auth::getRole() === 'etudiant') {
            if (!$this->etudiantPeutEcrireA($expediteurId, $destinataireId)) {
                return [
                    'succes'  => false,
                    'erreurs' => ['Vous ne pouvez écrire qu\'à vos enseignants ou à l\'administration.'],
                ];
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (expediteur_id, destinataire_id, sujet, contenu)
             VALUES (:exp, :dest, :sujet, :contenu)'
        );
        $stmt->execute([
            ':exp'     => $expediteurId,
            ':dest'    => $destinataireId,
            ':sujet'   => $sujet,
            ':contenu' => $contenu,
        ]);
        $messageId = (int) $this->pdo->lastInsertId();

        // Créer une notification pour le destinataire
        $this->pdo->prepare(
            'INSERT INTO notifications (utilisateur_id, type, contenu)
             VALUES (:uid, "nouveau_message", :contenu)'
        )->execute([
            ':uid'     => $destinataireId,
            ':contenu' => 'Nouveau message de '
                          . $_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']
                          . ' : ' . $sujet,
        ]);

        return ['succes' => true, 'id' => $messageId];
    }

    // -----------------------------------------------
    // Boîte de réception
    // -----------------------------------------------
    public function reception(int $page = 1, int $parPage = 20): array {
        Auth::exiger();

        $offset = ($page - 1) * $parPage;

        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.sujet, m.lu, m.date_envoi,
                    CONCAT(u.prenom, " ", u.nom) AS expediteur,
                    u.id AS expediteur_id
             FROM messages m
             JOIN utilisateurs u ON u.id = m.expediteur_id
             WHERE m.destinataire_id = :uid
             ORDER BY m.date_envoi DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':uid',    $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $parPage,             PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,              PDO::PARAM_INT);
        $stmt->execute();
        $messages = $stmt->fetchAll();

        // Nombre total pour la pagination
        $stmtCount = $this->pdo->prepare(
            'SELECT COUNT(*) AS nb FROM messages WHERE destinataire_id = :uid'
        );
        $stmtCount->execute([':uid' => $_SESSION['user_id']]);
        $total = (int) $stmtCount->fetch()['nb'];

        return [
            'messages'   => $messages,
            'total'      => $total,
            'page'       => $page,
            'par_page'   => $parPage,
            'non_lus'    => $this->compterNonLus(),
        ];
    }

    // -----------------------------------------------
    // Messages envoyés
    // -----------------------------------------------
    public function envoyes(int $page = 1, int $parPage = 20): array {
        Auth::exiger();

        $offset = ($page - 1) * $parPage;

        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.sujet, m.lu, m.date_envoi,
                    CONCAT(u.prenom, " ", u.nom) AS destinataire
             FROM messages m
             JOIN utilisateurs u ON u.id = m.destinataire_id
             WHERE m.expediteur_id = :uid
             ORDER BY m.date_envoi DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':uid',    $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $parPage,             PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,              PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Lire un message (marque comme lu)
    // -----------------------------------------------
    public function lire(int $messageId): array {
        Auth::exiger();

        $stmt = $this->pdo->prepare(
            'SELECT m.*, 
                    CONCAT(exp.prenom, " ", exp.nom) AS expediteur,
                    CONCAT(dest.prenom, " ", dest.nom) AS destinataire
             FROM messages m
             JOIN utilisateurs exp  ON exp.id  = m.expediteur_id
             JOIN utilisateurs dest ON dest.id = m.destinataire_id
             WHERE m.id = :id
               AND (m.destinataire_id = :uid OR m.expediteur_id = :uid)'
        );
        $stmt->execute([':id' => $messageId, ':uid' => $_SESSION['user_id']]);
        $message = $stmt->fetch();

        if (!$message) {
            return ['succes' => false, 'erreur' => 'Message introuvable ou accès non autorisé.'];
        }

        // Marquer comme lu si le destinataire le consulte
        if ((int)$message['destinataire_id'] === (int)$_SESSION['user_id'] && !$message['lu']) {
            $this->pdo->prepare('UPDATE messages SET lu = 1 WHERE id = :id')
                      ->execute([':id' => $messageId]);
            $message['lu'] = 1;
        }

        return ['succes' => true, 'message' => $message];
    }

    // -----------------------------------------------
    // Supprimer un message (uniquement le destinataire)
    // -----------------------------------------------
    public function supprimer(int $messageId): array {
        Auth::exiger();

        $stmt = $this->pdo->prepare(
            'SELECT id FROM messages
             WHERE id = :id AND destinataire_id = :uid'
        );
        $stmt->execute([':id' => $messageId, ':uid' => $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            return ['succes' => false, 'erreur' => 'Message introuvable ou non autorisé.'];
        }

        $this->pdo->prepare('DELETE FROM messages WHERE id = :id')
                  ->execute([':id' => $messageId]);

        return ['succes' => true];
    }

    // -----------------------------------------------
    // Liste des contacts disponibles selon le rôle
    // -----------------------------------------------
    public function contacts(): array {
        Auth::exiger();

        $role = Auth::getRole();
        $uid  = (int) $_SESSION['user_id'];

        if ($role === 'etudiant') {
            // Ses enseignants + admins
            $etudiantId = $_SESSION['etudiant_id'];
            $stmt = $this->pdo->prepare(
                'SELECT DISTINCT u.id, CONCAT(u.prenom, " ", u.nom) AS nom_complet,
                        r.nom AS role
                 FROM utilisateurs u
                 JOIN roles r ON r.id = u.role_id
                 WHERE u.actif = 1
                   AND (
                       r.nom = "admin"
                       OR u.id IN (
                           SELECT e.utilisateur_id
                           FROM enseignants e
                           JOIN cours c ON c.enseignant_id = e.id
                           JOIN inscriptions i ON i.cours_id = c.id
                           WHERE i.etudiant_id = :eid AND i.statut = "active"
                       )
                   )
                   AND u.id != :uid
                 ORDER BY nom_complet'
            );
            $stmt->execute([':eid' => $etudiantId, ':uid' => $uid]);
        } elseif ($role === 'enseignant') {
            // Ses étudiants + admins
            $enseignantId = $_SESSION['enseignant_id'];
            $stmt = $this->pdo->prepare(
                'SELECT DISTINCT u.id, CONCAT(u.prenom, " ", u.nom) AS nom_complet,
                        r.nom AS role
                 FROM utilisateurs u
                 JOIN roles r ON r.id = u.role_id
                 WHERE u.actif = 1
                   AND (
                       r.nom = "admin"
                       OR u.id IN (
                           SELECT et.utilisateur_id
                           FROM etudiants et
                           JOIN inscriptions i ON i.etudiant_id = et.id
                           JOIN cours c ON c.id = i.cours_id
                           WHERE c.enseignant_id = :eid AND i.statut = "active"
                       )
                   )
                   AND u.id != :uid
                 ORDER BY nom_complet'
            );
            $stmt->execute([':eid' => $enseignantId, ':uid' => $uid]);
        } else {
            // Admin : tous les utilisateurs actifs
            $stmt = $this->pdo->prepare(
                'SELECT u.id, CONCAT(u.prenom, " ", u.nom) AS nom_complet, r.nom AS role
                 FROM utilisateurs u
                 JOIN roles r ON r.id = u.role_id
                 WHERE u.actif = 1 AND u.id != :uid
                 ORDER BY r.nom, nom_complet'
            );
            $stmt->execute([':uid' => $uid]);
        }

        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Compter les messages non lus
    // -----------------------------------------------
    public function compterNonLus(): int {
        Auth::exiger();

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS nb FROM messages
             WHERE destinataire_id = :uid AND lu = 0'
        );
        $stmt->execute([':uid' => $_SESSION['user_id']]);
        return (int) $stmt->fetch()['nb'];
    }

    // -----------------------------------------------
    // Restriction : un étudiant ne peut écrire
    // qu'à ses enseignants ou aux admins
    // -----------------------------------------------
    private function etudiantPeutEcrireA(int $expediteurUid, int $destinataireUid): bool {
        $stmt = $this->pdo->prepare(
            'SELECT r.nom FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :uid'
        );
        $stmt->execute([':uid' => $destinataireUid]);
        $role = $stmt->fetch();

        if (!$role) return false;
        if ($role['nom'] === 'admin') return true;

        if ($role['nom'] === 'enseignant') {
            $etudiantId = $_SESSION['etudiant_id'];
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) AS nb
                 FROM inscriptions i
                 JOIN cours c ON c.id = i.cours_id
                 JOIN enseignants e ON e.id = c.enseignant_id
                 WHERE i.etudiant_id = :eid
                   AND e.utilisateur_id = :uid
                   AND i.statut = "active"'
            );
            $stmt->execute([':eid' => $etudiantId, ':uid' => $destinataireUid]);
            return $stmt->fetch()['nb'] > 0;
        }

        return false;
    }
}
