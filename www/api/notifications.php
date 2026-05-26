<?php
// api/notifications.php

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/NotificationController.php';

Auth::exiger();

$method     = $_SERVER['REQUEST_METHOD'];
$controller = new NotificationController();
$action     = $_GET['action'] ?? '';
$id         = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($action === 'non_lues') {
            echo json_encode($controller->lister(true));
        } elseif ($action === 'compter') {
            echo json_encode(['non_lues' => $controller->compterNonLues()]);
        } else {
            echo json_encode($controller->lister(false));
        }
        break;

    case 'PUT':
        if ($action === 'tout_lire') {
            echo json_encode($controller->toutMarquerLues());
        } elseif ($id) {
            $resultat = $controller->marquerLue($id);
            http_response_code($resultat['succes'] ? 200 : 404);
            echo json_encode($resultat);
        } else {
            http_response_code(400);
            echo json_encode(['erreur' => 'ID requis.']);
        }
        break;

    case 'POST':
        Auth::exiger('admin', 'enseignant');
        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $resultat = $controller->creer(
            (int)($data['utilisateur_id'] ?? 0),
            $data['type']    ?? '',
            $data['contenu'] ?? ''
        );
        http_response_code($resultat['succes'] ? 201 : 422);
        echo json_encode($resultat);
        break;

    case 'DELETE':
        if (!$id) { http_response_code(400); echo json_encode(['erreur' => 'ID requis.']); exit; }
        $resultat = $controller->supprimer($id);
        http_response_code($resultat['succes'] ? 200 : 404);
        echo json_encode($resultat);
        break;

    default:
        http_response_code(405);
        echo json_encode(['erreur' => 'Méthode non autorisée.']);
}
