<?php
// api/messages.php

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/MessageController.php';

Auth::exiger();

$method     = $_SERVER['REQUEST_METHOD'];
$controller = new MessageController();
$action     = $_GET['action'] ?? '';
$id         = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($action === 'reception') {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            echo json_encode($controller->reception($page));
        } elseif ($action === 'envoyes') {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            echo json_encode($controller->envoyes($page));
        } elseif ($action === 'lire' && $id) {
            $resultat = $controller->lire($id);
            http_response_code($resultat['succes'] ? 200 : 404);
            echo json_encode($resultat);
        } elseif ($action === 'contacts') {
            echo json_encode($controller->contacts());
        } elseif ($action === 'non_lus') {
            echo json_encode(['non_lus' => $controller->compterNonLus()]);
        } else {
            http_response_code(400);
            echo json_encode(['erreur' => 'Action inconnue.']);
        }
        break;

    case 'POST':
        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $resultat = $controller->envoyer($data);
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
