<?php
$erreurs = [];
$donnees = [];
$success = '';
$erreur = '';

if (!isset($_SESSION['utilisateur_connecter']) || empty($_SESSION['utilisateur_connecter'])) {
    header('Location: index.php?page=connexion');
    exit();
}


$_SESSION['utilisateur_connecter'] = is_array($_SESSION['utilisateur_connecter']) ? $_SESSION['utilisateur_connecter'] : [];
$sessionUser = $_SESSION['utilisateur_connecter'];

// Start from session
$showBalanceAlert = empty($_SESSION['balance_alert_dismissed']);
// Ne pas afficher si le solde crédité est 0 ou vide
$rawBal2 = $_SESSION['utilisateur_connecter']['account_balance2'] ?? null;
if ($rawBal2 === null || (float)$rawBal2 <= 0) {
    $showBalanceAlert = false;
}
// Also respect cookie fallback if present (persisted by dismiss_alert.php)
if (isset($_COOKIE['dismissed_balance']) && $_COOKIE['dismissed_balance'] == '1') {
    $showBalanceAlert = false;
}
// If the user is logged in, try to merge persisted dismissals from DB so they survive logout
try {
    $userIdForDismiss = $sessionUser['compte_id'] ?? $sessionUser['id'] ?? null;
    if ($userIdForDismiss && is_object(($db = (function_exists('connexion_db') ? connexion_db() : null)))) {
        $stmt = $db->prepare("SELECT alert_type, alert_id FROM dismissed_alerts WHERE compte_id = :cid");
        $stmt->execute([':cid' => $userIdForDismiss]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (($r['alert_type'] ?? '') === 'balance') {
                $showBalanceAlert = false;
            } elseif (($r['alert_type'] ?? '') === 'transaction') {
                // we'll merge transaction alert ids later where $dismissedTransactionAlerts is used
            }
        }
    }
} catch (Exception $e) {
    // ignore DB errors and rely on session fallback
}
$transferSuccess = isset($_GET['virement']) && $_GET['virement'] === 'success';

$accountId = $sessionUser['compte_id'] ?? $sessionUser['id'] ?? null;
$ownerUserId = $sessionUser['user_id'] ?? null;

if ($accountId === null) {
    header('Location: index.php?page=connexion');
    exit();
}

// Vérifier si le compte est toujours actif
$dbStatus = connexion_db();
if ($dbStatus) {
    try {
        $stmtStatus = $dbStatus->prepare('SELECT account_status FROM comptes WHERE id = ? LIMIT 1');
        $stmtStatus->execute([$accountId]);
        $statusCheck = $stmtStatus->fetch(PDO::FETCH_ASSOC);
        if ($statusCheck && !in_array($statusCheck['account_status'] ?? '', ['Activé', 'Actif', 'active', 'Active'])) {
            $clientToken = $_SESSION['client_token'] ?? '';
            $bankName = function_exists('t') ? (t('login_bank_name') ?: 'TRANSFERFLUX') : 'TRANSFERFLUX';
            $errMsg = "L'accès à votre compte {$bankName} est temporairement suspendu. Veuillez contacter le support.";
            $_SESSION['login_erreur'] = $errMsg;
            $_SESSION['login_alert_type'] = 'error';
            unset($_SESSION['utilisateur_connecter']);
            $redirect = !empty($clientToken) ? '?c=' . urlencode($clientToken) : 'index.php?page=connexion';
            header('Location: ' . $redirect);
            exit();
        }
    } catch (Exception $e) {}
}

$historique_transactions = [];
if ($ownerUserId !== null && function_exists('getTransactionHistory')) {
    try {
        $reflection = new ReflectionFunction('getTransactionHistory');
        if ($reflection->getNumberOfParameters() >= 2) {
            $historique_transactions = getTransactionHistory($ownerUserId, $accountId);
        } else {
            $historique_transactions = getTransactionHistory($ownerUserId);
        }
    } catch (ReflectionException $e) {
        $historique_transactions = getTransactionHistory($ownerUserId);
    }
}

$defaultDevise = $sessionUser['devise'] ?? 'EUR';

$utilisateur_connecte = getUserDetails($accountId);
if (!is_array($utilisateur_connecte)) {
    $utilisateur_connecte = [];
}

$account_balance = isset($utilisateur_connecte['account_balance']) ? (float) $utilisateur_connecte['account_balance'] : 0;
$formatted_balance = number_format($account_balance, 0, ',', ' ');

$account_balance2 = 0;
if (isset($sessionUser['account_balance2']) && $sessionUser['account_balance2'] !== '') {
    $account_balance2 = (float) $sessionUser['account_balance2'];
} elseif (isset($utilisateur_connecte['account_balance'])) {
    $account_balance2 = (float) $utilisateur_connecte['account_balance'];
}
$formatted_balance2 = number_format($account_balance2, 0, ',', ' ');

$devise = $sessionUser['devise'] ?? ($utilisateur_connecte['devise'] ?? 'EUR');
$deviseLabel = htmlspecialchars($devise, ENT_QUOTES, 'UTF-8');

// Account type: try translation key `account_type_<slug>` then fall back to raw value
$accountTypeRaw = trim((string)($utilisateur_connecte['account_type'] ?? 'Standard'));
$accountTypeSlug = strtolower($accountTypeRaw);
$accountTypeSlug = preg_replace('/[^a-z0-9]+/i', '_', $accountTypeSlug);
$accountTypeKey = 'account_type_' . $accountTypeSlug;
// Prefer the explicit `account_type_<slug>` key. If missing, try the raw lowercase word, otherwise fall back to DB value.
$accountTypeTranslated = t($accountTypeKey);
if ($accountTypeTranslated === $accountTypeKey) {
    $candidate = mb_strtolower($accountTypeRaw);
    $candidateTranslated = t($candidate);
    if ($candidateTranslated !== $candidate) {
        $accountTypeTranslated = $candidateTranslated;
    } else {
        // final fallback: use the raw DB value
        $accountTypeTranslated = $accountTypeRaw;
    }
}
$accountTypeLabel = htmlspecialchars($accountTypeTranslated, ENT_QUOTES, 'UTF-8');

// Account status: normalize common variants and translate using our short keys
$accountStatusRaw = trim((string)($utilisateur_connecte['account_status'] ?? 'active'));
$statusKeyNormalized = $accountStatusRaw !== '' ? (function_exists('mb_strtolower') ? mb_strtolower($accountStatusRaw) : strtolower($accountStatusRaw)) : '';
$statusMap = [
    'active' => 'active', 'activé' => 'active', 'actif' => 'active',
    'examen' => 'exam', 'exam' => 'exam', 'en examen' => 'exam',
    'suspendu' => 'suspended', 'suspended' => 'suspended', 'suspendue' => 'suspended',
    'bloqué' => 'blocked', 'bloque' => 'blocked', 'blocked' => 'blocked',
];
$mapped = $statusMap[$statusKeyNormalized] ?? null;
if ($mapped) {
    $accountStatusLabelRaw = t($mapped);
} else {
    // try direct translation of the raw status
    $accountStatusLabelRaw = t($statusKeyNormalized ?: $accountStatusRaw);
}
$accountStatusLabel = htmlspecialchars($accountStatusLabelRaw !== '' ? $accountStatusLabelRaw : $accountStatusRaw, ENT_QUOTES, 'UTF-8');
$accountStatusVariant = 'status-active';
if (stripos($accountStatusRaw, 'suspend') !== false || stripos($accountStatusRaw, 'bloqu') !== false) {
    $accountStatusVariant = 'status-blocked';
} elseif (stripos($accountStatusRaw, 'pending') !== false || stripos($accountStatusRaw, 'en attente') !== false) {
    $accountStatusVariant = 'status-pending';
}

$transactionLabels = function_exists('getTransactionLabels') ? getTransactionLabels() : [
    'transfer received' => 'Virement reçu',
    'Transfer sent' => 'Virement émis',
    'Refund received' => 'Remboursement',
    'Funds deducted' => 'Prélèvement',
    'Funds added' => 'Virement reçu',
];

$incomingTypeKeys = ['transfer received', 'funds added', 'solde initial', 'refund received'];
$transactionMetaMap = [
    'transfer received' => ['icon' => 'fa-money-bill-transfer', 'variant' => 'positive'],
    'funds added' => ['icon' => 'fa-money-bill-transfer', 'variant' => 'positive'],
    'solde initial' => ['icon' => 'fa-money-bill-transfer', 'variant' => 'positive'],
    'transfer sent' => ['icon' => 'fa-paper-plane', 'variant' => 'negative'],
    'funds deducted' => ['icon' => 'fa-minus-circle', 'variant' => 'negative'],
    'refund received' => ['icon' => 'fa-undo-alt', 'variant' => 'refund'],
    'default' => ['icon' => 'fa-university', 'variant' => 'neutral'],
];

