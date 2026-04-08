<?php
// connexion_via_lien.php
// Page qui affiche le formulaire de connexion lorsqu'un lien de connexion (token) est utilisé.
// Récupère les infos client via le proxy local `api_proxy_from_token.php?token=...`.

require_once __DIR__ . '/fonction.php'; // utilitaires existants (connexion_db, getUserPhotoUrl, ...)
require_once __DIR__ . '/lib/i18n.php';

$token = trim($_GET['c'] ?? $_GET['token'] ?? '');

// fonction utilitaire pour appeler le proxy
function fetch_client_by_token($token) {
    $url = 'api_proxy_from_token.php?token=' . urlencode($token);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $http !== 200) {
        return ['error' => true, 'http' => $http, 'msg' => $err ?: 'Unexpected HTTP ' . $http, 'body' => $body];
    }
    $data = json_decode($body, true);
    if (!is_array($data)) return ['error' => true, 'msg' => 'Invalid JSON from proxy', 'raw' => $body];
    return ['error' => false, 'data' => $data];
}

$client = null;
$error = null;
if ($token !== '') {
    $res = fetch_client_by_token($token);
    if ($res['error']) {
        $error = $res['msg'] ?? 'Erreur lors de la récupération du client';
    } else {
        $client = $res['data'];
        // Forcer la langue de l'interface selon la langue du compte client
        if (!empty($client['lang'])) {
            $clientLang = preg_replace('/[^a-z]/', '', strtolower((string)$client['lang']));
            if ($clientLang !== '') {
                if (session_status() === PHP_SESSION_NONE) session_start();
                $_SESSION['lang'] = $clientLang;
                if (!headers_sent()) {
                    setcookie('lang', $clientLang, time() + 60 * 60 * 24 * 365, '/');
                }
            }
        }
    }
}

// Compose display name: prefer prenom + nom, else nom (may contain both)
function client_display_name($c) {
    if (empty($c)) return '';
    $prenom = trim($c['prenom'] ?? '');
    $nom = trim($c['nom'] ?? ($c['name'] ?? ''));
    if ($prenom !== '') return strtoupper($prenom . ' ' . $nom);
    return strtoupper($nom);
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo t('log_in_to_your_account'); ?></title>
    <link rel="stylesheet" href="/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/style.css">
    <style>
        body { background: #fff; }
        .login-container { max-width: 520px; margin: 48px auto; }
        .name-pill { display:inline-flex; align-items:center; gap:.6rem; padding:.6rem 1rem; border-radius:28px; background:#ececec; color:#333; font-weight:600; }
        .name-pill img { width:28px; height:28px; border-radius:50%; object-fit:cover; }
        .logo { text-align:center; margin-bottom:1.5rem; }
        .logo img { max-width:240px; }
        .form-card { padding:2rem; border-radius:8px; }
    </style>
</head>
<body>
<div class="login-container">
    <div class="logo">
        <!-- Logo: replace with your image if available -->
        <img src="/image/transferwire_logo.png" alt="Transferwire" onerror="this.style.display='none'">
    </div>

    <div class="text-center mb-3">
        <h3><?= t('log_in_to_your_account') ?></h3>
    </div>

    <div class="text-center mb-4">
        <?php if ($client): ?>
            <?php $displayName = client_display_name($client); ?>
            <div class="name-pill">
                <?php
                    // Use photo_url or photo_path normalization helper if available
                    $avatarUrl = null;
                    if (!empty($client['photo_url'])) $avatarUrl = $client['photo_url'];
                    elseif (!empty($client['photo_path'])) $avatarUrl = getUserPhotoUrl($client['photo_path']);
                ?>
                <?php if ($avatarUrl): ?>
                    <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="avatar">
                <?php else: ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4z" stroke="#666" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M20 21v-1a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v1" stroke="#666" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <?php endif; ?>
                <span><?= htmlspecialchars($displayName ?: ''); ?></span>
            </div>
        <?php else: ?>
            <div class="text-muted"><?= t('no_client_selected') ?></div>
        <?php endif; ?>
    </div>

    <div class="card form-card">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/connexion.php">
            <input type="hidden" name="c" value="<?= htmlspecialchars($token) ?>">

            <div class="mb-3 input-group">
                <span class="input-group-text"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4h16v16H4z" stroke="#666" stroke-width="1.2"/></svg></span>
                <input name="email" class="form-control" placeholder="<?= t('email_label') ?>" value="<?= htmlspecialchars($client['email'] ?? '') ?>">
            </div>

            <div class="mb-3 input-group">
                <span class="input-group-text"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 15v2" stroke="#666" stroke-width="1.2" stroke-linecap="round"/><path d="M8 10a4 4 0 1 1 8 0v3H8v-3z" stroke="#666" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <input name="code" type="password" class="form-control" placeholder="<?= t('password_label') ?>">
            </div>

            <div class="d-grid">
                <button class="btn btn-primary btn-lg"><?= t('login_button') ?> →</button>
            </div>
        </form>
    </div>

    <div class="text-center text-muted mt-3 small">
        <!-- optional metadata -->
        <?= $client ? ('Token: '.htmlspecialchars($token)) : '' ?>
    </div>
</div>

</body>
</html>
