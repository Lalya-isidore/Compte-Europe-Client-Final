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
    $sql = "INSERT INTO transfers (user_id, compte_id, numerocompte, name_servieur, beneficiary_name, reason, solidvire, devise, token, status, created_at, updated_at)
        VALUES (:user_id, :compte_id, :numerocompte, :name_servieur, :beneficiary_name, :reasonPaypal, :solidvire, :devise, :token, :status, :created_at, :updated_at)";
        $stmt = $db->prepare($sql);

    $numerocompte = 'PayPal';
    $name_servieur = $paypalEmail;
    $beneficiaryLabel = 'PayPal - ' . $paypalEmail;

    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    if ($accountId === null) {
        $stmt->bindValue(':compte_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':compte_id', (int)$accountId, PDO::PARAM_INT);
    }
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

        // Begin an explicit DB transaction so we update transfer + history + account balance atomically
        $db->beginTransaction();
        try {
            if (! $stmt->execute()) {
                $errorInfo = $stmt->errorInfo();
                $db->rollBack();
                if (ob_get_length() !== false) { ob_clean(); }
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de l\'insertion des données de transfert.',
                    'details' => $errorInfo[2] ?? null
                ]);
                exit;
            }

            // capture the inserted transfers id for linkage
            $transferId = (int)$db->lastInsertId();
            // Préparation de la requête SQL pour insérer les données de l'historique des transactions
            $transaction_type = 'Transfer sent';
            $description = "PayPal";
            $date = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $amount = $solidvire; // Utilisez le montant du transfert pour l'historique

            $sql = "INSERT INTO transaction_histories (user_id, compte_id, transaction_type, amount, devise, description, transfer_id, created_at, updated_at) 
                    VALUES (:user_id, :compte_id, :transaction_type, :amount, :devise, :description, :transfer_id, :created_at, :updated_at)";

            $stmt2 = $db->prepare($sql);
            $stmt2->bindParam(':user_id', $user_id);
            if ($accountId === null) {
                $stmt2->bindValue(':compte_id', null, PDO::PARAM_NULL);
            } else {
                $stmt2->bindValue(':compte_id', (int)$accountId, PDO::PARAM_INT);
            }
            $stmt2->bindParam(':transaction_type', $transaction_type);
            $stmt2->bindParam(':amount', $amount);
            $stmt2->bindParam(':devise', $devise);
            $stmt2->bindParam(':description', $description);
            $stmt2->bindValue(':transfer_id', $transferId, PDO::PARAM_INT);
            $stmt2->bindParam(':created_at', $date);
            $stmt2->bindParam(':updated_at', $date);

            if (! $stmt2->execute()) {
                $errorInfo = $stmt2->errorInfo();
                $db->rollBack();
                if (ob_get_length() !== false) { ob_clean(); }
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de l\'insertion de l\'historique des transactions.',
                    'details' => $errorInfo[2] ?? null
                ]);
                exit;
            }

            // If the transfer status indicates completion, debit the compte balance
            if (is_string($status) && strtolower($status) === 'completed' && $accountId !== null) {
                $updateSql = "UPDATE comptes SET account_balance = account_balance - :amount WHERE id = :id";
                $uStmt = $db->prepare($updateSql);
                $uStmt->bindValue(':amount', $amount);
                $uStmt->bindValue(':id', (int)$accountId, PDO::PARAM_INT);
                if (! $uStmt->execute()) {
                    $err = $uStmt->errorInfo();
                    $db->rollBack();
                    if (ob_get_length() !== false) { ob_clean(); }
                    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du solde.', 'details' => $err[2] ?? null]);
                    exit;
                }
                // refresh session balance if present
                try {
                    $r = $db->prepare("SELECT account_balance FROM comptes WHERE id = :id");
                    $r->bindValue(':id', (int)$accountId, PDO::PARAM_INT);
                    $r->execute();
                    $row = $r->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        if (session_status() === PHP_SESSION_NONE) { session_start(); }
                        $_SESSION['utilisateur_connecter']['account_balance'] = (float)$row['account_balance'];
                    }
                } catch (Exception $e) {
                    // non-fatal: continue but log
                    error_log('Warning: unable to refresh session balance: ' . $e->getMessage());
                }
            }

            // commit the transaction now that all DB work succeeded
            $db->commit();

            // Envoi du bordereau moderne à l'email PayPal saisi (après commit)
            require_once __DIR__ . '/lib/bordereau.php';
            $logDir = __DIR__ . '/logs';
            if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
            $logFile = $logDir . '/transfer.log';
            $ts = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            @file_put_contents($logFile, "[{$ts}] PayPal transfer created transferId={$transferId} user={$user_id} amount={$amount} status={$status}\n", FILE_APPEND | LOCK_EX);
            // keep email.log entry for compatibility
            $emailLog = $logDir . '/email.log';
            @file_put_contents($emailLog, "[{$ts}] Invoking sendTransferBordereau to PayPal {$paypalEmail} for transferId={$transferId}\n", FILE_APPEND | LOCK_EX);

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
                $transferId,
                $sessionUser
            );

            if (ob_get_length() !== false) { ob_clean(); }
            echo json_encode(['success' => true]);
            exit; // Sortie immédiate après l'envoi du JSON

        } catch (Exception $e) {
            // Ensure rollback on any exception
            try { if ($db && $db->inTransaction()) { $db->rollBack(); } } catch (Exception $__) {}
            error_log('Exception insert_paypal_transfer transaction: ' . $e->getMessage());
            if (ob_get_length() !== false) { ob_clean(); }
            echo json_encode(['success' => false, 'message' => 'Erreur interne pendant le traitement du transfert']);
            exit;
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
