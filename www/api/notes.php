<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/NoteController.php';

Auth::exiger();
$method     = $_SERVER['REQUEST_METHOD'];
$controller = new NoteController();
$action     = $_GET['action'] ?? '';
$id         = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($action === 'etudiant') {
            $eid    = $id ?? ($_SESSION['etudiant_id'] ?? 0);
            $coursId = isset($_GET['cours_id']) ? (int)$_GET['cours_id'] : null;
            echo json_encode($controller->notesEtudiant($eid, $coursId));
        } elseif ($action === 'cours' && $id) {
            echo json_encode($controller->notesDuCours($id));
        } elseif ($action === 'bulletin') {
            $eid = $id ?? ($_SESSION['etudiant_id'] ?? 0);
            $sid = isset($_GET['semestre_id']) ? (int)$_GET['semestre_id'] : 0;
            echo json_encode($controller->bulletin($eid, $sid));
        } elseif ($action === 'moyenne' && $id) {
            $coursId = isset($_GET['cours_id']) ? (int)$_GET['cours_id'] : 0;
            echo json_encode($controller->calculerMoyenne($id, $coursId));
        } else {
            http_response_code(400);
            echo json_encode(['erreur' => 'Action inconnue.']);
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $r = $controller->saisir($data);
        http_response_code($r['succes'] ? 201 : 422);
        echo json_encode($r);
        break;
    case 'PUT':
        if ($action === 'verrouiller' && $id) {
            $r = $controller->verrouiller($id);
            echo json_encode($r);
            break;
        }
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