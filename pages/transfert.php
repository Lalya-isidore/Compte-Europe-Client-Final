<?php
$erreurs = [];
$donnees = [];
$success = '';
$erreur = '';

// Vérifiez si l'utilisateur est connecté
if (!isset($_SESSION['utilisateur_connecter']) || empty($_SESSION['utilisateur_connecter'])) {
    // Rediriger l'utilisateur vers la page de connexion s'il n'est pas connecté
    header('Location: index.php?page=connexion');
    exit();
}
$utilisateur = $_SESSION['utilisateur_connecter'];
// Récupérez l'ID de l'utilisateur connecté
$id_utilisateur_connecte = $_SESSION['utilisateur_connecter']['id'];

// Récupérez l'historique des transactions de l'utilisateur connecté
$historique_transactions = getTransactionHistory($id_utilisateur_connecte);
$utilisateur_connecte = getUserDetails($id_utilisateur_connecte);
// Formater le montant du solde du compte
$account_balance = $utilisateur_connecte['account_balance'];
$formatted_balance = number_format($account_balance, 2, ',', ' ');

?>

<body>
    <div class="modal fade" id="failureModal" tabindex="-1" role="dialog" aria-labelledby="failureModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="failureModalLabel">Transfer Failed</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Your balance is insufficient!
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard">
        <nav class="pt-2">
            <div><i class="fas fa-bars menu-icon"></i> <strong class="fs-4">TRANSFER</strong></div>
            <a href="{{ route('info') }}" class="icon-circle">
                <i class="fas fa-user"></i>
            </a>
        </nav>
        <hr>

        <div class="text-center">
            <i class="fas fa-paper-plane icon fs-3" style="color: var(--bs-link-color);"></i> <strong class="fs-3 mx-3">Make a Transfer</strong>
        </div>

        <div class="my-3 d-flex " style="justify-content: center;">
            <div class="transfer-option" id="bankTransferOption">
                <img src="/télécharger.jpeg" class="image" alt="Virement Bancaire">
            </div>
            <div class="transfer-option" id="paypalTransferOption"> 
                <img src="/OIP (1).jpeg" class="image" alt="PayPal">

            </div>
        </div>

        <h1 class="my-3 fw-bold d-flex justify-content-center">
            <?= $formatted_balance . ' ' . $utilisateur['devise'] ?>
        </h1>

        <form id="bankTransferForm" action="index.php?page=confirmVirement&method=bank" method="post" style="display: block;">
            <div class="text-center">
                <img src="image/1.jpg" class="image1" alt="Image d'exemple">
                <img src="image/7.jpg" class="image1" alt="Image d'exemple">
                <img src="image/3.jpg" class="image1" alt="Image d'exemple">
                <img src="image/4.jpeg" class="image1" alt="Image d'exemple">
                <img src="image/5.jpeg" class="image1" alt="Image d'exemple">
                <img src="image/6.jpg" class="image1" alt="Image d'exemple">
                <!-- <img src="image/7.jpg" class="image1" alt="Image d'exemple"> -->

            </div>
            <div class="text-left my-4">
                <i class="fas fa-info-circle fs-5" style="color: var(--bs-link-color);"></i> Transfer Details
            </div>
            <div class="form-group my-2">
                <input type="text" class="form-control" id="iban" name="iban" placeholder="" required>
                <label for="iban">Enter IBAN / Account Number</label>
                <span id="iban-error" class="text-danger"></span>
            </div>
            <div class="form-group my-2">
                <input type="text" class="form-control" id="bic" name="bic" placeholder="" required>
                <label for="bic">Bank Code (BIC / SWIFT)</label>
                <span id="bic-error" class="text-danger"></span>
            </div>
            <div class="form-group my-2">
                <input type="text" class="form-control" id="bank_name" name="bank_name" placeholder="" required>
                <label for="bank_name">Bank name</label>
                <span id="bank-name-error" class="text-danger"></span>
            </div>
            <div class="form-group my-2">
                <input type="text" class="form-control" id="beneficiary_name" name="beneficiary_name" placeholder="" required>
                <label for="beneficiary_name">Name of beneficiary</label>
                <span id="beneficiary-name-error" class="text-danger"></span>
            </div>
            <div class="form-group my-2">
                <input type="text" class="form-control" id="reason" name="reason" placeholder="" required>
                <label for="reason">Reason for Transfer</label>
                <span id="reason-error" class="text-danger"></span>
            </div>
            <div class="alert alert-warning text-left my-3">
                <i class="fas fa-exclamation-triangle"></i> Transfer processing in 1-3 business days. Fees: Free
            </div>
            <?php if ($formatted_balance > 1) { ?>
                <button type="submit" class="btn btn-primary btn-block my-2 d-grid gap-2 d-md-flex justify-content-md-end">Following</button>
            <?php } else { ?>
                <a data-toggle="modal" data-target="#failureModal" class="btn btn-primary btn-block my-2 d-grid gap-2 d-md-flex">Insufficient funds</a>
            <?php } ?>
        </form>


        <form id="paypalTransferForm" action="index.php?page=confirmVirementpaypal&method=paypal" method="post" style="display: none;" >
            <div class="text-left my-4">
                <i class="fas fa-info-circle fs-5" style="color: var(--bs-link-color);"></i> PayPal Transfer Details
            </div>
            <div class="form-group my-2">
                <input type="email" class="form-control" id="paypalEmail" name="paypalEmail" placeholder="" required>
                <label for="paypalEmail">PayPal email address</label>
                <span id="paypal-email-error" class="text-danger"></span>
            </div>
            <div class="form-group my-2">
                <input type="text" class="form-control" id="reasonPaypal" name="reasonPaypal" placeholder="" required>
                <label for="reasonPaypal">Reason for Transfer</label>
                <span id="reason-paypal-error" class="text-danger"></span>
            </div>
            <div class="alert alert-warning text-left my-3">
                <i class="fas fa-exclamation-triangle"></i> Transfer processing in 1-3 business days. Fees: Free
            </div>
            <?php if ($formatted_balance > 1) { ?>
                <button type="submit" class="btn btn-primary btn-block my-2 d-grid gap-2 d-md-flex justify-content-md-end">Suivant</button>
            <?php } else { ?>
                <a data-toggle="modal" data-target="#failureModal" class="btn btn-primary btn-block my-2 d-grid gap-2 d-md-flex">Fond insuffisant</a>
            <?php } ?>
        </form>


        <footer class="cards mt-5">
            <a href="index.php?page=show" class=" " style="text-decoration: none;">
                <i class=" fs-4  fas fa-coins"></i>
                <div class="">Pay</div>
            </a>
            <a href="index.php?page=carte" class="" style="text-decoration: none; ">
                <i class="fs-4 fas fa-credit-card"></i>
                <div class=" ">My card</div>
            </a>
            <a href=" index.php?page=transfert" class="" style="text-decoration: none; border-bottom: 2px #007bff solid; ">
                <i class="fs-4 fas fa-exchange-alt"></i>
                <div class=" ">Payment</div>
            </a>
            <a href="index.php?page=info" class="" style="text-decoration: none;">
                <i class="fs-4 fas fa-user"></i>
                <div class=" ">My account</div>
            </a>
        </footer>
    </div>

    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var bankTransferOption = document.getElementById('bankTransferOption');
            var paypalTransferOption = document.getElementById('paypalTransferOption');
            var bankTransferForm = document.getElementById('bankTransferForm');
            var paypalTransferForm = document.getElementById('paypalTransferForm');

            bankTransferOption.addEventListener('click', function() {
                bankTransferForm.style.display = 'block';
                paypalTransferForm.style.display = 'none';
            });

            paypalTransferOption.addEventListener('click', function() {
                bankTransferForm.style.display = 'none';
                paypalTransferForm.style.display = 'block';
            });

            // Écouteurs d'événements pour la soumission des formulaires
            bankTransferForm.addEventListener('submit', function(event) {
                if (!validateBankTransferForm()) {
                    event.preventDefault();
                }
            });

            paypalTransferForm.addEventListener('submit', function(event) {
                if (!validatePaypalTransferForm()) {
                    event.preventDefault();
                }
            });
        });

        function validateBankTransferForm() {
            var iban = document.getElementById('iban');
            var bic = document.getElementById('bic');
            var bank_name = document.getElementById('bank_name');
            var beneficiary_name = document.getElementById('beneficiary_name');
            var reason = document.getElementById('reason');

            var ibanError = document.getElementById('iban-error');
            var bicError = document.getElementById('bic-error');
            var bankNameError = document.getElementById('bank-name-error');
            var beneficiaryNameError = document.getElementById('beneficiary-name-error');
            var reasonError = document.getElementById('reason-error');

            ibanError.textContent = '';
            bicError.textContent = '';
            bankNameError.textContent = '';
            beneficiaryNameError.textContent = '';
            reasonError.textContent = '';

            var isValid = true;

            if (iban.value.trim() === '') {
                ibanError.textContent = 'This field is required.';
                isValid = false;
            }

            if (bic.value.trim() === '') {
                bicError.textContent = 'This field is required.';
                isValid = false;
            }

            if (bank_name.value.trim() === '') {
                bankNameError.textContent = 'This field is required.';
                isValid = false;
            }

            if (beneficiary_name.value.trim() === '') {
                beneficiaryNameError.textContent = 'This field is required.';
                isValid = false;
            }

            if (reason.value.trim() === '') {
                reasonError.textContent = 'This field is required.';
                isValid = false;
            }

            return isValid;
        }

        function validatePaypalTransferForm() {
            var paypalEmail = document.getElementById('paypalEmail');
            var reasonPaypal = document.getElementById('reasonPaypal');

            var paypalEmailError = document.getElementById('paypal-email-error');
            var reasonPaypalError = document.getElementById('reason-paypal-error');

            paypalEmailError.textContent = '';
            reasonPaypalError.textContent = '';

            var isValid = true;

            if (paypalEmail.value.trim() === '') {
                paypalEmailError.textContent = 'This field is required.';
                isValid = false;
            }

            if (reasonPaypal.value.trim() === '') {
                reasonPaypalError.textContent = 'This field is required.';
                isValid = false;
            }

            return isValid;
        }
    </script>
</body>

</html>