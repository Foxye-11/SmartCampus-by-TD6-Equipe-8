<?php
// api/presences.php

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/PresenceController.php';

Auth::exiger();

$method     = $_SERVER['REQUEST_METHOD'];
$controller = new PresenceController();
$action     = $_GET['action'] ?? '';
$id         = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($action === 'etudiant' && $id) {
            $coursId = isset($_GET['cours_id']) ? (int)$_GET['cours_id'] : null;
            echo json_encode($controller->presencesEtudiant($id, $coursId));
        } elseif ($action === 'session' && $id) {
            echo json_encode($controller->presencesSession($id));
        } elseif ($action === 'detail_cours' && $id) {
            // $id = etudiant_id, ?cours_id requis
            $coursId = isset($_GET['cours_id']) ? (int)$_GET['cours_id'] : 0;
            if (!$coursId) {
                http_response_code(400);
                echo json_encode(['erreur' => 'cours_id requis.']);
                break;
            }
            echo json_encode($controller->seancesEtudiantParCours($id, $coursId));
        } elseif ($action === 'resume' && $id) {
            echo json_encode($controller->resumeAbsences($id));
        } elseif ($action === 'alertes' && $id) {
            echo json_encode($controller->etudiantsEnAlerte($id));
        } elseif ($action === 'alertes_enseignant' && $id) {
            echo json_encode($controller->alertesEnseignant($id));
        } else {
            http_response_code(400);
            echo json_encode(['erreur' => 'Action inconnue.']);
        }
        break;

    case 'POST':
        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $resultat = $controller->enregistrerSession($data);
        http_response_code($resultat['succes'] ? 201 : 422);
        echo json_encode($resultat);
        break;

    case 'PUT':
        if (!$id) { http_response_code(400); echo json_encode(['erreur' => 'ID requis.']); exit; }
        $data   = json_decode(file_get_contents('php://input'), true) ?? [];
        $statut = $data['statut'] ?? '';
        $resultat = $controller->modifier($id, $statut);
        http_response_code($resultat['succes'] ? 200 : 422);
        echo json_encode($resultat);
        break;

    default:
        http_response_code(405);
        echo json_encode(['erreur' => 'Méthode non autorisée.']);
}
