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
            <div><i class="fas fa-bars menu-icon"></i> <strong class="fs-4">TRANSFER</strong></div>
            <a href="{{route('info')}}" class="icon-circle">
                <i class="fas fa-user"></i>
            </a>

        </nav>
        <hr>

        <div class="alert alert-success">
            A transfer of <strong class="fw-bold">
                <?= number_format($_SESSION['utilisateur_connecter']['account_balance2'], 2, ',', ' ') . ' '
                    . $_SESSION['utilisateur_connecter']['devise'] ?></strong>
           received and credited to your account. You can add your <strong>IBAN</strong>
            in order to make an external transfer to your <strong>Bank</strong>.
        </div>

        <div class=" my-2">

            <p> Hello <?= $_SESSION['utilisateur_connecter']['nom'] . " " . $_SESSION['utilisateur_connecter']['prenom']; ?></p>
            <div class="account-balance">
                <div>
                    <p><i class="fas fa-coins"></i> Account balance :</p>
                </div>
                <h2 class="fw-bold mx-3 fs-4 mb-4 balance"><?= $formatted_balance . " " . $_SESSION['utilisateur_connecter']['devise']; ?></h2>
                <div class='d-flex flex-nowrap align-items-center '>
                    <a href="index.php?page=transfert" class="btn btn-warning virement btnaccc ">Make a Transfer <i class=" fas fa-arrow-right"></i></a>
                    <a href="index.php?page=carte" class="btn btn-success carte btnaccc mx-3 ">My card <i class=" fas fa-arrow-right"></i></a>
                </div>
            </div>

            <style>
                .list-group-item{
                    white-space: nowrap;
                    flex-wrap: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                /* Include your custom CSS here */
                .transaction-history {
                    margin-top: 20px;
                }

                .list-group-item {
                    padding: 15px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }

                .icon-container {
                    font-size: 2vw;
                    /* Adjusts icon size based on viewport width */
                }

                .transaction-details {
                    width: 100%;
                }

                .transaction-details .d-flex {
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .transaction-details strong,
                .transaction-details small {
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .transaction-details small {
                    max-width: 50%;
                }

                .transaction-details strong {
                    font-size: 1.5vw;
                }

                .transaction-details small {
                    font-size: 1.2vw;
                }

                .icon-container+.transaction-details {
                    display: flex;
                    flex-direction: column;
                }

                @media (max-width: 1200px) {
                     .list-group-item{
                   
                    flex-wrap: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                    .transaction-details .d-flex {
                   
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                    
                    .icon-container {
                        font-size: 3vw;
                    }

                    .transaction-details strong {
                        font-size: 2vw;
                    }

                    .transaction-details small {
                        font-size: 1.6vw;
                    }
                }

                @media (max-width: 992px) {
                     .list-group-item{
                   
                    flex-wrap: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                    .icon-container {
                        font-size: 4vw;
                    }

                    .transaction-details strong {
                        font-size: 2.5vw;
                    }

                    .transaction-details small {
                        font-size: 2vw;
                    }
                    .transaction-details .d-flex {
                   
                    overflow: hidden;
                    text-overflow: ellipsis;
                  }
                }

                @media (max-width: 768px) {
                     .list-group-item{
                    
                    flex-wrap: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                    .icon-container {
                        font-size: 5vw;
                    }

                    .transaction-details strong {
                        font-size: 3vw;
                    }

                    .transaction-details small {
                        font-size: 2.5vw;
                    }
                    .transaction-details .d-flex {
                  
                    overflow: hidden;
                    text-overflow: ellipsis;
                    }
                }

                @media (max-width: 576px) {
                     .list-group-item{
                   
                    flex-wrap: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    }
                    .icon-container {
                        font-size: 6vw;
                    }

                    .transaction-details strong {
                        font-size: 3.5vw;
                    }

                    .transaction-details small {
                        font-size: 3vw;
                    }
                    .transaction-details .d-flex {
                   
                    overflow: hidden;
                    text-overflow: ellipsis;
                    }
                     .icon-container {
                    margin-right: 0px; /* Marge à droite réduite */
                    margin-left: -12px; /* Marge à gauche réduite */
                    }
}
                }

                .transaction-details .d-flex {
                    justify-content: space-between;
                }  
.iconHist {
    padding: 1rem;
    background-color: #ccebf5;
    border-radius: 50%;
    font-size: 1.7rem;
    margin-right: 5px;
    object-fit: cover;
}

.iconHist2 {
    padding: 1rem;
    background-color: red;
    border-radius: 50%;
    font-size: 1.7rem;
    margin-right: 5px;
    color: #f0f0f0;
    object-fit: cover;
}

.iconHist3 {
    padding: 1rem;
    background-color: green;
    border-radius: 50%;
    font-size: 1.7rem;
    margin-right: 5px;
    color: white;
    object-fit: cover;
}
                
                
            </style>
            </head>

            <body>

                <div class="transaction-history">
                    <h4>Transaction history</h4>
                    <ul class="list-group">
                        <?php if (!empty($historique_transactions)) : ?>
                            <?php foreach ($historique_transactions as $transaction) :
                                $amount = $transaction['amount'];
                                $formatted_trans = number_format($amount, 2, ',', ' ');
                            ?>
                                <li class="list-group-item">
                                    <!-- Icône de transaction -->
                                    <div class="icon-container col-md-1">
                                        <?php if ($transaction['transaction_type'] == 'transfer received') { ?>
                                            <i class="fas fa-university text-success transf"></i>
                                        <?php } ?>
                                        <?php if ($transaction['transaction_type'] == 'Transfer sent') { ?>
                                            <i class="fas fa-arrow-up iconHist2 transf"></i>
                                        <?php } ?>
                                        <?php if ($transaction['transaction_type'] == 'Refund received') { ?>
                                            <i class="fas fa-sync-alt iconHist transf"></i>
                                        <?php } ?>
                                        <?php if ($transaction['transaction_type'] == 'Funds deducted') { ?>
                                            <i class="fas fa-arrow-left iconHist2 transf"></i>
                                        <?php } ?>
                                        <?php if ($transaction['transaction_type'] == 'Funds added') { ?>
                                            <i class="fas fa-university iconHist3 transf"></i>
                                        <?php } ?>
                                    </div>

                                    <!-- Détails de la transaction -->
                                    <div class="transaction-details ">
                                        <div class="d-flex justify-content-between align-items-center flex-nowrap">
                                            <strong><?= $transaction['transaction_type'] ?></strong>
                                            <strong class="<?= $transaction['transaction_type'] == 'Transfer sent' || $transaction['transaction_type'] == 'Funds deducted' ? 'text-danger' : 'text-success' ?>">
                                                <?= ($transaction['transaction_type'] == 'Transfer sent' || $transaction['transaction_type'] == 'Funds deducted') ? '-' : '+' ?> <?= $formatted_trans ?> <?= $transaction['devise'] ?>
                                            </strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center flex-nowrap">
                                            <small><?= $transaction['transaction_type'] == 'Transfer sent' ? $transaction['description'] : "Bank"  ?></small>
                                            <small><?= $transaction['created_at'] ?></small>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Exemple de transaction fixe -->
                        <li class="list-group-item">
                            <div class="icon-container">
                                <i class="fas fa-university iconHist3"></i>
                            </div>
                            <div class="transaction-details" >
                                <div class="d-flex justify-content-between align-items-center flex-nowrap">
                                    <strong style="">Transfer received</strong>
                                    <strong style="" class="text-success">+ <?= $_SESSION['utilisateur_connecter']['account_balance2'] . ' ' . $_SESSION['utilisateur_connecter']['devise'] ?></strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center flex-nowrap">
                                    <small style="">BANK</small>
                                    <small style=""><?= $_SESSION['utilisateur_connecter']['created_at'] ?></small>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>


        </div>


        <footer class="cards mt-5">

            <a href="index.php?page=show" class=" " style="text-decoration: none; border-bottom: 2px #007bff solid;">
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
            <a href="index.php?page=info" class="" style="text-decoration: none;">
                <i class="fs-4 fas fa-user"></i>
                <div class=" ">My account</div>
            </a>
        </footer>

        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
        <div class="col-md-2"></div>

    </div>
</body>


</html>