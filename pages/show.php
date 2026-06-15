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

$incomingTypeKeys = ['transfer received', 'funds added', 'solde initial', 'refund received', 'recharge', 'loyalty bonus'];
$transactionMetaMap = [
    'transfer received' => ['icon' => 'fa-money-bill-transfer', 'variant' => 'positive'],
    'funds added' => ['icon' => 'fa-money-bill-transfer', 'variant' => 'positive'],
    'recharge' => ['icon' => 'fa-arrow-down', 'variant' => 'positive'],
    'loyalty bonus' => ['icon' => 'fa-gift', 'variant' => 'positive'],
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

// --- Sparkline: 7 dernières transactions par catégorie, courbes bezier ---
$_allIn = []; $_allOut = []; $_allTx = [];
foreach ($historique_transactions as $_tx) {
    $_amt  = (float)($_tx['amount'] ?? 0);
    $_type = strtolower(trim((string)($_tx['transaction_type'] ?? '')));
    if (in_array($_type, $incomingTypeKeys, true)) $_allIn[]  = $_amt;
    else                                            $_allOut[] = $_amt;
    $_allTx[] = $_amt;
}
if (!function_exists('_sparkPad')) :
function _sparkPad(array $a, int $n = 7): array {
    $a = array_slice(array_reverse($a), 0, $n);
    $a = array_reverse($a);
    while (count($a) < $n) array_unshift($a, 0);
    return $a;
}
function _sparkSmooth(array $vals, int $w = 90, int $h = 46, int $pad = 5): string {
    $max = max(array_merge($vals, [1]));
    $n   = count($vals);
    $pts = [];
    foreach ($vals as $i => $v) {
        $pts[] = [round(($i / ($n - 1)) * $w, 1), round($h - $pad - ($v / $max) * ($h - $pad * 2), 1)];
    }
    $d = "M {$pts[0][0]},{$pts[0][1]}";
    for ($i = 0; $i < $n - 1; $i++) {
        $p0 = $pts[max(0, $i - 1)]; $p1 = $pts[$i];
        $p2 = $pts[$i + 1];         $p3 = $pts[min($n - 1, $i + 2)];
        $t  = 0.35;
        $d .= sprintf(' C %.1f,%.1f %.1f,%.1f %.1f,%.1f',
            $p1[0] + ($p2[0] - $p0[0]) * $t, $p1[1] + ($p2[1] - $p0[1]) * $t,
            $p2[0] - ($p3[0] - $p1[0]) * $t, $p2[1] - ($p3[1] - $p1[1]) * $t,
            $p2[0], $p2[1]);
    }
    return $d;
}
function _sparkSmoothFill(array $vals, int $w = 90, int $h = 46, int $pad = 5): string {
    return _sparkSmooth($vals, $w, $h, $pad) . " L {$w},{$h} L 0,{$h} Z";
}
endif;
$sparkIn  = _sparkPad($_allIn);
$sparkOut = _sparkPad($_allOut);
$sparkTot = _sparkPad($_allTx);
$svgPathIn  = _sparkSmooth($sparkIn);
$svgFillIn  = _sparkSmoothFill($sparkIn);
$svgPathOut = _sparkSmooth($sparkOut);
$svgFillOut = _sparkSmoothFill($sparkOut);
$svgPathTot = _sparkSmooth($sparkTot);
$svgFillTot = _sparkSmoothFill($sparkTot);
// --- Fin sparkline ---

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
    if (!in_array($typeKey, ['refund received', 'funds added', 'funds deducted', 'recharge'], true)) {
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

    $txSortTs = strtotime($transaction['created_at'] ?? '') ?: 0;
    if ($typeKey === 'refund received') {
        $transactionAlerts[] = [
            'id' => $alertId, 'sort_ts' => $txSortTs,
            'variant' => 'refund', 'icon' => 'fa-rotate-left',
            'title' => t('notif_refund_title'),
            'message' => t('notif_refund_message', ['amount' => "<strong>{$amount} {$deviseSafe}</strong>", 'source' => $sourceSafe]),
        ];
    } elseif ($typeKey === 'funds added') {
        $transactionAlerts[] = [
            'id' => $alertId, 'sort_ts' => $txSortTs,
            'variant' => 'success', 'icon' => 'fa-circle-check',
            'title' => t('notif_funds_added_title'),
            'message' => t('notif_funds_added_message', ['amount' => "<strong>{$amount} {$deviseSafe}</strong>", 'source' => $sourceSafe]),
        ];
    } elseif ($typeKey === 'funds deducted') {
        $transactionAlerts[] = [
            'id' => $alertId, 'sort_ts' => $txSortTs,
            'variant' => 'deduct', 'icon' => 'fa-circle-exclamation',
            'title' => t('notif_funds_deducted_title'),
            'message' => t('notif_funds_deducted_message', ['amount' => "<strong>-{$amount} {$deviseSafe}</strong>", 'source' => $sourceSafe]),
        ];
    } elseif ($typeKey === 'recharge') {
        $transactionAlerts[] = [
            'id' => $alertId,
            'variant' => 'success',
            'icon' => 'fa-circle-check',
            'title' => t('notif_funds_added_title'),
            'message' => t('notif_funds_added_message', ['amount' => "<strong>{$amount} {$deviseSafe}</strong>", 'source' => $sourceSafe]),
            'sort_ts' => strtotime($transaction['created_at'] ?? '') ?: 0,
        ];
    }
}
// Ajouter sort_ts aux autres types
foreach ($transactionAlerts as &$ta) {
    if (!isset($ta['sort_ts'])) $ta['sort_ts'] = 0;
}
unset($ta);

// Notifications admin envoyées directement dans le compte client
$adminNotifications = [];
try {
    $dbNotif = connexion_db();
    if ($dbNotif && $accountId) {
        $stmtNotif = $dbNotif->prepare(
            "SELECT id, titre, message, created_at FROM compte_notifications WHERE compte_id = :cid AND is_read = 0 ORDER BY created_at DESC LIMIT 10"
        );
        $stmtNotif->execute([':cid' => $accountId]);
        $adminNotifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // ignore, notifications non critiques
}

// Fusionner et trier toutes les notifications par date (plus récente en premier)
$allNotifications = [];
foreach ($adminNotifications as $n) {
    $allNotifications[] = ['type' => 'admin', 'sort_ts' => strtotime($n['created_at'] ?? '') ?: 0, 'data' => $n];
}
foreach ($transactionAlerts as $a) {
    $allNotifications[] = ['type' => 'transaction', 'sort_ts' => (int)($a['sort_ts'] ?? 0), 'data' => $a];
}
usort($allNotifications, function($a, $b) { return $b['sort_ts'] <=> $a['sort_ts']; });
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
        body {
            background-color: #f4f6fa !important;
        }
        .show-wrapper {
            max-width: 1080px;
            margin: 0 auto;
            padding: 0 20px 140px;
            background-color: #f4f6fa;
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
            overflow: clip;
            isolation: isolate;
            border-radius: 24px;
            padding: 18px 18px 20px;
            color: #fff;
            background: linear-gradient(135deg, #2563eb 0%, #5850ec 35%, #8f52eb 70%, #d649eb 100%);
            box-shadow: 0 16px 32px rgba(99, 102, 241, 0.22);
            margin-bottom: 20px;
            min-height: 120px;
        }
        .hero-title {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .hero-chip {
            align-self: flex-start;
            padding: 4px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.22);
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .hero-greeting {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .hero-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.22);
            font-weight: 600;
            font-size: 0.82rem;
            color: #fff;
            align-self: flex-start;
            margin-top: 6px;
        }
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #fff;
            border-radius: 50%;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.4; transform: scale(0.7); }
        }
        .hero-status.status-active .status-dot {
            background: #34d399;
            animation: pulse-dot 1.6s ease-in-out infinite;
        }
        .hero-header {
            position: relative;
            z-index: 2;
        }
        .hero-title {
            padding-right: 0;
        }
        .hero-card-visual {
            position: absolute;
            top: 50px;
            right: 0;
            width: 155px;
            pointer-events: none;
            z-index: 1;
            opacity: 0.65;
        }
        .hero-card-visual img {
            width: 100%;
            height: auto;
            display: block;
            filter: drop-shadow(0 6px 16px rgba(30,10,70,0.2));
            -webkit-mask-image: radial-gradient(ellipse 80% 70% at 62% 35%, black 20%, rgba(0,0,0,0.4) 48%, transparent 72%);
            mask-image: radial-gradient(ellipse 80% 70% at 62% 35%, black 20%, rgba(0,0,0,0.4) 48%, transparent 72%);
        }
        .hero-main {
            margin-top: 16px;
            position: relative;
            z-index: 1;
        }
        .hero-label {
            margin-bottom: 4px;
            font-size: 0.63rem;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            opacity: 0.8;
            font-weight: 600;
        }
        .hero-balance {
            margin: 4px 0 8px;
            font-size: calc(3.4rem + 4px);
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.0;
        }
        .overview-hero .hero-balance {
            font-size: calc(3.4rem + 4px) !important;
            line-height: 1.0;
        }
        .hero-balance-currency {
            font-size: calc(2.5rem + 3px) !important;
            font-weight: 600;
            vertical-align: middle;
            color: #ffffff !important;
            margin-left: 4px;
        }
        .virement-card-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            color: #0b1d33 !important;
            padding: 11px 16px;
            border-radius: 14px;
            text-decoration: none;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .virement-card-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(15, 23, 42, 0.12);
        }
        .virement-card-btn i.fa-paper-plane {
            color: #5c3be0;
            font-size: 1.3rem;
        }
        .macarte-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.16);
            color: #ffffff !important;
            padding: 10px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.92rem;
            transition: background 0.18s ease, transform 0.18s ease;
        }
        .macarte-btn:hover {
            background: rgba(255, 255, 255, 0.24);
            transform: translateY(-1px);
        }
        .detail-icon-circle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.16);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .detail-row-label {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255, 255, 255, 0.75);
        }
        .detail-row-value {
            font-size: 0.92rem;
            font-weight: 700;
            color: #ffffff;
            text-align: right;
        }
        /* legacy meta classes (used in timeline) */
        .meta-item { display: flex; flex-direction: column; gap: 6px; }
        .meta-label { font-size: 0.82rem; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.95; font-weight: 600; }
        .meta-value { font-size: 1.18rem; font-weight: 700; }
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
            font-size: 2.6rem !important;
            line-height: 1.08;
        }
        .stat-grid {
            display: flex;
            flex-direction: column;
            gap: 18px;
            margin-bottom: 30px;
        }
        .stat-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 22px;
            border-radius: 18px;
            background: #fff;
            border: 1px solid rgba(226, 232, 240, 0.75);
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.12);
        }
        .stat-card-left { display: flex; align-items: center; gap: 16px; }
        .stat-sparkline {
            flex-shrink: 0;
            border-radius: 12px;
            padding: 8px 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-card.positive .stat-sparkline { background: rgba(16,185,129,0.12); }
        .stat-card.negative .stat-sparkline { background: rgba(239,68,68,0.10); }
        .stat-card.neutral  .stat-sparkline { background: rgba(99,102,241,0.10); }
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
            color: #6b7280 !important;
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
            color: #6b7280 !important; /* slightly lighter slate to match screenshot */
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
            color: #6b7280 !important;
            font-weight: 500 !important;
        }
        /* Ensure both date and time are equally legible (same color/weight) */
        .timeline-card .timeline-item .timeline-datetime .date,
        .timeline-card .timeline-item .timeline-datetime .time,
        .timeline-datetime .date,
        .timeline-datetime .time {
            color: #6b7280 !important;
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
                padding: 0 16px 140px 16px;
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
                padding: 14px 14px 16px;
                margin-bottom: 14px;
            }
            .hero-card-visual { width: 125px; top: 58px; }
            .hero-title { padding-right: 0; }
            .hero-chip { font-size: 0.64rem; padding: 3px 9px; }
            .hero-greeting { font-size: 0.88rem; }
            .hero-status { padding: 3px 9px; font-size: 0.74rem; }
            .hero-label { font-size: 0.62rem; }
            .hero-balance { font-size: calc(1.8rem + 1px); }
            .overview-hero .hero-balance { font-size: calc(1.8rem + 1px) !important; }
            .hero-balance .hero-balance-amount { font-size: calc(1.8rem + 8px) !important; }
            .hero-balance .hero-balance-currency { font-size: calc(1.4rem + 5px) !important; }

            .hero-actions { gap: 8px; margin-top: 8px; }

            .hero-actions {
                width: 100%;
                flex-direction: column;
                gap: 8px;
                margin-top: 8px;
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
            
            .stat-card { padding: 12px 14px !important; gap: 10px !important; border-radius: 14px !important; }
            .stat-icon { width: 38px !important; height: 38px !important; border-radius: 10px !important; font-size: 1.05rem !important; }
            .stat-label { font-size: 0.72rem !important; }
            .stat-value { font-size: 1.15rem !important; margin: 3px 0 0 !important; }

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
            .show-wrapper .timeline-card .timeline-item .timeline-icon i { font-size: calc(0.78rem + 2px) !important; }
            .timeline-card .timeline-item .timeline-content { gap: 4px !important; }
            .timeline-card .timeline-item .timeline-row { gap: 6px !important; }
            .timeline-card .timeline-item .timeline-title { font-size: calc(0.82rem + 2px) !important; font-weight: 900 !important; }
            .timeline-card .timeline-item .timeline-amount { font-size: calc(0.82rem + 2px) !important; font-weight: 900 !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-datetime { font-size: calc(0.72rem + 1px) !important; font-weight: 500 !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-datetime .date,
            .show-wrapper .timeline-card .timeline-item .timeline-datetime .time,
            .show-wrapper .timeline-card .timeline-item .timeline-datetime i { font-size: calc(0.72rem + 1px) !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-meta { font-size: calc(0.72rem + 1px) !important; font-weight: 500 !important; gap: 4px !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-meta .meta-bank,
            .show-wrapper .timeline-card .timeline-item .timeline-meta .meta-email,
            .show-wrapper .timeline-card .timeline-item .timeline-meta i,
            .show-wrapper .timeline-card .timeline-item .timeline-submeta,
            .show-wrapper .timeline-card .timeline-item .timeline-submeta .meta-email,
            .show-wrapper .timeline-card .timeline-item .timeline-submeta i { font-size: calc(0.72rem + 1px) !important; }
            
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
                padding: 0 12px 140px 12px;
            }
            
            .overview-hero {
                padding: 12px 14px 14px;
                border-radius: 18px;
            }

            .hero-greeting {
                font-size: 0.82rem;
            }

            .hero-balance {
                font-size: 1.6rem;
            }
            .overview-hero .hero-balance { font-size: calc(1.6rem + 1px) !important; }
            .hero-balance .hero-balance-amount { font-size: calc(1.6rem + 8px) !important; }
            .hero-balance .hero-balance-currency { font-size: calc(1.3rem + 4px) !important; }
            
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
            .show-wrapper .timeline-card .timeline-item .timeline-icon i { font-size: calc(0.68rem + 2px) !important; }
            .timeline-card .timeline-item .timeline-title { font-size: calc(0.80rem + 2px) !important; font-weight: 900 !important; color: #111827 !important; }
            .timeline-card .timeline-item .timeline-amount { font-size: calc(0.80rem + 2px) !important; font-weight: 900 !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-datetime { font-size: calc(0.67rem + 1px) !important; font-weight: 500 !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-datetime .date,
            .show-wrapper .timeline-card .timeline-item .timeline-datetime .time,
            .show-wrapper .timeline-card .timeline-item .timeline-datetime i { font-size: calc(0.67rem + 1px) !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-meta { font-size: calc(0.67rem + 1px) !important; font-weight: 500 !important; }
            .show-wrapper .timeline-card .timeline-item .timeline-meta .meta-bank,
            .show-wrapper .timeline-card .timeline-item .timeline-meta .meta-email,
            .show-wrapper .timeline-card .timeline-item .timeline-meta i,
            .show-wrapper .timeline-card .timeline-item .timeline-submeta,
            .show-wrapper .timeline-card .timeline-item .timeline-submeta .meta-email,
            .show-wrapper .timeline-card .timeline-item .timeline-submeta i { font-size: calc(0.67rem + 1px) !important; }
            
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
        footer a,
        footer a:hover,
        footer a.active {
            background: none !important;
        }
        footer a.active {
            border-bottom: 3px solid #6f63ff;
        }
        .footer-show a i {
            font-size: 1.41rem !important;
            height: auto;
            color: #6b7280 !important;
            margin-bottom: 4px;
        }
        .footer-show a {
            color: #6b7280 !important;
            flex: 1 1 0%;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .footer-show a.active i,
        .footer-show a.active {
            color: #6f63ff !important;
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
            footer.footer-show a div {
                font-size: 0.73rem !important;
            }
            footer.footer-show a i {
                font-size: 1.6rem;
            }
        }
        @media screen and (max-width: 768px) {
            footer a {
                font-size: 0.81rem;
            }
            footer a i {
                font-size: 1.9rem;
                margin-bottom: 4px;
            }
        }
        @media screen and (max-width: 480px) {
            footer a {
                font-size: 0.85rem;
            }
            footer a i {
                font-size: 2.0rem;
                margin-bottom: 4px;
            }
        }
    </style>
<div class="dashboard">
        <nav style="display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; background: #fff; width: 100%; box-sizing: border-box;">
            <!-- Left: hamburger + brand name -->
            <div class="nav-left" style="display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-bars" style="font-size: 1.45rem; color: #0b1d33; cursor: pointer;"></i>
                <strong style="font-size: 1.25rem; font-weight: 800; color: #0b1d33; letter-spacing: -0.2px;">TRANSFERFLUX</strong>
            </div>
            
            <!-- Right: bell icon and avatar -->
            <div class="nav-right" style="justify-self: end; display: flex; align-items: center; gap: 16px;">
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
                <a href="#" onclick="toggleNotifPanel(event)" style="color:#6b7280; font-size:1.35rem; text-decoration:none; position:relative;" id="notif-bell">
                    <i class="far fa-bell"></i>
                    <?php
                    $notifCount = count($allNotifications ?? []);
                    if ($transferSuccess) $notifCount++;
                    if ($showBalanceAlert) $notifCount++;
                    ?>
                    <?php if ($notifCount > 0): ?>
                    <span id="notif-badge" style="position:absolute; top:-4px; right:-6px; background:#ef4444; color:#fff; font-size:0.65rem; font-weight:700; min-width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid #fff;"><?= $notifCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="index.php?page=info" style="display:inline-flex; align-items:center; position:relative;">
                    <img id="avatar-img" src="<?= htmlspecialchars($photoUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>" onerror="this.style.display='none'; document.getElementById('avatar-fallback').style.display='flex';" alt="avatar" style="width:42px; height:42px; border-radius:50%; object-fit:cover; border:2px solid rgba(0,0,0,0.08); box-shadow:0 2px 8px rgba(0,0,0,0.1); <?= empty($photoUrl) ? 'display:none;' : '' ?>">
                    <div id="avatar-fallback" style="width:42px; height:42px; border-radius:50%; background:#0b1d33; display:<?= empty($photoUrl) ? 'flex' : 'none' ?>; align-items:center; justify-content:center; border: 2px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.15);"><i class="fas fa-user" style="color:#fff; font-size:1.1rem;"></i></div>
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
                <?php if (!$transferSuccess && !$showBalanceAlert && empty($allNotifications)): ?>
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

                    <?php foreach ($allNotifications as $notifItem):
                        if ($notifItem['type'] === 'admin'):
                            $notif = $notifItem['data']; ?>
                        <div class="alert-modern variant-info admin-notif-alert" role="alert" data-notif-id="<?= (int)$notif['id']; ?>">
                            <div class="alert-icon variant-info"><i class="fas fa-bell"></i></div>
                            <div class="alert-body">
                                <p class="alert-title"><?= htmlspecialchars($notif['titre'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="alert-message"><?= nl2br(htmlspecialchars($notif['message'], ENT_QUOTES, 'UTF-8')); ?></p>
                            </div>
                            <button type="button" class="btn-close dismiss-admin-notif" data-notif-id="<?= (int)$notif['id']; ?>" aria-label="Fermer">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php else: $alert = $notifItem['data']; ?>
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
                        <?php endif; ?>
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
                    <div class="hero-card-visual" aria-hidden="true">
                        <img src="image/wallet-3d.png" alt="">
                    </div>
                    <div class="hero-header">
                        <div class="hero-title">
                            <span class="hero-chip"><?= $accountTypeLabel; ?></span>
                            <?php
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
                            <div class="hero-status <?= $accountStatusVariant; ?>">
                                <span class="status-dot"></span>
                                <span><?= $accountStatusLabel; ?></span>
                            </div>
                        </div>
                    </div>
                <div class="hero-main" style="margin-top:18px;">
                    <div class="hero-label"><?= htmlspecialchars(t('hero_label'), ENT_QUOTES, 'UTF-8') ?></div>
                    <p class="hero-balance"><span class="hero-balance-amount"><?= $formatted_balance; ?></span><span class="hero-balance-currency"> <?= $deviseLabel; ?></span></p>
                </div>
                <div class="hero-actions" style="display:flex; flex-direction:column; gap:8px; margin-top:12px; width:100%;">
                    <a href="index.php?page=transfert" class="virement-card-btn">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="display:flex; align-items:center; justify-content:center; transform:rotate(-20deg);">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <strong><?= htmlspecialchars(t('perform_transfer'), ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    
                    <a href="index.php?page=carte" class="macarte-btn">
                        <i class="fas fa-credit-card"></i>
                        <span><?= htmlspecialchars(t('my_card'), ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                </div>

                <div class="hero-details-box" style="margin-top:12px; background:rgba(0,0,0,0.12); border-radius:14px; padding:4px 14px;">
                    <div class="hero-detail-row" style="display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid rgba(255,255,255,0.08);">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div class="detail-icon-circle"><i class="fas fa-user"></i></div>
                            <span class="detail-row-label">TYPE DE COMPTE</span>
                        </div>
                        <span class="detail-row-value"><?= $accountTypeLabel; ?></span>
                    </div>
                    <div class="hero-detail-row" style="display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid rgba(255,255,255,0.08);">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div class="detail-icon-circle" style="font-weight:800; font-size:0.75rem; color:#fff; display:flex; align-items:center; justify-content:center; line-height:1;"><?= $deviseLabel; ?></div>
                            <span class="detail-row-label">DEVISE DU COMPTE</span>
                        </div>
                        <span class="detail-row-value"><?= $deviseLabel; ?></span>
                    </div>
                    <div class="hero-detail-row" style="display:flex; align-items:center; justify-content:space-between; padding:12px 0;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div class="detail-icon-circle"><i class="far fa-calendar-alt"></i></div>
                            <span class="detail-row-label">DERNIER MOUVEMENT</span>
                        </div>
                        <span class="detail-row-value"><?= $lastMovementDisplay; ?></span>
                    </div>
                </div>
            </section>

            <!-- Raccourcis rapides -->
            <?php
            $qaRecharge = function_exists('t') ? (t('footer_recharge') ?: '') : '';
            if ($qaRecharge === 'footer_recharge' || $qaRecharge === '') $qaRecharge = 'Recharger';
            $qaWithdraw = function_exists('t') ? (t('footer_withdraw') ?: '') : '';
            if ($qaWithdraw === 'footer_withdraw' || $qaWithdraw === '') $qaWithdraw = 'Retirer';
            $qaHistory = function_exists('t') ? (t('footer_history') ?: '') : '';
            if ($qaHistory === 'footer_history' || $qaHistory === '') $qaHistory = 'Historique';
            $qaMore = function_exists('t') ? (t('footer_more') ?: '') : '';
            if ($qaMore === 'footer_more' || $qaMore === '') $qaMore = 'Plus';
            ?>
            <section class="stat-grid">
                <article class="stat-card positive">
                    <div class="stat-card-left">
                        <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
                        <div>
                            <p class="stat-label"><?= htmlspecialchars(t('transactions_incoming'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="stat-value"><?= $incomingCountFormatted; ?></p>
                        </div>
                    </div>
                    <div class="stat-sparkline">
                        <svg viewBox="0 0 90 46" width="90" height="46" xmlns="http://www.w3.org/2000/svg" overflow="visible">
                            <defs>
                                <linearGradient id="sg-pos" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#10b981" stop-opacity="0.25"/>
                                    <stop offset="100%" stop-color="#10b981" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <path d="<?= $svgFillIn ?>" fill="url(#sg-pos)"/>
                            <path d="<?= $svgPathIn ?>" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </article>
                <article class="stat-card negative">
                    <div class="stat-card-left">
                        <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
                        <div>
                            <p class="stat-label"><?= htmlspecialchars(t('transactions_outgoing'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="stat-value"><?= $outgoingCountFormatted; ?></p>
                        </div>
                    </div>
                    <div class="stat-sparkline">
                        <svg viewBox="0 0 90 46" width="90" height="46" xmlns="http://www.w3.org/2000/svg" overflow="visible">
                            <defs>
                                <linearGradient id="sg-neg" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#ef4444" stop-opacity="0.22"/>
                                    <stop offset="100%" stop-color="#ef4444" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <path d="<?= $svgFillOut ?>" fill="url(#sg-neg)"/>
                            <path d="<?= $svgPathOut ?>" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </article>
                <article class="stat-card neutral">
                    <div class="stat-card-left">
                        <div class="stat-icon"><i class="fas fa-list-ul"></i></div>
                        <div>
                            <p class="stat-label"><?= htmlspecialchars(t('transactions_total'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="stat-value"><?= $totalTransactionsFormatted; ?></p>
                        </div>
                    </div>
                    <div class="stat-sparkline">
                        <svg viewBox="0 0 90 46" width="90" height="46" xmlns="http://www.w3.org/2000/svg" overflow="visible">
                            <defs>
                                <linearGradient id="sg-neu" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#6366f1" stop-opacity="0.22"/>
                                    <stop offset="100%" stop-color="#6366f1" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <path d="<?= $svgFillTot ?>" fill="url(#sg-neu)"/>
                            <path d="<?= $svgPathTot ?>" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </article>
            </section>

            <section class="timeline-card" id="timeline-section">
                <div class="timeline-header">
                            <div>
                                <h4><?= htmlspecialchars(t('activity_recent'), ENT_QUOTES, 'UTF-8') ?></h4>
                                <p>Suivez vos virements entrants et sortants en temps reel.</p>
                            </div>
                            <span class="timeline-pill"><?= count($historique_transactions); ?> <?= htmlspecialchars(t('movements'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                <?php if (!empty($historique_transactions)) : ?>
                    <?php foreach ($sortedTransactions as $transaction) :
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
                    <div class="timeline-item variant-<?= $variant; ?>">
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
                                <div class="timeline-meta" style="font-size:calc(0.72rem + 1px)!important;">
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
                                <span class="timeline-datetime" style="display:inline-flex!important;flex-direction:column!important;align-items:flex-end!important;white-space:nowrap;gap:1px;font-size:calc(0.72rem + 1px)!important;"><span style="display:inline-flex!important;flex-direction:row!important;align-items:center!important;flex-wrap:nowrap!important;gap:3px;white-space:nowrap;"><i class="far fa-clock" style="display:inline!important;font-size:calc(0.72rem + 1px)!important;flex-shrink:0;"></i><span class="date" style="display:inline!important;"><?= $dateOnlySafe; ?></span></span><?php if ($timeOnlySafe !== ''): ?><span class="time" style="display:inline!important;"><?= $timeOnlySafe; ?></span><?php endif; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
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
                updateNotifBadge();
            });
        });

        document.querySelectorAll('.dismiss-admin-notif').forEach(btn => {
            btn.addEventListener('click', function () {
                const notifId = this.getAttribute('data-notif-id');
                const alertEl = this.closest('.alert-modern');
                if (alertEl) {
                    alertEl.style.opacity = '0';
                    setTimeout(() => alertEl.remove(), 150);
                }
                if (notifId) {
                    fetch('dismiss_alert.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ alert: 'admin_notif', notif_id: notifId })
                    }).catch(() => {});
                }
                updateNotifBadge();
            });
        });

        function updateNotifBadge() {
            const remaining = document.querySelectorAll('#notif-panel .alert-modern').length;
            const badge = document.getElementById('notif-badge');
            if (badge) {
                if (remaining > 0) {
                    badge.textContent = remaining;
                } else {
                    badge.remove();
                }
            }
        }
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

    document.querySelectorAll('.qa-scroll-history').forEach(function(el) {
        el.addEventListener('click', function(e) {
            var target = document.getElementById('timeline-section');
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    </script>
