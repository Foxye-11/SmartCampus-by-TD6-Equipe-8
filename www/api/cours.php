<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/CoursController.php';

Auth::exiger();
$method     = $_SERVER['REQUEST_METHOD'];
$controller = new CoursController();
$id         = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action     = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'semestres') { echo json_encode($controller->semestres()); break; }
        if ($action === 'departements') { echo json_encode($controller->departements()); break; }
        if ($action === 'matieres') { echo json_encode($controller->matieres()); break; }
        if ($action === 'sessions' && $id) { echo json_encode($controller->sessionsParCours($id)); break; }
        if ($action === 'groupes' && $id) { echo json_encode($controller->groupesDuCours($id)); break; }
        if ($id) {
            $c = $controller->obtenir($id);
            if (!$c) { http_response_code(404); echo json_encode(['erreur'=>'Introuvable.']); break; }
            echo json_encode($c);
        } else {
            $semestreId    = isset($_GET['semestre_id'])    ? (int)$_GET['semestre_id']    : null;
            $departementId = isset($_GET['departement_id']) ? (int)$_GET['departement_id'] : null;
            $groupeTdId    = isset($_GET['groupe_td_id'])   ? (int)$_GET['groupe_td_id']   : null;
            $matiere       = isset($_GET['matiere'])        ? $_GET['matiere']             : null;
            echo json_encode($controller->lister($semestreId, $departementId, $groupeTdId, $matiere));
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