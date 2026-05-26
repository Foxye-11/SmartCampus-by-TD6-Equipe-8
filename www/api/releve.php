<?php
// api/releve.php

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../controllers/ReleveNotesController.php';

Auth::exiger('etudiant', 'admin');

$controller = new ReleveNotesController();
$action     = $_GET['action'] ?? 'pdf';
$etudiantId = isset($_GET['etudiant_id']) ? (int)$_GET['etudiant_id'] : ($_SESSION['etudiant_id'] ?? 0);
$semestreId = isset($_GET['semestre_id']) ? (int)$_GET['semestre_id'] : 0;

if (!$etudiantId || !$semestreId) {
    http_response_code(400);
    echo json_encode(['erreur' => 'etudiant_id et semestre_id sont requis.']);
    exit;
}

if ($action === 'donnees') {
    // Retourne le JSON des données (pour affichage React)
    $donnees = $controller->genererDonnees($etudiantId, $semestreId);
    http_response_code($donnees['succes'] ? 200 : 422);
    echo json_encode($donnees);
} else {
    // Téléchargement PDF direct
    $controller->genererPDF($etudiantId, $semestreId);
}