$incomingCount = 0;
$outgoingCount = 0;
$incomingTotal = 0;
$outgoingTotal = 0;
$lastMovementDateRaw = null;

foreach ($historique_transactions as $tx) {
    $typeKey = is_string($tx['transaction_type'] ?? null) ? strtolower(trim($tx['transaction_type'])) : '';
    $txAmount = (float)($tx['amount'] ?? 0);
    if (in_array($typeKey, $incomingTypeKeys, true)) {
        $incomingCount++;
        $incomingTotal += $txAmount;
    } else {
        $outgoingCount++;
        $outgoingTotal += $txAmount;
    }
    if ($lastMovementDateRaw === null && !empty($tx['created_at'])) {
        $lastMovementDateRaw = $tx['created_at'];
    }
}

// Formater les montants avec k pour les milliers
function formatAmountShort($amount) {
    return number_format(abs($amount), 0, ',', ' ');
}

$incomingCountFormatted = ($incomingTotal > 0 ? '+' : '') . formatAmountShort($incomingTotal);
$outgoingCountFormatted = ($outgoingTotal > 0 ? '-' : '') . formatAmountShort($outgoingTotal);
$totalTransactions = $incomingCount + $outgoingCount;
$totalTransactionsFormatted = number_format($totalTransactions, 0, ',', ' ');

$lastMovementDisplay = '';
if ($lastMovementDateRaw !== null) {
    if (function_exists('formatTransactionDate')) {
        $lastMovementDisplay = htmlspecialchars(formatTransactionDate($lastMovementDateRaw), ENT_QUOTES, 'UTF-8');
    } else {
        $lastMovementDisplay = htmlspecialchars($lastMovementDateRaw, ENT_QUOTES, 'UTF-8');
    }
}

$sortedTransactions = $historique_transactions;
usort($sortedTransactions, function ($a, $b) {
    $ta = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
    $tb = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
    if ($ta === $tb) {
        return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
    }
    return $tb <=> $ta;
});

// Load dismissed transaction alerts from session, then merge persisted dismissals from DB
// Load from session first
$dismissedTransactionAlerts = $_SESSION['dismissed_transaction_alerts'] ?? [];
if (!is_array($dismissedTransactionAlerts)) {
    $dismissedTransactionAlerts = [];
}
// Merge cookie-based dismissals (fallback)
try {
    $cookieTx = json_decode($_COOKIE['dismissed_transaction_alerts'] ?? '[]', true);
    if (is_array($cookieTx)) {
        $dismissedTransactionAlerts = array_values(array_unique(array_merge($dismissedTransactionAlerts, $cookieTx)));
    }
} catch (Exception $e) {
    // ignore cookie parse errors
}
try {
    $userIdForDismiss = $sessionUser['compte_id'] ?? $sessionUser['id'] ?? null;
    if ($userIdForDismiss && is_object(($db = (function_exists('connexion_db') ? connexion_db() : null)))) {
        $stmt = $db->prepare("SELECT alert_type, alert_id FROM dismissed_alerts WHERE compte_id = :cid AND alert_type = 'transaction'");
        $stmt->execute([':cid' => $userIdForDismiss]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $aid = isset($r['alert_id']) ? (string)$r['alert_id'] : '';
            if ($aid !== '') {
                $dismissedTransactionAlerts[] = $aid;
            }
        }
        // ensure unique values
        $dismissedTransactionAlerts = array_values(array_unique($dismissedTransactionAlerts));
    }
} catch (Exception $e) {
    // ignore DB errors and keep session-only values
}

$transactionAlerts = [];
$maxTransactionAlerts = 5;

