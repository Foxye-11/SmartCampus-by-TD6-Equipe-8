<?php
// api/notes.php — Routeur REST JSON

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/NoteController.php';

Auth::exiger();

$method     = $_SERVER['REQUEST_METHOD'];
$controller = new NoteController();

$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($action === 'etudiant' && $id) {
            $coursId = isset($_GET['cours_id']) ? (int)$_GET['cours_id'] : null;
            echo json_encode($controller->notesEtudiant($id, $coursId));
        } elseif ($action === 'cours' && $id) {
            echo json_encode($controller->notesDuCours($id));
        } elseif ($action === 'matiere') {
            $mat = $_GET['matiere'] ?? '';
            if ($mat === '') { http_response_code(400); echo json_encode(['erreur' => 'Matière requise.']); break; }
            echo json_encode($controller->notesParMatiere($mat));
        } elseif ($action === 'bulletin' && $id) {
            $semestreId = (int)($_GET['semestre_id'] ?? 0);
            echo json_encode($controller->bulletin($id, $semestreId));
        } elseif ($action === 'moyenne' && $id) {
            $coursId = (int)($_GET['cours_id'] ?? 0);
            echo json_encode($controller->calculerMoyenne($id, $coursId));
        } else {
            http_response_code(400);
            echo json_encode(['erreur' => 'Action inconnue.']);
        }
        break;

    case 'POST':
        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $resultat = $controller->saisir($data);
        http_response_code($resultat['succes'] ? 201 : 422);
        echo json_encode($resultat);
        break;

    case 'PUT':
        if (!$id) { http_response_code(400); echo json_encode(['erreur' => 'ID note requis.']); exit; }
        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $resultat = $controller->modifier($id, $data);
        http_response_code($resultat['succes'] ? 200 : 422);
        echo json_encode($resultat);
        break;

    case 'DELETE':
        if (!$id) { http_response_code(400); echo json_encode(['erreur' => 'ID note requis.']); exit; }
        $resultat = $controller->supprimer($id);
        http_response_code($resultat['succes'] ? 200 : 422);
        echo json_encode($resultat);
        break;

    default:
        http_response_code(405);
        echo json_encode(['erreur' => 'Méthode non autorisée.']);
}