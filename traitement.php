<?php
// Démarrer la session AVANT tout output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('fonction.php');

$erreurs = [];
$donnees = [];
$success = '';
$erreur = '';

// ✅ CORRECTION : NE PAS utiliser strip_tags() sur les mots de passe
// On nettoie uniquement l'email, le mot de passe reste intact
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validation de l'email
if (empty($email)) {
    $erreurs['email'] = 'Le champ adresse email est obligatoire.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erreurs['email'] = 'Le champ adresse email doit être une adresse mail valide.';
}

// Validation du mot de passe
if (empty($password)) {
    $erreurs['password'] = 'Le champ mot de passe est obligatoire.';
}

// Si des erreurs de validation existent
if (!empty($erreurs)) {
    $erreur = "Oups!!! Un ou plusieurs champs sont vide(s) ou mal(s) renseigné(s).";
} else {
    // ✅ CORRECTION : Utiliser la fonction qui vérifie email ET mot de passe
    $utilisateur = chercher_utilisateur_par_son_email_et_son_mot_de_passe($email, $password);
    
    if (!empty($utilisateur)) {
        // Vérifier que le compte correspond au lien d'accès utilisé
        $clientToken = $_SESSION['client_token'] ?? '';
        if (!empty($clientToken) && strpos($clientToken, 'test.') !== 0) {
            $compteNumero = $utilisateur['numerocompte'] ?? '';
            $compteToken = $utilisateur['token'] ?? '';
            if ($clientToken !== $compteNumero && $clientToken !== $compteToken) {
                $erreur = "Les identifiants ne correspondent pas à ce lien d'accès.";
                $token = $clientToken;
                $redirectUrl = '?c=' . urlencode($token) . '&erreur=' . urlencode($erreur);
                header('Location: ' . $redirectUrl);
                exit;
            }
        }

        // Vérifier si le compte est bloqué ou suspendu
        $status = $utilisateur['account_status'] ?? 'Activé';
        $isActivated = in_array($status, ['Activé', 'Actif', 'active', 'Active']);
        if (!$isActivated) {
            $bankName = function_exists('t') ? (t('login_bank_name') ?: 'TRANSFERFLUX') : 'TRANSFERFLUX';
            if ($status === 'Examen') {
                $erreur = function_exists('t') ? t('account_exam_message', ['bank' => $bankName]) : "Votre compte {$bankName} est en cours d'examen. Veuillez patienter ou contacter le support.";
                $alertType = 'warning';
            } else {
                $erreur = function_exists('t') ? t('account_blocked_message', ['bank' => $bankName]) : "L'accès à votre compte {$bankName} est temporairement suspendu. Veuillez contacter le support.";
                $alertType = 'error';
            }
            $token = $_SESSION['client_token'] ?? '';
            $params = '&erreur=' . urlencode($erreur) . '&alert_type=' . $alertType;
            $redirectUrl = !empty($token) ? '?c=' . urlencode($token) . $params : 'index.php?page=connexion' . $params;
            header('Location: ' . $redirectUrl);
            exit;
        }

        $success = "Bravo!!! Vous êtes authentifié.";

        // ✅ Création explicite et normalisée de la session
        $_SESSION['utilisateur_connecter'] = [
            'compte_id' => $utilisateur['id'] ?? null,
            'user_id' => $utilisateur['user_id'] ?? null,
            'email' => $utilisateur['email'] ?? '',
            'nom' => $utilisateur['nom'] ?? '',
            'prenom' => $utilisateur['prenom'] ?? '',
            'devise' => $utilisateur['devise'] ?? '€'
        ];

        // Appliquer la langue du compte pour l'interface client
        $compteLang = $utilisateur['lang'] ?? 'fr';
        if (!empty($compteLang)) {
            $_SESSION['lang'] = $compteLang;
        }
        
        // ✅ Synchronisation des alertes (gardée du code original)
        try {
            $db = connexion_db();
            $compteId = $_SESSION['utilisateur_connecter']['compte_id'] ?? null;
            if ($compteId && is_object($db)) {
                if (!empty($_SESSION['balance_alert_dismissed'])) {
                    $stmt = $db->prepare("INSERT IGNORE INTO dismissed_alerts (compte_id, alert_type, alert_id) VALUES (:cid, 'balance', '')");
                    $stmt->execute([':cid' => $compteId]);
                }

                $txAlerts = $_SESSION['dismissed_transaction_alerts'] ?? [];
                if (!is_array($txAlerts)) {
                    $txAlerts = [];
                }
                $txAlerts = array_values(array_unique($txAlerts));
                if (!empty($txAlerts)) {
                    $stmt = $db->prepare("INSERT IGNORE INTO dismissed_alerts (compte_id, alert_type, alert_id) VALUES (:cid, 'transaction', :aid)");
                    foreach ($txAlerts as $aid) {
                        if ($aid === '' || $aid === null) continue;
                        $stmt->execute([':cid' => $compteId, ':aid' => (string)$aid]);
                    }
                }

                // Enregistrer l'heure de connexion
                $db->prepare("UPDATE comptes SET last_activity = :ts WHERE id = :id")
                   ->execute([':ts' => time(), ':id' => $compteId]);
                $_SESSION['last_db_activity'] = time();
            }
        } catch (Exception $e) {
            error_log("❌ Erreur synchronisation alertes: " . $e->getMessage());
            // Ne pas bloquer l'authentification si la synchronisation échoue
        }
    } else {
        $erreur = "Oups!!! Adresse mail ou mot de passe incorrect.";
    }
}

// ✅ Redirection avec encodage propre
header('Location: index.php?page=connexion&erreur=' . urlencode($erreur) . 
       '&erreurs=' . json_encode($erreurs) . 
       '&success=' . urlencode($success));

exit();