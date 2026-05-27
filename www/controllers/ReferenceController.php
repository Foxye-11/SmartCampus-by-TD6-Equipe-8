<?php
// controllers/ReferenceController.php
// Référentiels utilisés par les formulaires (groupes de TD).
// Lecture : tout utilisateur connecté. Création : admin uniquement.

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

    // Création d'un groupe de TD (admin uniquement).
    public function creerGroupe(array $data): array {
        Auth::exiger('admin');

        $niveau = trim($data['niveau'] ?? '');
        $nom    = trim($data['nom'] ?? '');
        $annee  = trim($data['annee_scolaire'] ?? '');

        $erreurs = [];
        if ($niveau === '') $erreurs[] = 'Niveau requis.';
        if ($nom === '')    $erreurs[] = 'Nom du groupe requis (ex: TD05).';
        if (!preg_match('/^\d{4}-\d{4}$/', $annee)) $erreurs[] = 'Année scolaire invalide (ex: 2025-2026).';
        if (!empty($erreurs)) return ['succes' => false, 'erreurs' => $erreurs];

        // Unicité (niveau, nom, annee_scolaire)
        $stmt = $this->pdo->prepare(
            'SELECT id FROM groupes_td WHERE niveau = :n AND nom = :no AND annee_scolaire = :a'
        );
        $stmt->execute([':n' => $niveau, ':no' => $nom, ':a' => $annee]);
        if ($stmt->fetch()) {
            return ['succes' => false, 'erreurs' => ['Ce groupe existe déjà.']];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO groupes_td (niveau, nom, annee_scolaire) VALUES (:n, :no, :a)'
        );
        $stmt->execute([':n' => $niveau, ':no' => $nom, ':a' => $annee]);

        return [
            'succes'  => true,
            'id'      => (int)$this->pdo->lastInsertId(),
            'libelle' => $niveau . ' ' . $nom,
        ];
    }
}
