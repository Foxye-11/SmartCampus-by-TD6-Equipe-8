<?php
// api/compte.php — Gestion du compte de l'utilisateur connecté
// (changement de mot de passe en libre-service, tous rôles confondus)

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';

Auth::exiger(); // tout utilisateur connecté

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'PUT' && $action === 'mot_de_passe') {
    $data         = json_decode(file_get_contents('php://input'), true) ?? [];
    $ancien       = $data['ancien']       ?? '';
    $nouveau      = $data['nouveau']      ?? '';
    $confirmation = $data['confirmation'] ?? '';

    $resultat = Auth::changerMotDePasse($ancien, $nouveau, $confirmation);
    http_response_code($resultat['succes'] ? 200 : 422);
    echo json_encode($resultat);
    exit;
}

http_response_code(400);
echo json_encode(['erreur' => 'Action inconnue.']);
