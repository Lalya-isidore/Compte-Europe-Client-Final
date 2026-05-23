<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Déconnexion automatique après 30 minutes d'inactivité
if (isset($_SESSION['utilisateur_connecter']) && isset($_SESSION['last_activity'])) {
    if ((time() - $_SESSION['last_activity']) > 1800) {
        $clientToken = $_SESSION['client_token'] ?? '';
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        session_start();
        $redirect = !empty($clientToken) ? '?c=' . urlencode($clientToken) : 'index.php?page=connexion';
        header('Location: ' . $redirect);
        exit();
    }
}
if (isset($_SESSION['utilisateur_connecter'])) {
    $_SESSION['last_activity'] = time();
}

$erreurs = [];
$donnees = [];
$success = '';
$erreur = '';

require_once(__DIR__ . '/fonction.php');
require_once __DIR__ . '/lib/i18n.php';
$__currentLang = current_lang();

// human readable language names (small map for support display)
$langNames = [
    'fr' => 'Français', 'en' => 'English', 'de' => 'Deutsch', 'es' => 'Español', 'ru' => 'Русский',
    'pl' => 'Polski', 'hr' => 'Hrvatski', 'pt' => 'Português', 'it' => 'Italiano', 'zh' => '中文',
    'ja' => '日本語', 'ko' => '한국어', 'fa' => 'فارسی', 'ps' => 'Pashto', 'nl' => 'Nederlands',
    'sv' => 'Svenska', 'no' => 'Norsk', 'da' => 'Dansk', 'fi' => 'Suomi', 'ro' => 'Română',
];
$__currentLangLabel = $langNames[$__currentLang] ?? $__currentLang;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>TRANSFERFLUX</title>
    <link rel="stylesheet" href="bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="/image/image.png">
    <!-- Favicon FB -->
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Space+Grotesk:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>

<body class="d-flex flex-column min-vh-100">
    <div class="container">
        <!-- inclusion de l'entête du site -->

        <div class="row g-3 mt-3">
            <?php
            if (isset($_GET['page']) && !empty($_GET['page'])) {
                switch ($_GET['page']) {

                    case 'connexion':
                        include_once(__DIR__ . '/connexion.php');
                        break;

                    case 'deconnexion':
                        include_once(__DIR__ . '/deconnexion.php');
                        break;

                    case 'traitement':
                        include_once(__DIR__ . '/traitement.php');
                        break;

                    case 'show':
                        include_once(__DIR__ . '/pages/show.php');
                        break;

                    case 'virement':
                        include_once(__DIR__ . '/pages/virement.php');
                        break;

                    case 'carte':
                        include_once(__DIR__ . '/pages/carte.php');
                        break;

                    case 'confirmVirement':
                        include_once(__DIR__ . '/pages/confirmVirement.php');
                        break;

                    case 'confirmVirementpaypal':
                        include_once(__DIR__ . '/pages/confirmVirementpaypal.php');
                        break;

                    case 'virementDetail':
                        include_once(__DIR__ . '/pages/virementDetail.php');
                        break;

                    case 'virementDetailpaypal':
                        include_once(__DIR__ . '/pages/virementDetailpaypal.php');
                        break;

                    case 'info':
                        include_once(__DIR__ . '/pages/info.php');
                        break;

                    case 'validate_code':
                        include_once(__DIR__ . '/validate_code.php');
                        break;

                    case 'transfert':
                        include_once(__DIR__ . '/pages/transfert.php');
                        break;

                    default:
                        include_once(__DIR__ . '/connexion.php');
                        break;
                }
            } else {
                include_once(__DIR__ . '/connexion.php');
            }
            ?>
        </div>
    </div>
    <!-- inclusion du bas de page du site -->
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.6/clipboard.min.js"></script>

</html>