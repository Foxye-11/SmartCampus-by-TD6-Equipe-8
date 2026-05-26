<?php
// controllers/SalleController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';

class SalleController {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    // -----------------------------------------------
    // Lister toutes les salles
    // -----------------------------------------------
    public function lister(): array {
        $stmt = $this->pdo->query(
            'SELECT id, nom, capacite, batiment, type_salle, disponible
             FROM salles
             ORDER BY batiment, nom'
        );
        return $stmt->fetchAll();
    }

    // -----------------------------------------------
    // Obtenir une salle par ID
    // -----------------------------------------------
    public function obtenir(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT id, nom, capacite, batiment, type_salle, disponible
             FROM salles WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $salle = $stmt->fetch();
        return $salle ?: null;
    }

    // -----------------------------------------------
    // Créer une salle (admin uniquement)
    // -----------------------------------------------
    public function creer(array $data): array {
        Auth::exiger('admin');

        $erreurs = $this->valider($data);
        if (!empty($erreurs)) {
            return ['succes' => false, 'erreurs' => $erreurs];
        }

        // Vérifier unicité du nom
        $stmt = $this->pdo->prepare('SELECT id FROM salles WHERE nom = :nom');
        $stmt->execute([':nom' => trim($data['nom'])]);
        if ($stmt->fetch()) {
            return ['succes' => false, 'erreurs' => ['Une salle avec ce nom existe déjà.']];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO salles (nom, capacite, batiment, type_salle, disponible)
             VALUES (:nom, :capacite, :batiment, :type_salle, :disponible)'
        );
        $stmt->execute([
            ':nom'        => trim($data['nom']),
            ':capacite'   => (int) $data['capacite'],
            ':batiment'   => isset($data['batiment']) ? trim($data['batiment']) : null,
            ':type_salle' => $data['type_salle'] ?? 'cours',
            ':disponible' => isset($data['disponible']) ? (int)(bool)$data['disponible'] : 1,
        ]);

        return ['succes' => true, 'id' => (int) $this->pdo->lastInsertId()];
    }

    // -----------------------------------------------
    // Modifier une salle (admin uniquement)
    // -----------------------------------------------
    public function modifier(int $id, array $data): array {
        Auth::exiger('admin');

        if (!$this->obtenir($id)) {
            return ['succes' => false, 'erreurs' => ['Salle introuvable.']];
        }

        $erreurs = $this->valider($data);
        if (!empty($erreurs)) {
            return ['succes' => false, 'erreurs' => $erreurs];
        }

        // Unicité du nom (hors soi-même)
        $stmt = $this->pdo->prepare('SELECT id FROM salles WHERE nom = :nom AND id != :id');
        $stmt->execute([':nom' => trim($data['nom']), ':id' => $id]);
        if ($stmt->fetch()) {
            return ['succes' => false, 'erreurs' => ['Une autre salle porte déjà ce nom.']];
        }

        $stmt = $this->pdo->prepare(
            'UPDATE salles
             SET nom = :nom, capacite = :capacite, batiment = :batiment,
                 type_salle = :type_salle, disponible = :disponible
             WHERE id = :id'
        );
        $stmt->execute([
            ':nom'        => trim($data['nom']),
            ':capacite'   => (int) $data['capacite'],
            ':batiment'   => isset($data['batiment']) ? trim($data['batiment']) : null,
            ':type_salle' => $data['type_salle'] ?? 'cours',
            ':disponible' => isset($data['disponible']) ? (int)(bool)$data['disponible'] : 1,
            ':id'         => $id,
        ]);

        return ['succes' => true];
    }

    // -----------------------------------------------
    // Supprimer une salle (admin uniquement)
    // -----------------------------------------------
    public function supprimer(int $id): array {
        Auth::exiger('admin');

        if (!$this->obtenir($id)) {
            return ['succes' => false, 'erreurs' => ['Salle introuvable.']];
        }

        // Vérifier qu'aucune session n'utilise cette salle à venir
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS nb FROM sessions_cours
             WHERE salle_id = :id
               AND (date_specifique IS NULL OR date_specifique >= CURDATE())'
        );
        $stmt->execute([':id' => $id]);
        if ($stmt->fetch()['nb'] > 0) {
            return [
                'succes'  => false,
                'erreurs' => ['Impossible de supprimer : des séances utilisent cette salle.']
            ];
        }

        $stmt = $this->pdo->prepare('DELETE FROM salles WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return ['succes' => true];
    }

    // -----------------------------------------------
    // Disponibilité d'une salle pour un créneau donné
    // -----------------------------------------------
    public function verifierDisponibilite(
        int $salleId,
        int $jourSemaine,
        string $heureDebut,
        string $heureFin,
        ?string $dateSpecifique = null,
        ?int $sessionExclueId  = null
    ): bool {
        $sql = 'SELECT COUNT(*) AS nb
                FROM sessions_cours
                WHERE salle_id = :salle_id
                  AND jour_semaine = :jour
                  AND heure_debut < :heure_fin
                  AND heure_fin   > :heure_debut';

        $params = [
            ':salle_id'    => $salleId,
            ':jour'        => $jourSemaine,
            ':heure_debut' => $heureDebut,
            ':heure_fin'   => $heureFin,
        ];

        if ($dateSpecifique !== null) {
            $sql .= ' AND (date_specifique IS NULL OR date_specifique = :date)';
            $params[':date'] = $dateSpecifique;
        }

        if ($sessionExclueId !== null) {
            $sql .= ' AND id != :exclue';
            $params[':exclue'] = $sessionExclueId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch()['nb'] === 0;
    }

    // -----------------------------------------------
    // Validation interne
    // -----------------------------------------------
    private function valider(array $data): array {
        $erreurs = [];
        $typesValides = ['cours', 'tp', 'amphi', 'seminaire'];

        if (empty($data['nom']) || strlen(trim($data['nom'])) < 2) {
            $erreurs[] = 'Le nom de la salle est requis (minimum 2 caractères).';
        }
        if (!isset($data['capacite']) || (int)$data['capacite'] < 1) {
            $erreurs[] = 'La capacité doit être un entier positif.';
        }
        if (isset($data['type_salle']) && !in_array($data['type_salle'], $typesValides, true)) {
            $erreurs[] = 'Type de salle invalide.';
        }
        return $erreurs;
    }
}
