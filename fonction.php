<?php


/**
 * Cette fonction permet de se connecter la base de données.
 * @return PDO | string L'instance de la base de données ou le message d'erreur.
 */
function connexion_db()
{
    try {
        $dns = 'mysql:host=localhost;dbname=u537825448_europeB;charset=utf8';
        $user_name = 'u537825448_europeB';
        $password = 'Paul@0815';
        return new PDO($dns, $user_name, $password);
    } catch (Exception $e) {
        return $e->getMessage();
    }
}


/**
 * Cette fonction permet de verifier si un utilisateur existe dans la base de données grace a son adresse email et son mot de passe.
 * 
 * @param string $email L'adresse email de l'utilisateur.
 * @param string $mot_de_passe Le mot de passe de l'utilisateur.
 * @return bool
 */
/**
 * Cette fonction permet de verifier si un utilisateur existe dans la base de données grace a son adresse email et son mot de passe.
 * 
 * @param string $email L'adresse email de l'utilisateur.
 * @param string $password Le mot de passe de l'utilisateur.
 * @return bool
 */
function chercher_utilisateur_par_son_email_et_son_mot_de_passe(string $email, string $password): array
{
    $utilisateur_est_trouver = [];

    $db = connexion_db();

    if (is_object($db)) {
        $requetteSql = 'SELECT * FROM `comptes` WHERE `email`=:email and `password2`=:password';
        $requette = $db->prepare($requetteSql);
        try {

            $requette->execute(
                [
                    'email' => $email,
                    'password' => $password
                ]
            );
            $utilisateur = $requette->fetch(PDO::FETCH_ASSOC);
            if (is_array($utilisateur)) {
                $utilisateur_est_trouver =  $utilisateur;
            }
        } catch (Exception $e) {
            $utilisateur_est_trouver = [];
        }
    }

    return $utilisateur_est_trouver;
}


function chercher_utilisateur_par_son_email(string $email): ?array
{
    $db = connexion_db();

    if (is_object($db)) {
        $requeteSql = 'SELECT * FROM `comptes` WHERE email = :email';
        $requete = $db->prepare($requeteSql);
        try {
            $requete->execute(['email' => $email]);
            return $requete->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            return null;
        }
    }
    return null;
}

/**
 * Cette fonction permet de vérifier si un utilisateur est connecté.
 * 
 * @return bool
 */
function est_connecter(): bool
{
    if (isset($_SESSION['utilisateur_connecter']) && !empty($_SESSION['utilisateur_connecter'])) {
        $utilisateur = chercher_utilisateur_par_son_email($_SESSION['utilisateur_connecter']['email']);
        if ($utilisateur) {
            return true;
        } else {
            session_destroy();
        }
    }
    return false;
}

/**
 * Cette fonction permet de récupérer l'historique des transactions d'un utilisateur.
 * 
 * @param int $user_id L'ID de l'utilisateur.
 * @return array L'historique des transactions.
 */
function getTransactionHistory($user_id)
{
    $db = connexion_db();

    if (is_object($db)) {
        $requeteSql = 'SELECT * FROM `transaction_histories` WHERE `user_id` = :user_id ORDER BY `created_at` DESC';
        $requete = $db->prepare($requeteSql);
        try {
            $requete->execute(['user_id' => $user_id]);
            return $requete->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    return [];
}
/**
 * Cette fonction permet de récupérer toutes les informations de l'utilisateur connecté.
 * 
 * @param int $user_id L'ID de l'utilisateur.
 * @return array Les informations de l'utilisateur.
 */
function getUserDetails($user_id)
{
    $db = connexion_db();

    if (is_object($db)) {
        $requeteSql = 'SELECT * FROM `comptes` WHERE `id` = :user_id';
        $requete = $db->prepare($requeteSql);
        try {
            $requete->execute(['user_id' => $user_id]);
            return $requete->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    return [];
}
function deconnexion()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Détruire toutes les variables de session
    $_SESSION = array();

    // Si vous voulez détruire complètement la session, supprimez également le cookie de session.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Finalement, détruire la session.
    session_destroy();

    // Rediriger l'utilisateur vers la page de connexion ou d'accueil
    header('Location: index.php?page=connexion');
}


function updateBalanceToZero($id_utilisateur_connecte) {
    $db = connexion_db();

    try {
        // Sélection du compte
        $stmt = $db->prepare("SELECT * FROM comptes WHERE id = :id");
        $stmt->bindParam(':id', $id_utilisateur_connecte);
        $stmt->execute();
        $compte = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($compte) {
            // Vérifier si end_percentage est de 100
            if ($compte['end_percentage'] == 100) {
                // Mettre à jour le solde du compte à zéro
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
        return json_encode(['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
    }
}


function createTransactionHistory()
{
    try {
        // Connexion à la base de données
        $db = connexion_db();

        // Préparation de la requête SQL pour insérer les données
        $sql = "INSERT INTO transaction_histories (user_id, transaction_type, amount, devise, description, created_at, updated_at) 
                VALUES (:user_id, :transaction_type, :amount, :devise, :description, :created_at, :updated_at)";

        $stmt = $db->prepare($sql);
        $id_utilisateur_connecte = $_SESSION['utilisateur_connecter']['id'];
        $utilisateur_connecte = getUserDetails($id_utilisateur_connecte);
        // Définir les valeurs
        $transaction_type = 'Transfer sent';
        $description = 'Transfer to ';
        $date = date('Y-m-d H:i:s');

        // Liaison des paramètres
        $stmt->bindParam(':user_id', $id_utilisateur_connecte, PDO::PARAM_INT);
        $stmt->bindParam(':transaction_type', $transaction_type);
        $stmt->bindParam(':amount', $utilisateur_connecte['account_balance2'], PDO::PARAM_STR);
        $stmt->bindParam(':devise', $utilisateur_connecte['devise'], PDO::PARAM_STR);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':created_at', $date);
        $stmt->bindParam(':updated_at', $date);

        // Exécution de la requête
        if ($stmt->execute()) {
            return json_encode(['success' => true, 'message' => 'Transaction history created successfully']);
        } else {
            return json_encode(['success' => false, 'message' => 'Failed to create transaction history']);
        }
    } catch (PDOException $e) {
        return json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
