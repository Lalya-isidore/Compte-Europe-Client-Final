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
    <div class="dashboard  ">
        <nav class="pt-2">
            <div><i class="fas fa-bars menu-icon"></i> <strong class="fs-4">TRANSFER</strong></div>
            <a href="{{route('info')}}" class="icon-circle">
                <i class="fas fa-user"></i>
            </a>

        </nav>
        <hr>
        <div class="alert alert-warning">
            To update your account information, please contact our support team.
        </div>
        <div class="info-section">
            <h6 class="text-primary fw-bold fs-4"><i class="fas fa-user "></i> Personal Information</h6>
            <p><strong>Account holder:</strong> <?= $utilisateur_connecte['nom'] . ' ' . $utilisateur_connecte['prenom']  ?></p>
            <p><strong>E-mail address:</strong> <?= $utilisateur_connecte['email'] ?></p>
            <p><strong>Phone number:</strong> <?= $utilisateur_connecte['phone_number'] ?></p>
            <p><strong>Country of Residence:</strong> <?= $utilisateur_connecte['country'] ?></p>
            <p><strong>Residence address:</strong> <?= $utilisateur_connecte['address'] ?> </p>
        </div>
        <div class="info-section">
            <h6 class="text-primary fw-bold fs-4"><i class="fas fa-university"></i> Account and Transfer</h6>
            <p><strong>Account balance:</strong> <?= $formatted_balance . ' ' . $utilisateur_connecte['devise'] ?> </p>
            <p><strong>Account type:</strong> <?= $utilisateur_connecte['account_type'] ?></p>
            <p><strong>Account Status:</strong> <span class="text-success">
                    <?php
                    if ($utilisateur_connecte['account_status'] === 'Active') { ?>
                        <span class="fw-bold"></span> <i style="background-color: green; border-radius:10%; color:white;
                        font-size:.8rem; padding:.2rem; "><?= $utilisateur_connecte['account_status'] ?></i>
                    <?php }?>
                    <?php
                    if ($utilisateur_connecte['account_status'] === 'Exam') { ?>
                        <span class="fw-bold"></span> <i style="background-color:blue; border-radius:10%; color:white; 
                        font-size:.8rem; padding:.2rem; "><?= $utilisateur_connecte['account_status'] ?></i>
                    <?php }?>
                    <?php
                    if ($utilisateur_connecte['account_status'] === 'Suspended') { ?>
                        <span class="fw-bold"></span> <i style="background-color: #e97c23; border-radius:10%; color:white;
                        font-size:.8rem; padding:.2rem; "><?= $utilisateur_connecte['account_status'] ?></i>
                    <?php }?>
                    <?php
                    if ($utilisateur_connecte['account_status'] === 'Blocked') { ?>
                          <span class="fw-bold"></span> <i style="background-color: red; border-radius:10%; color:white; 
                        font-size:.8rem; padding:.2rem; "><?= $utilisateur_connecte['account_status'] ?></i>
                    <?php }?>

                    
                </span></p>
            <p><strong>Transfer supported:</strong><?= $utilisateur_connecte['transfer_supported'] ?> </p>
            <p><strong>Beneficiary's account or IBAN number:</strong> <span class="text-danger"> No IBAN/Account Number registered</span></p>
        </div>
        <form action="index.php?page=deconnexion" method="POST" class="mt-3">
            <button type="submit" class="btn btn-danger fw-bold">Disconnect <i class="fas fa-sign-out-alt"></i></button>
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
            <a href=" index.php?page=transfert" class="" style="text-decoration: none; ">
                <i class="fs-4 fas fa-exchange-alt"></i>
                <div class=" ">Payment</div>
            </a>
            <a href="index.php?page=info" class="" style="text-decoration: none;  border-bottom: 2px #007bff solid;">
                <i class="fs-4 fas fa-user"></i>
                <div class=" ">My account</div>
            </a>
        </footer>

    </div>


    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>