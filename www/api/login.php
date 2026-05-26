<?php
// api/login.php  — Point d'entrée API JSON

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erreur' => 'Méthode non autorisée.']);
    exit;
}

$donnees = json_decode(file_get_contents('php://input'), true);
$email      = $donnees['email']      ?? '';
$motDePasse = $donnees['mot_de_passe'] ?? '';

$resultat = Auth::login($email, $motDePasse);

if ($resultat['succes']) {
    echo json_encode([
        'succes' => true,
        'role'   => $resultat['role'],
        'nom'    => $_SESSION['user_nom'],
        'prenom' => $_SESSION['user_prenom'],
    ]);
} else {
    http_response_code(401);
    echo json_encode(['succes' => false, 'erreur' => $resultat['erreur']]);
}
