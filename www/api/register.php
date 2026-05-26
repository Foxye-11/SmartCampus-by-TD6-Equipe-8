<?php
// api/register.php — Inscription publique d'un étudiant ou enseignant
// Route PUBLIQUE : pas d'Auth::exiger() au niveau du fichier.

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/EtudiantController.php';
require_once __DIR__ . '/../controllers/EnseignantController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['succes' => false, 'erreur' => 'Méthode non autorisée.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];

// Validation du rôle demandé
$role = $data['role'] ?? '';
if (!in_array($role, ['etudiant', 'enseignant'], true)) {
    http_response_code(422);
    echo json_encode([
        'succes'  => false,
        'erreurs' => ['Rôle invalide. Choisissez "etudiant" ou "enseignant".'],
    ]);
    exit;
}

// Validation minimale commune (les contrôleurs valident plus en détail)
$erreurs = [];
if (empty($data['nom']))                         $erreurs[] = 'Nom requis.';
if (empty($data['prenom']))                      $erreurs[] = 'Prénom requis.';
if (empty($data['email']))                       $erreurs[] = 'Email requis.';
if (empty($data['mot_de_passe']))                $erreurs[] = 'Mot de passe requis.';
if (!empty($data['mot_de_passe']) && strlen($data['mot_de_passe']) < 8)
    $erreurs[] = 'Le mot de passe doit contenir au moins 8 caractères.';
if (!empty($erreurs)) {
    http_response_code(422);
    echo json_encode(['succes' => false, 'erreurs' => $erreurs]);
    exit;
}

try {
    if ($role === 'etudiant') {
        $controller = new EtudiantController();
        $resultat   = $controller->inscriptionPublique($data);
    } else {
        $controller = new EnseignantController();
        $resultat   = $controller->inscriptionPublique($data);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'succes' => false,
        'erreur' => 'Erreur serveur lors de l\'inscription.',
    ]);
    exit;
}

http_response_code($resultat['succes'] ? 201 : 422);
echo json_encode($resultat);
