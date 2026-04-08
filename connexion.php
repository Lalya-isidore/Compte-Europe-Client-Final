<?php
// Si un nouveau token est fourni, déconnecter la session précédente
if (!empty($_GET['c']) && function_exists('est_connecter') && est_connecter()) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    unset($_SESSION['utilisateur_connecter']);
}

if (function_exists('est_connecter') && est_connecter()) {
    	$message = t('already_logged_in');
    header('location:index.php?page=show&message=' . urlencode($message));
    exit;
}

$erreurs = [];
$donnees = [];
$erreur = '';
$success = '';

// Client name and public url from API (if token present). IMPORTANT: we will NOT prefill email/password.
$clientName = null;
$clientPhoto = '';
$clientPublicUrl = null;

// -- Si ?c=TOKEN présent, appeler l'API du projet Laravel pour récupérer uniquement des infos publiques (nom, phone, public_url).
// NOTE: URL Laravel locale sous XAMPP : adapte si nécessaire.
// On permet de configurer l'URL de base via :
//  - constante PHP COMPTE_EUROPE_API_BASE
//  - variable d'environnement COMPTE_EUROPE_API_BASE
//  - fichier .env local (clé COMPTE_EUROPE_API_BASE)
// Si aucune n'est fournie, on garde l'URL de développement par défaut.
$laravelApiBase = null;
if (defined('COMPTE_EUROPE_API_BASE') && COMPTE_EUROPE_API_BASE) {
	$laravelApiBase = COMPTE_EUROPE_API_BASE;
} elseif (getenv('COMPTE_EUROPE_API_BASE')) {
	$laravelApiBase = getenv('COMPTE_EUROPE_API_BASE');
} else {
	// fallback: parser minimal du fichier .env s'il existe
	$envFile = __DIR__ . '/.env';
	if (is_readable($envFile)) {
		$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
				continue;
			}
			list($k, $v) = explode('=', $line, 2);
			$k = trim($k);
			$v = trim($v);
			if ((substr($v, 0, 1) === '"' && substr($v, -1) === '"') || (substr($v, 0, 1) === "'" && substr($v, -1) === "'")) {
				$v = substr($v, 1, -1);
			}
			if ($k === 'COMPTE_EUROPE_API_BASE' && $v !== '') {
				$laravelApiBase = $v;
				putenv('COMPTE_EUROPE_API_BASE=' . $v);
				$_ENV['COMPTE_EUROPE_API_BASE'] = $v;
				break;
			}
		}
	}
}
if (!$laravelApiBase) {
	$laravelApiBase = 'http://localhost:8080/CompteEurope/public';
}

// ===================== MODE TEST =====================
$isTestMode = false;
$testEmail = '';
$testPassword = '';
$tokenSource = $_GET['c'] ?? '';

// Stocker le token d'accès original en session pour la redirection après déconnexion
if (!empty($_GET['c'])) {
	$_SESSION['client_token'] = $_GET['c'];
}

// Nouveau token fourni : nettoyer la session de login précédente
if (!empty($tokenSource)) {
	unset($_SESSION['login_token_active'], $_SESSION['login_client_name'], $_SESSION['login_is_test'], $_SESSION['login_test_email'], $_SESSION['login_test_password'], $_SESSION['login_erreur'], $_SESSION['login_alert_type']);
}

if (!empty($tokenSource) && strpos($tokenSource, 'test.') === 0) {
	$isTestMode = true;
	$_SESSION['client_token'] = $tokenSource;
	$tokenSource = ''; // Empêcher l'appel API quel que soit le résultat
	$encodedId = substr($_GET['c'], 5);
	$adminUserId = (int) base64_decode($encodedId);

	$db = connexion_db();
	if (!$db) {
		error_log('Test mode: connexion DB échouée');
		$isTestMode = false;
	}
}

