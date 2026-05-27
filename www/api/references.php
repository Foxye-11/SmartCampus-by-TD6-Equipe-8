<?php
// api/references.php — Référentiels (écoles, groupes de TD)

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/ReferenceController.php';

Auth::exiger(); // au minimum connecté

$controller = new ReferenceController();
$action     = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['erreur' => 'Méthode non autorisée.']);
    exit;
}

switch ($action) {
    case 'groupes_td':
        $niveau = $_GET['niveau'] ?? null;
        echo json_encode($controller->groupesTD($niveau));
        break;
    default:
        http_response_code(400);
        echo json_encode(['erreur' => 'Action inconnue.']);
}
