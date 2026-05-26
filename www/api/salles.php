<?php
// api/salles.php — Routeur REST JSON

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/SalleController.php';

Auth::exiger(); // au minimum connecté

$method     = $_SERVER['REQUEST_METHOD'];
$controller = new SalleController();

// Extraire l'ID depuis l'URL : /api/salles.php?id=5
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($id) {
            $salle = $controller->obtenir($id);
            if (!$salle) { http_response_code(404); echo json_encode(['erreur' => 'Salle introuvable.']); exit; }
            echo json_encode($salle);
        } else {
            echo json_encode($controller->lister());
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $resultat = $controller->creer($data);
        http_response_code($resultat['succes'] ? 201 : 422);
        echo json_encode($resultat);
        break;

    case 'PUT':
        if (!$id) { http_response_code(400); echo json_encode(['erreur' => 'ID requis.']); exit; }
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $resultat = $controller->modifier($id, $data);
        http_response_code($resultat['succes'] ? 200 : 422);
        echo json_encode($resultat);
        break;

    case 'DELETE':
        if (!$id) { http_response_code(400); echo json_encode(['erreur' => 'ID requis.']); exit; }
        $resultat = $controller->supprimer($id);
        http_response_code($resultat['succes'] ? 200 : 422);
        echo json_encode($resultat);
        break;

    default:
        http_response_code(405);
        echo json_encode(['erreur' => 'Méthode non autorisée.']);
}
