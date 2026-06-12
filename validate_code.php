<?php
// public_html/validate_code.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('fonction.php');
header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

// Vérifier SESSION correcte
if (!isset($_SESSION['utilisateur_connecter']['compte_id'])) {
    $response['error'] = 'Session invalide - compte_id manquant';
    echo json_encode($response);
    exit;
}

$compte_id = $_SESSION['utilisateur_connecter']['compte_id'];
$code_soumis = $_POST['codeVirement'] ?? '';

if (empty($code_soumis)) {
    $response['error'] = 'Code non fourni';
    echo json_encode($response);
    exit;
}

// Connexion DB
$db = connexion_db();
if (!is_object($db)) {
    $response['error'] = 'Erreur connexion DB';
    echo json_encode($response);
    exit;
}

// Récupérer code et montant ACTUELS depuis la base
try {
    $stmt = $db->prepare("SELECT code_virement, account_balance, token, email FROM comptes WHERE id = ?");
    $stmt->execute([$compte_id]);
    $compte = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$compte) {
        $response['error'] = 'Compte introuvable en base';
        echo json_encode($response);
        exit;
    }

    // Détecter si c'est un compte test
    $isTestCompte = false;
    $stmtTest = $db->prepare("SELECT numerocompte FROM comptes WHERE id = ?");
    $stmtTest->execute([$compte_id]);
    $compteRow = $stmtTest->fetch(PDO::FETCH_ASSOC);
    if ($compteRow && strpos($compteRow['numerocompte'], 'test_') === 0) {
        $isTestCompte = true;
    }

    $isTestFailureCode = $isTestCompte && $code_soumis === '000000';

    // Comparaison EXACTE (ou code d'échec test)
    if ($compte['code_virement'] === $code_soumis || $isTestFailureCode) {
        // Comptes test : ajuster end_percentage en base ET en session selon le code utilisé
        if ($isTestCompte) {
            $newEnd = ($code_soumis === '000000') ? 50 : 100;
            $stmtUp = $db->prepare("UPDATE comptes SET end_percentage = ? WHERE id = ?");
            $stmtUp->execute([$newEnd, $compte_id]);
            if (isset($_SESSION['utilisateur_connecter'])) {
                $_SESSION['utilisateur_connecter']['end_percentage'] = $newEnd;
            }
        }
        $response = [
            'success' => true,
            'montant' => $compte['account_balance']
        ];

        // Marquer le code comme utilisé dans unlock_codes
        try {
            $stmtUnlock = $db->prepare("INSERT INTO unlock_codes (compte_id, code, used_at, created_at, updated_at) VALUES (?, ?, NOW(), NOW(), NOW())");
            $stmtUnlock->execute([$compte_id, $code_soumis]);
        } catch (Exception $e) {
            // Table peut ne pas exister ou doublon, ignorer
        }

        $accountToken = $compte['token'] ?? ($_SESSION['utilisateur_connecter']['token'] ?? null);
        $accountEmail = $compte['email'] ?? ($_SESSION['utilisateur_connecter']['email'] ?? null);
        $unlockEndpoint = resolveEnvValue('UNLOCK_CODES_ENDPOINT') ?: 'https://ton-backend/api/unlock-codes/consume';
        $unlockApiKey = resolveEnvValue('UNLOCK_CODES_API_KEY') ?: 'XXX';

        if (!empty($unlockEndpoint) && function_exists('curl_init')) {
            $payload = [
                'compte_token' => $accountToken ?: '',
                'compte_id' => $compte_id,
                'code' => $code_soumis,
                'api_key' => $unlockApiKey,
            ];
            if (!empty($accountEmail)) {
                $payload['email'] = $accountEmail;
            }

            $ch = curl_init($unlockEndpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            $apiResponse = curl_exec($ch);
            $apiStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $apiError = $apiResponse === false ? curl_error($ch) : '';
            curl_close($ch);

            $logDir = __DIR__ . '/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/unlock_code_sync.log';
            $ts = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $logPayload = json_encode([
                'compte_id' => $compte_id,
                'compte_token' => $accountToken,
                'status' => $apiStatus,
                'error' => $apiError,
                'body' => is_string($apiResponse) ? substr($apiResponse, 0, 500) : null,
            ]);
            @file_put_contents($logFile, "[{$ts}] unlock-sync {$logPayload}\n", FILE_APPEND | LOCK_EX);

            if ($apiStatus !== 200 && empty($response['sync_warning'])) {
                $response['sync_warning'] = 'unlock_sync_failed';
            }
        } else {
            $logDir = __DIR__ . '/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/unlock_code_sync.log';
            $reason = empty($unlockEndpoint) ? 'missing_endpoint' : 'curl_extension_missing';
            $ts = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            @file_put_contents($logFile, "[{$ts}] unlock-sync skipped reason={$reason} compte_id={$compte_id}\n", FILE_APPEND | LOCK_EX);
        }
    } else {
        $response['error'] = 'Code incorrect';
    }
} catch (Exception $e) {
    $response['error'] = 'Erreur SQL : ' . $e->getMessage();
}

echo json_encode($response);
