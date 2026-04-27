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
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4b59d9">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Compte Europe">
    <link rel="apple-touch-icon" href="icon-192.png">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Space+Grotesk:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        #pwa-install-banner {
            display: none;
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 95%;
            max-width: 450px;
            background: linear-gradient(135deg, #4b59d9 0%, #3a48c7 100%);
            color: white;
            padding: 14px 18px;
            border-radius: 20px;
            box-shadow: 0 15px 45px rgba(75, 89, 217, 0.4);
            z-index: 99999;
            align-items: center;
            gap: 15px;
            animation: slideDown 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes slideDown {
            from { transform: translate(-50%, -120px); opacity: 0; }
            to { transform: translate(-50%, 0); opacity: 1; }
        }

        .pwa-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            background: white;
            padding: 2px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .pwa-text {
            flex: 1;
        }

        .pwa-text h4 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
            font-family: 'Montserrat', sans-serif;
            color: white !important;
        }

        .pwa-text p {
            margin: 4px 0 0;
            font-size: 0.82rem;
            opacity: 0.95;
            line-height: 1.2;
            color: white !important;
        }

        .pwa-btn {
            background: white;
            color: #4b59d9;
            border: none;
            padding: 10px 18px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(255,255,255,0.2);
            white-space: nowrap;
        }

        .pwa-btn:active {
            transform: scale(0.95);
        }

        .pwa-close {
            background: transparent;
            color: rgba(255,255,255,0.7);
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            line-height: 1;
        }
    </style>

</head>

<body class="d-flex flex-column min-vh-100">
    <!-- PWA Install Banner -->
    <div id="pwa-install-banner">
        <img src="icon-192.png" class="pwa-icon" alt="App Icon">
        <div class="pwa-text">
            <h4>Compte Europe</h4>
            <p id="pwa-desc">Téléchargez l'application pour un accès rapide et sécurisé.</p>
        </div>
        <button id="pwa-install-btn" class="pwa-btn">Télécharger</button>
        <button id="pwa-close-btn" class="pwa-close">&times;</button>
    </div>
    <!-- Language detection badge removed: UI should not display automatic language detection in footer -->
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
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => console.log('Service Worker registered', reg))
                    .catch(err => console.log('Service Worker not registered', err));
            });
        }

        // PWA Install Logic
        let deferredPrompt;
        const pwaBanner = document.getElementById('pwa-install-banner');
        const installBtn = document.getElementById('pwa-install-btn');
        const closeBtn = document.getElementById('pwa-close-btn');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
            const isDismissed = sessionStorage.getItem('pwa-banner-dismissed');
            
            if (!isStandalone && !isDismissed) {
                pwaBanner.style.display = 'flex';
            }
        });

        // Detection for iOS
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        const isStandalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
        const isDismissed = sessionStorage.getItem('pwa-banner-dismissed');

        if (isIOS && !isStandalone && !isDismissed) {
            pwaBanner.style.display = 'flex';
            document.getElementById('pwa-desc').innerText = "Appuyez sur Partager puis 'Sur l'écran d'accueil'";
            installBtn.style.display = 'none';
        }

        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                if (outcome === 'accepted') {
                    pwaBanner.style.display = 'none';
                }
                deferredPrompt = null;
            }
        });

        closeBtn.addEventListener('click', () => {
            pwaBanner.style.display = 'none';
            sessionStorage.setItem('pwa-banner-dismissed', 'true');
        });
    </script>
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.6/clipboard.min.js"></script>

</html>