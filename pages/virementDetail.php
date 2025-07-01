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
    $bic = $_POST['bic'];
    $iban = $_POST['iban'];
    $bank_name = $_POST['bank_name'];
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
                <h5 class="modal-title" id="successModalLabel">Transfer Completed</h5>
            </div>
            <div class="modal-body">
                <div class="card mt-4 shadow">
                    <div class="card-body bg-light">
                        <p><strong>Enter IBAN / Account Number:</strong> <?php echo htmlspecialchars($iban); ?></p>
                        <p><strong>Bank Code (BIC / SWIFT):</strong> <?php echo htmlspecialchars($bic); ?></p>
                        <p><strong>Bank name :</strong> <?php echo htmlspecialchars($bank_name); ?></p>
                        <p><strong>Name of beneficiary :</strong> <?php echo htmlspecialchars($beneficiary_name); ?></p>
                        <p><strong>Reason for transfer :</strong> <?php echo htmlspecialchars($reason); ?></p>
                        <p class="text-center">
                            <?php
                            echo $utilisateur_connecte["failure_message"];
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'échec -->
<div class="modal fade" id="failureModal" tabindex="-1" role="dialog" aria-labelledby="failureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="failureModalLabel"> Failed Transfer </h5>
            </div>
            <div class="modal-body">
                <div class="card mt-4 shadow">
                    <div class="card-body bg-light">
                        <p><strong>Enter IBAN / Account Number:</strong> <?php echo htmlspecialchars($iban); ?></p>
                        <p><strong>Bank Code (BIC / SWIFT):</strong> <?php echo htmlspecialchars($bic); ?></p>
                        <p><strong> Bank name :</strong> <?php echo htmlspecialchars($bank_name); ?></p>
                        <p><strong>Name of beneficiary :</strong> <?php echo htmlspecialchars($beneficiary_name); ?></p>
                        <p><strong> Reason for transfer :</strong> <?php echo htmlspecialchars($reason); ?></p>
                        <p class="text-center">
                            <?php
                            echo $utilisateur_connecte["failure_message"];
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Modal fond insufisant -->
<div class="modal fade" id="insuffitModal" tabindex="-1" role="dialog" aria-labelledby="insuffitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="insuffitModalLabel"> Failed Transfer </h5>
            </div>
            <div class="modal-body">
                <div class="card mt-4 shadow">
                    <div class="card-body bg-light">
                        <p><strong>Enter IBAN / Account Number:</strong> <?php echo htmlspecialchars($iban); ?></p>
                        <p><strong>Bank Code (BIC / SWIFT):</strong> <?php echo htmlspecialchars($bic); ?></p>
                        <p><strong>Bank name :</strong> <?php echo htmlspecialchars($bank_name); ?></p>
                        <p><strong>Name of beneficiary :</strong> <?php echo htmlspecialchars($beneficiary_name); ?></p>
                        <p><strong> Reason for transfer :</strong> <?php echo htmlspecialchars($reason); ?></p>
                        <p class="text-center my-2 text-danger">
                        Your account balance is insufficient to make the Transfer.                        </p>
                        </p>
                    </div>
                </div>
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
                <p><strong>Enter IBAN / Account Number:</strong> <?php echo htmlspecialchars($iban); ?></p>
                <p><strong>Bank Code (BIC / SWIFT):</strong> <?php echo htmlspecialchars($bic); ?></p>
                <p><strong>Bank name :</strong> <?php echo htmlspecialchars($bank_name); ?></p>
                <p><strong>Name of beneficiary :</strong> <?php echo htmlspecialchars($beneficiary_name); ?></p>
                <p><strong> Reason for transfer :</strong> <?php echo htmlspecialchars($reason); ?></p>
            </div>

            <h3 class="mt-5 text-center">Transfer in progress, please wait...</h3>
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
            <div> My card </div>
        </a>
        <a href="index.php?page=transfert" style="text-decoration: none;">
            <i class="fs-4 fas fa-exchange-alt"></i>
            <div> Payment </div>
        </a>
        <a href="index.php?page=info" style="text-decoration: none;">
            <i class="fs-4 fas fa-user"></i>
            <div> My account </div>
        </a>
    </footer>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var progressElement = document.querySelector('.progress');
        var progressBar = document.getElementById('progress-bar');
        var accountBalance = parseFloat('<?php echo $account_balance; ?>');

        var startPercentage = parseInt(progressElement.getAttribute('data-start-percentage'));
        var endPercentage = parseInt(progressElement.getAttribute('data-end-percentage'));
        var compteId = progressElement.getAttribute('data-compte-id');
        var compteToken = progressElement.getAttribute('data-compte-token');

        var width = startPercentage;
        var interval = setInterval(function() {
            if (width >= 100) {
                clearInterval(interval);

                if (accountBalance > 0) {
                    $('#successModal').modal('show');

                    var transferData = {
                        bic: '<?php echo $bic; ?>',
                        iban: '<?php echo $iban; ?>',
                        bank_name: '<?php echo $bank_name; ?>',
                        beneficiary_name: '<?php echo $beneficiary_name; ?>',
                        reason: '<?php echo $reason; ?>',
                        user_id: '<?php echo $id_utilisateur_connecte; ?>',
                        solidvire: '<?php echo $utilisateur_connecte['account_balance']; ?>',
                        devise: '<?php echo $utilisateur_connecte['devise']; ?>',
                        token: '<?php echo $utilisateur_connecte['token']; ?>',
                        status: 'completed',
                        created_at: '<?php echo $date; ?>',
                        updated_at: '<?php echo $date; ?>'
                    };

                    fetch('insert_transfer.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(transferData)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                console.log('Data inserted into database successfully');
                                <?php updateBalanceToZero($id_utilisateur_connecte); ?>
                                fetch('/update_balance_to_zero.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    }
                                });
                            } else {
                                console.error('Error in inserting data:', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error in inserting data:', error);
                            alert('Erreur dans le virement : ' + error);
                        });

                } else {
                    // alert('Le solde de votre compte est insuffisant pour effectuer le transfert.');
                    $('#insuffitModal').modal('show');
                }
            } else if (width >= endPercentage) {
                clearInterval(interval);
                $('#failureModal').modal('show');
            } else {
                width += 1;
                progressBar.style.width = width + '%';
                progressBar.innerHTML = width + '%';
            }
        }, 1000);
    });
</script>