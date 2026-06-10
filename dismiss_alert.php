<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!is_array($data)) {
	$data = [];
}

$alertType = $data['alert'] ?? ($_POST['alert'] ?? null);
$response = ['success' => false];

if ($alertType === 'balance') {
	$_SESSION['balance_alert_dismissed'] = true;
	$response['success'] = true;
} elseif ($alertType === 'transaction' && !empty($data['alert_id'])) {
	$alertId = (string)$data['alert_id'];
	if (!isset($_SESSION['dismissed_transaction_alerts']) || !is_array($_SESSION['dismissed_transaction_alerts'])) {
		$_SESSION['dismissed_transaction_alerts'] = [];
	}
	if (!in_array($alertId, $_SESSION['dismissed_transaction_alerts'], true)) {
		$_SESSION['dismissed_transaction_alerts'][] = $alertId;
	}
	$response['success'] = true;
}

// Marquer la notification admin comme lue
if ($alertType === 'admin_notif' && !empty($data['notif_id'])) {
	$notifId = (int)$data['notif_id'];
	try {
		$compteIdSession = $_SESSION['utilisateur_connecter']['compte_id'] ?? $_SESSION['utilisateur_connecter']['id'] ?? null;
		if ($notifId > 0 && $compteIdSession && is_object(($dbNotif = (function_exists('connexion_db') ? connexion_db() : null)))) {
			$stmt = $dbNotif->prepare("UPDATE compte_notifications SET is_read = 1 WHERE id = :nid AND compte_id = :cid");
			$stmt->execute([':nid' => $notifId, ':cid' => $compteIdSession]);
		}
		$response['success'] = true;
	} catch (Exception $e) {
		// ignore
	}
}

// Persist dismissal in DB when the user is logged in so it survives logout
try {
	$compteId = $_SESSION['utilisateur_connecter']['compte_id'] ?? $_SESSION['utilisateur_connecter']['id'] ?? null;
	if ($compteId && is_object(($db = (function_exists('connexion_db') ? connexion_db() : null)))) {
		if ($alertType === 'balance') {
			// Use empty string for alert_id for balance so UNIQUE key works consistently
			$stmt = $db->prepare("INSERT IGNORE INTO dismissed_alerts (compte_id, alert_type, alert_id) VALUES (:cid, 'balance', '')");
			$stmt->execute([':cid' => $compteId]);
		} elseif ($alertType === 'transaction' && !empty($alertId)) {
			$stmt = $db->prepare("INSERT IGNORE INTO dismissed_alerts (compte_id, alert_type, alert_id) VALUES (:cid, 'transaction', :aid)");
			$stmt->execute([':cid' => $compteId, ':aid' => $alertId]);
		}
	}
} catch (Exception $e) {
	// If DB not available or table missing, ignore silently and keep using session fallback
}

// Fallback: persist in cookie so dismissals survive logout even if DB write failed or user dismissed while not logged in
try {
	if ($alertType === 'balance') {
		setcookie('dismissed_balance', '1', time() + 31536000, '/'); // 1 year
	} elseif ($alertType === 'transaction' && !empty($alertId)) {
		$cookieArr = json_decode($_COOKIE['dismissed_transaction_alerts'] ?? '[]', true);
		if (!is_array($cookieArr)) $cookieArr = [];
		if (!in_array($alertId, $cookieArr, true)) {
			$cookieArr[] = $alertId;
		}
		setcookie('dismissed_transaction_alerts', json_encode(array_values($cookieArr)), time() + 31536000, '/');
	}
} catch (Exception $e) {
	// ignore cookie failures
}

header('Content-Type: application/json');
echo json_encode($response);
