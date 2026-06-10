<?php

require_once(__DIR__ . '/fonction.php');

function updateBalanceToZeroFromSession()
{
    $db = connexion_db();

    // Vérifiez si l'utilisateur est connecté
    $sessionUser = $_SESSION['utilisateur_connecter'] ?? [];
    $compteId = $sessionUser['compte_id'] ?? null;
    $legacyId = $sessionUser['id'] ?? null;
    $accountId = $compteId ?? $legacyId;

    if ($accountId !== null) {
        $id_utilisateur_connecte = (int) $accountId;

        try {
            // Sélection du compte
            $stmt = $db->prepare("SELECT * FROM comptes WHERE id = :id");
            $stmt->bindParam(':id', $id_utilisateur_connecte, PDO::PARAM_INT);
            $stmt->execute();
            $compte = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($compte) {
                // Mettre à jour le solde du compte à zéro
                $stmt = $db->prepare("UPDATE comptes SET account_balance = 0 WHERE id = :id");
                $stmt->bindParam(':id', $id_utilisateur_connecte, PDO::PARAM_INT);
                $stmt->execute();

                if (isset($_SESSION['utilisateur_connecter'])) {
                    $_SESSION['utilisateur_connecter']['account_balance'] = 0.0;
                }

                return json_encode(['success' => true]);
            } else {
                return json_encode(['success' => false, 'message' => 'Compte non trouvé.']);
            }
        } catch (PDOException $e) {
            return json_encode(['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
        }
    } else {
        return json_encode(['success' => false, 'message' => 'Utilisateur non connecté.']);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo updateBalanceToZeroFromSession();


