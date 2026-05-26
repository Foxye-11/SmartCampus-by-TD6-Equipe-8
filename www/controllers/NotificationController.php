<?php
// controllers/NotificationController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';

class NotificationController {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    // -----------------------------------------------
    // Lister les notifications de l'utilisateur connecté
    // -----------------------------------------------
    public function lister(bool $nonLuesSeulement = false): array {
        Auth::exiger();

        $sql = 'SELECT id, type, contenu, lue, date_creation
                FROM notifications
                WHERE utilisateur_id = :uid';

        if ($nonLuesSeulement) {
            $sql .= ' AND lue = 0';
        }

        $sql .= ' ORDER BY date_creation DESC LIMIT 50';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uid' => $_SESSION['user_id']]);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Marquer une notification comme lue
    // -----------------------------------------------
    public function marquerLue(int $notifId): array {
        Auth::exiger();

        $stmt = $this->pdo->prepare(
            'UPDATE notifications SET lue = 1
             WHERE id = :id AND utilisateur_id = :uid'
        );
        $stmt->execute([':id' => $notifId, ':uid' => $_SESSION['user_id']]);

        if ($stmt->rowCount() === 0) {
            return ['succes' => false, 'erreur' => 'Notification introuvable.'];
        }
        return ['succes' => true];
    }

    // -----------------------------------------------
    // Marquer toutes les notifications comme lues
    // -----------------------------------------------
    public function toutMarquerLues(): array {
        Auth::exiger();

        $this->pdo->prepare(
            'UPDATE notifications SET lue = 1 WHERE utilisateur_id = :uid'
        )->execute([':uid' => $_SESSION['user_id']]);

        return ['succes' => true];
    }

    // -----------------------------------------------
    // Compter les notifications non lues
    // -----------------------------------------------
    public function compterNonLues(): int {
        Auth::exiger();

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS nb FROM notifications
             WHERE utilisateur_id = :uid AND lue = 0'
        );
        $stmt->execute([':uid' => $_SESSION['user_id']]);
        return (int) $stmt->fetch()['nb'];
    }

    // -----------------------------------------------
    // Supprimer une notification
    // -----------------------------------------------
    public function supprimer(int $notifId): array {
        Auth::exiger();

        $stmt = $this->pdo->prepare(
            'DELETE FROM notifications WHERE id = :id AND utilisateur_id = :uid'
        );
        $stmt->execute([':id' => $notifId, ':uid' => $_SESSION['user_id']]);

        if ($stmt->rowCount() === 0) {
            return ['succes' => false, 'erreur' => 'Notification introuvable.'];
        }
        return ['succes' => true];
    }

    // -----------------------------------------------
    // Créer une notification (usage interne / admin)
    // -----------------------------------------------
    public function creer(int $utilisateurId, string $type, string $contenu): array {
        Auth::exiger('admin', 'enseignant');

        if (empty($type) || empty($contenu)) {
            return ['succes' => false, 'erreur' => 'Type et contenu requis.'];
        }

        $this->pdo->prepare(
            'INSERT INTO notifications (utilisateur_id, type, contenu)
             VALUES (:uid, :type, :contenu)'
        )->execute([
            ':uid'     => $utilisateurId,
            ':type'    => $type,
            ':contenu' => $contenu,
        ]);

        return ['succes' => true, 'id' => (int) $this->pdo->lastInsertId()];
    }
}
