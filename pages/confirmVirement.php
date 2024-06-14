<?php

$erreurs = [];
$donnees = [];
$success = '';
$erreur = '';

// Récupérer les données du formulaire
$numerocompte = $_POST['numerocompte'];
$name_servieur = $_POST['name_servieur'];
$beneficiary_name = $_POST['beneficiary_name'];
$reason = $_POST['reason'];


?>

<script>
    function validateCode(event) {
        event.preventDefault();
        const codeVirement = document.querySelector('input[name="codeVirement"]').value;
        const errorMessage = document.getElementById('error-message');
        const form = document.getElementById('virement-form');

        // Envoyer une requête AJAX pour valider le code
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'validate_code.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    form.submit(); // Soumettre le formulaire si le code est correct
                } else {
                    errorMessage.textContent = 'Code incorrect';
                    errorMessage.style.display = 'block';
                }
            }
        };
        xhr.send('codeVirement=' + encodeURIComponent(codeVirement));
    }
</script>

<body>
    <div class="container">
        <div class="dashboard">
            <h2 class="text-info">Confirmation du Virement</h2>
            <form id="virement-form" action="index.php?page=virementDetail" method="post" onsubmit="validateCode(event)">
                <input type="hidden" name="numerocompte" value="<?php echo htmlspecialchars($numerocompte); ?>">
                <input type="hidden" name="name_servieur" value="<?php echo htmlspecialchars($name_servieur); ?>">
                <input type="hidden" name="beneficiary_name" value="<?php echo htmlspecialchars($beneficiary_name); ?>">
                <input type="hidden" name="reason" value="<?php echo htmlspecialchars($reason); ?>">

                <div class="card mt-4 shadow">
                    <div class="card-body">
                        <p><strong>Numéro de Compte :</strong> <?php echo htmlspecialchars($numerocompte); ?></p>
                        <p><strong>Nom du serveur :</strong> <?php echo htmlspecialchars($name_servieur); ?></p>
                        <p><strong>Nom du bénéficiaire :</strong> <?php echo htmlspecialchars($beneficiary_name); ?></p>
                        <p><strong>Motif :</strong> <?php echo htmlspecialchars($reason); ?></p>
                    </div>
                </div>
                <h4 class="mt-2">Code de sécurité</h4>
                <input type="text" class="form-control" name="codeVirement" placeholder="Code de sécurité" required>
                <div id="error-message" class="text-danger mt-2" style="display:none;"></div>

                <button type="submit" class="btn btn-primary mt-4">Confirmer le Virement</button>
            </form>
        </div>
    </div>
</body>

</html>