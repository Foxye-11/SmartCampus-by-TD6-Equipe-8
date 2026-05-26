<?php
// controllers/EnseignantController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';

class EnseignantController {

    private PDO $pdo;

    public function __construct() { $this->pdo = getDB(); }

    public function lister(): array {
        Auth::exiger('admin', 'etudiant', 'enseignant');
        $stmt = $this->pdo->query(
            'SELECT e.id, e.grade, e.specialite, e.departement,
                    u.nom, u.prenom, u.email, u.actif,
                    (SELECT COUNT(*) FROM cours c WHERE c.enseignant_id=e.id) AS nb_cours
             FROM enseignants e
             JOIN utilisateurs u ON u.id = e.utilisateur_id
             WHERE u.actif=1
             ORDER BY u.nom, u.prenom'
        );
        return $stmt->fetchAll();
    }

    public function obtenir(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, u.nom, u.prenom, u.email, u.actif
             FROM enseignants e
             JOIN utilisateurs u ON u.id = e.utilisateur_id
             WHERE e.id=:id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function creer(array $data): array {
        Auth::exiger('admin');
        $erreurs = $this->valider($data);
        if (!empty($erreurs)) return ['succes' => false, 'erreurs' => $erreurs];

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role_id, actif)
                 VALUES (:nom, :prenom, :email, :mdp,
                         (SELECT id FROM roles WHERE nom="enseignant"), 1)'
            );
            $stmt->execute([
                ':nom'    => trim($data['nom']),
                ':prenom' => trim($data['prenom']),
                ':email'  => strtolower(trim($data['email'])),
                ':mdp'    => password_hash($data['mot_de_passe'] ?? 'Enseignant2024!', PASSWORD_BCRYPT, ['cost'=>12]),
            ]);
            $userId = (int)$this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare(
                'INSERT INTO enseignants (utilisateur_id, grade, specialite, departement)
                 VALUES (:uid, :grade, :specialite, :departement)'
            );
            $stmt->execute([
                ':uid'          => $userId,
                ':grade'        => $data['grade'] ?? 'Maître de conférences',
                ':specialite'   => $data['specialite'] ?? null,
                ':departement'  => $data['departement'] ?? null,
            ]);
            $enseignantId = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
            return ['succes' => true, 'id' => $enseignantId];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['succes' => false, 'erreurs' => ['Erreur lors de la création.']];
        }
    }

    public function modifier(int $id, array $data): array {
        Auth::exiger('admin');
        $ens = $this->obtenir($id);
        if (!$ens) return ['succes' => false, 'erreurs' => ['Enseignant introuvable.']];

        $this->pdo->prepare(
            'UPDATE utilisateurs SET nom=:nom, prenom=:prenom, email=:email WHERE id=:uid'
        )->execute([
            ':nom'    => trim($data['nom']),
            ':prenom' => trim($data['prenom']),
            ':email'  => strtolower(trim($data['email'])),
            ':uid'    => $ens['utilisateur_id'],
        ]);
        $this->pdo->prepare(
            'UPDATE enseignants SET grade=:grade, specialite=:specialite, departement=:dept WHERE id=:id'
        )->execute([
            ':grade'       => $data['grade'] ?? '',
            ':specialite'  => $data['specialite'] ?? null,
            ':dept'        => $data['departement'] ?? null,
            ':id'          => $id,
        ]);
        return ['succes' => true];
    }

    public function supprimer(int $id): array {
        Auth::exiger('admin');
        $ens = $this->obtenir($id);
        if (!$ens) return ['succes' => false, 'erreurs' => ['Enseignant introuvable.']];
        $this->pdo->prepare('UPDATE utilisateurs SET actif=0 WHERE id=:uid')
                  ->execute([':uid' => $ens['utilisateur_id']]);
        return ['succes' => true];
    }

    public function coursDEnseignant(int $id): array {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.code, c.intitule, c.credits, s.libelle AS semestre,
                    (SELECT COUNT(*) FROM inscriptions i WHERE i.cours_id=c.id AND i.statut="active") AS inscrits
             FROM cours c
             JOIN semestres s ON s.id = c.semestre_id
             WHERE c.enseignant_id=:id
             ORDER BY s.libelle, c.intitule'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    private function valider(array $data): array {
        $erreurs = [];
        if (empty($data['nom'])) $erreurs[] = 'Nom requis.';
        if (empty($data['prenom'])) $erreurs[] = 'Prénom requis.';
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
            $erreurs[] = 'Email valide requis.';
        else {
            $stmt = $this->pdo->prepare('SELECT id FROM utilisateurs WHERE email=:e');
            $stmt->execute([':e' => strtolower(trim($data['email']))]);
            if ($stmt->fetch()) $erreurs[] = 'Cet email est déjà utilisé.';
        }
        return $erreurs;
    }
}