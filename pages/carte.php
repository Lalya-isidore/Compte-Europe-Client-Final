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

// Ajoutez deux ans à la date de création du compte
$created_at = $utilisateur['created_at']; // Assume that this is in 'Y-m-d H:i:s' format
$date = new DateTime($created_at);
$date->add(new DateInterval('P2Y'));
$futureDate = $date->format('m/Y');

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
        <div class="alert alert-info">
            Félicitations, votre carte de débit est disponible. Avant toute usage, vous devez activer votre carte pour
            accélérer le transfert des fonds crédité sur votre compte.
        </div>
        <div class="row">

            <div class="col-md-6">
                <div class="card-custom">
                    <div class="card-number fw-bold"><?= $utilisateur['card_number'] ?>
                    </div>
                    <div class="card-name fw-bold"><?= $utilisateur['nom'] . ' ' . $utilisateur['prenom'] ?></div>

                    <div class="row">
                        <div class="col-md-8 fw-bold">
                            <div class="card-expiry ">VALIDE JUSQU'AU : <?= $futureDate ?> </div>
                        </div>
                        <div class="col-md-4">
                            <i class="card-cvv d-flex justify-content-end fw-bold">CVV: <?= $utilisateur['cvv'] ?> </i>
                        </div>
                    </div>
                    <i class="card-visa fs-2 fw-bold d-flex justify-content-end"> VISA</i>
                </div>
            </div>
            <div class="col-md-6 ">
                <button class="btn btn-success" data-toggle="modal" data-target="#carteActive">Activer ma carte</button>
                <button class="btn btn-danger" data-toggle="modal" data-target="#carteBloque">Bloquer ma carte</button>
            </div>
        </div>
        <!-- Modal active la carte -->
        <div class="modal fade" id="carteActive" tabindex="-1" role="dialog" aria-labelledby="carteActive" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-danger">
                        <h5 class="modal-title color-red" id="failureModalLabel" style="color: #f3f3f3;"><i class="fas fa-exclamation-triangle"></i>
                            Alert</h5>
                        <button type="button" class="close bg-danger fs-3" style="color: #f3f3f3; border:0px; " data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>L’activation de votre carte de débit n’est pas disponible pour des raisons de sécurité, veuillez réessayer plus tard...</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal bloqué la carte -->
        <div class="modal fade" id="carteBloque" tabindex="-1" role="dialog" aria-labelledby="carteBloque" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-danger">
                        <h5 class="modal-title color-red" id="failureModalLabel" style="color: #f3f3f3;"><i class="fas fa-exclamation-triangle"></i>
                            Alert</h5>
                        <button type="button" class="close bg-danger fs-3" style="color: #f3f3f3; border:0px; " data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Cette action n’est pas autorisée, veuillez d’abord activer votre carte de débit.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <h5>Transaction(s) par carte</h5>
            <div class="card">


                <div class="loader"></div>
            </div>
        </div>

        <footer class="cards mt-5">

            <a href="index.php?page=show" class=" " style="text-decoration: none; ">
                <i class=" fs-4  fas fa-coins"></i>
                <div class="">Solde</div>
            </a>
            <a href="index.php?page=carte" class="" style="text-decoration: none; border-bottom: 2px #007bff solid;">
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
    </div>
    <!-- <div class="col-md-2"></div> -->

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    </div>
    </div>
</body>

</html>