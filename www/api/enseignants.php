<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/EnseignantController.php';

Auth::exiger();
$method     = $_SERVER['REQUEST_METHOD'];
$controller = new EnseignantController();
$id         = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action     = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'cours' && $id) { echo json_encode($controller->coursDEnseignant($id)); break; }
        if ($id) {
            $e = $controller->obtenir($id);
            if (!$e) { http_response_code(404); echo json_encode(['erreur'=>'Introuvable.']); break; }
            echo json_encode($e);
        } else {
            echo json_encode($controller->lister());
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $r = $controller->creer($data);
        http_response_code($r['succes'] ? 201 : 422);
        echo json_encode($r);
        break;
    case 'PUT':
        if (!$id) { http_response_code(400); echo json_encode(['erreur'=>'ID requis.']); break; }
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $r = $controller->modifier($id, $data);
        http_response_code($r['succes'] ? 200 : 422);
        echo json_encode($r);
        break;
    case 'DELETE':
        if (!$id) { http_response_code(400); echo json_encode(['erreur'=>'ID requis.']); break; }
        $r = $controller->supprimer($id);
        http_response_code($r['succes'] ? 200 : 422);
        echo json_encode($r);
        break;
    default:
        http_response_code(405);
        echo json_encode(['erreur'=>'Méthode non autorisée.']);
}