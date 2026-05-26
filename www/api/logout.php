<?php
// api/logout.php

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/Auth.php';

Auth::logout();
echo json_encode(['succes' => true, 'message' => 'Déconnecté.']);
