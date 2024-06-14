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


    <div class="dashboard">
        <nav class="pt-2">
            <div><i class="fas fa-bars menu-icon"></i> <strong class="fs-4">TRANSFERT</strong></div>
            <a href="{{route('info')}}" class="icon-circle">
                <i class="fas fa-user"></i>
            </a>

        </nav>
        <hr>

        <!-- <div class="alert alert-success alert-dismissible fade show" role="alert">
            <p>{{ session('success') }}</p>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>


        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div> -->

        <div class=" my-5">

            <p> Hello <?= $_SESSION['utilisateur_connecter']['nom'] . " " . $_SESSION['utilisateur_connecter']['prenom']; ?></p>
            <div class="account-balance">
                <div>
                    <p><i class="fas fa-coins"></i> Account balance :</p>
                </div>
                <h2 class="fw-bold mx-3 fs-4 mb-4 balance"><?= $formatted_balance . " " . $_SESSION['utilisateur_connecter']['devise']; ?></h2>
                <div class='d-flex flex-nowrap align-items-center '>
                    <a href="index.php?page=transfert" class="btn btn-warning virement btnaccc ">Effectuer un Transfert <i class=" fas fa-arrow-right"></i></a>
                    <a href="index.php?page=carte" class="btn btn-success carte btnaccc ">Ma carte <i class=" fas fa-arrow-right"></i></a>
                </div>
            </div>
            <style>
                @media (max-width: 500px) {


                    .iconHist {
                        padding: .7rem;
                        background-color: #ccebf5;
                        border-radius: 50%;
                        font-size: 1rem;
                        object-fit: cover;
                    }

                    .iconHist2 {
                        padding: .7rem;
                        background-color: red;
                        border-radius: 50%;
                        font-size: 1rem;
                        color: #f0f0f0;
                        object-fit: cover;
                    }

                    .iconHist3 {
                        padding: .7rem;
                        background-color: green;
                        border-radius: 50%;
                        font-size: 1rem;
                        color: white;
                        object-fit: cover;
                    }

                    .dashboard {
                        position: relative;
                        background-color: white;
                        max-width: 900px;
                        min-height: 100vh;
                        box-shadow: 0 0 12px 0 rgba(0, 0, 0, .2);
                        overflow: auto;
                        padding: 10px 30px 150px 30px;
                        transition: all 200ms linear;
                    }

                    .transftype {
                        font-size: .7rem;
                    }

                    .transftyped {
                        font-size: .5rem;
                    }

                    .btnacc {
                        margin: .3rem;
                    }

                    .btnaccc {
                        font-size: .6rem;
                        margin: .1rem;
                    }
                }
            </style>


            <div class="transaction-history">
                <h4>Historique des transactions</h4>
                <ul class="list-group">
                    <ul class="list-group">

                        <?php if (!empty($historique_transactions)) :

                        ?>
                            <?php foreach ($historique_transactions as $transaction) :
                                $amount = $transaction['amount'];
                                $formatted_trans = number_format($amount, 2, ',', ' ');
                            ?>
                                <ul class="list-group">
                                    <li class="list-group-item d-flex flex-nowrap align-items-center mb-1">
                                        <div class="col-auto">
                                            <?php if ($transaction['transaction_type'] == 'transfer received') { ?>
                                                <i class="fas fa-university text-success transf"></i>
                                            <?php } ?>
                                            <?php if ($transaction['transaction_type'] == 'Transfer sent') { ?>
                                                <i class="fas fa-arrow-up iconHist2 transf"></i>
                                            <?php } ?>
                                            <?php if ($transaction['transaction_type'] == 'Refund received') { ?>
                                                <i class="fas fa-sync-alt iconHist transf"></i>
                                            <?php } ?>
                                        </div>
                                        <div class="col-auto">
                                            <strong class="transftype"><?= $transaction['transaction_type'] ?></strong><br>
                                            <small class="transftype"><?= $transaction['description'] ?></small>
                                        </div>
                                        <div class="flex-grow-1"></div>
                                        <div class="col-auto text-end">
                                            <?php if ($transaction['transaction_type'] == 'Transfer sent') { ?>
                                                <div>
                                                    <strong class="text-danger transftype">- <?= $formatted_trans ?> <?= $transaction['devise'] ?></strong><br>
                                                    <small class="transftyped"><?= $transaction['created_at'] ?></small>
                                                </div>
                                            <?php } ?>
                                            <?php if ($transaction['transaction_type'] == 'Refund received') { ?>
                                                <div>
                                                    <strong class="text-success transftype">+ <?= $formatted_trans ?> <?= $transaction['devise'] ?></strong><br>
                                                    <small class="transftyped"><?= $transaction['created_at'] ?></small>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </li>
                                </ul>
                            <?php endforeach; ?>

                    </ul>

                <?php endif; ?>

                <ul class="list-group">
                    <li class="list-group-item d-flex flex-nowrap align-items-center">
                        <div class="col-auto">
                            <i class="fas fa-university iconHist3"></i>
                        </div>
                        <div class="col-auto">
                            <strong class="transftype">transfer received</strong><br>
                            <small class="transftype">TRANSAFRICASH</small>
                        </div>
                        <div class="flex-grow-1"></div>
                        <div class="col-auto text-end">
                            <strong class="text-success transftype"> + <?= $_SESSION['utilisateur_connecter']['account_balance2'] . ' ' . $_SESSION['utilisateur_connecter']['devise'] ?></strong><br>
                            <small class='transftyped '><?= $_SESSION['utilisateur_connecter']['created_at'] ?></small>
                        </div>
                    </li>
                </ul>
            </div>

        </div>
        <footer class="cards mt-5">

            <a href="index.php?page=show" class=" " style="text-decoration: none; border-bottom: 2px #007bff solid;">
                <i class=" fs-4  fas fa-coins"></i>
                <div class="">Solde</div>
            </a>
            <a href="index.php?page=carte" class="" style="text-decoration: none; ">
                <i class="fs-4 fas fa-credit-card"></i>
                <div class=" ">Ma carte</div>
            </a>
            <a href=" index.php?page=transfert" class="" style="text-decoration: none; ">
                <i class="fs-4 fas fa-exchange-alt"></i>
                <div class=" ">Transfert</div>
            </a>
            <a href="index.php?page=info" class="" style="text-decoration: none;">
                <i class="fs-4 fas fa-user"></i>
                <div class=" ">Mon compte</div>
            </a>
        </footer>

        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
        <div class="col-md-2"></div>

    </div>
</body>


</html>