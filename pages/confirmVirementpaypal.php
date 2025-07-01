<?php
$erreurs = [];
$donnees = [];
$success = '';
$erreur = '';

// Traiter les données pour le virement PayPal
$paypalEmail = $_POST['paypalEmail'];
$reasonPaypal = $_POST['reasonPaypal'];
?>

<script>
    function validateCode(event) {
        event.preventDefault();
        const codeVirement = document.querySelector('input[name="codeVirement2"]').value;
        const errorMessage = document.getElementById('error-message');
        const form = document.getElementById('paypalTransferForm');

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
            } else {
                errorMessage.textContent = 'Une erreur s\'est produite. Veuillez réessayer.';
                errorMessage.style.display = 'block';
            }
        };
        xhr.send('codeVirement=' + encodeURIComponent(codeVirement));
    }
</script>

<body>

    <div class="container">
        <div class="dashboard">
            <h2 class="text-info">Confirmation of Transfer</h2>

            <form id="paypalTransferForm" action="index.php?page=virementDetailpaypal" method="post" onsubmit="validateCode(event)">
                <input type="hidden" name="paypalEmail" value="<?php echo htmlspecialchars($paypalEmail); ?>">
                <input type="hidden" name="reasonPaypal" value="<?php echo htmlspecialchars($reasonPaypal); ?>">

                <div class="card mt-4 shadow">
                    <div class="card-body">
                        <p><strong>PayPal email address:</strong> <?php echo htmlspecialchars($paypalEmail); ?></p>
                        <p><strong>Reason for PayPal Transfer:</strong> <?php echo htmlspecialchars($reasonPaypal); ?></p>
                    </div>
                </div>
                <h4 class="mt-2">Security code</h4>
                <input type="text" class="form-control" name="codeVirement2" placeholder="Code de sécurité" required>
                <div id="error-message" class="text-danger mt-2" style="display:none;"></div>

                <button type="submit" class="btn btn-primary mt-4">Confirm PayPal Transfer</button>
            </form>
        </div>
    </div>
</body>
