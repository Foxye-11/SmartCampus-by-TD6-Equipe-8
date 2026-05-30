<?php
// auth/Auth.php

require_once __DIR__ . '/../config/database.php';

class Auth {

    /**
     * Démarre la session de façon sécurisée.
     */
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => false,   // passer à true en HTTPS
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    /**
     * Connexion d'un utilisateur.
     * Retourne ['succes' => true, 'role' => ...] ou ['succes' => false, 'erreur' => ...]
     */
    public static function login(string $email, string $motDePasse): array {
        self::startSession();

        $email = trim(strtolower($email));

        if (empty($email) || empty($motDePasse)) {
            return ['succes' => false, 'erreur' => 'Email et mot de passe requis.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['succes' => false, 'erreur' => 'Format d\'email invalide.'];
        }

        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.nom, u.prenom, u.email, u.mot_de_passe,
                    u.actif, r.nom AS role
             FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email
             LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $utilisateur = $stmt->fetch();

        if (!$utilisateur) {
            return ['succes' => false, 'erreur' => 'Identifiants incorrects.'];
        }

        if (!$utilisateur['actif']) {
            return ['succes' => false, 'erreur' => 'Compte désactivé. Contactez l\'administration.'];
        }

        if (!password_verify($motDePasse, $utilisateur['mot_de_passe'])) {
            return ['succes' => false, 'erreur' => 'Identifiants incorrects.'];
        }

        // Régénérer l'ID de session pour prévenir la fixation de session
        session_regenerate_id(true);

        $_SESSION['user_id']    = $utilisateur['id'];
        $_SESSION['user_nom']   = $utilisateur['nom'];
        $_SESSION['user_prenom']= $utilisateur['prenom'];
        $_SESSION['user_email'] = $utilisateur['email'];
        $_SESSION['user_role']  = $utilisateur['role'];

        // Récupérer l'ID métier selon le rôle
        if ($utilisateur['role'] === 'etudiant') {
            $s = $pdo->prepare('SELECT id FROM etudiants WHERE utilisateur_id = :uid');
            $s->execute([':uid' => $utilisateur['id']]);
            $row = $s->fetch();
            $_SESSION['etudiant_id'] = $row ? $row['id'] : null;
        } elseif ($utilisateur['role'] === 'enseignant') {
            $s = $pdo->prepare('SELECT id FROM enseignants WHERE utilisateur_id = :uid');
            $s->execute([':uid' => $utilisateur['id']]);
            $row = $s->fetch();
            $_SESSION['enseignant_id'] = $row ? $row['id'] : null;
        }

        return ['succes' => true, 'role' => $utilisateur['role']];
    }

    /**
     * Déconnexion complète.
     */
    public static function logout(): void {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Vérifie si un utilisateur est connecté.
     */
    public static function estConnecte(): bool {
        self::startSession();
        return isset($_SESSION['user_id']);
    }

    /**
     * Retourne le rôle de l'utilisateur connecté ou null.
     */
    public static function getRole(): ?string {
        self::startSession();
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Protège une page : redirige si non connecté ou rôle insuffisant.
     * Utilisation : Auth::exiger('admin');
     */
    public static function exiger(string ...$roles): void {
        self::startSession();
        if (!self::estConnecte()) {
            header('Location: /login.php');
            exit;
        }
        if (!empty($roles) && !in_array(self::getRole(), $roles, true)) {
            http_response_code(403);
            die(json_encode(['erreur' => 'Accès interdit.']));
        }
    }

    /**
     * Hash un mot de passe.
     */
    public static function hasherMotDePasse(string $motDePasse): string {
        return password_hash($motDePasse, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Retourne l'id de l'utilisateur connecté ou null.
     */
    public static function getUserId(): ?int {
        self::startSession();
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    /**
     * Changement de mot de passe par l'utilisateur lui-même.
     * Vérifie l'ancien mot de passe puis applique des règles de robustesse
     * minimales (longueur, présence de lettres et de chiffres).
     */
    public static function changerMotDePasse(string $ancien, string $nouveau, string $confirmation): array {
        self::startSession();

        if (!self::estConnecte()) {
            return ['succes' => false, 'erreur' => 'Non authentifié.'];
        }

        if (empty($ancien) || empty($nouveau) || empty($confirmation)) {
            return ['succes' => false, 'erreur' => 'Tous les champs sont requis.'];
        }
        if ($nouveau !== $confirmation) {
            return ['succes' => false, 'erreur' => 'La confirmation ne correspond pas au nouveau mot de passe.'];
        }
        if (strlen($nouveau) < 8) {
            return ['succes' => false, 'erreur' => 'Le mot de passe doit contenir au moins 8 caractères.'];
        }
        if (!preg_match('/[A-Za-z]/', $nouveau) || !preg_match('/\d/', $nouveau)) {
            return ['succes' => false, 'erreur' => 'Le mot de passe doit contenir au moins une lettre et un chiffre.'];
        }

        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT mot_de_passe FROM utilisateurs WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => self::getUserId()]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($ancien, $row['mot_de_passe'])) {
            return ['succes' => false, 'erreur' => 'Mot de passe actuel incorrect.'];
        }
        if (password_verify($nouveau, $row['mot_de_passe'])) {
            return ['succes' => false, 'erreur' => 'Le nouveau mot de passe doit être différent de l\'ancien.'];
        }

        $hash = self::hasherMotDePasse($nouveau);
        $upd = $pdo->prepare('UPDATE utilisateurs SET mot_de_passe = :mdp WHERE id = :id');
        $upd->execute([':mdp' => $hash, ':id' => self::getUserId()]);

        return ['succes' => true, 'message' => 'Mot de passe modifié avec succès.'];
    }
}
