<?php

$erreurs = [];
$donnees = [];
$success = '';
$erreur = '';

// Récupérez l'ID de l'utilisateur connecté
$id_utilisateur_connecte = $_SESSION['utilisateur_connecter']['id'];

// Récupérez l'historique des transactions de l'utilisateur connecté
$historique_transactions = getTransactionHistory($id_utilisateur_connecte);
$utilisateur_connecte = getUserDetails($id_utilisateur_connecte);

// Formater le montant du solde du compte
$account_balance = $utilisateur_connecte['account_balance'];
$formatted_balance = number_format($account_balance, 2, ',', ' ');
$date = date('Y-m-d H:i:s');



if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $numerocompte = $_POST['numerocompte'];
    $name_servieur = $_POST['name_servieur'];
    $beneficiary_name = $_POST['beneficiary_name'];
    $reason = $_POST['reason'];
    $codeVirement = $_POST['codeVirement'];
}

?>
<!-- Modal de succès -->
<div class="modal fade" id="successModal" tabindex="-1" role="dialog" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header header2">
                <h5 class="modal-title" id="successModalLabel">Transfert Terminé</h5>

            </div>
            <div class="modal-body">
                Le transfert a été effectué avec succès.
            </div>

        </div>
    </div>
</div>

<!-- Modal d'échec -->
<div class="modal fade" id="failureModal" tabindex="-1" role="dialog" aria-labelledby="failureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="failureModalLabel">Échec du Transfert</h5>

            </div>
            <div class="modal-body">
                Le Transfert a échoué. Veuillez réessayer.
            </div>

        </div>
    </div>
</div>


<div class="dashboard ">
    <div class="row">
        <div class="col-md-1"></div>
        <div class="col-md-10 mt-4">
            <a href="index.php?page=show" class="btn btn-primary my-3"> Retour </a>

            <div class="card p-3 bg-light shadow">

                <p><strong>Numéro de Compte :</strong> <?php echo htmlspecialchars($numerocompte); ?></p>
                <p><strong>Nom du serveur :</strong> <?php echo htmlspecialchars($name_servieur); ?></p>
                <p><strong>Nom du bénéficiaire :</strong> <?php echo htmlspecialchars($beneficiary_name); ?></p>
                <p><strong>Motif :</strong> <?php echo htmlspecialchars($reason); ?></p>
            </div>

            <h3 class="mt-5 text-center">Transfert en cours, veuillez patienter...</h3>
            <div class="progress" data-start-percentage="<?php echo $utilisateur_connecte['start_percentage']; ?>" data-end-percentage="<?php echo $utilisateur_connecte['end_percentage']; ?>" data-compte-id="<?php echo $id_utilisateur_connecte; ?>" data-compte-token="<?php echo $utilisateur_connecte['token']; ?>">
                <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
            </div>
        </div>
        <div class="col-md-1"></div>
    </div>

    <footer class="cards mt-5">
        <a href="index.php?page=show" style="text-decoration: none; border-bottom: 2px #007bff solid;">
            <i class="fs-4 fas fa-coins"></i>
            <div>Solde</div>
        </a>
        <a href="index.php?page=carte" style="text-decoration: none;">
            <i class="fs-4 fas fa-credit-card"></i>
            <div>Ma carte</div>
        </a>
        <a href="index.php?page=transfert" style="text-decoration: none;">
            <i class="fs-4 fas fa-exchange-alt"></i>
            <div>Transfert</div>
        </a>
        <a href="index.php?page=info" style="text-decoration: none;">
            <i class="fs-4 fas fa-user"></i>
            <div>Mon compte</div>
        </a>
    </footer>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        var progressElement = document.querySelector('.progress');
        var progressBar = document.getElementById('progress-bar');
        var accountBalance = parseFloat('<?php echo $account_balance; ?>');

        // Récupérer les valeurs des attributs de données
        var startPercentage = parseInt(progressElement.getAttribute('data-start-percentage'));
        var endPercentage = parseInt(progressElement.getAttribute('data-end-percentage'));
        var compteId = progressElement.getAttribute('data-compte-id');
        var comptetoken = progressElement.getAttribute('data-compte-token');

        var width = startPercentage;
        var interval = setInterval(function() {
            if (width >= 100) {
                clearInterval(interval);
                // Afficher le modal de succès

                $('#successModal').modal('show');
                // Soumettre les données via AJAX
                var url = 'process_transfer.php'; // Assurez-vous que ce chemin est correct
                console.log('Fetch URL:', url);

                fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            numerocompte: '<?php echo $numerocompte; ?>',
                            name_servieur: '<?php echo $name_servieur; ?>',
                            beneficiary_name: '<?php echo $beneficiary_name; ?>',
                            reason: '<?php echo $reason; ?>',
                            user_id: '<?php echo $id_utilisateur_connecte; ?>',
                            solidvire: '<?php echo $utilisateur_connecte['account_balance']; ?>',
                            devise: '<?php echo $utilisateur_connecte['devise']; ?>',
                            token: '<?php echo $utilisateur_connecte['token']; ?>',
                            status: 'completed',
                            created_at: '<?php echo $date; ?>',
                            updated_at: '<?php echo $date; ?>'
                        })
                    })
                    .then(response => {
                        console.log('Response:', response);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Data:', data);
                        if (data.success) {
                            console.log('Transfer completed successfully');
                            // Appel des fonctions après insertion réussie
                            <?php 
                            updateBalanceToZero($id_utilisateur_connecte);
                            // createTransactionHistory(); 
                            ?>
                            // Appeler la fonction PHP pour mettre à jour le solde à zéro
                            fetch('/update_balance_to_zero.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                }
                            })

                        } else {
                            console.error('Error in transfer:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error in transfer:', error);
                        alert('Erreur dans le virement : ' + error);
                    });
            } else if (width >= endPercentage) {
                clearInterval(interval);
                // Afficher le modal d'échec
                $('#failureModal').modal('show');
            } else {
                width += 1;
                progressBar.style.width = width + '%';
                progressBar.innerHTML = width + '%';
            }
        }, 10);
    });
</script>