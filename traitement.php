<?php
session_start();
require_once('fonction.php');

$erreurs = [];
$donnees = [];
$success = '';
$erreur = '';

$db = connexion_db();

foreach ($_POST as $cle => $valeur) {
    $donnees[$cle] = strip_tags($valeur);
}

if (empty($donnees['email'])) {
    $erreurs['email'] = 'Le champ adresse email est obligatoire.';
} elseif (!filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)) {
    $erreurs['email'] = 'Le champ adresse email doit être une adresse mail valide.';
}

if (empty($donnees['password'])) {
    $erreurs['password'] = 'Le champ mot de passe est obligatoire.';
} 

if (!empty($erreurs)) {
    $erreur = "Oups!!! Un ou plusieurs champs sont vide(s) ou mal(s) renseigné(s).";
} else {
    $utilisateur = chercher_utilisateur_par_son_email($donnees['email']);
    if ($utilisateur && ($donnees['password']== $utilisateur['password'])) {
        $success = "Bravo!!! Vous êtes authentifié.";
        unset($utilisateur['password']);
        $_SESSION['utilisateur_connecter'] = $utilisateur;
    } else {
        $erreur = "Oups!!! Adresse mail ou mot de passe incorrect.";
    }
}

header('location: index.php?page=connexion&erreur=' . $erreur . '&erreurs=' . json_encode($erreurs)   . '&donnees=' . json_encode($donnees) . '&success=' . $success);

exit();
?>
