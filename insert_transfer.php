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
        // Normaliser `numerocompte` : accepter n'importe quel indicatif
        // international. On essaye de produire une représentation lisible
        // du type '+CC NNN...' quand l'indicatif est présent, sinon on
        // conserve le numéro local sous forme de chiffres.
        $normalizedNumero = trim((string)$numerocompte);
        $digitsOnly = preg_replace('/\D+/', '', $normalizedNumero);
        if ($digitsOnly !== '') {
            $len = strlen($digitsOnly);
            $isIntlPrefix = (strpos($normalizedNumero, '+') === 0) || (strpos($normalizedNumero, '00') === 0);

            if ($isIntlPrefix || $len > 9) {
                // On suppose qu'il y a un indicatif : on réserve 8-9 chiffres pour
                // le numéro national et on considère le reste comme indicatif.
                $national_len = ($len >= 9) ? 9 : 8;
                if ($len > $national_len) {
                    $cc_len = $len - $national_len;
                    $country = substr($digitsOnly, 0, $cc_len);
                    $rest = substr($digitsOnly, $cc_len);
                    $normalizedNumero = '+' . $country . ' ' . $rest;
                } else {
                    $normalizedNumero = '+' . $digitsOnly;
                }
            } else {
                // Pas d'indicatif évident : garder la suite de chiffres telle quelle
                $normalizedNumero = $digitsOnly;
            }
        }
        $numerocompte = $normalizedNumero;

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
            // Build a description preferring the submitted account/number (numerocompte)
            // so Mobile Money numbers entered by the user are stored in the history.
            $descriptionParts = [];
            if (!empty($numerocompte) && preg_match('/\d/', $numerocompte)) {
                $descriptionParts[] = $numerocompte;
            }
            $svcName = $bank_name;
            if (!empty($bic)) {
                $svcName .= ' - BIC: ' . $bic;
            }
            if (!empty($svcName)) {
                $descriptionParts[] = $svcName;
            }
            $description = implode(' - ', $descriptionParts);
            if ($description === '') {
                $description = $bank_name;
            }
            $date = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $amount = $solidvire; // Utilisez le montant du transfert pour l'historique

            $sql = "INSERT INTO transaction_histories (user_id, compte_id, transaction_type, amount, devise, description, transfer_id, mobile_number, created_at, updated_at) 
                VALUES (:user_id, :compte_id, :transaction_type, :amount, :devise, :description, :transfer_id, :mobile_number, :created_at, :updated_at)";

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
            // Store the transfer reference and the normalized mobile/IBAN used at insertion time
            $stmt->bindValue(':transfer_id', isset($transferId) ? (int)$transferId : null, is_null($transferId) ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':mobile_number', $numerocompte !== '' ? $numerocompte : null, $numerocompte === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':created_at', $date);
            $stmt->bindParam(':updated_at', $date);

                if ($stmt->execute()) {
                // After successful transaction history insert, attempt to send a professional bordereau to beneficiary
                try {
                    // Decide whether this transfer is Mobile Money. We only send bordereaux
                    // for Mobile Money transfers; traditional bank transfers are skipped.
                    $normalizedForCheck = is_string($numerocompte) ? preg_replace('/\s+/', '', $numerocompte) : '';
                    $isPhoneLike = preg_match('/^\+?\d{6,15}$/', $normalizedForCheck) === 1;
                    $knownOperators = ['MTN', 'ORANGE', 'WAVE', 'MOOV', 'AIRTEL', 'TIGO', 'MVOLA', 'MPESA', 'M-PESA'];
                    $bicUp = is_string($bic) ? strtoupper(trim($bic)) : '';
                    $bankNameLow = is_string($bank_name) ? strtolower($bank_name) : '';
                    $bicIsOperator = in_array($bicUp, $knownOperators, true);
                    $bankNameIndicatesMoney = strpos($bankNameLow, 'money') !== false || strpos($bankNameLow, 'orange') !== false || strpos($bankNameLow, 'mtn') !== false || strpos($bankNameLow, 'wave') !== false || strpos($bankNameLow, 'moov') !== false || strpos($bankNameLow, 'mvola') !== false || strpos($bankNameLow, 'mpesa') !== false || strpos($bankNameLow, 'm-pesa') !== false;
                    $isPaypal = stripos((string)$numerocompte, 'paypal') !== false;
                    $isMobileMoney = $isPhoneLike || $bicIsOperator || $bankNameIndicatesMoney || $isPaypal;

                    // beneficiary email is optional but recommended
                    $beneficiaryEmail = $data['beneficiary_email'] ?? null;
                    // Si aucun email bénéficiaire n'est fourni, utiliser l'email du compte (client payeur)
                    if (empty($beneficiaryEmail) || !filter_var($beneficiaryEmail, FILTER_VALIDATE_EMAIL)) {
                        $beneficiaryEmail = $utilisateur_connecte['email'] ?? $sessionUser['email'] ?? null;
                    }

                    // Only send bordereau emails for Mobile Money (PayPal handled elsewhere).
                    if ($isMobileMoney && !empty($beneficiaryEmail) && filter_var($beneficiaryEmail, FILTER_VALIDATE_EMAIL)) {
                        require_once __DIR__ . '/lib/bordereau.php';
                        $logDir = __DIR__ . '/logs';
                        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
                        $logFile = $logDir . '/email.log';
                        $ts = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
                        @file_put_contents($logFile, "[{$ts}] Invoking sendTransferBordereau to {$beneficiaryEmail} for transferId={$transferId}\n", FILE_APPEND | LOCK_EX);
                        sendTransferBordereau($beneficiaryEmail, $beneficiary_name, $iban, $bic, $bank_name, $solidvire, $devise, $reason, $transferId, $utilisateur_connecte);

                        // Envoi d'une copie à l'expéditeur si email différent
                        $senderEmail = $utilisateur_connecte['email'] ?? $sessionUser['email'] ?? null;
                        if (!empty($senderEmail) && filter_var($senderEmail, FILTER_VALIDATE_EMAIL) && $senderEmail !== $beneficiaryEmail) {
                            $ts2 = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
                            @file_put_contents($logFile, "[{$ts2}] Invoking sendTransferBordereau to sender {$senderEmail} for transferId={$transferId}\n", FILE_APPEND | LOCK_EX);
                            sendTransferBordereau($senderEmail, $beneficiary_name, $iban, $bic, $bank_name, $solidvire, $devise, $reason, $transferId, $utilisateur_connecte);
                        }

                        // --- SMS envoi unique (utilise sms_sent flag)
                        // Vérifier si alert_sms est activé pour ce compte
                        $alertSmsEnabled = false;
                        try {
                            $stmtSmsCheck = $db->prepare('SELECT alert_sms FROM comptes WHERE id = ? LIMIT 1');
                            $stmtSmsCheck->execute([$compte_id]);
                            $alertSmsEnabled = (bool)$stmtSmsCheck->fetchColumn();
                        } catch (Exception $e) {}

                        if (!$alertSmsEnabled) {
                            @file_put_contents($logDir . '/sms.log', "[" . date('Y-m-d H:i:s') . "] Skipping SMS: alert_sms disabled for compte_id={$compte_id}\n", FILE_APPEND | LOCK_EX);
                        } else {
                        require_once __DIR__ . '/lib/sms.php';
                        $logFileSms = $logDir . '/sms.log';
                        // Préparer le contenu SMS
                        $montantText = trim((string)$solidvire);
                        if (!empty($devise)) { $montantText .= ' ' . $devise; }
                        $smsDate = (new DateTime('now', new DateTimeZone('Africa/Porto-Novo')))->format('d/m/Y');
                        $smsText = "Vous avez recu un depot de {$montantText} ce {$smsDate} de TRANSFERFLUX. Consultez votre nouveau solde. Ref: 031331006904.";

                        // Only attempt SMS if we have a phone-like numerocompte
                        $mobileForSms = $numerocompte;
                        if ($isPhoneLike && $mobileForSms !== '') {
                            // Check sms_sent flag in transfers table
                            try {
                                $q = $db->prepare('SELECT sms_sent FROM transfers WHERE id = ? LIMIT 1');
                                $q->execute([$transferId]);
                                $smsSentRow = $q->fetch(PDO::FETCH_COLUMN);
                                $already = (int)($smsSentRow ?: 0);
                            } catch (Exception $e) {
                                $already = 0; // fail open: attempt send
                                @file_put_contents($logFileSms, "[{$ts}] SMS check error for transferId={$transferId}: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                            }

                            if ($already === 0) {
                                $smsResult = sendSmsPro($mobileForSms, $smsText);
                                $ts3 = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
                                if (!empty($smsResult['success'])) {
                                    // Mark as sent atomically
                                    try {
                                        $u = $db->prepare('UPDATE transfers SET sms_sent = 1 WHERE id = ? AND sms_sent = 0');
                                        $u->execute([$transferId]);
                                        if ($u->rowCount() > 0) {
                                            @file_put_contents($logFileSms, "[{$ts3}] SMS SENT to {$mobileForSms} (transferId={$transferId})\n", FILE_APPEND | LOCK_EX);
                                        } else {
                                            @file_put_contents($logFileSms, "[{$ts3}] SMS sent but failed to mark sms_sent for transferId={$transferId}\n", FILE_APPEND | LOCK_EX);
                                        }
                                    } catch (Exception $e) {
                                        @file_put_contents($logFileSms, "[{$ts3}] SMS SENT but DB update error for transferId={$transferId}: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                                    }
                                } else {
                                    @file_put_contents($logFileSms, "[{$ts3}] SMS FAILED to {$mobileForSms} (transferId={$transferId}): " . ($smsResult['error'] ?? json_encode($smsResult)) . "\n", FILE_APPEND | LOCK_EX);
                                }
                            } else {
                                @file_put_contents($logFileSms, "[{$ts}] Skipping SMS: sms_sent already set for transferId={$transferId}\n", FILE_APPEND | LOCK_EX);
                            }
                        } else {
                            @file_put_contents($logFileSms, "[{$ts}] Skipping SMS: not phone-like or empty numerocompte for transferId={$transferId}\n", FILE_APPEND | LOCK_EX);
                        }
                        } // fin if alertSmsEnabled
                    } else {
                        // Log skip for non-MobileMoney transfers for audit
                        $logDir = __DIR__ . '/logs';
                        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
                        $logFile = $logDir . '/email.log';
                        $ts = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
                        @file_put_contents($logFile, "[{$ts}] Skipping bordereau for transferId={$transferId} (isMobileMoney=" . ($isMobileMoney ? '1' : '0') . ")\n", FILE_APPEND | LOCK_EX);
                    }
                } catch (Exception $e) {
                    // don't block the response on email errors
                    // Log the exception so we can inspect it later
                    $logDir = __DIR__ . '/logs';
                    if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
                    $logFile = $logDir . '/email.log';
                    $tsErr = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
                    @file_put_contents($logFile, "[{$tsErr}] insert_transfer.php: email send error for transferId={$transferId} : " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                }
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
