<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (empty($_SESSION['utilisateur_connecter'])) {
    echo json_encode(['ok' => false]);
    exit;
}

require_once('fonction.php');

$compteId = $_SESSION['utilisateur_connecter']['compte_id'] ?? null;
if (!$compteId) {
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $db = connexion_db();
    $db->prepare("UPDATE comptes SET last_activity = :ts WHERE id = :id")
       ->execute([':ts' => time(), ':id' => $compteId]);
    $_SESSION['last_db_activity'] = time();
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false]);
}
