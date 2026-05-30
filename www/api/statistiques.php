<?php
// api/statistiques.php — Statistiques académiques (admin uniquement)

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/StatistiqueController.php';

Auth::exiger('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['erreur' => 'Méthode non autorisée.']);
    exit;
}

$controller = new StatistiqueController();
echo json_encode($controller->tableauDeBord());
