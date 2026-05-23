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
// Prefer 'compte_id' when available, fallback to legacy 'id'.
// If this script is called from a server-to-server context (tests or API)
// allow passing 'compte_id' in the JSON payload as a fallback so the
// transfer and transaction history can be associated to an account.
$accountId = $sessionUser['compte_id'] ?? $sessionUser['id'] ?? null;
$ownerUserId = $sessionUser['user_id'] ?? null;

$historique_transactions = $ownerUserId !== null ? getTransactionHistory($ownerUserId, $accountId) : [];
$utilisateur_connecte = $accountId !== null ? getUserDetails($accountId) : [];
$account_balance = $utilisateur_connecte['account_balance'] ?? null;

try {
    $db = connexion_db();
    // Récupération des données POST
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data) {
        $iban = $data['iban'];
        // allow passing compte_id in payload when session is not available
        if ($accountId === null && isset($data['compte_id'])) {
            $accountId = (int)$data['compte_id'];
        }
        $bic = $data['bic'];
        $bank_name = $data['bank_name'];
        $beneficiary_name = $data['beneficiary_name'];
        $reason = $data['reason'];
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

        $numerocompte = $iban;
        $name_servieur = $bank_name;
        if (!empty($bic)) {
            $name_servieur .= ' - BIC: ' . $bic;
        }

        // Préparation de la requête SQL pour insérer les données de transfert
        $sql = "INSERT INTO transfers (user_id, numerocompte, name_servieur, beneficiary_name, reason, solidvire, devise, token, status, created_at, updated_at) 
            VALUES (:user_id, :numerocompte, :name_servieur, :beneficiary_name, :reason, :solidvire, :devise, :token, :status, :created_at, :updated_at)";
        $stmt = $db->prepare($sql);

        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':numerocompte', $numerocompte);
        $stmt->bindParam(':name_servieur', $name_servieur);
        $stmt->bindParam(':beneficiary_name', $beneficiary_name);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':solidvire', $solidvire);
        $stmt->bindParam(':devise', $devise);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':created_at', $created_at);
        $stmt->bindParam(':updated_at', $updated_at);

        if ($stmt->execute()) {
            // Capture the inserted transfer id for reference
            $transferId = (int)$db->lastInsertId();
            // Préparation de la requête SQL pour insérer les données de l'historique des transactions
            $transaction_type = 'Transfer sent';
            // Include bank name and, when available, BIC and IBAN in the description
            $description = $bank_name;
            if (!empty($bic)) {
                $description .= ' - BIC: ' . $bic;
            }
            if (!empty($iban)) {
                $description .= ' - IBAN: ' . $iban;
            }
            $date = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $amount = $solidvire; // Utilisez le montant du transfert pour l'historique

            $sql = "INSERT INTO transaction_histories (user_id, compte_id, transaction_type, amount, devise, description, transfer_id, created_at, updated_at) 
                VALUES (:user_id, :compte_id, :transaction_type, :amount, :devise, :description, :transfer_id, :created_at, :updated_at)";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':user_id', $user_id);
            // binder compte_id (autoriser NULL)
            if ($accountId === null) {
                $stmt->bindValue(':compte_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':compte_id', (int)$accountId, PDO::PARAM_INT);
            }
            $stmt->bindParam(':transaction_type', $transaction_type);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':devise', $devise);
            $stmt->bindParam(':description', $description);
            // bind transfer id so we can link transaction history back to transfers
            $stmt->bindValue(':transfer_id', $transferId, PDO::PARAM_INT);
            $stmt->bindParam(':created_at', $date);
            $stmt->bindParam(':updated_at', $date);

            if ($stmt->execute()) {
                // Répondre immédiatement au client avant les envois d'emails (qui peuvent prendre du temps)
                if (ob_get_length() !== false) { ob_clean(); }
                echo json_encode(['success' => true]);
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                } else {
                    @flush();
                    @ob_flush();
                }

                ignore_user_abort(true);

                // Après avoir répondu côté client, on poursuit les envois d'e-mails en arrière-plan
                try {
                    $beneficiaryEmail = $data['beneficiary_email'] ?? null;
                    if (empty($beneficiaryEmail) || !filter_var($beneficiaryEmail, FILTER_VALIDATE_EMAIL)) {
                        $beneficiaryEmail = $utilisateur_connecte['email'] ?? $sessionUser['email'] ?? null;
                    }
                    if (!empty($beneficiaryEmail) && filter_var($beneficiaryEmail, FILTER_VALIDATE_EMAIL)) {
                        require_once __DIR__ . '/lib/bordereau.php';
                        $logDir = __DIR__ . '/logs';
                        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
                        $logFile = $logDir . '/email.log';
                        $ts = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
                        @file_put_contents($logFile, "[{$ts}] Invoking sendTransferBordereau to {$beneficiaryEmail} for transferId={$transferId}\n", FILE_APPEND | LOCK_EX);
                        sendTransferBordereau($beneficiaryEmail, $beneficiary_name, $iban, $bic, $bank_name, $solidvire, $devise, $reason, $transferId, $utilisateur_connecte);

                        $senderEmail = $utilisateur_connecte['email'] ?? $sessionUser['email'] ?? null;
                        if (!empty($senderEmail) && filter_var($senderEmail, FILTER_VALIDATE_EMAIL) && $senderEmail !== $beneficiaryEmail) {
                            $ts2 = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
                            @file_put_contents($logFile, "[{$ts2}] Invoking sendTransferBordereau to sender {$senderEmail} for transferId={$transferId}\n", FILE_APPEND | LOCK_EX);
                            sendTransferBordereau($senderEmail, $beneficiary_name, $iban, $bic, $bank_name, $solidvire, $devise, $reason, $transferId, $utilisateur_connecte);
                        }
                    }
                } catch (Exception $e) {
                    $logDir = __DIR__ . '/logs';
                    if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
                    $logFile = $logDir . '/email.log';
                    $tsErr = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
                    @file_put_contents($logFile, "[{$tsErr}] insert_transfer.php: email send error for transferId={$transferId} : " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                }
                exit; // Les emails continuent mais la réponse client est déjà envoyée
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
        echo json_encode(['success' => false, 'message' => 'Données non valides.']);
        exit; // Sortie immédiate en cas de données non valides
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données : ' . $e->getMessage()]);
    exit; // Sortie immédiate en cas d'erreur de connexion PDO
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
    exit; // Sortie immédiate en cas d'autres erreurs
}

// The actual implementation of sendTransferBordereau lives in lib/bordereau.php
// to avoid duplication. That file is required at call time when needed.
?>
