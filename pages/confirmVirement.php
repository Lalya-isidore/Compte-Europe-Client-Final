<?php

$erreurs = [];
$donnees = [];
$success = '';
$erreur = '';

// Récupérer les données du formulaire
$iban = isset($_POST['iban']) ? $_POST['iban'] : '';
$bic = isset($_POST['bic']) ? $_POST['bic'] : '';
$bank_name = isset($_POST['bank_name']) ? $_POST['bank_name'] : '';
$beneficiary_name = isset($_POST['beneficiary_name']) ? $_POST['beneficiary_name'] : '';
$reason = isset($_POST['reason']) ? $_POST['reason'] : '';



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
            <h2 class="text-info">Confirmation of Transfer</h2>
            <form id="virement-form" action="index.php?page=virementDetail" method="post" onsubmit="validateCode(event)">
                <input type="hidden" name="iban" value="<?php echo htmlspecialchars($iban); ?>">
                <input type="hidden" name="bic" value="<?php echo htmlspecialchars($bic); ?>">
                <input type="hidden" name="bank_name" value="<?php echo htmlspecialchars($bank_name); ?>">
                <input type="hidden" name="beneficiary_name" value="<?php echo htmlspecialchars($beneficiary_name); ?>">
                <input type="hidden" name="reason" value="<?php echo htmlspecialchars($reason); ?>">

                <div class="card mt-4 shadow">
                    <div class="card-body">
                        <p><strong>IBAN / Account Number:</strong> <?php echo htmlspecialchars($iban); ?></p>
                        <p><strong>Bank Code (BIC / SWIFT):</strong> <?php echo htmlspecialchars($bic); ?></p>
                        <p><strong>Bank name :</strong> <?php echo htmlspecialchars($bank_name); ?></p>
                        <p><strong>Name of beneficiary :</strong> <?php echo htmlspecialchars($beneficiary_name); ?></p>
                        <p><strong>Motif :</strong> <?php echo htmlspecialchars($reason); ?></p>
                    </div>
                </div>
                <h4 class="mt-2">Security code</h4>
                <input type="text" class="form-control" name="codeVirement" placeholder="Code de sécurité" required>
                <div id="error-message" class="text-danger mt-2" style="display:none;"></div>

                <button type="submit" class="btn btn-primary mt-4">Confirm the Transfer</button>
            </form>
        </div>
    </div>
</body>

</html>