if ($isTestMode) {
	// Récupérer le nom et l'email de l'admin
	try {
		$stmtAdmin = $db->prepare('SELECT nom, prenom, email FROM users WHERE id = :id LIMIT 1');
		$stmtAdmin->execute([':id' => $adminUserId]);
		$adminRow = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
		if ($adminRow) {
			$clientName = trim(($adminRow['prenom'] ?? '') . ' ' . ($adminRow['nom'] ?? '')) ?: 'UTILISATEUR TEST';
			$adminEmail = $adminRow['email'] ?? '';
		} else {
			$clientName = 'UTILISATEUR TEST';
			$adminEmail = '';
		}
	} catch (Exception $e) {
		$clientName = 'UTILISATEUR TEST';
		$adminEmail = '';
	}

	// Chercher un compte test existant
	$testPassword = '000000';
	$testEmail = $adminEmail;
	try {
		$stmt = $db->prepare('SELECT id, email, password FROM comptes WHERE numerocompte LIKE :pattern AND user_id = :uid LIMIT 1');
		$stmt->execute([':pattern' => 'test_%', ':uid' => $adminUserId]);
		$testCompte = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($testCompte) {
			// Réinitialiser à l'état initial à chaque connexion
			$stmtReset = $db->prepare('UPDATE comptes SET
				email = :email, start_percentage = 0, end_percentage = 50,
				account_balance = 10000, account_balance2 = 10000,
				account_status = :status, devise = :devise, region = :region,
				phone_number = :phone, country = :country, address = :address,
				failure_message = :failure_msg, success_message = :success_msg,
				updated_at = NOW()
				WHERE id = :id');
			$stmtReset->execute([
				':email' => $adminEmail ?: $testCompte['email'],
				':status' => 'Activé',
				':devise' => '€',
				':region' => 'europe',
				':phone' => '+33600000000',
				':country' => 'France (+33)',
				':address' => 'Adresse de test',
				':failure_msg' => 'Votre virement a échoué en raison d\'une vérification de sécurité. Veuillez contacter le support pour finaliser l\'opération.',
				':success_msg' => 'Votre virement a été effectué avec succès. Les fonds seront disponibles sous 24 à 48 heures.',
				':id' => $testCompte['id'],
			]);
			// Réinitialiser aussi l'historique des transactions
			$db->prepare('DELETE FROM transaction_histories WHERE compte_id = ?')->execute([$testCompte['id']]);
			$stmtTx = $db->prepare('INSERT INTO transaction_histories (user_id, compte_id, transaction_type, amount, description, devise, created_at, updated_at) VALUES (:uid, :cid, :type, :amount, :desc, :devise, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY))');
			$stmtTx->execute([
				':uid' => $adminUserId,
				':cid' => $testCompte['id'],
				':type' => 'Funds added',
				':amount' => 10000.00,
				':desc' => 'TRANSFERFLUX',
				':devise' => '€',
			]);
			$testPassword = $testCompte['password'] ?? '000000';
		} else {
			// Créer le compte test automatiquement
			$testNumero = 'test_' . substr(md5('test_' . $adminUserId . '_' . time()), 0, 6);
			$cardNum = rand(4100, 4999) . str_repeat('*', 8) . rand(1000, 9999);
			$cvv = rand(100, 999);

			$stmt = $db->prepare('INSERT INTO comptes (
				user_id, region, numerocompte, nom, prenom, email, password, devise, lang,
				phone_number, country, address, account_balance, account_balance2,
				code_virement, account_type, account_status, transfer_supported,
				card_number, cvv, start_percentage, end_percentage, failure_message, success_message,
				alert_email, alert_sms, created_at, updated_at
			) VALUES (
				:user_id, :region, :numero, :nom, :prenom, :email, :password, :devise, :lang,
				:phone, :country, :address, :balance, :balance2,
				:code_virement, :account_type, :account_status, :transfer_supported,
				:card_number, :cvv, :start_pct, :end_pct, :failure_msg, :success_msg,
				:alert_email, :alert_sms, NOW(), NOW()
			)');
			$nameParts = explode(' ', $clientName, 2);
			$stmt->execute([
				':user_id' => $adminUserId,
				':region' => 'europe',
				':numero' => $testNumero,
				':nom' => $nameParts[1] ?? $nameParts[0],
				':prenom' => $nameParts[0],
				':email' => $adminEmail,
				':password' => $testPassword,
				':devise' => '€',
				':lang' => 'fr',
				':phone' => '+33600000000',
				':country' => 'France (+33)',
				':address' => 'Adresse de test',
				':balance' => 10000.00,
				':balance2' => 10000.00,
				':code_virement' => '111111',
				':account_type' => 'Professionnel',
				':account_status' => 'Activé',
				':transfer_supported' => 'Oui',
				':card_number' => $cardNum,
				':cvv' => $cvv,
				':start_pct' => 0,
				':end_pct' => 50,
				':failure_msg' => 'Votre virement a échoué en raison d\'une vérification de sécurité. Veuillez contacter le support pour finaliser l\'opération.',
				':success_msg' => 'Votre virement a été effectué avec succès. Les fonds seront disponibles sous 24 à 48 heures.',
				':alert_email' => 1,
				':alert_sms' => 0,
			]);

			// Ajouter la transaction initiale
			$newCompteId = $db->lastInsertId();
			$stmtTx = $db->prepare('INSERT INTO transaction_histories (user_id, compte_id, transaction_type, amount, description, devise, created_at, updated_at) VALUES (:uid, :cid, :type, :amount, :desc, :devise, NOW(), NOW())');
			$stmtTx->execute([
				':uid' => $adminUserId,
				':cid' => $newCompteId,
				':type' => 'Funds added',
				':amount' => 10000.00,
				':desc' => 'TRANSFERFLUX',
				':devise' => '€',
			]);
		}
	} catch (Exception $e) {
		error_log('Test mode: erreur création compte test: ' . $e->getMessage());
	}

}
// ===================== FIN MODE TEST =====================

if (!empty($tokenSource)) {
	// En local : lire directement la base de données au lieu de l'API
	$localDbResolved = false;
	$db = connexion_db();
	if ($db) {
		try {
			$stmt = $db->prepare('SELECT id, nom, prenom, email, token, photo_path, account_status, lang FROM comptes WHERE token = :token OR numerocompte = :numero LIMIT 1');
			$stmt->execute([':token' => $tokenSource, ':numero' => $tokenSource]);
			$localCompte = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($localCompte) {
				$clientName = trim(($localCompte['prenom'] ?? '') . ' ' . ($localCompte['nom'] ?? ''));
				$clientPhoto = $localCompte['photo_path'] ?? '';
				$localDbResolved = true;

				// Forcer la langue de l'interface selon la langue du compte client
				if (!empty($localCompte['lang'])) {
					$clientLang = preg_replace('/[^a-z]/', '', strtolower((string)$localCompte['lang']));
					if ($clientLang !== '') {
						if (session_status() === PHP_SESSION_NONE) session_start();
						$_SESSION['lang'] = $clientLang;
						if (!headers_sent()) {
							setcookie('lang', $clientLang, time() + 60 * 60 * 24 * 365, '/');
						}
					}
				}

				// Vérifier si le compte était bloqué et est maintenant activé
				$currentStatus = $localCompte['account_status'] ?? 'Activé';
				$wasBlocked = !empty($_SESSION['login_erreur']);
				if ($wasBlocked && in_array($currentStatus, ['Activé', 'Actif', 'active', 'Active'])) {
					unset($_SESSION['login_erreur'], $_SESSION['login_alert_type']);
					$bankName = function_exists('t') ? (t('login_bank_name') ?: 'TRANSFERFLUX') : 'TRANSFERFLUX';
					$success = "Votre compte {$bankName} a été réactivé avec succès. Vous pouvez vous connecter.";
				}
			}
		} catch (Exception $e) {
			error_log('Connexion local DB fallback error: ' . $e->getMessage());
		}
	}

	if ($localDbResolved) {
		// Données trouvées en base locale, pas besoin de l'API
	} else {
	$publicToken = $tokenSource;
	// Prefer calling a local server-side proxy (keeps API key server-side) if it exists.
	$localProxyFile = __DIR__ . '/api_proxy_from_token.php';
	if (is_readable($localProxyFile)) {
		// Build absolute URL to local proxy based on current request host
		$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
		$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), "\\/");
		$baseUrl = $scheme . '://' . $host . ($scriptDir === '/' ? '' : $scriptDir);
		$apiUrl = rtrim($baseUrl, '/') . '/api_proxy_from_token.php?token=' . urlencode($publicToken);
	} else {
		$apiUrl = rtrim($laravelApiBase, '/') . '/api/clients/from-token?token=' . urlencode($publicToken);
	}

	// Lire la clé API depuis plusieurs sources (ordre de priorité):
	// 1) constante PHP définie dans le code (option B)
	// 2) variable d'environnement système / getenv()
	// 3) fichier .env local (simple parser) — pratique si vous n'avez pas phpdotenv
	if (defined('COMPTE_EUROPE_API_KEY') && COMPTE_EUROPE_API_KEY) {
		$apiKey = COMPTE_EUROPE_API_KEY;
	} else {
		$apiKey = getenv('COMPTE_EUROPE_API_KEY') ?: null;

		if (!$apiKey) {
			$envFile = __DIR__ . '/.env';
			if (is_readable($envFile)) {
				$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				foreach ($lines as $line) {
					$line = trim($line);
					if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
						continue;
					}
					list($k, $v) = explode('=', $line, 2);
					$k = trim($k);
					$v = trim($v);
					if ((substr($v, 0, 1) === '"' && substr($v, -1) === '"') || (substr($v, 0, 1) === "'" && substr($v, -1) === "'")) {
						$v = substr($v, 1, -1);
					}
					if ($k === 'COMPTE_EUROPE_API_KEY' && $v !== '') {
						$apiKey = $v;
						putenv('COMPTE_EUROPE_API_KEY=' . $v);
						$_ENV['COMPTE_EUROPE_API_KEY'] = $v;
						break;
					}
				}
			}
		}
	}

		if (!$apiKey) {
				$erreur = "Clé API non configurée sur le projet externe (COMPTE_EUROPE_API_KEY manquante).";
		} else {
			// Log the exact URL we will call so you can check Apache/PHP error logs when things fail.
			error_log('connexion.php will call: ' . $apiUrl);
			$ch = curl_init($apiUrl);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
						'X-API-KEY: ' . $apiKey,
						'Accept: application/json',
				]);
		// Slightly longer timeouts for local/remote calls
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		// Enable verbose logging to help diagnose network issues
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$verbose = fopen('php://temp', 'w+');
		curl_setopt($ch, CURLOPT_STDERR, $verbose);
		// En dev si certificat auto-signé : curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$res = curl_exec($ch);
		$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlErr = curl_error($ch);
		// capture verbose output
		if (is_resource($verbose)) {
			rewind($verbose);
			$verboseLog = stream_get_contents($verbose);
			fclose($verbose);
		} else {
			$verboseLog = '';
		}
		curl_close($ch);

				if ($res === false || $http !== 200) {
						$erreur = 'Impossible de récupérer les informations du compte à partir du lien fourni.';
			if ($curlErr) {
				$erreur .= ' (' . htmlspecialchars($curlErr) . ')';
				// log verbose for debugging
				error_log('connexion.php curl error for ' . $apiUrl . ' : ' . $curlErr . "\n" . $verboseLog);
			} elseif ($res) {
								$maybe = json_decode($res, true);
								if (is_array($maybe) && isset($maybe['error'])) {
										$erreur .= ' — ' . htmlspecialchars($maybe['error']);
								}
						}
				} else {
						$data = json_decode($res, true);
						if (!is_array($data)) {
								$erreur = 'Réponse API invalide.';
						} elseif (isset($data['error'])) {
								$erreur = 'Erreur API : ' . htmlspecialchars($data['error']);
						} else {
								// IMPORTANT : NE PAS pré-remplir email ni mot de passe à partir de l'API.
								if (!empty($e = $data['nom'])) {
										$clientName = $e;
								}
								if (!empty($data['public_url'])) {
										$clientPublicUrl = $data['public_url'];
								}
								// Forcer la langue selon le champ lang du compte
								if (!empty($data['lang'])) {
										$clientLang = preg_replace('/[^a-z]/', '', strtolower((string)$data['lang']));
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
		}
	} // fin else localDbResolved
}

