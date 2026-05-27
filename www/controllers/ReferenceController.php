<?php
// controllers/ReferenceController.php
// Référentiels en lecture seule utilisés par les formulaires
// (écoles, groupes de TD). Accessible à tout utilisateur connecté.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';

class ReferenceController {

    private PDO $pdo;

    public function __construct() { $this->pdo = getDB(); }

    // Liste des groupes de TD, éventuellement filtrée par niveau.
    // Renvoie un libellé prêt à afficher : « ING1 TD03 ».
    public function groupesTD(?string $niveau = null): array {
        $sql = 'SELECT id, nom, niveau, annee_scolaire,
                       CONCAT(niveau, " ", nom) AS libelle
                FROM groupes_td';
        $params = [];
        if ($niveau !== null && $niveau !== '') {
            $sql .= ' WHERE niveau = :niveau';
            $params[':niveau'] = $niveau;
        }
        $sql .= ' ORDER BY niveau, nom';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
