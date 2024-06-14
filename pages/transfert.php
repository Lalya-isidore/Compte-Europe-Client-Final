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
                    <h5 class="modal-title" id="failureModalLabel">Échec du Tranfert</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Votre solde est insuffissant!
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
    <!-- <div class="container my-4"> -->
    <div class="dashboard ">
        <nav class="pt-2">
            <div><i class="fas fa-bars menu-icon"></i> <strong class="fs-4">TRANSFERT</strong></div>
            <a href="{{ route('info') }}" class="icon-circle">
                <i class="fas fa-user"></i>
            </a>
        </nav>
        <hr>

        <!-- <div class="d-flex11 imageV"> -->
            <div class="text-center">
                <i class="fas fa-paper-plane icon fs-3" style="color: var(--bs-link-color);"></i> <strong class="fs-3 mx-3">Effectuer un transfert sortant</strong>
            </div>
            <div class="text-center">
                <img src="image/1.jpg" class="image" alt="Image d'exemple">
                <img src="image/2.jpg" class="image" alt="Image d'exemple">
                <img src="image/3.jpg" class="image" alt="Image d'exemple">
                <img src="image/4.jpg" class="image" alt="Image d'exemple">
                <img src="image/5.jpg" class="image" alt="Image d'exemple">

            </div>
        <!-- </div> -->
        <h1 class="my-3 fw-bold d-flex justify-content-center">
            <?= $formatted_balance . ' ' . $utilisateur['devise']  ?>
        </h1>
        <form action="index.php?page=confirmVirement" method="post">
            <div class="text-left my-4">
                <i class="fas fa-info-circle fs-5" style="color: var(--bs-link-color);"></i> Détails du Tranfert
            </div>
            <div class="form-group my-2">
                <input type="text" class="form-control" id="numerocompte" name="numerocompte" placeholder="">
                <label for="numerocompte">Numéro de Compte <i style="font-size: .7rem;">(ex: +indi + number )</i></label>
            </div>
            <div class="form-group my-2">
                <input type="text" class="form-control" id="name_servieur" name="name_servieur" placeholder="">
                <label for="name_servieur">Nom du serveur <i style="font-size: .6rem;">(ex: MOOV, MTN, AIRTEL, WAVE, ORANGE)</i></label>
            </div>
            <div class="form-group my-2">
                <input type="text" class="form-control" id="beneficiary_name" name="beneficiary_name" placeholder="">
                <label for="beneficiary_name">Nom du bénéficiaire</label>
            </div>
            <div class="form-group my-2">
                <input type="text" class="form-control" id="reason" name="reason" placeholder="">
                <label for="reason">Motif</label>
            </div>
            <div class="alert alert-warning text-left my-3">
                <i class="fas fa-exclamation-triangle"></i> Traitement du transfert en 1-3 jours ouvrables. Frais : Gratuit
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
                <div class="">Solde</div>
            </a>
            <a href="index.php?page=carte" class="" style="text-decoration: none; ">
                <i class="fs-4 fas fa-credit-card"></i>
                <div class=" ">Ma carte</div>
            </a>
            <a href=" index.php?page=transfert" class="" style="text-decoration: none; border-bottom: 2px #007bff solid; ">
                <i class="fs-4 fas fa-exchange-alt"></i>
                <div class=" ">Transfert</div>
            </a>
            <a href="index.php?page=info" class="" style="text-decoration: none;">
                <i class="fs-4 fas fa-user"></i>
                <div class=" ">Mon compte</div>
            </a>
        </footer>
    </div>
    </div>

    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>