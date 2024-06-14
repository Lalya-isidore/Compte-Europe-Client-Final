<?php
// Démarrer la session
session_start();

// Inclure le fichier de fonctions
require_once(__DIR__ . '/fonction.php');

// Définir le type de contenu comme JSON
header('Content-Type: application/json');

// Initialiser la réponse par défaut
$response = ['success' => false];
$db=connexion_db();
// Vérifier si la base de données est connectée et si le code de virement est envoyé
if ($db = connexion_db() && isset($_POST['codeVirement'])) {
    // Récupérer le code de virement depuis le POST
    $codeVirement = $_POST['codeVirement'];

    // Vérifier si l'utilisateur est connecté
    if (isset($_SESSION['utilisateur_connecter']['id'])) {
        $id_utilisateur_connecte = $_SESSION['utilisateur_connecter']['id'];
        $db=connexion_db();
        // Préparer la requête SQL pour vérifier le code de virement
        $requeteSql = 'SELECT code_virement FROM comptes WHERE id=:id';
        $requete = $db->prepare($requeteSql);

        try {
            // Exécuter la requête
            $requete->execute(['id' => $id_utilisateur_connecte]);
            $result = $requete->fetch(PDO::FETCH_ASSOC);

            // Vérifier si le code de virement correspond
            if ($result && $result['code_virement'] === $codeVirement) {
                $response['success'] = true;
            }
        } catch (Exception $e) {
            // En cas d'erreur, ajouter le message d'erreur à la réponse
            $response['error'] = "Erreur lors de la vérification du code de sécurité : " . $e->getMessage();
        }
    } else {
        // Si l'utilisateur n'est pas connecté, ajouter un message d'erreur
        $response['error'] = "Utilisateur non connecté.";
    }
} else {
    // Si la connexion à la base de données a échoué ou si le code de virement n'est pas défini, ajouter un message d'erreur
    $response['error'] = "Connexion à la base de données échouée ou code de virement non fourni.";
}

// Retourner la réponse en JSON
echo json_encode($response);
?>
