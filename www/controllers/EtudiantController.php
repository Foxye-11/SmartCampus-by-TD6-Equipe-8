<?php
// controllers/EtudiantController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';

class EtudiantController {

    private PDO $pdo;

    public function __construct() { $this->pdo = getDB(); }

    public function lister(array $filtres = []): array {
        Auth::exiger('admin', 'enseignant');
        $sql = 'SELECT et.id, et.numero_etudiant, et.niveau, et.annee_scolaire,
                       u.nom, u.prenom, u.email, u.actif,
                       d.nom AS departement,
                       et.ecole,
                       et.groupe_td_id,
                       CONCAT(g.niveau, " ", g.nom) AS groupe_td
                FROM etudiants et
                JOIN utilisateurs u ON u.id = et.utilisateur_id
                LEFT JOIN departements d ON d.id = et.departement_id
                LEFT JOIN groupes_td g ON g.id = et.groupe_td_id
                WHERE 1=1';
        $params = [];
        if (!empty($filtres['niveau'])) { $sql .= ' AND et.niveau=:niveau'; $params[':niveau'] = $filtres['niveau']; }
        if (!empty($filtres['departement_id'])) { $sql .= ' AND et.departement_id=:did'; $params[':did'] = $filtres['departement_id']; }
        if (!empty($filtres['groupe_td_id'])) { $sql .= ' AND et.groupe_td_id=:gtd'; $params[':gtd'] = $filtres['groupe_td_id']; }
        if (!empty($filtres['ecole'])) { $sql .= ' AND et.ecole=:eco'; $params[':eco'] = $filtres['ecole']; }
        if (!empty($filtres['recherche'])) {
            // PDO en mode non-émulé interdit la réutilisation d'un même placeholder
            $sql .= ' AND (u.nom LIKE :r1 OR u.prenom LIKE :r2 OR et.numero_etudiant LIKE :r3 OR u.email LIKE :r4)';
            $like = '%' . $filtres['recherche'] . '%';
            $params[':r1'] = $like;
            $params[':r2'] = $like;
            $params[':r3'] = $like;
            $params[':r4'] = $like;
        }
        $sql .= ' ORDER BY u.nom, u.prenom';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtenir(int $id): ?array {
        Auth::exiger('admin', 'enseignant', 'etudiant');
        if (Auth::getRole() === 'etudiant' && $_SESSION['etudiant_id'] !== $id) return null;
        $stmt = $this->pdo->prepare(
            'SELECT et.*, u.nom, u.prenom, u.email, u.actif, d.nom AS departement
             FROM etudiants et
             JOIN utilisateurs u ON u.id = et.utilisateur_id
             LEFT JOIN departements d ON d.id = et.departement_id
             WHERE et.id = :id'
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
            // Créer l'utilisateur
            $stmt = $this->pdo->prepare(
                'INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role_id, actif)
                 VALUES (:nom, :prenom, :email, :mdp,
                         (SELECT id FROM roles WHERE nom="etudiant"), 1)'
            );
            $stmt->execute([
                ':nom'    => trim($data['nom']),
                ':prenom' => trim($data['prenom']),
                ':email'  => strtolower(trim($data['email'])),
                ':mdp'    => password_hash($data['mot_de_passe'] ?? 'SmartCampus2024!', PASSWORD_BCRYPT, ['cost'=>12]),
            ]);
            $userId = (int)$this->pdo->lastInsertId();

            // Créer le profil étudiant
            $stmt = $this->pdo->prepare(
                'INSERT INTO etudiants (utilisateur_id, numero_etudiant, niveau,
                                        annee_scolaire, departement_id, ecole, groupe_td_id)
                 VALUES (:uid, :num, :niveau, :annee, :did, :eco, :gtd)'
            );
            $stmt->execute([
                ':uid'    => $userId,
                ':num'    => $this->genererNumeroEtudiant(),
                ':niveau' => $data['niveau'] ?? 'L1',
                ':annee'  => $data['annee_scolaire'] ?? date('Y') . '-' . (date('Y')+1),
                ':did'    => !empty($data['departement_id']) ? (int)$data['departement_id'] : null,
                ':eco'    => !empty($data['ecole']) ? trim($data['ecole']) : null,
                ':gtd'    => !empty($data['groupe_td_id']) ? (int)$data['groupe_td_id'] : null,
            ]);
            $etudiantId = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
            return ['succes' => true, 'id' => $etudiantId, 'utilisateur_id' => $userId];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['succes' => false, 'erreurs' => ['Erreur lors de la création.']];
        }
    }

    public function modifier(int $id, array $data): array {
        Auth::exiger('admin');
        $stmt = $this->pdo->prepare('SELECT utilisateur_id FROM etudiants WHERE id=:id');
        $stmt->execute([':id' => $id]);
        $et = $stmt->fetch();
        if (!$et) return ['succes' => false, 'erreurs' => ['Étudiant introuvable.']];

        $this->pdo->prepare(
            'UPDATE utilisateurs SET nom=:nom, prenom=:prenom, email=:email WHERE id=:uid'
        )->execute([
            ':nom'    => trim($data['nom']),
            ':prenom' => trim($data['prenom']),
            ':email'  => strtolower(trim($data['email'])),
            ':uid'    => $et['utilisateur_id'],
        ]);
        $this->pdo->prepare(
            'UPDATE etudiants SET niveau=:niveau, annee_scolaire=:annee, departement_id=:did,
                                  ecole=:eco, groupe_td_id=:gtd
             WHERE id=:id'
        )->execute([
            ':niveau' => $data['niveau'] ?? 'L1',
            ':annee'  => $data['annee_scolaire'] ?? '',
            ':did'    => !empty($data['departement_id']) ? (int)$data['departement_id'] : null,
            ':eco'    => !empty($data['ecole']) ? trim($data['ecole']) : null,
            ':gtd'    => !empty($data['groupe_td_id']) ? (int)$data['groupe_td_id'] : null,
            ':id'     => $id,
        ]);
        return ['succes' => true];
    }

    public function supprimer(int $id): array {
        Auth::exiger('admin');
        $stmt = $this->pdo->prepare('SELECT utilisateur_id FROM etudiants WHERE id=:id');
        $stmt->execute([':id' => $id]);
        $et = $stmt->fetch();
        if (!$et) return ['succes' => false, 'erreurs' => ['Étudiant introuvable.']];
        // Désactiver plutôt que supprimer (conservation historique)
        $this->pdo->prepare('UPDATE utilisateurs SET actif=0 WHERE id=:uid')
                  ->execute([':uid' => $et['utilisateur_id']]);
        return ['succes' => true];
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

    private function genererNumeroEtudiant(): string {
        $annee = date('Y');
        $stmt = $this->pdo->query('SELECT COUNT(*)+1 AS nb FROM etudiants');
        $nb = str_pad($stmt->fetch()['nb'], 4, '0', STR_PAD_LEFT);
        return "E{$annee}{$nb}";
    }
}