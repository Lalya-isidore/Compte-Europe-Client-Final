<?php
require_once(__DIR__ . '/fonction.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$data = json_decode(file_get_contents('php://input'), true);
$amount = isset($data['amount']) ? (float)$data['amount'] : 0;

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Montant invalide.']);
    exit;
}

$sessionUser = $_SESSION['utilisateur_connecter'] ?? [];
$accountId = $sessionUser['compte_id'] ?? $sessionUser['id'] ?? null;

if ($accountId === null) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté.']);
    exit;
}

try {
    $db = connexion_db();
    $stmt = $db->prepare("UPDATE comptes SET account_balance = GREATEST(0, account_balance - :amount) WHERE id = :id");
    $stmt->bindValue(':amount', $amount);
    $stmt->bindValue(':id', (int)$accountId, PDO::PARAM_INT);
    $stmt->execute();

    $new_balance = (float)$db->query("SELECT account_balance FROM comptes WHERE id = " . (int)$accountId)->fetchColumn();
    if (isset($_SESSION['utilisateur_connecter'])) {
        $_SESSION['utilisateur_connecter']['account_balance'] = $new_balance;
    }

    echo json_encode(['success' => true, 'new_balance' => $new_balance]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur base de données : ' . $e->getMessage()]);
}
