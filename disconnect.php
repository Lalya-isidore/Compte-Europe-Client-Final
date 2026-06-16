<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (empty($_SESSION['utilisateur_connecter'])) {
    echo '{}';
    exit;
}

require_once('fonction.php');

$compteId = $_SESSION['utilisateur_connecter']['compte_id'] ?? null;
if ($compteId) {
    try {
        $db = connexion_db();
        if (is_object($db)) {
            $db->prepare("UPDATE comptes SET last_activity = :ts WHERE id = :id")
               ->execute([':ts' => time(), ':id' => $compteId]);
        }
    } catch (Exception $e) {}
}

echo '{}';
