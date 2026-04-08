<?php
header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? null;
if (!$token) { http_response_code(400); echo json_encode(['error'=>'token_missing']); exit; }

function read_env_val($key) {
	$v = getenv($key);
	if ($v !== false && $v !== '') return $v;
	$env = __DIR__ . '/.env';
	if (!is_readable($env)) return null;
	foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
		$line = trim($line);
		if ($line === '' || $line[0] === '#') continue;
		if (strpos($line,'=') === false) continue;
		list($k,$val) = explode('=', $line, 2);
		$k = trim($k); $val = trim($val);
		if ($k === $key) {
			if ((($val[0] ?? '') === '"' && substr($val,-1) === '"') || (($val[0] ?? '') === "'" && substr($val,-1) === "'")) $val = substr($val,1,-1);
			return $val;
		}
	}
	return null;
}

$base = read_env_val('COMPTE_EUROPE_API_BASE');
$apiKey = read_env_val('COMPTE_EUROPE_API_KEY');
if (!$base || !$apiKey) { http_response_code(500); echo json_encode(['error'=>'server_misconfigured']); exit; }

$target = rtrim($base,'/') . '/api/clients/from-token?token=' . urlencode($token);
$ch = curl_init($target);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-KEY: '.$apiKey, 'Accept: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
curl_close($ch);

$data = null;
if ($res !== false) { $d = json_decode($res, true); if (is_array($d)) $data = $d; }

$allowed = ['nom','phone','public_token','public_url','photo_path','photo_url'];
$out = [];
if (is_array($data)) {
	foreach ($allowed as $k) if (isset($data[$k])) $out[$k] = $data[$k];
}

if ($http === 200 && !empty($out)) {
	// Normalize any returned photo_path/public_url into a full URL if needed
	if (!empty($out['photo_path']) && is_string($out['photo_path'])) {
		$pp = $out['photo_path'];
		if (stripos($pp, 'http://') !== 0 && stripos($pp, 'https://') !== 0) {
			// make absolute using upstream base when possible
			if (!empty($base)) {
				$out['photo_path'] = rtrim($base, '/') . '/' . ltrim($pp, '/');
			} else {
				$out['photo_path'] = '/' . ltrim($pp, '/');
			}
		}
	}
	if (!empty($out['public_url']) && is_string($out['public_url'])) {
		$pu = $out['public_url'];
		if (stripos($pu, 'http://') !== 0 && stripos($pu, 'https://') !== 0) {
			if (!empty($base)) {
				$out['public_url'] = rtrim($base, '/') . '/' . ltrim($pu, '/');
			} else {
				$out['public_url'] = '/' . ltrim($pu, '/');
			}
		}
	}

	http_response_code(200);
	echo json_encode($out);
	exit;
}

// Try DB fallback for development if upstream is not usable (including 401)
try {
	$pdo = new PDO('mysql:host=127.0.0.1;dbname=compteeurope;charset=utf8','root','');
	$stmt = $pdo->prepare('SELECT nom, phone_number AS phone, public_token, photo_path, lang FROM comptes WHERE public_token = :t LIMIT 1');
	$stmt->execute([':t'=>$token]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if ($row) {
		// Return photo_path (if present) so frontend can normalize it
		echo json_encode([
			'nom' => $row['nom'] ?? null,
			'phone' => $row['phone'] ?? null,
			'public_token' => $row['public_token'] ?? null,
			'photo_path' => $row['photo_path'] ?? null,
			'lang' => $row['lang'] ?? null,
			'public_url' => null,
		]);
		exit;
	}
} catch (Exception $e) { /* ignore DB errors here */ }

// If upstream returned an explicit non-auth error, forward it
if (is_array($data) && isset($data['error']) && $data['error'] !== 'Unauthorized') {
	http_response_code($http?:500);
	echo json_encode(['error'=>$data['error']]);
	exit;
}

// Default: forward upstream status/message (including Unauthorized) or not_found
if (is_array($data) && isset($data['error'])) {
	http_response_code($http?:500);
	echo json_encode(['error'=>$data['error']]);
	exit;
}

http_response_code(404);
echo json_encode(['error'=>'not_found']);

?>