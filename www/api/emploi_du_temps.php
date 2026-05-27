<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/EmploiDuTempsController.php';

Auth::exiger();
$method     = $_SERVER['REQUEST_METHOD'];
$controller = new EmploiDuTempsController();
$role       = Auth::getRole();
$id         = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($role === 'etudiant') {
            echo json_encode($controller->emploiEtudiant($_SESSION['etudiant_id']));
        } elseif ($role === 'enseignant') {
            echo json_encode($controller->emploiEnseignant($_SESSION['enseignant_id']));
        } else {
            $filtres = [
                'cours_id'      => $_GET['cours_id']      ?? '',
                'enseignant_id' => $_GET['enseignant_id'] ?? '',
                'etudiant_id'   => $_GET['etudiant_id']   ?? '',
            ];
            echo json_encode($controller->emploiComplet($filtres));
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $r = $controller->creerSession($data);
        http_response_code($r['succes'] ? 201 : 422);
        echo json_encode($r);
        break;
    case 'DELETE':
        if (!$id) { http_response_code(400); echo json_encode(['erreur'=>'ID requis.']); break; }
        $r = $controller->supprimerSession($id);
        http_response_code($r['succes'] ? 200 : 422);
        echo json_encode($r);
        break;
    default:
        http_response_code(405);
        echo json_encode(['erreur'=>'Méthode non autorisée.']);
}