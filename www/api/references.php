<?php
// api/references.php — Référentiels (groupes de TD)

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/ReferenceController.php';

Auth::exiger(); // au minimum connecté

$controller = new ReferenceController();
$method     = $_SERVER['REQUEST_METHOD'];
$action     = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'groupes_td') {
            $niveau = $_GET['niveau'] ?? null;
            echo json_encode($controller->groupesTD($niveau));
        } else {
            http_response_code(400);
            echo json_encode(['erreur' => 'Action inconnue.']);
        }
        break;

    case 'POST':
        // Création d'un groupe de TD (admin uniquement, contrôlé dans le contrôleur)
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $r = $controller->creerGroupe($data);
        http_response_code($r['succes'] ? 201 : 422);
        echo json_encode($r);
        break;

    default:
        http_response_code(405);
        echo json_encode(['erreur' => 'Méthode non autorisée.']);
}