// Merge d'éventuelles erreurs/données via query (mais on n'utilise pas le token pour préremplir)
if (isset($_GET['erreurs']) && !empty($_GET['erreurs'])) {
		$erreurs = json_decode($_GET['erreurs'], true) ?: $erreurs;
}
if (isset($_GET['donnees']) && !empty($_GET['donnees'])) {
		$fromQuery = json_decode($_GET['donnees'], true);
		if (is_array($fromQuery)) {
				$donnees = array_merge($donnees, $fromQuery);
		}
}
$alertType = 'error';
if (isset($_GET['erreur']) && !empty($_GET['erreur'])) {
		$erreur = $_GET['erreur'];
		$alertType = $_GET['alert_type'] ?? 'error';
		$_SESSION['login_erreur'] = $erreur;
		$_SESSION['login_alert_type'] = $alertType;
} elseif (!empty($_SESSION['login_erreur'])) {
		$erreur = $_SESSION['login_erreur'];
		$alertType = $_SESSION['login_alert_type'] ?? 'error';
		unset($_SESSION['login_erreur'], $_SESSION['login_alert_type']);
}
if (isset($_GET['success']) && !empty($_GET['success'])) {
		$success = $_GET['success'];
}
?>
<style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');

        /* ============================================
           SOVEREIGN VAULT — DESIGN SYSTEM (by Stitch)
           ============================================ */
        :root {
            /* Core Palette */
            --surface:          #0d1031;
            --surface-low:      #151839;
            --surface-mid:      #191c3d;
            --surface-high:     #242748;
            --surface-highest:  #2f3254;
            --surface-bright:   #333659;
            --primary:          #c1c3ee;
            --primary-container:#0a0d2e;
            --secondary:        #c3c0ff;
            --secondary-container: #3626ce;
            --tertiary:         #4edea3;
            --tertiary-dim:     #6ffbbe;
            --on-surface:       #e0e0ff;
            --on-surface-var:   #c7c5cf;
            --outline-var:      rgba(70,70,78,0.18);
            --error:            #ffb4ab;
            --warning-bg:       rgba(255,251,235,0.08);
            --warning-border:   rgba(253,230,138,0.2);
            --warning-text:     #fde68a;
            /* Helpers */
            --radius-card:      32px;
            --radius-btn:       9999px;
            --radius-input:     14px;
            --blur-glass:       28px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #ffffff;
            color: #1e293b;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* ============================
           RESTRICTED PAGE (no token)
           ============================ */
        .restricted-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
            background: var(--primary-container);
            overflow: hidden;
        }
        .restricted-page::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 15% 20%, rgba(78,222,163,0.12) 0%, transparent 60%),
                radial-gradient(ellipse 50% 60% at 85% 80%, rgba(54,38,206,0.2) 0%, transparent 60%);
        }
        /* Dot grid */
        .restricted-page::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(193,195,238,0.07) 1px, transparent 1px);
            background-size: 32px 32px;
        }
        .restricted-content {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 500px;
            background: rgba(47, 50, 84, 0.55);
            backdrop-filter: blur(var(--blur-glass));
            -webkit-backdrop-filter: blur(var(--blur-glass));
            border: 1px solid var(--outline-var);
            border-radius: var(--radius-card);
            padding: 64px 48px;
            text-align: center;
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.06) inset,
                0 64px 80px -20px rgba(10,13,46,0.5);
            animation: sv-fadein 0.8s cubic-bezier(0.16,1,0.3,1) both;
        }
        .restricted-icon-wrap {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 36px;
        }
        .restricted-icon-ring {
            position: absolute;
            inset: -16px;
            border: 1.5px dashed rgba(255,180,171,0.25);
            border-radius: 50%;
            animation: sv-spin 24s linear infinite;
        }
        .restricted-icon-ring2 {
            position: absolute;
            inset: -32px;
            border: 1px solid rgba(255,180,171,0.08);
            border-radius: 50%;
            animation: sv-pulse-ring 3s ease-in-out infinite;
        }
        .restricted-icon {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: radial-gradient(circle at 40% 35%, rgba(255,180,171,0.18) 0%, rgba(147,0,10,0.35) 100%);
            border: 1px solid rgba(255,180,171,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 48px rgba(255,100,80,0.2), inset 0 0 24px rgba(255,100,80,0.1);
        }
        .restricted-icon i { font-size: 38px; color: var(--error); filter: drop-shadow(0 0 12px rgba(255,80,80,0.7)); }
        .restricted-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--on-surface);
            letter-spacing: -0.5px;
            margin-bottom: 18px;
        }
        .restricted-text {
            font-size: 1.05rem;
            color: var(--on-surface-var);
            line-height: 1.8;
            margin-bottom: 40px;
        }
        .restricted-text strong { color: var(--on-surface); font-weight: 600; }
        .restricted-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 26px;
            border-radius: var(--radius-btn);
            background: rgba(255,180,171,0.08);
            border: 1px solid rgba(255,180,171,0.18);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--error);
            cursor: default;
            transition: all 0.3s;
        }
        .restricted-badge:hover {
            background: rgba(255,180,171,0.14);
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(255,80,80,0.1);
        }

        /* ============================
           SPLIT LOGIN LAYOUT
           ============================ */
        .sv-login {
            display: flex;
            position: fixed; /* Breaks out of the parent .container in index.php */
            inset: 0;
            z-index: 9999;
            background: #ffffff;
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* LEFT PANEL */
        .sv-left {
            flex: 1.35;
            position: relative;
            background: #ffffff url('image/mobile_banking.png') center/70% no-repeat;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 56px 64px 52px;
            overflow: hidden;
            border-right: 1px solid #f1f5f9;
        }
        .sv-left > * { position: relative; z-index: 1; }

        /* Left — Bottom branding */
        .sv-left-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 2px;
            color: #64748b;
            text-transform: uppercase;
            margin-top: auto;
        }
        .sv-left-brand i { font-size: 1.1rem; color: #2563eb; opacity: 0.8; }

        /* RIGHT PANEL */
        .sv-right {
            flex: 0 0 480px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 36px;
            position: relative;
        }

        /* Login Card */
        .sv-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 400px;
            background: #ffffff;
            padding: 20px;
            animation: sv-slidein 0.7s cubic-bezier(0.16,1,0.3,1) both;
        }

        /* Card — Logo */
        .sv-logo-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 32px;
        }
        .sv-logo-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: sv-spin 40s linear infinite;
        }
        .sv-logo-name {
            font-size: 1.85rem;
            font-weight: 800;
            font-style: italic;
            letter-spacing: -0.5px;
            color: #1a56db;
            text-transform: uppercase;
            line-height: 1;
        }
        .sv-card-subtitle {
            text-align: center;
            font-size: 1rem;
            font-weight: 500;
            color: #475569;
            margin-bottom: 28px;
        }

        /* Card — Profile */
        .sv-profile {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }
        .sv-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #f1f5f9;
            border: 3px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 30px;
            object-fit: cover;
            flex-shrink: 0;
        }
        .sv-name-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-btn);
            padding: 8px 18px;
            box-shadow: none;
        }
        .sv-name-badge i { color: #64748b; font-size: 1.1rem; }
        .sv-name-badge span {
            font-size: 0.9rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
        }

        /* Card — Alerts */
        .sv-alert {
            padding: 20px 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 0.95rem;
            line-height: 1.5;
            font-weight: 500;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 12px;
            animation: sv-bounce 0.5s cubic-bezier(0.16,1,0.3,1) both;
        }
        .sv-alert i { font-size: 1.65rem; margin-bottom: 2px; flex-shrink: 0; }
        .sv-alert.error  { background: #fecaca; color: #7f1d1d; border: 1px solid #f87171; }
        .sv-alert.warning{ background: #fff3cd; color: #9a3412; border: 1px solid #f59e0b; }
        .sv-alert.success{ background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }

        /* Test Banner */
        .sv-test-banner {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 0.85rem;
            color: #92400e;
        }
        .sv-test-banner strong { color: #b45309; }

        /* Card — Inputs */
        .sv-form-group { position: relative; margin-bottom: 28px; }
        .sv-input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
            pointer-events: none;
            transition: color 0.3s;
        }
        .sv-input {
            width: 100%;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 14px 14px 14px 44px;
            font-size: 0.95rem;
            font-family: inherit;
            color: #1e293b;
            outline: none;
            transition: all 0.2s;
        }
        .sv-input::placeholder { color: #94a3b8; }
        .sv-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }
        .sv-input:focus ~ .sv-input-icon,
        .sv-input:not(:placeholder-shown) ~ .sv-input-icon {
            color: #2563eb;
        }

        /* Card — Submit Button */
        .sv-btn-login {
            width: 100%;
            margin-top: 8px;
            padding: 16px;
            background: #2563eb;
            color: #ffffff;
            font-size: 1.05rem;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .sv-btn-login:hover { background: #1d4ed8; }

        /* Card — Security Notice */
        .sv-security {
            margin-top: 24px;
            text-align: center;
            font-size: 0.8rem;
            color: #94a3b8;
        }
        .sv-security i { color: #94a3b8; margin-right: 4px; }
        .sv-security-text { color: #94a3b8; }

        /* ============================
           KEYFRAMES
           ============================ */
        @keyframes sv-fadein  { from { opacity:0; transform: scale(0.94); } to { opacity:1; transform: scale(1); } }
        @keyframes sv-slidein { from { opacity:0; transform: translateY(32px); } to { opacity:1; transform: translateY(0); } }
        @keyframes sv-bounce  { 0%{opacity:0;transform:scale(0.92)} 60%{transform:scale(1.02)} 100%{opacity:1;transform:scale(1)} }
        
        /* ============================
           RESPONSIVE
           ============================ */
        @media (max-width: 1100px) {
            .sv-right { flex: 0 0 420px; }
            .sv-left  { padding: 48px; }
        }
        @media (max-width: 860px) {
            html, body, .sv-login {
                background: #ffffff;
            }
            .sv-login { flex-direction: column; overflow-y: auto; }
            .sv-left  { display: none; }
            .sv-right {
                flex: 1;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: flex-start !important;
                align-items: stretch;
                padding: 50px 24px; /* Increased padding */
            }
            .sv-logo-wrap { margin-bottom: 35px; } /* Breathable logo margin */
            .sv-card-subtitle { margin-bottom: 25px; font-size: 1rem; } /* Breathable subtitle margin */
            .sv-profile { margin-bottom: 30px; gap: 12px; } /* Breathable profile margin */
            .sv-avatar { width: 80px; height: 80px; font-size: 30px; } /* Restored avatar size */
            .sv-alert { padding: 20px 24px; margin-bottom: 25px; } /* Breathable alert margin */
            .sv-card {
                padding: 10px 0;
                box-shadow: none;
                animation: none;
                margin-top: 0;
            }
        }
    </style>
    <?php
    $hasToken = !empty($_GET['c']);
    if ($hasToken) {
        $_SESSION['login_token_active'] = true;
        if (!empty($clientName)) {
            $_SESSION['login_client_name'] = $clientName;
        }
        $_SESSION['login_client_photo'] = $clientPhoto ?? '';
        if ($isTestMode) {
            $_SESSION['login_is_test'] = true;
            $_SESSION['login_test_email'] = $testEmail;
            $_SESSION['login_test_password'] = $testPassword;
        } else {
            // Vrai compte : nettoyer les données test de la session
            unset($_SESSION['login_is_test'], $_SESSION['login_test_email'], $_SESSION['login_test_password']);
        }
    }
    if (!$hasToken && !empty($_SESSION['login_token_active'])) {
        $hasToken = true;
        if (empty($clientName) && !empty($_SESSION['login_client_name'])) {
            $clientName = $_SESSION['login_client_name'];
        }
        if (!$isTestMode && !empty($_SESSION['login_is_test'])) {
            $isTestMode = true;
            $testEmail = $_SESSION['login_test_email'] ?? '';
            $testPassword = $_SESSION['login_test_password'] ?? '';
        }
        if (empty($clientPhoto) && !empty($_SESSION['login_client_photo'])) {
            $clientPhoto = $_SESSION['login_client_photo'];
        }
        // Vérifier si le compte était bloqué et est maintenant activé
        if (!empty($_SESSION['login_erreur']) && !empty($_SESSION['client_token'])) {
            $dbCheck = connexion_db();
            if ($dbCheck) {
                try {
                    $tokenCheck = $_SESSION['client_token'];
                    $stmtCheck = $dbCheck->prepare('SELECT account_status FROM comptes WHERE token = :t OR numerocompte = :n LIMIT 1');
                    $stmtCheck->execute([':t' => $tokenCheck, ':n' => $tokenCheck]);
                    $statusRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                    if ($statusRow && in_array($statusRow['account_status'] ?? '', ['Activé', 'Actif', 'active', 'Active'])) {
                        unset($_SESSION['login_erreur'], $_SESSION['login_alert_type']);
                        $erreur = '';
                        $bankName = function_exists('t') ? (t('login_bank_name') ?: 'TRANSFERFLUX') : 'TRANSFERFLUX';
                        $success = "Votre compte {$bankName} a été réactivé avec succès. Vous pouvez vous connecter.";
                    }
                } catch (Exception $e) {}
            }
        }
    }
    ?>

    <?php if (!$hasToken) : ?>
    <!-- ========== PAGE ACCÈS RESTREINT (pas de token) ========== -->
    <div class="restricted-page">
        <div class="restricted-content">
            <div class="restricted-icon-wrap">
                <div class="restricted-icon-ring"></div>
                <div class="restricted-icon-ring2"></div>
                <div class="restricted-icon"><i class="fas fa-lock"></i></div>
            </div>
            <h1 class="restricted-title">Accès Privé Sécurisé</h1>
            <p class="restricted-text">
                Cet espace est protégé par un chiffrement de bout en bout.<br>
                Vous devez utiliser le <strong>lien d'accès unique</strong> fourni par votre conseiller.
            </p>
            <div class="restricted-badge">
                <i class="fas fa-shield-halved"></i> Connexion restreinte
            </div>
        </div>
    </div>

    <?php else : ?>
    <!-- ========== PAGE DE CONNEXION — SOVEREIGN VAULT (avec token) ========== -->
    <div class="sv-login">

        <!-- LEFT PANEL -->
        <!-- LEFT PANEL (WITH IMAGE) -->
        <div class="sv-left">
            <!-- Note: text content was removed to strictly feature the generated image background -->
            <div class="sv-left-brand">
                <i class="fas fa-globe"></i>
                <?= t('login_bank_name') ?>
            </div>
        </div>

        <!-- RIGHT PANEL -->
        <div class="sv-right">
            <div class="sv-card">

                <!-- Logo -->
                <div class="sv-logo-wrap">
                    <div class="sv-logo-icon">
                        <svg width="46" height="46" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- Outer Swirl -->
                            <circle cx="20" cy="50" r="11" fill="#1d4ed8" />
                            <circle cx="28" cy="27" r="9" fill="#2563eb" />
                            <circle cx="28" cy="73" r="9" fill="#2563eb" />
                            <circle cx="48" cy="12" r="7" fill="#0ea5e9" />
                            <circle cx="48" cy="88" r="7" fill="#0ea5e9" />
                            <circle cx="72" cy="18" r="5" fill="#10b981" />
                            <circle cx="72" cy="82" r="5" fill="#10b981" />
                            <!-- Inner Swirl -->
                            <circle cx="44" cy="50" r="6" fill="#06b6d4" />
                            <circle cx="50" cy="34" r="5" fill="#0ea5e9" />
                            <circle cx="50" cy="66" r="5" fill="#0ea5e9" />
                            <circle cx="65" cy="28" r="4" fill="#34d399" />
                            <circle cx="65" cy="72" r="4" fill="#34d399" />
                            <!-- Tail -->
                            <circle cx="82" cy="36" r="3" fill="#6ee7b7" />
                            <circle cx="82" cy="64" r="3" fill="#6ee7b7" />
                            <circle cx="92" cy="50" r="2.5" fill="#a7f3d0" />
                        </svg>
                    </div>
                    <div class="sv-logo-name"><?= t('login_bank_name') ?></div>
                </div>

                <p class="sv-card-subtitle">Bienvenue dans votre espace</p>

                <!-- Profile Widget -->
                <?php if (!empty($clientName)) : ?>
                    <?php
                    $photoUrl = '';
                    if (!empty($clientPhoto)) {
                        if (strpos($clientPhoto, 'http') === 0) {
                            $photoUrl = $clientPhoto;
                        } else {
                            $storageBase = resolveEnvValue('COMPTE_EUROPE_STORAGE_PUBLIC_BASE') ?: 'http://localhost:8000/storage';
                            $photoUrl = rtrim($storageBase, '/') . '/' . $clientPhoto;
                        }
                    }
                    ?>
                    <div class="sv-profile">
                        <?php if ($photoUrl): ?>
                            <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Photo" class="sv-avatar">
                        <?php else: ?>
                            <div class="sv-avatar"><i class="fas fa-user"></i></div>
                        <?php endif; ?>
                        <div class="sv-name-badge">
                            <i class="far fa-address-card"></i>
                            <span><?= htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Test Mode Banner -->
                <?php if ($isTestMode) : ?>
                <div class="sv-test-banner">
                    <strong>Vous êtes en mode Test.</strong> Ce message est automatiquement supprimé avec un vrai flash compte client.<br><br>
                    Pour simuler un <strong>echec virement</strong> à 50%, veuillez utiliser le code de déblocage du virement : <strong>000000</strong><br>
                    Pour simuler un <strong>virement effectué avec succès</strong> à 100%, veuillez utiliser le code de déblocage du virement : <strong>111111</strong>
                </div>
                <?php endif; ?>

                <!-- Error / Success Alerts -->
                <?php if (!empty($erreur)) : ?>
                <div class="sv-alert <?= htmlspecialchars($alertType) ?>">
                    <i class="fas <?= $alertType === 'warning' ? 'fa-exclamation-triangle' : 'fa-circle-xmark' ?>"></i>
                    <span><?= htmlspecialchars($erreur) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($success)) : ?>
                <div class="sv-alert success">
                    <i class="fas fa-circle-check"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" action="index.php?page=traitement" autocomplete="off" novalidate>
                    <div class="sv-form-group">
                        <input type="email" id="email" name="email"
                            class="sv-input"
                            placeholder="Adresse e-mail"
                            required
                            <?php if ($isTestMode && $testEmail): ?>value="<?= htmlspecialchars($testEmail, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>>
                        <span class="sv-input-icon"><i class="fas fa-envelope"></i></span>
                    </div>

                    <div class="sv-form-group">
                        <input type="password" id="password" name="password"
                            class="sv-input"
                            placeholder="Code d'accès sécurisé"
                            required
                            <?php if ($isTestMode && $testPassword): ?>value="<?= htmlspecialchars($testPassword, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>>
                        <span class="sv-input-icon"><i class="fas fa-lock"></i></span>
                    </div>

                    <button type="submit" class="sv-btn-login">
                        Se connecter <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <!-- Security Notice -->
                <div class="sv-security">
                    <i class="fas fa-shield-halved"></i>
                    <span class="sv-security-text">Chiffrement SSL Actif — <?= t('secure_login_notice') ?></span>
                </div>
            </div>
        </div>
    </div>
    <script>
    if (window.history.replaceState && window.location.search) {
        window.history.replaceState(null, '', window.location.pathname);
    }
    </script>
    <?php endif; ?>