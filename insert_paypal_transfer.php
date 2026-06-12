<?php
require_once(__DIR__ . '/fonction.php');

// Ensure we return JSON and hide PHP warnings from output (log them instead)
header('Content-Type: application/json');
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
// Start output buffering to prevent accidental HTML or warning output mixing with JSON
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$sessionUser = $_SESSION['utilisateur_connecter'] ?? [];
// Prefer 'compte_id' when available, fallback to legacy 'id'
$accountId = $sessionUser['compte_id'] ?? $sessionUser['id'] ?? null;
$ownerUserId = $sessionUser['user_id'] ?? null;

try {
    $db = connexion_db();
    // Récupération des données POST
    $data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Erreur de format JSON : ' . json_last_error_msg()]);
        exit;
    }

    if ($data) {
        $paypalEmail = $data['paypalEmail'];
        $reasonPaypal = $data['reasonPaypal'];
        $user_id = $data['user_id'] ?? $ownerUserId;
        if ($user_id === null) {
            throw new RuntimeException("Identifiant utilisateur manquant pour l'historique de transaction");
        }
        $solidvire = $data['solidvire'];
        $devise = $data['devise'];
        $token = $data['token'];
        $status = $data['status'];
        $created_at = $data['created_at'];
        $updated_at = $data['updated_at'];

        // Préparation de la requête SQL pour insérer les données de transfert PayPal
    $sql = "INSERT INTO transfers (user_id, numerocompte, name_servieur, beneficiary_name, reason, solidvire, devise, token, status, created_at, updated_at) 
        VALUES (:user_id, :numerocompte, :name_servieur, :beneficiary_name, :reasonPaypal, :solidvire, :devise, :token, :status, :created_at, :updated_at)";
        $stmt = $db->prepare($sql);

    // Store the PayPal email as numerocompte so it can be displayed like other transfers
    $numerocompte = $paypalEmail;
    $name_servieur = 'PayPal';
    $beneficiaryLabel = 'PayPal - ' . $paypalEmail;

    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':numerocompte', $numerocompte);
    $stmt->bindParam(':name_servieur', $name_servieur);
    $stmt->bindParam(':beneficiary_name', $beneficiaryLabel);
    $stmt->bindParam(':reasonPaypal', $reasonPaypal);
        $stmt->bindParam(':solidvire', $solidvire);
        $stmt->bindParam(':devise', $devise);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':created_at', $created_at);
        $stmt->bindParam(':updated_at', $updated_at);

        if ($stmt->execute()) {
            // capture the inserted transfer id so we can link it to the transaction history
            $transferId = (int)$db->lastInsertId();
            // Préparation de la requête SQL pour insérer les données de l'historique des transactions
                $transaction_type = 'Transfer sent';
                $description = "PayPal";
            $date = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $amount = $solidvire; // Utilisez le montant du transfert pour l'historique
                // Insert into transaction_histories and store transfer_id and mobile_number for PayPal
                $sql = "INSERT INTO transaction_histories (user_id, compte_id, transaction_type, amount, devise, description, transfer_id, mobile_number, created_at, updated_at) 
                    VALUES (:user_id, :compte_id, :transaction_type, :amount, :devise, :description, :transfer_id, :mobile_number, :created_at, :updated_at)";

                $stmt = $db->prepare($sql);
            $stmt->bindParam(':user_id', $user_id);
            if ($accountId === null) {
                $stmt->bindValue(':compte_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':compte_id', (int)$accountId, PDO::PARAM_INT);
            }
            $stmt->bindParam(':transaction_type', $transaction_type);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':devise', $devise);
            $stmt->bindParam(':description', $description);
            // store the transfer id and the paypal email as mobile_number
            $stmt->bindValue(':transfer_id', isset($transferId) ? (int)$transferId : null, is_null($transferId) ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':mobile_number', $paypalEmail !== '' ? $paypalEmail : null, $paypalEmail === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':created_at', $date);
            $stmt->bindParam(':updated_at', $date);

            if ($stmt->execute()) {
                // Envoi du bordereau moderne à l'email PayPal saisi
                require_once __DIR__ . '/lib/bordereau.php';
                $logDir = __DIR__ . '/logs';
                if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
                $logFile = $logDir . '/email.log';
                $ts = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
                @file_put_contents($logFile, "[{$ts}] Invoking sendTransferBordereau to PayPal {$paypalEmail} for transfer (PayPal)\n", FILE_APPEND | LOCK_EX);
                // Pour PayPal, IBAN/BIC/bankName sont vides ou 'PayPal', motif = $reasonPaypal
                sendTransferBordereau(
                    $paypalEmail,
                    $beneficiaryLabel,
                    '', // IBAN
                    '', // BIC
                    'PayPal', // Banque
                    $solidvire,
                    $devise,
                    $reasonPaypal,
                    null, // pas de transferId classique
                    $sessionUser
                );
                if (ob_get_length() !== false) { ob_clean(); }
                echo json_encode(['success' => true]);
                exit; // Sortie immédiate après l'envoi du JSON
            } else {
                $errorInfo = $stmt->errorInfo();
                if (ob_get_length() !== false) { ob_clean(); }
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de l\'insertion de l\'historique des transactions.',
                    'details' => $errorInfo[2] ?? null
                ]);
                exit; // Sortie immédiate en cas d'erreur
            }
        } else {
            $errorInfo = $stmt->errorInfo();
            if (ob_get_length() !== false) { ob_clean(); }
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de l\'insertion des données de transfert.',
                'details' => $errorInfo[2] ?? null
            ]);
            exit; // Sortie immédiate en cas d'erreur
        }
    } else {
        if (ob_get_length() !== false) { ob_clean(); }
        echo json_encode(['success' => false, 'message' => 'Données non valides.']);
        exit; // Sortie immédiate en cas de données non valides
    }
} catch (PDOException $e) {
    if (ob_get_length() !== false) { ob_clean(); }
    error_log('PDOException insert_paypal_transfer: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit; // Sortie immédiate en cas d'erreur de connexion PDO
} catch (Exception $e) {
    if (ob_get_length() !== false) { ob_clean(); }
    error_log('Exception insert_paypal_transfer: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne']);
    exit; // Sortie immédiate en cas d'autres erreurs
}
// End of script
if (ob_get_length() !== false) { ob_end_flush(); }
?>
