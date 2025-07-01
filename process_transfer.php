<?php
// require_once(__DIR__ . '/fonction.php');

// header('Content-Type: application/json');

// $id_utilisateur_connecte = $_SESSION['utilisateur_connecter']['id'];

// // Récupérez l'historique des transactions de l'utilisateur connecté
// $historique_transactions = getTransactionHistory($id_utilisateur_connecte);
// $utilisateur_connecte = getUserDetails($id_utilisateur_connecte);
// $account_balance = $utilisateur_connecte['account_balance'];

// try {
//     $db = connexion_db();
//     // Récupération des données POST
//     $data = json_decode(file_get_contents('php://input'), true);
//     // if ($account_balance > 0) {
//         if ($data) {
//             $iban = $data['iban'];
//             $bic = $data['bic'];
//             $bank_name = $data['bank_name'];
//             $beneficiary_name = $data['beneficiary_name'];
//             $reason = $data['reason'];
//             $user_id = $data['user_id'];
//             $solidvire = $data['solidvire'];
//             $devise = $data['devise'];
//             $token = $data['token'];
//             $status = $data['status'];
//             $created_at = $data['created_at'];
//             $updated_at = $data['updated_at'];

//             // Préparation de la requête SQL pour insérer les données de transfert
//             $sql = "INSERT INTO transfers (iban, bic, bank_name, beneficiary_name, reason, user_id, solidvire, devise, token, status, created_at, updated_at) 
//                 VALUES (:iban, :bic, :bank_name, :beneficiary_name, :reason, :user_id, :solidvire, :devise, :token, :status, :created_at, :updated_at)";
//             $stmt = $db->prepare($sql);

//             $stmt->bindParam(':iban', $iban);
//             $stmt->bindParam(':bic', $bic);
//             $stmt->bindParam(':bank_name', $bank_name);
//             $stmt->bindParam(':beneficiary_name', $beneficiary_name);
//             $stmt->bindParam(':reason', $reason);
//             $stmt->bindParam(':user_id', $user_id);
//             $stmt->bindParam(':solidvire', $solidvire);
//             $stmt->bindParam(':devise', $devise);
//             $stmt->bindParam(':token', $token);
//             $stmt->bindParam(':status', $status);
//             $stmt->bindParam(':created_at', $created_at);
//             $stmt->bindParam(':updated_at', $updated_at);

//             if ($stmt->execute()) {
//                 // Préparation de la requête SQL pour insérer les données de l'historique des transactions
//                 $transaction_type = 'Transfer sent';
//                 $description = $bank_name;
//                 $date = date('Y-m-d H:i:s');
//                 $amount = $solidvire; // Utilisez le montant du transfert pour l'historique

//                 $sql = "INSERT INTO transaction_histories (user_id, transaction_type, amount, devise, description, created_at, updated_at) 
//                     VALUES (:user_id, :transaction_type, :amount, :devise, :description, :created_at, :updated_at)";

//                 $stmt = $db->prepare($sql);
//                 $stmt->bindParam(':user_id', $user_id);
//                 $stmt->bindParam(':transaction_type', $transaction_type);
//                 $stmt->bindParam(':amount', $amount);
//                 $stmt->bindParam(':devise', $devise);
//                 $stmt->bindParam(':description', $description);
//                 $stmt->bindParam(':created_at', $date);
//                 $stmt->bindParam(':updated_at', $date);

//                 if ($stmt->execute()) {
//                     echo json_encode(['success' => true]);
//                     exit; // Sortie immédiate après l'envoi du JSON
//                 } else {
//                     echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'insertion de l\'historique des transactions.']);
//                     exit; // Sortie immédiate en cas d'erreur
//                 }
//             } else {
//                 echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'insertion des données de transfert.']);
//                 exit; // Sortie immédiate en cas d'erreur
//             }
//         } else {
//             echo json_encode(['success' => false, 'message' => 'Données non valides.']);
//             exit; // Sortie immédiate en cas de données non valides
//         }
//     // }
// } catch (PDOException $e) {
//     echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données : ' . $e->getMessage()]);
//     exit; // Sortie immédiate en cas d'erreur de connexion PDO
// } catch (Exception $e) {
//     echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
//     exit; // Sortie immédiate en cas d'autres erreurs
// }
?>

