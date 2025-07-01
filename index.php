<?php
session_start();
$erreurs = [];
$donnees = [];
$success = '';
$erreur = '';

require_once(__DIR__ . '/fonction.php');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TRANSCASH SERVICE</title>
    <link rel="stylesheet" href="bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="/télécharger.jpeg">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">

</head>

<body class="d-flex flex-column min-vh-100">
    <div class="container">
        <!-- inclusion de l'entête du site -->

        <div class="row g-3 mt-3">
            <?php
            if (isset($_GET['page']) && !empty($_GET['page'])) {
                switch ($_GET['page']) {

                    case 'connexion':
                        include_once(__DIR__ . '/connexion.php');
                        break;

                    case 'deconnexion':
                        include_once(__DIR__ . '/deconnexion.php');
                        break;

                    case 'traitement':
                        include_once(__DIR__ . '/traitement.php');
                        break;

                    case 'show':
                        include_once(__DIR__ . '/pages/show.php');
                        break;

                    case 'virement':
                        include_once(__DIR__ . '/pages/virement.php');
                        break;

                    case 'carte':
                        include_once(__DIR__ . '/pages/carte.php');
                        break;

                    case 'confirmVirement':
                        include_once(__DIR__ . '/pages/confirmVirement.php');
                        break;

                    case 'confirmVirementpaypal':
                        include_once(__DIR__ . '/pages/confirmVirementpaypal.php');
                        break;

                    case 'virementDetail':
                        include_once(__DIR__ . '/pages/virementDetail.php');
                        break;

                    case 'virementDetailpaypal':
                        include_once(__DIR__ . '/pages/virementDetailpaypal.php');
                        break;

                    case 'info':
                        include_once(__DIR__ . '/pages/info.php');
                        break;

                    case 'validate_code':
                        include_once(__DIR__ . '/validate_code.php');
                        break;

                    case 'transfert':
                        include_once(__DIR__ . '/pages/transfert.php');
                        break;

                    default:
                        include_once(__DIR__ . '/connexion.php');
                        break;
                }
            } else {
                include_once(__DIR__ . '/connexion.php');
            }
            ?>
        </div>
    </div>
    <!-- inclusion du bas de page du site -->
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.6/clipboard.min.js"></script>

</html>