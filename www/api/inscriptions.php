<?php
// api/inscriptions.php — Routeur REST JSON

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/InscriptionController.php';

Auth::exiger();

$method     = $_SERVER['REQUEST_METHOD'];
$controller = new InscriptionController();

$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($action === 'disponibles') {
            $eid = $id ?? ($_SESSION['etudiant_id'] ?? 0);
            echo json_encode($controller->coursDisponibles($eid));
        } elseif ($action === 'suivis') {
            $eid = $id ?? ($_SESSION['etudiant_id'] ?? 0);
            echo json_encode($controller->coursSuivis($eid));
        } elseif ($action === 'etudiants' && $id) {
            echo json_encode($controller->etudiantsDuCours($id));
        } else {
            http_response_code(400);
            echo json_encode(['erreur' => 'Action inconnue.']);
        }
        break;

    case 'POST':
        $data      = json_decode(file_get_contents('php://input'), true) ?? [];
        $etudiantId = (int)($data['etudiant_id'] ?? $_SESSION['etudiant_id'] ?? 0);
        $coursId    = (int)($data['cours_id'] ?? 0);
        $resultat   = $controller->inscrire($etudiantId, $coursId);
        http_response_code($resultat['succes'] ? 201 : 422);
        echo json_encode($resultat);
        break;

    case 'DELETE':
        if (!$id) { http_response_code(400); echo json_encode(['erreur' => 'ID inscription requis.']); exit; }
        $resultat = $controller->annuler($id);
        http_response_code($resultat['succes'] ? 200 : 422);
        echo json_encode($resultat);
        break;

    default:
        http_response_code(405);
        echo json_encode(['erreur' => 'Méthode non autorisée.']);
}