foreach ($sortedTransactions as $transaction) {
    if (count($transactionAlerts) >= $maxTransactionAlerts) {
        break;
    }
    $typeKey = is_string($transaction['transaction_type'] ?? null) ? strtolower(trim($transaction['transaction_type'])) : '';
    if (!in_array($typeKey, ['refund received', 'funds added', 'funds deducted'], true)) {
        continue;
    }

    $txId = $transaction['id'] ?? null;
    $alertId = $txId !== null ? (string) $txId : md5(json_encode([$typeKey, $transaction['amount'] ?? 0, $transaction['created_at'] ?? '']));
    if (in_array($alertId, $dismissedTransactionAlerts, true)) {
        continue;
    }

    $amount = isset($transaction['amount']) ? number_format((float) $transaction['amount'], 0, ',', ' ') : '0';
    $deviseSafe = htmlspecialchars($transaction['devise'] ?? $defaultDevise, ENT_QUOTES, 'UTF-8');

    $rawDesc = trim((string)($transaction['description'] ?? ''));
    $sourceLabel = 'TRANSFERFLUX';
    if ($rawDesc !== '') {
        if (stripos($rawDesc, 'transafricash') !== false) {
            $sourceLabel = 'TRANSFERFLUX';
        } elseif (stripos($rawDesc, 'paypal') !== false || strpos($rawDesc, '@') !== false) {
            $sourceLabel = 'PayPal';
        } else {
            $sourceLabel = $rawDesc;
        }
    }
    $sourceSafe = htmlspecialchars($sourceLabel, ENT_QUOTES, 'UTF-8');

    if ($typeKey === 'refund received') {
        $transactionAlerts[] = [
            'id' => $alertId,
            'variant' => 'refund',
            'icon' => 'fa-rotate-left',
            'title' => t('notif_refund_title'),
            'message' => t('notif_refund_message', ['amount' => "<strong>{$amount} {$deviseSafe}</strong>", 'source' => $sourceSafe]),
        ];
    } elseif ($typeKey === 'funds added') {
            $transactionAlerts[] = [
                'id' => $alertId,
                'variant' => 'success',
                'icon' => 'fa-circle-check',
                'title' => t('notif_funds_added_title'),
                'message' => t('notif_funds_added_message', ['amount' => "<strong>{$amount} {$deviseSafe}</strong>", 'source' => $sourceSafe]),
            ];
    } elseif ($typeKey === 'funds deducted') {
        $transactionAlerts[] = [
            'id' => $alertId,
            'variant' => 'deduct',
            'icon' => 'fa-circle-exclamation',
            'title' => t('notif_funds_deducted_title'),
            'message' => t('notif_funds_deducted_message', ['amount' => "<strong>-{$amount} {$deviseSafe}</strong>", 'source' => $sourceSafe]),
        ];
    }
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap');
        :root {
            --ui-font: 'Inter', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --alert-font: 'Space Grotesk', var(--ui-font);
        }
        .dashboard nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            color: #111827;
        }
        .dashboard hr {
            display: none;
        }
        .show-wrapper {
            max-width: 1080px;
            margin: 0 auto;
            padding: 0 20px 84px;
        }
        .alert-stack {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 28px;
        }
        .alert-modern {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 16px;
            padding: 18px 22px;
            border-radius: 16px;
            border: 1px solid rgba(226, 232, 240, 0.7);
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            backdrop-filter: blur(16px);
        }
        .alert-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
        }
        .alert-body {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .alert-title {
            margin: 0;
            font-size: 1.05rem; /* slightly larger */
            font-weight: 700;
            font-family: var(--alert-font);
            letter-spacing: 0.01em;
        }
        .alert-message {
            margin: 0;
            font-size: 1.00rem; /* readable on mobile */
            line-height: 1.5;
            color: #374151;
            font-weight: 500;
            font-family: var(--alert-font);
            letter-spacing: 0.005em;
        }
        .alert-modern .btn-close {
            border: none;
            background: transparent;
            color: #6b7280;
            font-size: 1.1rem;
            cursor: pointer;
            opacity: 0.7;
        }
        .alert-modern .btn-close:hover {
            opacity: 1;
        }
        .alert-modern.variant-success { border-left: 4px solid #10b981; }
        .alert-modern.variant-info { border-left: 4px solid #6366f1; }
        .alert-modern.variant-refund { border-left: 4px solid #10b981; }
        .alert-modern.variant-added { border-left: 4px solid #6366f1; }
        .alert-modern.variant-deduct { border-left: 4px solid #f97316; }
        .alert-icon.variant-success,
        .alert-icon.variant-refund {
            background: rgba(16, 185, 129, 0.12);
            color: #047857;
        }
        .alert-icon.variant-info,
        .alert-icon.variant-added {
            background: rgba(99, 102, 241, 0.15);
            color: #4338ca;
        }
        .alert-icon.variant-deduct {
            background: rgba(249, 115, 22, 0.15);
            color: #b45309;
        }
        .overview-hero {
            position: relative;
            overflow: hidden;
            border-radius: 26px;
            padding: 32px;
            color: #fff;
            background: linear-gradient(135deg, #4f2ee8 0%, #6c3ce0 50%, #8244e0 100%);
            box-shadow: 0 25px 60px rgba(79, 46, 232, 0.30);
            margin-bottom: 28px;
        }
        .overview-hero::after {
            content: '';
            position: absolute;
            top: -80px;
            right: -40px;
            width: 260px;
            height: 260px;
            background: radial-gradient(circle, rgba(255,255,255,0.45), transparent 68%);
        }
        .hero-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .hero-title {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .hero-chip {
            align-self: flex-start;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.2);
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .hero-greeting {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .hero-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
            font-weight: 600;
        }
        .hero-status.status-active { color: #d1fae5; background: rgba(16,185,129,0.25); }
        .hero-status.status-blocked { color: #fee2e2; background: rgba(248,113,113,0.35); }
        .hero-status.status-pending { color: #fef3c7; background: rgba(251,191,36,0.32); }
        .hero-main {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: flex-end;
            margin-top: 26px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .hero-label {
            margin-bottom: 6px;
            font-size: 0.95rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            opacity: 1;
            font-weight: 600;
        }
        .hero-balance {
            margin: 0;
            font-size: 4.6rem;
            font-weight: 800;
            letter-spacing: -0.004em;
        }
        /* Stronger override pour conserver la taille dans le hero principal */
        .overview-hero .hero-balance {
            font-size: 4.6rem !important;
            line-height: 1.08;
        }
        .hero-actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }
        .primary-btn,
        .ghost-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            font-size: 0.95rem;
        }
        .primary-btn {
            background: #fff;
            color: #1f2937;
            box-shadow: 0 18px 35px rgba(255, 255, 255, 0.3);
        }
        .ghost-btn {
            background: rgba(255, 255, 255, 0.16);
            color: #f9fafb;
            border: 1px solid rgba(255, 255, 255, 0.22);
        }
        .primary-btn:hover,
        .ghost-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 22px 40px rgba(15, 23, 42, 0.18);
        }
        .hero-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 18px;
            margin-top: 24px;
            position: relative;
            z-index: 1;
        }
        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .meta-label {
            font-size: 0.82rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.95;
            font-weight: 600;
        }
        .meta-value {
            font-size: 1.18rem;
            font-weight: 700;
        }
        /* Ensure hero area text stays white even if timeline rules override colors */
        .overview-hero .meta-value,
        .overview-hero .meta-label,
        .overview-hero .hero-chip,
        .overview-hero .hero-greeting,
        .overview-hero .hero-label,
        .overview-hero .hero-balance,
        .overview-hero .hero-status,
        .overview-hero .timeline-datetime,
        .overview-hero .timeline-datetime .date,
        .overview-hero .timeline-datetime .time {
            color: #ffffff !important;
            /* strengthen weight/size for better legibility on gradient backgrounds */
            font-weight: 700 !important;
        }
        .overview-hero .meta-value,
        .overview-hero .timeline-datetime .date,
        .overview-hero .timeline-datetime .time {
            /* ensure numbers/dates are slightly larger and bolder */
            font-size: 1.05rem !important;
            font-weight: 700 !important;
        }
        .overview-hero .hero-balance {
            font-size: 4.6rem !important;
            line-height: 1.08;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }
        .stat-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 22px;
            border-radius: 18px;
            background: #fff;
            border: 1px solid rgba(226, 232, 240, 0.75);
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.12);
        }
        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }
        .stat-card.positive .stat-icon { background: rgba(16,185,129,0.12); color: #047857; }
        .stat-card.negative .stat-icon { background: rgba(239,68,68,0.16); color: #b91c1c; }
        .stat-card.neutral .stat-icon { background: rgba(99,102,241,0.17); color: #4338ca; }
        .stat-label {
            margin: 0;
            font-size: 0.8rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #6b7280;
        }
        .stat-value {
            margin: 6px 0 0;
            font-size: 1.45rem;
            font-weight: 700;
        }
        .timeline-card {
            background: transparent;
            border-radius: 0;
            padding: 0;
            box-shadow: none;
        }
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
            padding: 0;
        }
        .timeline-header h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
        }
        .timeline-header p {
            display: none;
        }
        .timeline-pill {
            padding: 4px 12px;
            border-radius: 999px;
            background: transparent;
            color: #6366f1;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .timeline-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 10px;
            margin-bottom: 8px;
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.75);
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
            transition: all 0.2s ease;
        }
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        .timeline-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }
        /* Fond coloré pour les transactions sur mobile */
        .timeline-item.variant-negative {
            background: #fff1f2;
            border-color: #fecdd3;
        }
        .timeline-item.variant-positive,
        .timeline-item.variant-refund {
            background: #ecfdf5;
            border-color: #a7f3d0;
        }
        .timeline-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        .timeline-icon.variant-positive { 
            background: #d1fae5; 
            color: #047857; 
        }
        .timeline-icon.variant-negative { 
            background: #fecdd3; 
            color: #dc2626; 
        }
        .timeline-icon.variant-refund { 
            background: #d1fae5; 
            color: #047857; 
        }
        .timeline-icon.variant-neutral { 
            background: #ddd6fe; 
            color: #6366f1; 
        }
        .timeline-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .timeline-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .timeline-row:first-child {
            margin-bottom: 1px;
        }
        .timeline-title {
            font-weight: 900;
            color: #111827;
            font-size: calc(0.82rem + 2px);
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .timeline-amount {
            font-size: calc(0.82rem + 2px);
            font-weight: 900;
            white-space: nowrap;
            flex-shrink: 0;
            margin-left: 6px;
        }
        .timeline-amount.variant-positive { color: #16a34a; }
        .timeline-amount.variant-negative { color: #dc2626; }
        .timeline-amount.variant-refund { color: #16a34a; }
        .timeline-amount.variant-neutral { color: #374151; }
        .timeline-datetime {
            font-size: 0.68rem;
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
            gap: 3px;
            color: #6b7280;
        }
        .timeline-datetime i {
            font-size: 0.65rem;
            flex-shrink: 0;
        }
        .timeline-datetime .date,
        .timeline-datetime .time {
            display: inline !important;
            margin-left: 2px;
        }
        .timeline-meta {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.68rem;
            color: #6b7280;
        }
        /* allow bank/iban text to truncate with ellipsis when space is limited */
        .timeline-meta { min-width: 0; }
        .timeline-meta .meta-bank {
            display: inline-block;
            max-width: 100%;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: middle;
        }
        .timeline-meta i {
            font-size: 0.85rem;
        }
        .timeline-submeta {
            display: block;
            color: #6b7280;
            font-size: 0.82rem;
            margin-top: 6px;
        }
        .timeline-submeta i { font-size: 0.78rem; margin-right: 6px; }
        .meta-email { font-weight: 500; }
        /* ensure email/account meta can ellipsize when needed */
        .meta-email, .meta-bank {
            display: inline-block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
            white-space: nowrap;
            /* prevent browsers from breaking long tokens like emails */
            word-break: normal;
            overflow-wrap: normal;
        }
        /* PayPal label should remain visible even on very small screens (short word) */
        .paypal-label {
            white-space: nowrap;
            max-width: none;
            overflow: visible;
            text-overflow: clip;
            word-break: normal;
            overflow-wrap: normal;
        }
        /* Utility to force no breaking and ellipsis when space is limited.
           Uses !important to override other rules in global stylesheet. */
        .no-break {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
            display: inline-block !important;
            max-width: 100% !important;
            vertical-align: middle !important;
        }
        /* Ensure timeline meta area doesn't force inner items to wrap in tight layouts */
        .timeline-row,
        .timeline-meta,
        .timeline-content {
            min-width: 0;
        }

        /* Keep the meta area flexible so email can use available space.
           Let title truncate first; keep the datetime/amount fixed size. */
        .timeline-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            flex: 1 1 auto;
            min-width: 0;
        }

        .timeline-datetime {
            flex: 0 0 auto;
            white-space: nowrap;
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
        }

        .timeline-row {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: nowrap; /* prefer truncation on children rather than wrapping */
            min-width: 0;
        }

        /* Ensure PayPal icon + label sit on one line; allow email to shrink and ellipsize
           Parent elements must allow children to shrink (min-width:0) for ellipsis to work. */
        .timeline-submeta {
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            overflow: hidden;
            min-width: 0; /* allow children to shrink */
        }

        .timeline-submeta .paypal-label {
            flex: 0 0 auto;
            overflow: visible !important;
            max-width: none !important;
            text-overflow: clip !important;
            white-space: nowrap !important;
            display: inline-block;
        }

        /* Let the email element use remaining space and ellipsize when it doesn't fit.
           Use strict no-break rules and prefer keeping tokens together (keep-all) so
           emails and account numbers do not break character-by-character. */
        .timeline-submeta .meta-email,
        .timeline-meta .meta-email {
            flex: 1 1 auto !important;
            min-width: 0 !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            word-break: keep-all !important;
            overflow-wrap: normal !important;
            display: inline-block !important;
            max-width: 100% !important;
        }

        /* Short variant: ensure it doesn't wrap and has slightly more room when needed. */
        .meta-email-short {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            max-width: calc(100% - 40px) !important;
            display: inline-block !important;
        }

        /* Make PayPal label clearly visible and bold on small screens */
        .timeline-submeta .paypal-label {
            font-weight: 600 !important;
            color: inherit;
        }
        /* Override: ensure PayPal label uses the same meta color as site rules
           This is placed here to take precedence over external stylesheets. */
        .timeline-submeta .paypal-label,
        .timeline-submeta .paypal-label a {
            color: #1f2937 !important;
            font-weight: 500 !important;
        }
        /* Increase contrast for all timeline meta labels and the date/time so they're
           clearly readable. This override strengthens color/weight for bank/source
           labels, PayPal, emails, values and the datetime. */
            .timeline-card .timeline-item .timeline-meta,
        .timeline-card .timeline-item .timeline-submeta,
        .timeline-meta,
        .timeline-submeta,
        .timeline-meta .meta-bank,
        .timeline-submeta .meta-bank,
        .meta-email,
        .meta-bank,
        .meta-value,
        .timeline-submeta a,
        .timeline-meta a,
        .meta-email a,
        .meta-bank a,
        .meta-value a,
            .timeline-datetime,
            .timeline-datetime i {
            color: #1f2937 !important; /* slightly lighter slate to match screenshot */
            font-weight: 500 !important;
        }
        /* Bring the PayPal label closer to its icon (tighter grouping for paypal only) */
        .timeline-card .timeline-item .timeline-submeta i.fab.fa-paypal {
            margin-right: 0 !important; /* tighten icon->label gap to zero */
        }
        /* Slight negative offset to bring the label even closer to the icon */
        .timeline-card .timeline-item .timeline-submeta i.fab.fa-paypal + .paypal-label {
            margin-left: -2px !important;
        }
        /* Extra-high specificity fallback: target the paypal label inside timeline-card items
           to override any other conflicting rules (including component/utility CSS). */
        .timeline-card .timeline-item .timeline-submeta .meta-bank.paypal-label,
        .timeline-card .timeline-item .timeline-submeta .meta-bank.paypal-label a,
        .timeline-card .timeline-item .timeline-submeta .meta-bank.paypal-label span {
            color: #1f2937 !important;
            font-weight: 500 !important;
        }
        /* Ensure both date and time are equally legible (same color/weight) */
        .timeline-card .timeline-item .timeline-datetime .date,
        .timeline-card .timeline-item .timeline-datetime .time,
        .timeline-datetime .date,
        .timeline-datetime .time {
            color: #1f2937 !important;
            font-weight: 600 !important;
        }
        .timeline-empty {
            text-align: center;
            padding: 32px 12px;
            color: #94a3b8;
        }
        .timeline-empty i {
            display: block;
            font-size: 2.8rem;
            margin-bottom: 12px;
        }
        @media (max-width: 768px) {
            .dashboard nav {
                padding: 8px 16px;
            }

            .show-wrapper {
                padding: 0 16px 84px 16px;
            }
            
            .alert-stack {
                gap: 12px;
                margin-bottom: 20px;
            }
            
            .alert-modern {
                padding: 14px 16px;
                gap: 12px;
            }
            
            .alert-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .alert-body {
                gap: 4px;
            }
            
            .alert-title {
                font-size: 0.9rem;
            }
            
            .alert-message {
                font-size: 0.82rem;
            }
            
            .overview-hero {
                border-radius: 20px;
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .hero-chip {
                font-size: 0.7rem;
                padding: 4px 10px;
            }
            
            .hero-greeting {
                font-size: 1.15rem;
            }
            
            .hero-status {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
            
            .hero-label {
                font-size: 0.75rem;
            }
            
            .hero-balance { 
                font-size: 3.0rem; 
            }
            .overview-hero .hero-balance { font-size: 3.0rem !important; }
            
            .hero-actions { 
                width: 100%;
                flex-direction: column;
                gap: 10px;
                margin-top: 16px;
            }
            
            .primary-btn,
            .ghost-btn { 
                width: 100%;
                justify-content: center;
                padding: 12px 20px;
                font-size: 0.9rem;
                border-radius: 12px;
            }
            
            .hero-meta {
                grid-template-columns: 1fr;
                gap: 14px;
                margin-top: 18px;
            }
            
            .meta-item {
                display: flex;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
            }
            
            .meta-label {
                font-size: 0.72rem;
                opacity: 0.8;
            }
            
            .meta-value {
                font-size: 0.9rem;
                text-align: right;
            }
            
            .timeline-card {
                padding: 0;
                background: transparent;
            }
            
            .timeline-header {
                margin-bottom: 12px;
                padding: 0 4px;
            }
            
            .timeline-header h4 {
                font-size: 0.9rem;
            }
            
            .timeline-pill {
                font-size: 0.8rem;
            }
            
            .timeline-card .timeline-item { padding: 10px 10px !important; gap: 10px !important; margin-bottom: 8px !important; border-radius: 14px !important; align-items: center !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-icon { width: 29px !important; height: 29px !important; min-width: 29px !important; min-height: 29px !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-icon i { font-size: calc(0.78rem + 1px) !important; }
            .timeline-card .timeline-item .timeline-content { gap: 4px !important; }
            .timeline-card .timeline-item .timeline-row { gap: 6px !important; }
            .timeline-card .timeline-item .timeline-title { font-size: calc(0.82rem + 2px) !important; font-weight: 900 !important; }
            .timeline-card .timeline-item .timeline-amount { font-size: calc(0.82rem + 2px) !important; font-weight: 900 !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-datetime { font-size: 0.72rem !important; font-weight: 500 !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-datetime .date,
            .show-wrapper .timeline-card .timeline-item .timeline-datetime .time,
            .show-wrapper .timeline-card .timeline-item .timeline-datetime i { font-size: 0.72rem !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-meta { font-size: 0.72rem !important; font-weight: 500 !important; gap: 4px !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-meta .meta-bank,
            .show-wrapper .timeline-card .timeline-item .timeline-meta .meta-email,
            .show-wrapper .timeline-card .timeline-item .timeline-meta i,
            .show-wrapper .timeline-card .timeline-item .timeline-submeta,
            .show-wrapper .timeline-card .timeline-item .timeline-submeta .meta-email,
            .show-wrapper .timeline-card .timeline-item .timeline-submeta i { font-size: 0.72rem !important; }
            
            footer.footer-show {
                width: calc(100% - 24px);
                max-width: 100%;
                left: 12px;
                right: 12px;
                transform: none;
                bottom: 12px;
                padding: 10px 8px;
                border-radius: 24px;
            }
        }
        
        @media (max-width: 576px) {
            .show-wrapper {
                padding: 0 12px 84px 12px;
            }
            
            .overview-hero {
                padding: 18px;
                border-radius: 18px;
            }
            
            .hero-greeting {
                font-size: 1.05rem;
            }
            
            .hero-balance {
                font-size: 1.9rem;
            }
            .overview-hero .hero-balance { font-size: 1.9rem !important; }
            
            .primary-btn,
            .ghost-btn {
                padding: 12px 20px;
                font-size: 0.9rem;
            }
            
            .primary-btn i,
            .ghost-btn i {
                font-size: 0.82rem;
            }
            
            .stat-grid { 
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .hero-meta { 
                grid-template-columns: 1fr;
                gap: 12px;
                margin-top: 16px;
            }
            
            .meta-label {
                font-size: 0.7rem;
            }
            
            .meta-value {
                font-size: 0.85rem;
            }
            
            .timeline-card {
                padding: 0;
            }
            
            .timeline-card .timeline-item { padding: 8px 10px !important; margin-bottom: 6px !important; align-items: center !important; }

            /* Notifications: améliorer lisibilité sur très petits écrans */
            .alert-modern {
                padding: 12px 14px;
                gap: 10px;
            }

            .alert-modern .alert-title {
                font-size: 1rem;
                font-weight: 800;
                line-height: 1.12;
                font-family: var(--ui-font);
                color: #0f172a;
            }

            .alert-modern .alert-message {
                font-size: 0.95rem;
                font-weight: 600;
                line-height: 1.3;
                font-family: var(--ui-font);
                color: #374151;
                /* Ensure message wraps and remains readable */
                white-space: normal;
            }

            .alert-icon {
                width: 44px;
                height: 44px;
                font-size: 1.05rem;
            }
            
            .show-wrapper .timeline-card .timeline-item .timeline-icon { width: 24px !important; height: 24px !important; min-width: 24px !important; min-height: 24px !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-icon i { font-size: calc(0.68rem + 1px) !important; }
            .timeline-card .timeline-item .timeline-title { font-size: calc(0.80rem + 2px) !important; font-weight: 900 !important; color: #111827 !important; }
            .timeline-card .timeline-item .timeline-amount { font-size: calc(0.80rem + 2px) !important; font-weight: 900 !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-datetime { font-size: 0.67rem !important; font-weight: 500 !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-datetime .date,
            .show-wrapper .timeline-card .timeline-item .timeline-datetime .time,
            .show-wrapper .timeline-card .timeline-item .timeline-datetime i { font-size: 0.67rem !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-meta { font-size: 0.67rem !important; font-weight: 500 !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-meta .meta-bank,
            .show-wrapper .timeline-card .timeline-item .timeline-meta .meta-email,
            .show-wrapper .timeline-card .timeline-item .timeline-meta i,
            .show-wrapper .timeline-card .timeline-item .timeline-submeta,
            .show-wrapper .timeline-card .timeline-item .timeline-submeta .meta-email,
            .show-wrapper .timeline-card .timeline-item .timeline-submeta i { font-size: 0.67rem !important; }
            
        }

        /* Target small phones (iPhone 5 / SE width 320px, iPhone X width 375px)
           Force email meta to ellipsize and limit max-width so it doesn't push layout */
        @media (max-width: 375px) {
            .timeline-submeta .meta-email,
            .timeline-meta .meta-email {
                white-space: nowrap;
                /* leave room for icon / amount / date; tweak if needed */
                max-width: calc(100vw - 160px);
                overflow: hidden;
                text-overflow: ellipsis;
                display: inline-block;
                vertical-align: middle;
            }
            /* ensure the timeline meta container can shrink */
            .timeline-row { min-width: 0; }
            .timeline-content { min-width: 0; }
        }

        /* Responsive font weight adjustments (placed outside other @media blocks) */
        @media (max-width: 600px) {
            .timeline-submeta,
            .timeline-meta,
            .meta-email,
            .meta-bank,
            .timeline-submeta .meta-email,
            .timeline-submeta .meta-bank {
                font-weight: 500 !important;
            }
        }

        @media (max-width: 400px) {
            .timeline-datetime,
            .timeline-datetime .date,
            .timeline-datetime .time {
                font-weight: 500 !important;
            }
        }

        /* ===== HISTORIQUE : 5 premiers visibles, reste masqué ===== */
        .timeline-see-all-wrap {
            display: flex;
            justify-content: center;
            margin-top: 10px;
        }
        .timeline-see-all-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 22px;
            border: 1.5px solid #6366f1;
            border-radius: 999px;
            background: transparent;
            color: #6366f1;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.18s, color 0.18s;
        }
        .timeline-see-all-btn:hover { background: #6366f1; color: #fff; }
        .timeline-see-all-btn i { font-size: 0.75rem; transition: transform 0.2s; }
        .timeline-see-all-btn.expanded i { transform: rotate(180deg); }

        /* ===== FOOTER (copié de style.css / carte.php) ===== */
        footer {
            position: fixed;
            display: flex;
            flex-wrap: nowrap;
            max-width: 840px;
            width: calc(100% - 80px);
            left: 50%;
            transform: translateX(-50%);
            height: 64px;
            border-radius: 20px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .06);
            border: 1px solid #e9f2ff;
            bottom: 16px;
            overflow: hidden;
            background-color: white;
            z-index: 1000;
            padding: 0 12px;
            box-sizing: border-box;
        }
        @media screen and (max-width: 800px) {
            footer {
                left: 20px;
                right: 20px;
                transform: none;
                width: auto;
                border-radius: 16px;
                bottom: 14px;
            }
        }
        @media screen and (max-width: 768px) {
            footer {
                width: calc(100% - 24px);
                max-width: 100%;
                left: 12px;
                right: 12px;
                transform: none;
                bottom: 12px;
                padding: 10px 8px;
                border-radius: 24px;
                height: auto;
            }
            footer a {
                padding: 8px 6px;
                font-size: 1.05rem;
                font-weight: 700;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }
            footer a i {
                font-size: 1.6rem;
                height: auto;
                margin-bottom: 4px;
                line-height: 1;
            }
        }
        @media screen and (max-width: 576px) {
            footer {
                padding: 8px 6px;
                bottom: 10px;
                border-radius: 20px;
            }
            footer a {
                font-size: 0.88rem;
                padding: 8px 6px;
                line-height: 1.1;
                white-space: normal;
                font-weight: 700;
            }
            footer a i {
                font-size: 1.35rem;
            }
            footer a.active {
                border-bottom-width: 2.5px;
            }
        }
        @media screen and (max-width: 480px) {
            footer a {
                font-size: 1.1rem;
                padding: 10px 6px;
            }
            footer a i {
                font-size: 1.8rem;
                margin-bottom: 6px;
            }
        }
        @media screen and (min-width: 901px) {
            footer {
                width: min(840px, calc(100% - 80px));
                left: 50%;
                transform: translateX(-50%);
            }
        }
        footer a i {
            display: block;
            color: #007bff;
            margin-bottom: 6px;
            font-size: 1.5rem;
            line-height: 1;
            height: 26px;
        }
        footer, footer a, footer a i {
            font-family: var(--ui-font);
        }
        footer a {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            width: 25%;
            text-align: center;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding: 11px 6px;
            transition: all 120ms ease;
            color: #1f2937;
            position: relative;
            font-weight: 700;
        }
        footer a:hover {
            background-color: #ccebf5;
        }
        footer a.active {
            border-bottom: 3px solid #6f63ff;
        }
        .footer-show a i {
            font-size: 1.9rem;
            height: 34px;
            color: #6f63ff;
        }
        .footer-show a {
            color: #6f63ff;
            flex: 1 1 0%;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        footer.footer-show {
            max-width: 780px;
            width: min(780px, calc(100% - 120px));
            left: 50%;
            transform: translateX(-50%);
            bottom: 12px;
        }
        @media (max-width: 800px) {
            footer.footer-show {
                left: 20px;
                right: 20px;
                transform: none;
                width: auto;
            }
        }
        @media (max-width: 768px) {
            footer.footer-show {
                width: calc(100% - 24px);
                left: 12px;
                right: 12px;
                bottom: 12px;
                border-radius: 25px;
            }
        }
        @media (max-width: 576px) {
            footer.footer-show {
                padding: 8px 6px;
                bottom: 10px;
                border-radius: 25px;
            }
            footer.footer-show a {
                font-size: 0.88rem;
                white-space: nowrap;
            }
            footer.footer-show a i {
                font-size: 1.6rem;
            }
        }
        @media screen and (max-width: 768px) {
            footer a {
                font-size: 1.12rem;
            }
            footer a i {
                font-size: 1.9rem;
            }
        }
        @media screen and (max-width: 480px) {
            footer a {
                font-size: 1.16rem;
            }
            footer a i {
                font-size: 2.0rem;
                margin-bottom: 8px;
            }
        }
    </style>
<div class="dashboard">
        <nav>
            <div><i class="fas fa-bars menu-icon"></i> <strong style="font-size:1.35rem;letter-spacing:-0.3px;">TRANSFERFLUX</strong></div>
            <?php
            $photoUrl = null;
            $rawPhoto = $utilisateur_connecte['photo_path'] ?? '';
            if (!empty($rawPhoto)) {
                if (strpos($rawPhoto, 'http') === 0) {
                    $photoUrl = $rawPhoto;
                } else {
                    $storageBase = resolveEnvValue('COMPTE_EUROPE_STORAGE_PUBLIC_BASE') ?: 'http://127.0.0.1:8000/storage';
                    $photoUrl = rtrim($storageBase, '/') . '/' . $rawPhoto;
                }
            }
            ?>
            <div style="display:flex;align-items:center;gap:16px;">
                <a href="#" onclick="toggleNotifPanel(event)" style="color:#6b7280;font-size:1.3rem;text-decoration:none;position:relative;" id="notif-bell">
                    <i class="fas fa-bell"></i>
                    <?php
                    $notifCount = count($transactionAlerts ?? []);
                    if ($transferSuccess) $notifCount++;
                    if ($showBalanceAlert) $notifCount++;
                    ?>
                    <?php if ($notifCount > 0): ?>
                    <span id="notif-badge" style="position:absolute;top:-6px;right:-8px;background:#ef4444;color:#fff;font-size:0.65rem;font-weight:700;min-width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid #fff;"><?= $notifCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="index.php?page=info" style="display:inline-flex;align-items:center;">
                    <?php if (!empty($photoUrl)): ?>
                        <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="avatar" style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid rgba(0,0,0,0.08);box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <div style="width:42px;height:42px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;"><i class="fas fa-user" style="color:#9ca3af;font-size:1.1rem;"></i></div>
                    <?php endif; ?>
                </a>
            </div>
        </nav>

        <!-- Panneau notifications (s'ouvre au clic sur la cloche) -->
        <div id="notif-panel" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:200;">
            <div id="notif-overlay" onclick="toggleNotifPanel(event)" style="position:absolute;inset:0;background:rgba(0,0,0,0.3);"></div>
            <div style="position:absolute;top:60px;right:12px;left:12px;max-width:420px;margin-left:auto;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.15);max-height:70vh;overflow-y:auto;padding:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <strong style="font-size:1.05rem;">Notifications</strong>
                    <button onclick="toggleNotifPanel(event)" style="background:none;border:none;font-size:1.3rem;color:#9ca3af;cursor:pointer;">&times;</button>
                </div>
                <?php if (!$transferSuccess && !$showBalanceAlert && empty($transactionAlerts)): ?>
                    <p style="text-align:center;color:#9ca3af;padding:24px 0;">Aucune notification</p>
                <?php else: ?>
                <div class="alert-stack">
                    <?php if ($transferSuccess): ?>
                        <div class="alert-modern variant-success" role="alert" data-alert-id="transfer-success">
                            <div class="alert-icon variant-success"><i class="fas fa-circle-check"></i></div>
                            <div class="alert-body">
                                <p class="alert-title"><?php echo htmlspecialchars(t('transfer_completed_success'), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="alert-message"><?php echo htmlspecialchars(t('transfer_completed_success'), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <button type="button" class="btn-close" data-dismiss="alert" aria-label="<?php echo htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8'); ?>">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($showBalanceAlert): ?>
                        <div class="alert-modern variant-success balance-credit-alert" role="alert" data-alert-id="balance-credit">
                            <div class="alert-icon variant-success"><i class="fas fa-check-circle"></i></div>
                            <div class="alert-body">
                                <p class="alert-title" style="color:#047857"><?php echo htmlspecialchars(t('notif_funds_added_title'), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="alert-message"><?php echo htmlspecialchars(t('notif_funds_added_message', ['amount' => $formatted_balance2 . ' ' . $deviseLabel, 'source' => '—']), ENT_QUOTES, 'UTF-8'); ?> <br> <?php echo htmlspecialchars(t('add_iban_or_paypal'), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <button type="button" class="btn-close dismiss-balance-alert" aria-label="<?php echo htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8'); ?>">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($transactionAlerts as $alert): ?>
                        <div class="alert-modern variant-<?= htmlspecialchars($alert['variant'], ENT_QUOTES, 'UTF-8'); ?> transaction-alert" role="alert" data-alert-id="<?= htmlspecialchars($alert['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="alert-icon variant-<?= htmlspecialchars($alert['variant'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fas <?= htmlspecialchars($alert['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></div>
                            <div class="alert-body">
                                <p class="alert-title"><?= htmlspecialchars($alert['title'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="alert-message"><?= $alert['message']; ?></p>
                            </div>
                            <button type="button" class="btn-close dismiss-transaction-alert" data-alert-id="<?= htmlspecialchars($alert['id'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8'); ?>">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="show-wrapper">

            <?php
                $display_nom = $utilisateur_connecte['nom'] ?? $sessionUser['nom'] ?? '';
                $display_prenom = $utilisateur_connecte['prenom'] ?? $sessionUser['prenom'] ?? '';
                $display_full = trim(($display_prenom ? $display_prenom . ' ' : '') . $display_nom);
                $display_full = htmlspecialchars($display_full, ENT_QUOTES, 'UTF-8');
            ?>

            <section class="overview-hero">
                <div class="hero-header">
                    <div class="hero-title">
                        <span class="hero-chip"><?= $accountTypeLabel; ?></span>
                        <?php
                        // Localized greeting: try a short map for common languages, fallback to generic 'User'
                        $lang = function_exists('current_lang') ? current_lang() : 'fr';
                        $greetings = [
                            'fr' => 'Bonjour', 'en' => 'Hello', 'es' => 'Hola', 'de' => 'Hallo', 'it' => 'Ciao',
                            'pt' => 'Olá', 'nl' => 'Hallo', 'pl' => 'Witaj', 'ru' => 'Здравствуйте', 'sv' => 'Hej',
                            'no' => 'Hei', 'da' => 'Hej', 'fi' => 'Hei', 'zh' => '你好', 'ja' => 'こんにちは',
                            'ko' => '안녕하세요', 'tr' => 'Merhaba', 'cs' => 'Ahoj', 'ro' => 'Bună', 'hr' => 'Bok'
                        ];
                        $gword = $greetings[$lang] ?? ($greetings[substr($lang,0,2)] ?? 'Hello');
                        $nameForDisplay = $display_full !== '' ? $display_full : (t('user_placeholder'));
                        ?>
                        <h2 class="hero-greeting"><?= htmlspecialchars("{$gword} {$nameForDisplay}", ENT_QUOTES, 'UTF-8') ?></h2>
                    </div>
                    <div class="hero-status <?= $accountStatusVariant; ?>">
                        <i class="fas fa-circle"></i>
                        <span><?= $accountStatusLabel; ?></span>
                    </div>
                </div>
                <div class="hero-main">
                    <div>
                        <div class="hero-label"><?= htmlspecialchars(t('hero_label'), ENT_QUOTES, 'UTF-8') ?></div>
                        <p class="hero-balance"><?= $formatted_balance . ' ' . $deviseLabel; ?></p>
                    </div>
                    <div class="hero-actions">
                        <a href="index.php?page=transfert" class="primary-btn"><i class="fas fa-paper-plane"></i><span><?= htmlspecialchars(t('perform_transfer'), ENT_QUOTES, 'UTF-8') ?></span></a>
                        <a href="index.php?page=carte" class="ghost-btn"><i class="fas fa-credit-card"></i><span><?= htmlspecialchars(t('my_card'), ENT_QUOTES, 'UTF-8') ?></span></a>
                    </div>
                </div>
                <div class="hero-meta">
                    <div class="meta-item">
                        <span class="meta-label"><?= htmlspecialchars(t('account_type_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="meta-value"><?= $accountTypeLabel; ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label"><?= htmlspecialchars(t('account_currency_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="meta-value"><?= $deviseLabel; ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label"><?= htmlspecialchars(t('last_movement'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="meta-value"><?= $lastMovementDisplay; ?></span>
                    </div>
                </div>
            </section>

            <section class="stat-grid">
                <article class="stat-card positive">
                    <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
                    <div>
                        <p class="stat-label"><?= htmlspecialchars(t('transactions_incoming'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="stat-value"><?= $incomingCountFormatted; ?></p>
                    </div>
                </article>
                <article class="stat-card negative">
                    <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
                    <div>
                        <p class="stat-label"><?= htmlspecialchars(t('transactions_outgoing'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="stat-value"><?= $outgoingCountFormatted; ?></p>
                    </div>
                </article>
                <article class="stat-card neutral">
                    <div class="stat-icon"><i class="fas fa-list-ul"></i></div>
                    <div>
                        <p class="stat-label"><?= htmlspecialchars(t('transactions_total'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="stat-value"><?= $totalTransactionsFormatted; ?></p>
                    </div>
                </article>
            </section>

            <section class="timeline-card">
                <div class="timeline-header">
                            <div>
                                <h4><?= htmlspecialchars(t('activity_recent'), ENT_QUOTES, 'UTF-8') ?></h4>
                                <p>Suivez vos virements entrants et sortants en temps reel.</p>
                            </div>
                            <span class="timeline-pill"><?= count($historique_transactions); ?> <?= htmlspecialchars(t('movements'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                <?php if (!empty($historique_transactions)) : ?>
                    <?php $tx_index = 0; foreach ($sortedTransactions as $transaction) : $tx_index++; ?>
                        $amount = (float)($transaction['amount'] ?? 0);
                        $formattedAmount = number_format($amount, 2, ',', ' ');
                        $typeKey = is_string($transaction['transaction_type'] ?? null) ? strtolower(trim($transaction['transaction_type'])) : '';
                        // Prefer i18n keys for transaction labels. Try `transaction_<slug>` then fall back to known notif titles.
                        $rawTypeLabel = $transaction['transaction_type'] ?? 'Transaction';
                        $slugLabel = strtolower(trim((string)$rawTypeLabel));
                        $slugLabel = preg_replace('/[^a-z0-9]+/i', '_', $slugLabel);
                        $i18nKey = 'transaction_' . $slugLabel;
                        $i18nLabel = t($i18nKey);
                        if ($i18nLabel !== $i18nKey) {
                            $transactionLabel = $i18nLabel;
                        } else {
                            // fallback mapping to existing notif keys
                            if (stripos($slugLabel, 'refund') !== false) {
                                $transactionLabel = t('notif_refund_title');
                            } elseif (stripos($slugLabel, 'funds_added') !== false || stripos($slugLabel, 'funds') !== false) {
                                $transactionLabel = t('notif_funds_added_title');
                            } elseif (stripos($slugLabel, 'funds_deducted') !== false || stripos($slugLabel, 'deduct') !== false) {
                                $transactionLabel = t('notif_funds_deducted_title');
                            } elseif (stripos($slugLabel, 'transfer_sent') !== false || stripos($slugLabel, 'transfer') !== false) {
                                // Label for outgoing transfers in the activity list (translated)
                                $transactionLabel = t('virement_sortant');
                            } else {
                                $transactionLabel = $rawTypeLabel;
                            }
                        }
                        // Ensure we don't show the generic 'perform_transfer' label
                        // in the activity list; prefer the outgoing-transfer label.
                        if (isset($transactionLabel) && $transactionLabel === t('perform_transfer')) {
                            $transactionLabel = t('virement_sortant');
                        }
                        $meta = $transactionMetaMap[$typeKey] ?? $transactionMetaMap['default'];
                        $variant = $meta['variant'];
                        $iconClassName = htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8');
                        $isIncoming = in_array($typeKey, $incomingTypeKeys, true);

                        $rawDesc = trim((string)($transaction['description'] ?? ''));
                        $displayDesc = $rawDesc;

                        // Preferer IBAN/numero de compte à la place du BIC/SWIFT
                        $ibanField = trim((string)($transaction['iban'] ?? ''));
                        // support plusieurs variantes de nom de champ pour le numéro de compte
                        $acctField = trim((string)($transaction['account_number'] ?? $transaction['account'] ?? $transaction['numero_compte'] ?? $transaction['numerocompte'] ?? ''));

                        // Extraire IBAN ou BIC depuis la description si present
                        $ibanFromDescription = '';
                        $bicFromDescription = '';
                        if ($rawDesc !== '') {
                            // IBAN pattern: starts with 2 letters then digits/letters (approximate, broad match)
                            if (preg_match('/\b([A-Z]{2}[0-9A-Z]{10,30})\b/i', $rawDesc, $mIban)) {
                                $ibanFromDescription = strtoupper($mIban[1]);
                            }
                            if (preg_match('/BIC:\s*([A-Z0-9]+)/i', $rawDesc, $mBic)) {
                                $bicFromDescription = strtoupper($mBic[1]);
                            }
                        }

                        // Extraire l'IBAN directement depuis la description (format IBAN: XXXX)
                        if (preg_match('/IBAN:\s*([A-Z0-9\s]+)/i', $rawDesc, $mIbanDirect)) {
                            $ibanFromDescription = strtoupper(trim($mIbanDirect[1]));
                        }

                        // Resolve preferred identifier: IBAN > account number > cleaned bank name
                        $resolvedIban = $ibanField !== '' ? $ibanField : $ibanFromDescription;
                        $resolvedAcct = $acctField !== '' ? $acctField : '';

                        if ($resolvedIban !== '') {
                            $displayDesc = $resolvedIban;
                        } elseif ($resolvedAcct !== '') {
                            $displayDesc = $resolvedAcct;
                        } else {
                            // otherwise use a cleaned bank name or generic label
                            if ($rawDesc !== '') {
                                $cleanBank = trim(preg_replace('/\s*-?\s*(?:BIC:\s*[A-Z0-9]+|IBAN:\s*[A-Z0-9]+)\s*$/i', '', $rawDesc));
                                $cleanBank = trim(preg_replace('/@.+$/', '', $cleanBank));
                                if (stripos($cleanBank, 'transafricash') !== false) {
                                    $displayDesc = 'TRANSFERFLUX';
                                } elseif (stripos($cleanBank, 'paypal') !== false || strpos($cleanBank, '@') !== false) {
                                    $displayDesc = 'PayPal';
                                } else {
                                    $displayDesc = $cleanBank !== '' ? $cleanBank : 'TRANSFERFLUX';
                                }
                            } else {
                                $displayDesc = 'TRANSFERFLUX';
                            }
                        }
                        
                        $displayDescSafe = htmlspecialchars($displayDesc !== '' ? $displayDesc : 'TRANSFERFLUX', ENT_QUOTES, 'UTF-8');

                        // If this appears to be a PayPal-related entry, try to extract an email
                        $paypalEmail = '';
                        if (stripos($displayDesc, 'paypal') !== false || strpos($rawDesc, '@') !== false) {
                            if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $rawDesc, $mEmail)) {
                                $paypalEmail = $mEmail[0];
                            } elseif (!empty($transaction['transfer_id'])) {
                                try {
                                    $db = function_exists('connexion_db') ? connexion_db() : null;
                                    if (is_object($db)) {
                                        $stmt = $db->prepare('SELECT * FROM transfers WHERE id = :id LIMIT 1');
                                        $stmt->execute([':id' => $transaction['transfer_id']]);
                                        $trow = $stmt->fetch(PDO::FETCH_ASSOC);
                                        if ($trow) {
                                            foreach ($trow as $v) {
                                                if (is_string($v) && preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $v, $m)) {
                                                    $paypalEmail = $m[0];
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    // ignore DB issues here
                                }
                            }
                        }
                        $paypalEmailSafe = htmlspecialchars($paypalEmail, ENT_QUOTES, 'UTF-8');

                        $dateValue = function_exists('formatTransactionDate') ? formatTransactionDate($transaction['created_at'] ?? '') : ($transaction['created_at'] ?? '');
                        // split into date and time parts when possible so we can show time under date on small screens
                        $dateOnly = $dateValue;
                        $timeOnly = '';
                        if (preg_match('/(\d{1,4}[\/\-]\d{1,2}[\/\-]\d{1,4})\s+(\d{1,2}:\d{2}(?::\d{2})?)/', $dateValue, $m)) {
                            $dateOnly = $m[1];
                            $timeOnly = $m[2];
                        } elseif (preg_match('/(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}(?::\d{2})?)/', $dateValue, $m)) {
                            $dateOnly = $m[1];
                            $timeOnly = $m[2];
                        } else {
                            $pos = strrpos($dateValue, ' ');
                            if ($pos !== false) {
                                $dateOnly = substr($dateValue, 0, $pos);
                                $timeOnly = substr($dateValue, $pos + 1);
                            }
                        }
                        $dateOnlySafe = htmlspecialchars($dateOnly, ENT_QUOTES, 'UTF-8');
                        $timeOnlySafe = htmlspecialchars($timeOnly, ENT_QUOTES, 'UTF-8');
                        $deviseSafe = htmlspecialchars($transaction['devise'] ?? $devise, ENT_QUOTES, 'UTF-8');
                        $labelSafe = htmlspecialchars($transactionLabel, ENT_QUOTES, 'UTF-8');
                        $sign = $isIncoming ? '+' : '-';
                    ?>
                    <div class="timeline-item variant-<?= $variant; ?><?= $tx_index > 5 ? ' timeline-extra' : ''; ?>"<?= $tx_index > 5 ? ' style="display:none!important;"' : ''; ?>>
                        <div class="timeline-icon variant-<?= $variant; ?>" style="width:30px!important;height:30px!important;min-width:30px!important;min-height:30px!important;max-width:30px!important;max-height:30px!important;border-radius:50%!important;flex-shrink:0!important;align-self:center!important;aspect-ratio:1/1!important;overflow:hidden!important;box-sizing:border-box!important;">
                            <i class="fas <?= $iconClassName; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-row">
                                <span class="timeline-title"><?= $labelSafe; ?></span>
                                <?php
                                    $amountColor = '#374151';
                                    if ($variant === 'positive' || $variant === 'refund') {
                                        $amountColor = '#16a34a';
                                    } elseif ($variant === 'negative') {
                                        $amountColor = '#dc2626';
                                    }
                                ?>
                                <span class="timeline-amount variant-<?= $variant; ?>" style="color: <?= $amountColor; ?> !important;"><?= $sign; ?> <?= $formattedAmount; ?> <?= $deviseSafe; ?></span>
                            </div>
                            <div class="timeline-row">
                                <div class="timeline-meta" style="font-size:0.72rem!important;">
                                    <?php
                                        // Si c'est un remboursement, et que l'entrée est liée à PayPal,
                                        // afficher l'étiquette localisée 'PayPal' (avec icône PayPal) au lieu
                                        // du nom de la banque / nom utilisateur. Sinon afficher la banque.
                                        if ($variant === 'refund') :
                                            $isPaypalRelated = false;
                                            if (!empty($paypalEmail)) {
                                                $isPaypalRelated = true;
                                            } elseif (stripos($displayDesc, 'paypal') !== false) {
                                                $isPaypalRelated = true;
                                            } elseif (strpos($rawDesc, '@') !== false) {
                                                // fallback: description contains an email-like fragment
                                                $isPaypalRelated = true;
                                            }

                                            if ($isPaypalRelated) :
                                    ?>
                                                <div class="timeline-submeta"><i class="fab fa-paypal"></i> <span class="meta-bank paypal-label"><?= htmlspecialchars(t('paypal'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                                    <?php
                                            else:
                                    ?>
                                                    <?php
                                                        // For refunds that reference a transfer, prefer the original transfer's bank/name_servieur
                                                        $refundBankDisplay = $displayDesc;
                                                        if (!empty($transaction['transfer_id'])) {
                                                            try {
                                                                $db2 = function_exists('connexion_db') ? connexion_db() : null;
                                                                if (is_object($db2)) {
                                                                    $q = $db2->prepare('SELECT bank_name, name_servieur, beneficiary_name, numerocompte, iban FROM transfers WHERE id = :id LIMIT 1');
                                                                    $q->execute([':id' => $transaction['transfer_id']]);
                                                                    $tr = $q->fetch(PDO::FETCH_ASSOC);
                                                                    if ($tr) {
                                                                        // prefer bank_name, then name_servieur, then beneficiary_name, then numerocompte, then iban
                                                                        $cand = trim((string)($tr['bank_name'] ?? ''));
                                                                        if ($cand === '') { $cand = trim((string)($tr['name_servieur'] ?? '')); }
                                                                        if ($cand === '') { $cand = trim((string)($tr['beneficiary_name'] ?? '')); }
                                                                        if ($cand === '') { $cand = trim((string)($tr['numerocompte'] ?? '')); }
                                                                        if ($cand === '') { $cand = trim((string)($tr['iban'] ?? '')); }
                                                                        if ($cand !== '') {
                                                                            $refundBankDisplay = $cand;
                                                                        }
                                                                    }
                                                                }
                                                            } catch (Exception $e) {
                                                                // ignore DB errors and keep existing display
                                                            }
                                                        }
                                                        $refundBankDisplaySafe = htmlspecialchars($refundBankDisplay !== '' ? $refundBankDisplay : $displayDescSafe, ENT_QUOTES, 'UTF-8');
                                                    ?>
                                                    <i class="fas fa-building-columns"></i> <span class="meta-bank"><?= $refundBankDisplaySafe; ?></span>
                                    <?php
                                            endif;
                                        elseif (!empty($paypalEmailSafe)) :
                                    ?>
                                        <?php
                                            // Prepare PayPal email display: use 5+... truncation server-side
                                            // If an email contains '@', preserve the domain (after @):
                                            //   localpart@domain.tld -> first5...@domain.tld
                                            // Otherwise fall back to first5...last3 for other long tokens.
                                            $paypalDisplay = $paypalEmail !== '' ? $paypalEmail : $paypalEmailSafe;
                                            $plain = $paypalDisplay;
                                            if ($plain !== '') {
                                                // Strip invisible/breakable chars and any stray <wbr>/entities
                                                $plain = preg_replace('/(?:\x{00AD}|\x{200B})/u', '', $plain);
                                                $plain = str_replace(array('<wbr>', '&shy;', '&#8203;'), '', $plain);
                                                // Remove accidental whitespace/newlines
                                                $plain = preg_replace('/\s+/u', '', $plain);
                                                $plain = trim($plain);
                                            }

                                            $displayShort = $plain;
                                            if ($plain !== '') {
                                                // If contains an @, keep domain and shorten local part to 5 chars
                                                if (strpos($plain, '@') !== false) {
                                                    list($local, $domain) = explode('@', $plain, 2) + array('', '');
                                                    if (mb_strlen($local) > 4) {
                                                        $displayShort = mb_substr($local, 0, 4) . '...' . '@' . $domain;
                                                    } else {
                                                        $displayShort = $plain;
                                                    }
                                                } else {
                                                    // No @ — fallback: first4 + '...' + last3 when long
                                                    if (mb_strlen($plain) > 7) {
                                                        $displayShort = mb_substr($plain, 0, 4) . '...' . mb_substr($plain, -3);
                                                    } else {
                                                        $displayShort = $plain;
                                                    }
                                                }
                                            }
                                            $displayShortSafe = $displayShort !== '' ? htmlspecialchars($displayShort, ENT_QUOTES, 'UTF-8') : '';
                                        ?>
                                        <div class="timeline-submeta"><i class="fab fa-paypal"></i> <span class="meta-email no-break"><?= $displayShortSafe; ?></span></div>
                                    <?php else: ?>
                                        <i class="fas fa-building-columns"></i> <span class="meta-bank"><?= $displayDescSafe; ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="timeline-datetime" style="display:inline-flex!important;flex-direction:column!important;align-items:flex-end!important;white-space:nowrap;gap:1px;font-size:0.72rem!important;"><span style="display:inline-flex!important;flex-direction:row!important;align-items:center!important;flex-wrap:nowrap!important;gap:3px;white-space:nowrap;"><i class="far fa-clock" style="display:inline!important;font-size:0.72rem!important;flex-shrink:0;"></i><span class="date" style="display:inline!important;"><?= $dateOnlySafe; ?></span></span><?php if ($timeOnlySafe !== ''): ?><span class="time" style="display:inline!important;"><?= $timeOnlySafe; ?></span><?php endif; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($sortedTransactions) > 5) : ?>
                    <div class="timeline-see-all-wrap">
                        <button class="timeline-see-all-btn" id="timelineSeeAllBtn" onclick="toggleTimelineExtra(this)">
                            <span><?= htmlspecialchars(t('see_all'), ENT_QUOTES, 'UTF-8') ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="timeline-empty">
                        <i class="fas fa-inbox"></i>
                        <p>Aucune transaction enregistree pour l'instant.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <?php include __DIR__ . '/../partials/footer_nav.php'; ?>
    <script>
        function toggleTimelineExtra(btn) {
            const extras = document.querySelectorAll('.timeline-extra');
            const expanded = btn.classList.toggle('expanded');
            extras.forEach(function(el) {
                if (expanded) {
                    el.style.removeProperty('display');
                } else {
                    el.style.setProperty('display', 'none', 'important');
                }
            });
            const label = btn.querySelector('span');
            label.textContent = expanded ? '<?= htmlspecialchars(t('see_less') !== 'see_less' ? t('see_less') : 'Voir moins', ENT_QUOTES, 'UTF-8') ?>' : '<?= htmlspecialchars(t('see_all'), ENT_QUOTES, 'UTF-8') ?>';
        }

        function persistDismiss(alert, alert_id = null) {
            const payload = { alert };
            if (alert_id) {
                payload.alert_id = alert_id;
            }
            fetch('dismiss_alert.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            }).then(resp => {
                // optional: could handle response.json() to confirm success
            }).catch(() => {
                console.warn('Impossible de persister la fermeture de l\'alerte.');
            });
        }

        document.querySelectorAll('.dismiss-balance-alert').forEach(btn => {
            btn.addEventListener('click', function () {
                const alertEl = this.closest('.alert-modern');
                if (alertEl) {
                    alertEl.style.opacity = '0';
                    setTimeout(() => alertEl.remove(), 150);
                }
                persistDismiss('balance');
            });
        });

        document.querySelectorAll('.dismiss-transaction-alert').forEach(btn => {
            btn.addEventListener('click', function () {
                const alertId = this.getAttribute('data-alert-id');
                const alertEl = this.closest('.alert-modern');
                if (alertEl) {
                    alertEl.style.opacity = '0';
                    setTimeout(() => alertEl.remove(), 150);
                }
                if (alertId) {
                    persistDismiss('transaction', alertId);
                }
            });
        });
    </script>
    <!-- Truncation JS removed: showing full emails by default -->
    <script>
        // Force hide horizontal overflow and log any elements wider than the viewport
        try {
            document.documentElement.style.overflowX = 'hidden';
            document.body.style.overflowX = 'hidden';
        } catch (e) {
            console.warn('Could not force overflow hidden', e);
        }

        // After layout stabilizes, log elements causing horizontal overflow (for debugging)
        setTimeout(function () {
            try {
                var vw = document.documentElement.clientWidth || window.innerWidth;
                document.querySelectorAll('*').forEach(function (el) {
                    if (el.scrollWidth > vw + 1) {
                        console.warn('Element wider than viewport:', el, 'scrollWidth:', el.scrollWidth);
                    }
                });
            } catch (e) {
                console.warn('Overflow debug failed', e);
            }
        }, 600);
    </script>
    <script>
    function toggleNotifPanel(e) {
        if (e) e.preventDefault();
        var panel = document.getElementById('notif-panel');
        if (panel.style.display === 'none') {
            panel.style.display = 'block';
        } else {
            panel.style.display = 'none';
        }
    }
    </script>
