<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/EmploiDuTempsController.php';

Auth::exiger();
$method     = $_SERVER['REQUEST_METHOD'];
$controller = new EmploiDuTempsController();
$role       = Auth::getRole();
$id         = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Sous-action : lister les annees scolaires disponibles
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'annees') {
    echo json_encode([
        'annees'  => $controller->anneesDisponibles(),
        'defaut'  => EmploiDuTempsController::ANNEE_DEFAUT,
    ]);
    return;
}

// Filtres communs (mode calendrier)
$filtres = [
    'annee_scolaire' => $_GET['annee_scolaire'] ?? EmploiDuTempsController::ANNEE_DEFAUT,
    'date_debut'     => $_GET['date_debut'] ?? null,
    'date_fin'       => $_GET['date_fin']   ?? null,
];

switch ($method) {
    case 'GET':
        if ($role === 'etudiant') {
            echo json_encode($controller->emploiEtudiant($_SESSION['etudiant_id'], $filtres));
        } elseif ($role === 'enseignant') {
            echo json_encode($controller->emploiEnseignant($_SESSION['enseignant_id'], $filtres));
        } else {
            $filtres += [
                'cours_id'      => $_GET['cours_id']      ?? '',
                'matiere'       => $_GET['matiere']       ?? '',
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
