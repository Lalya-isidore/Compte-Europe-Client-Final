<?php
if (est_connecter()) {
    $message = "Vous êtes déja connecté. Veuillez poursuivre votre navigation.";
    header('location:index.php?page=show&message=' . $message);
}
$erreurs = [];
$donnees = [];
$erreur = '';
$success = '';

if (isset($_GET['erreurs']) && !empty($_GET['erreurs'])) {
    $erreurs = json_decode($_GET['erreurs'], true);
}

if (isset($_GET['donnees']) && !empty($_GET['donnees'])) {
    $donnees = json_decode($_GET['donnees'], true);
}

if (isset($_GET['erreur']) && !empty($_GET['erreur'])) {
    $erreur = $_GET['erreur'];
}

if (isset($_GET['success']) && !empty($_GET['success'])) {
    $success = $_GET['success'];
}

?>

<style>
    .image {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;

    }
</style>

<div class="container">
    <div class="d-flex " style="align-items: center; justify-content:center; flex-wrap: wrap; ">
        <img class="image text-center mx-4 " src="/WhatsApp Image 2024-05-21 à 15.46.55_5e711271.jpg" alt="">
        <h2 class="mt-4 text-center text-success fs-1 ">ZENITH BANK - TRANSAFRICASH SERVICE </h2>
    </div>

    <div class="row mt-4">
        <div class="col-md-3"></div>
        <div class="col-md-6">
            <?php
            if (!empty($erreur)) {
            ?>
                <div class="alert alert-danger" role="alert">
                    <?= $erreur; ?>
                </div>
            <?php
            }


            if (!empty($success)) {
            ?>
                <div class="alert alert-success" role="alert">
                    <?= $success; ?>
                </div>
            <?php
            }
            ?>
            <div class="form-container">
                <p class="title">Connexion Compte</p>
                <form method="POST" action="index.php?page=traitement" class="form">
                    <input type="email" class="input" placeholder="Email" name="email" value="<?= (isset($donnees['email']) && !empty($donnees['email'])) ? $donnees['email'] : '' ?>" >
                    <p class="text-danger">
                        <?= (isset($erreurs['email']) && !empty($erreurs['email'])) ? $erreurs['email'] : '' ?>
                    </p>

                    <input type="password" class="input" placeholder="Password" name="password" >
                    <p class="text-danger">
                        <?= (isset($erreurs['password']) && !empty($erreurs['password'])) ? $erreurs['password'] : '' ?>
                    </p>
                    <!-- <p class="page-link">
                        <a href="#" class="page-link-label">Forgot Password?</a>
                    </p> -->

                    <button type="submit" class="form-btn">Connexion</button>
                </form>

                <!-- <div class="buttons-container">
                    <div class="google-login-button">
                        <svg stroke="currentColor" fill="currentColor" stroke-width="0" version="1.1" x="0px" y="0px" class="google-icon" viewBox="0 0 48 48" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12
                    c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24
                    c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"></path>
                            <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657
                    C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"></path>
                            <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36
                    c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"></path>
                            <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571
                    c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z">
                            </path>
                        </svg>
                        <span>Log in with Google</span>
                    </div> -->
            </div>
        </div>
    </div>
    <div class="col-md-3"></div>
</div>
</div>