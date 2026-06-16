<?php
// public_html/fonction.php

date_default_timezone_set('Europe/Paris');
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Lit une valeur de configuration en vérifiant : constante, variables d'environnement, puis fichier .env
 */
function resolveEnvValue(string $key): ?string
{
    if ($key === '') return null;

    if (defined($key)) {
        $val = constant($key);
        if (is_string($val) && $val !== '') return $val;
    }

    $env = getenv($key);
    if ($env !== false && $env !== '') return $env;

    $file = __DIR__ . '/.env';
    if (is_readable($file)) {
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if ($k === $key) return trim($v, "'\"");
        }
    }
    return null;
}

/**
 * Connexion à la base de données (compatible avec votre .env)
 */
function connexion_db()
{
    try {
        // ✅ ADAPTÉ À VOTRE .ENV
        $host = resolveEnvValue('DB_HOST') ?: 'localhost';
        $dbname = resolveEnvValue('DB_DATABASE') ?: 'compteeurope';
        $user = resolveEnvValue('DB_USERNAME') ?: 'root';
        $pass = resolveEnvValue('DB_PASSWORD') ?: '';
        $port = resolveEnvValue('DB_PORT') ?: '3306';
        
        $dns = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8";
        $pdo = new PDO($dns, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (Exception $e) {
        error_log("❌ ERREUR CONNEXION DB: " . $e->getMessage());
        return null; // Retourne null pour détecter l'échec
    }
}

/**
 * Cherche un utilisateur par email et mot de passe
 */
function chercher_utilisateur_par_son_email_et_son_mot_de_passe(string $email, string $password): array
{
    $db = connexion_db();
    if ($db === null) return [];
    
    try {
        // ✅ VÉRIFIEZ ICI : colonne 'password' ou 'password2' ?
        $sql = 'SELECT * FROM `comptes` WHERE `email`=:email AND `password`=:password';
        $stmt = $db->prepare($sql);
        $stmt->execute(['email' => $email, 'password' => $password]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("❌ Erreur recherche utilisateur: " . $e->getMessage());
        return [];
    }
}

function chercher_utilisateur_par_son_email(string $email): ?array
{
    $db = connexion_db();
    if ($db === null) return null;
    
    try {
        $sql = 'SELECT * FROM `comptes` WHERE `email` = :email';
        $stmt = $db->prepare($sql);
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log("❌ Erreur recherche email: " . $e->getMessage());
        return null;
    }
}

function est_connecter(): bool
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (isset($_SESSION['utilisateur_connecter']['email'])) {
        $user = chercher_utilisateur_par_son_email($_SESSION['utilisateur_connecter']['email']);
        if ($user) return true;
        session_destroy();
    }
    return false;
}

function getTransactionHistory($user_id, $compte_id = null): array
{
    $db = connexion_db();
    if (!is_object($db)) return [];
    
    try {
        if (!empty($compte_id) && $compte_id > 0) {
            $sql = 'SELECT * FROM `transaction_histories` WHERE `compte_id` = :compte_id ORDER BY CAST(`created_at` AS DATETIME) DESC, `id` DESC';
            $stmt = $db->prepare($sql);
            $stmt->execute(['compte_id' => (int)$compte_id]);
        } elseif (!empty($user_id) && $user_id > 0) {
            $sql = 'SELECT * FROM `transaction_histories` WHERE `user_id` = :user_id ORDER BY CAST(`created_at` AS DATETIME) DESC, `id` DESC';
            $stmt = $db->prepare($sql);
            $stmt->execute(['user_id' => (int)$user_id]);
        } else {
            return [];
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("❌ Erreur getTransactionHistory: " . $e->getMessage());
        return [];
    }
}

function getUserPhotoUrl($user)
{
    if (is_string($user) && $user !== '') {
        $user = ['photo_path' => $user];
    }

    if (!is_array($user) || empty($user)) {
        return null;
    }

    $candidateKeys = ['photo_path', 'photo', 'avatar', 'image', 'profile_image', 'profile_pic', 'picture', 'photo_url', 'public_url'];
    $candidateValues = [];

    foreach ($candidateKeys as $key) {
        if (!empty($user[$key])) {
            $candidateValues[] = trim((string)$user[$key]);
        }
    }

    foreach ($candidateValues as $value) {
        if ($value === '') {
            continue;
        }

        $normalized = str_replace('\\', '/', $value);

        if (filter_var($normalized, FILTER_VALIDATE_URL) || stripos($normalized, 'data:') === 0) {
            return wrapPhotoUrlForClient($normalized);
        }

        if ($normalized[0] === '/') {
            $absolute = rtrim(__DIR__, '\\/') . $normalized;
            if (file_exists($absolute)) {
                return $normalized;
            }
            return $normalized;
        }

        $relativeCandidates = [
            '/storage/' . ltrim($normalized, '/'),
            '/image/' . ltrim($normalized, '/'),
            '/' . ltrim($normalized, '/')
        ];

        foreach ($relativeCandidates as $relative) {
            $absolute = rtrim(__DIR__, '\\/') . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (file_exists($absolute)) {
                return $relative;
            }
        }

        static $photoBases = null;
        if ($photoBases === null) {
            $photoBases = computePhotoBaseCandidates();
        }

        $segment = ltrim($normalized, '/');
        if (stripos($segment, 'storage/') === 0) {
            $segment = substr($segment, strlen('storage/'));
        }

        $proxyFallback = null;
        foreach ($photoBases as $base) {
            if (!$base) {
                continue;
            }

            $trimmedBase = rtrim($base, '/');
            $needsStoragePrefix = !preg_match('#/storage/?$#i', $trimmedBase);
            $remoteUrl = $trimmedBase . ($needsStoragePrefix ? '/storage/' : '/') . $segment;
            $finalUrl = wrapPhotoUrlForClient($remoteUrl);
            if (is_string($finalUrl) && str_starts_with($finalUrl, '/photo_proxy.php')) {
                $proxyFallback = $finalUrl;
                continue;
            }

            if ($finalUrl !== null) {
                return $finalUrl;
            }
        }

        if ($proxyFallback !== null) {
            return $proxyFallback;
        }

        return '/' . ltrim($normalized, '/');
    }

    return null;
}

function getUserDetails($compte_id): array
{
    $db = connexion_db();
    if (!is_object($db)) return [];
    
    try {
        $sql = 'SELECT * FROM `comptes` WHERE `id` = :compte_id';
        $stmt = $db->prepare($sql);
        $stmt->execute(['compte_id' => $compte_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("❌ Erreur getUserDetails: " . $e->getMessage());
        return [];
    }
}

function getTransactionLabels(): array
{
    return [
        'transfer received' => 'Virement reçu',
        'Transfer sent' => 'Virement émis',
        'Refund received' => 'Remboursement',
        'Funds deducted' => 'Prélèvement',
        'Funds added' => 'Virement reçu',
        'Solde initial' => 'Virement reçu',
    ];
}

function deconnexion()
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $clientToken = $_SESSION['client_token'] ?? '';
    $compteId = $_SESSION['utilisateur_connecter']['compte_id'] ?? null;
    if ($compteId) {
        try {
            $db = connexion_db();
            if (is_object($db)) {
                $db->prepare("UPDATE comptes SET last_activity = :ts WHERE id = :id")
                   ->execute([':ts' => time(), ':id' => $compteId]);
            }
        } catch (Exception $e) {}
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    // Rediriger vers la page de connexion du même compte
    if ($clientToken !== '') {
        $redirect = '?c=' . urlencode($clientToken);
    } else {
        $redirect = 'index.php?page=connexion';
    }
    header('Location: ' . $redirect);
}

function updateBalanceToZero($id_utilisateur_connecte)
{
    $db = connexion_db();
    if (!is_object($db)) return json_encode(['success' => false, 'message' => 'Connexion DB non disponible']);
    
    try {
        $stmt = $db->prepare("SELECT * FROM comptes WHERE id = :id");
        $stmt->bindParam(':id', $id_utilisateur_connecte);
        $stmt->execute();
        $compte = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($compte) {
            if ($compte['end_percentage'] == 100) {
                $stmt = $db->prepare("UPDATE comptes SET account_balance = 0 WHERE id = :id");
                $stmt->bindParam(':id', $id_utilisateur_connecte);
                $stmt->execute();
                return json_encode(['success' => true]);
            } else {
                return json_encode(['success' => false, 'message' => 'Le pourcentage de fin n\'est pas de 100%.']);
            }
        } else {
            return json_encode(['success' => false, 'message' => 'Compte non trouvé.']);
        }
    } catch (PDOException $e) {
        return json_encode(['success' => false, 'message' => 'Erreur DB: ' . $e->getMessage()]);
    }
}

function createTransactionHistory($user_id, $compte_id, $transaction_type, $amount, $devise, $description, $transfer_id = null): string
{
    try {
        $db = connexion_db();
        if (!is_object($db)) {
            return json_encode(['success' => false, 'message' => 'Connexion DB non disponible']);
        }
        
        // support optional transfer_id linkage
        if ($transfer_id === null) {
            $sql = "INSERT INTO transaction_histories 
                (user_id, compte_id, transaction_type, amount, devise, description, created_at, updated_at) 
                VALUES (:user_id, :compte_id, :transaction_type, :amount, :devise, :description, :created_at, :updated_at)";
        } else {
            $sql = "INSERT INTO transaction_histories 
                (user_id, compte_id, transaction_type, amount, devise, description, transfer_id, created_at, updated_at) 
                VALUES (:user_id, :compte_id, :transaction_type, :amount, :devise, :description, :transfer_id, :created_at, :updated_at)";
        }
        
        $stmt = $db->prepare($sql);
        $date = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':compte_id', $compte_id, PDO::PARAM_INT);
        $stmt->bindParam(':transaction_type', $transaction_type);
        $stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
        $stmt->bindParam(':devise', $devise);
        $stmt->bindParam(':description', $description);
        if ($transfer_id !== null) {
            $stmt->bindValue(':transfer_id', $transfer_id, PDO::PARAM_INT);
        }
        $stmt->bindParam(':created_at', $date);
        $stmt->bindParam(':updated_at', $date);
        
        return $stmt->execute() 
            ? json_encode(['success' => true, 'message' => 'Historique créé']) 
            : json_encode(['success' => false, 'message' => 'Échec de création']);
    } catch (PDOException $e) {
        return json_encode(['success' => false, 'message' => 'Erreur DB: ' . $e->getMessage()]);
    }
}

function formatTransactionDate($dateValue, $format = 'd/m/Y H:i:s'): string {
    if (is_string($dateValue) && preg_match('/\d{2}\/\d{2}\/\d{4}/', $dateValue)) {
        return $dateValue;
    }
    if (is_numeric($dateValue)) {
        return date($format, (int)$dateValue);
    }
    if (empty($dateValue)) {
        return date($format);
    }
    try {
        $date = new DateTime($dateValue, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Europe/Paris'));
        return $date->format($format);
    } catch (Exception $e) {
        error_log("❌ Erreur format date: " . $e->getMessage());
        return $dateValue;
    }
}

/**
 * Indique si un host correspond à une adresse locale/privée (localhost, 127.x, 10.x, etc.).
 */
function isLocalLikeHost(?string $host): bool
{
    if ($host === null || $host === '') {
        return false;
    }

    $host = strtolower($host);
    if ($host === 'localhost' || $host === '::1') {
        return true;
    }

    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if ($host === '127.0.0.1') {
            return true;
        }
        if (str_starts_with($host, '10.')) {
            return true;
        }
        if (str_starts_with($host, '192.168.')) {
            return true;
        }
        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host)) {
            return true;
        }
    }

    return false;
}

/**
 * Retourne une liste ordonnée des bases d'URL potentielles pour accéder aux photos distantes.
 * Les hôtes publics sont priorisés par rapport aux adresses locales.
 */
function computePhotoBaseCandidates(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $keys = [
        'COMPTE_EUROPE_STORAGE_PUBLIC_BASE',
        'COMPTE_EUROPE_ASSET_BASE',
        'COMPTE_EUROPE_STORAGE_BASE',
        'COMPTE_EUROPE_API_BASE',
        'APP_URL',
    ];

    $values = [];
    foreach ($keys as $key) {
        $value = resolveEnvValue($key);
        if ($value) {
            $values[] = rtrim($value, '/');
        }
    }

    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $values[] = $scheme . '://' . $_SERVER['HTTP_HOST'];
    }

    $values = array_values(array_unique($values));

    usort($values, function ($a, $b) {
        $hostA = strtolower(parse_url($a, PHP_URL_HOST) ?? '');
        $hostB = strtolower(parse_url($b, PHP_URL_HOST) ?? '');
        $weightA = isLocalLikeHost($hostA) ? 1 : 0;
        $weightB = isLocalLikeHost($hostB) ? 1 : 0;
        if ($weightA === $weightB) {
            return 0;
        }
        return $weightA <=> $weightB;
    });

    return $cached = $values;
}

/**
 * En cas d'URL absolue pointant vers un host local (127.x ou réseau privé),
 * rebascule vers un host public configuré lorsque disponible.
 */
function mapPhotoUrlToPublicHost(string $url): string
{
    if (!preg_match('#^https?://#i', $url)) {
        return $url;
    }

    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return $url;
    }

    if (!isLocalLikeHost($parts['host'])) {
        return $url;
    }

    foreach (computePhotoBaseCandidates() as $base) {
        $baseParts = parse_url($base);
        if (!$baseParts || empty($baseParts['host'])) {
            continue;
        }
        if (isLocalLikeHost($baseParts['host'])) {
            continue;
        }

        $scheme = $baseParts['scheme'] ?? ($parts['scheme'] ?? 'https');
        $rebuilt = $scheme . '://' . $baseParts['host'];
        if (!empty($baseParts['port'])) {
            $rebuilt .= ':' . $baseParts['port'];
        }

        $basePath = $baseParts['path'] ?? '';
        $sourcePath = $parts['path'] ?? '';
        if ($basePath !== '') {
            $rebuilt .= rtrim($basePath, '/') . '/' . ltrim($sourcePath, '/');
        } else {
            $rebuilt .= $sourcePath;
        }

        if (!empty($parts['query'])) {
            $rebuilt .= '?' . $parts['query'];
        }
        if (!empty($parts['fragment'])) {
            $rebuilt .= '#' . $parts['fragment'];
        }

        return $rebuilt;
    }

    return $url;
}

function wrapPhotoUrlForClient(?string $url): ?string
{
    if (!is_string($url) || $url === '') {
        return $url;
    }

    $url = mapPhotoUrlToPublicHost($url);

    if (stripos($url, 'photo_proxy.php?target=') !== false) {
        return $url;
    }

    if (!preg_match('#^https?://#i', $url)) {
        return $url;
    }

    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
    $port = parse_url($url, PHP_URL_PORT);
    $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');

    // Exception pour le serveur Laravel local (127.0.0.1:8000) - accessible directement
    if ($host === '127.0.0.1' && $port === 8000) {
        return $url;
    }

    $isLocalHost = isLocalLikeHost($host);

    if (!empty($_SERVER['HTTP_HOST'])) {
        $currentHost = strtolower($_SERVER['HTTP_HOST']);
        $currentHost = strpos($currentHost, ':') !== false ? substr($currentHost, 0, strpos($currentHost, ':')) : $currentHost;
        if ($currentHost !== '' && $host === $currentHost) {
            $isLocalHost = false;
        }
    }

    $requestIsHttps = (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );

    if ($isLocalHost || ($requestIsHttps && $scheme === 'http')) {
        return '/photo_proxy.php?target=' . urlencode($url);
    }

    return $url;
}