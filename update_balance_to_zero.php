<?php

require_once(__DIR__ . '/fonction.php');

function updateBalanceToZero()
{
    $db = connexion_db();

    // Vérifiez si l'utilisateur est connecté
    if (isset($_SESSION['utilisateur_connecter']['id'])) {
        $id_utilisateur_connecte = $_SESSION['utilisateur_connecter']['id'];

        try {
            // Sélection du compte
            $stmt = $db->prepare("SELECT * FROM comptes WHERE id = :id");
            $stmt->bindParam(':id', $id_utilisateur_connecte);
            $stmt->execute();
            $compte = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($compte) {
                // Mettre à jour le solde du compte à zéro
                $stmt = $db->prepare("UPDATE comptes SET account_balance = 00.0 WHERE id = :id");
                $stmt->bindParam(':id', $id_utilisateur_connecte);
                $stmt->execute();

                return json_encode(['success' => true]);
            } else {
                return json_encode(['success' => false, 'message' => 'Compte non trouvé.']);
            }
        } catch (PDOException $e) {
            return json_encode(['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
        }
    } else {
        return json_encode(['success' => false, 'message' => 'Utilisateur non connecté.']);
    }
}


