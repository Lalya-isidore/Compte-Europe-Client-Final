<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('fonction.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=transfert');
    exit;
}

// Détection du type de transfert
$transfer_method = trim((string)($_POST['method'] ?? 'bank')); // 'mobilemoney' ou 'bank'

// Tableau des opérateurs Mobile Money pour récupérer le nom et le code
$mobileMoneyOperators = [
    'mtn' => [
        'name' => 'MTN Money',
        'code' => 'MTN'
    ],
    'moov' => [
        'name' => 'Moov Money',
        'code' => 'MOOV'
    ],
    'orange' => [
        'name' => 'Orange Money',
        'code' => 'ORANGE'
    ],
    'wave' => [
        'name' => 'Wave',
        'code' => 'WAVE'
    ],
    'airtel' => [
        'name' => 'Airtel Money',
        'code' => 'AIRTEL'
    ],
    'mvola' => [
        'name' => 'MVola',
        'code' => 'MVOLA'
    ],
    'mpesa' => [
        'name' => 'M-Pesa',
        'code' => 'MPESA'
    ]
];

// Si c'est un transfert Mobile Money
if ($transfer_method === 'mobilemoney') {
    $operator_key = trim((string)($_POST['operator'] ?? ''));
    $nom = trim((string)($_POST['nom'] ?? ''));
    $prenom = trim((string)($_POST['prenom'] ?? ''));
    $mobile_number = trim((string)($_POST['mobile_number'] ?? ''));
    $rawReason = trim((string)($_POST['reason'] ?? ''));

    // Mapper les données Mobile Money aux variables existantes
    $beneficiary_name = htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8');
    $bank_name = htmlspecialchars($mobileMoneyOperators[$operator_key]['name'] ?? '—', ENT_QUOTES, 'UTF-8');
    $iban = htmlspecialchars($mobile_number, ENT_QUOTES, 'UTF-8');
    $bic = htmlspecialchars($mobileMoneyOperators[$operator_key]['code'] ?? '—', ENT_QUOTES, 'UTF-8');
    $reason = htmlspecialchars($rawReason, ENT_QUOTES, 'UTF-8');

    // Labels et icônes adaptés pour Mobile Money
    $label_iban = 'Numéro Mobile Money';
    $icon_iban = 'fas fa-mobile-alt';
    $label_bic = 'Code opérateur';
    $icon_bic = 'fas fa-code';
    $label_bank = 'Opérateur';
    $icon_bank = 'fas fa-signal';
} else {
    // Transfert bancaire classique
    $rawIban = trim((string)($_POST['iban'] ?? ''));
    $rawBic = trim((string)($_POST['bic'] ?? ''));
    $rawBankName = trim((string)($_POST['bank_name'] ?? ''));
    $rawBeneficiary = trim((string)($_POST['beneficiary_name'] ?? ''));
    $rawReason = trim((string)($_POST['reason'] ?? ''));

    $iban = htmlspecialchars($rawIban, ENT_QUOTES, 'UTF-8');
    $bic = htmlspecialchars($rawBic, ENT_QUOTES, 'UTF-8');
    $bank_name = htmlspecialchars($rawBankName, ENT_QUOTES, 'UTF-8');
    $beneficiary_name = htmlspecialchars($rawBeneficiary, ENT_QUOTES, 'UTF-8');
    $reason = htmlspecialchars($rawReason, ENT_QUOTES, 'UTF-8');

    // Labels et icônes pour transfert bancaire
    $label_iban = 'IBAN';
    $icon_iban = 'fas fa-credit-card';
    $label_bic = 'BIC';
    $icon_bic = 'fas fa-code';
    $label_bank = 'Banque';
    $icon_bank = 'fas fa-building';
}

$sessionUser = $_SESSION['utilisateur_connecter'] ?? [];
$compte_id = $sessionUser['compte_id'] ?? null;

$compte = [];
if ($compte_id) {
    $db = connexion_db();
    $stmt = $db->prepare('SELECT account_balance, devise FROM comptes WHERE id = ?');
    $stmt->execute([$compte_id]);
    $compte = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

$account_balance = isset($compte['account_balance']) ? (float)$compte['account_balance'] : 0.0;
$formatted_balance = number_format($account_balance, 2, ',', ' ');
$devise = htmlspecialchars($compte['devise'] ?? 'EUR', ENT_QUOTES, 'UTF-8');
$reason_display = $reason !== '' ? $reason : t('not_provided');

$photoUrl = null;
if (!empty($sessionUser)) {
    $photoUrl = getUserPhotoUrl($sessionUser);
}
if ($photoUrl === null && $compte_id) {
    $userDetails = getUserDetails($compte_id);
    if (is_array($userDetails)) {
        $photoUrl = getUserPhotoUrl($userDetails);
    }
}
?>
<div class="dashboard">
    <nav class="pt-2 d-flex justify-content-between align-items-center">
        <div><i class="fas fa-bars menu-icon"></i> <strong class="fs-4">TRANSFERFLUX</strong></div>
        <a href="index.php?page=info" class="icon-circle d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;">
            <?php if (!empty($photoUrl)): ?>
                <img src="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="avatar" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid rgba(0,0,0,0.06);">
            <?php else: ?>
                <i class="fas fa-user"></i>
            <?php endif; ?>
        </a>
    </nav>

    <style>
        .confirm-section {
            --primary-color: #6b48e7;
            --success-gradient: linear-gradient(135deg, #0f9d58 0%, #34a853 100%);
            --card-shadow: 0 8px 30px rgba(107, 72, 231, 0.12);
            --hover-shadow: 0 12px 40px rgba(107, 72, 231, 0.18);
            --glass-bg: rgba(255, 255, 255, 0.88);
            --glass-border: rgba(255, 255, 255, 0.45);
        }

        .confirm-section .premium-header {
            background: var(--primary-color);
            color: #fff;
            padding: 1.5rem 1rem;
            margin-top: 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 10px 30px rgba(107, 72, 231, 0.2);
        }

        .confirm-section .balance-label {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-bottom: 0.5rem;
        }

        .confirm-section .balance-display {
            font-size: 2.4rem;
            font-weight: 700;
            margin: 0;
        }

        .stepper {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .step {
            display: flex;
            align-items: center;
            color: #a0aec0;
            font-weight: 500;
        }

        .step-number {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .step.active {
            color: var(--primary-color);
        }

        .step.active .step-number {
            background: var(--primary-color);
            color: #fff;
            transform: scale(1.05);
        }

        .step.completed {
            color: #0f9d58;
        }

        .step.completed .step-number {
            background: var(--success-gradient);
            color: #fff;
        }

        .step-connector {
            width: 60px;
            height: 2px;
            background: #e2e8f0;
        }

        .confirm-grid {
            margin: 0 auto;
            max-width: 900px;
        }

        .confirm-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            padding: 1.75rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            backdrop-filter: blur(12px);
        }

        .confirm-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--hover-shadow);
        }

        /* Ensure consistent site font and alert sizing */
        body { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .alert-modern, .alert-premium { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .alert-title { font-size: 1.05rem; font-weight: 700; }
        .alert-message { font-size: 1.00rem; line-height: 1.5; font-weight: 500; }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            color: #1f2937;
        }

        .detail-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 0.75rem;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            border-bottom: 1px dashed rgba(148, 163, 184, 0.4);
            padding-bottom: 0.75rem;
        }

        .detail-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .detail-label {
            color: #64748b;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-value {
            color: #1f2937;
            font-weight: 600;
            text-align: right;
        }

        .amount-highlight {
            color: var(--primary-color);
            font-weight: 800;
        }

        .code-intro {
            color: #718096;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        .code-input {
            width: 100%;
            padding: 14px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: 12px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .code-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(107, 72, 231, 0.15);
        }

        .btn-premium {
            background: var(--primary-color);
            border: none;
            color: #fff;
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 6px 18px rgba(107, 72, 231, 0.35);
            transition: all 0.3s ease;
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            background: #5b39c9 !important;
            box-shadow: 0 10px 26px rgba(107, 72, 231, 0.45);
            color: #fff !important;
        }

        .btn-premium:active {
            transform: translateY(0);
        }

        .error-banner {
            display: none;
            margin-top: 1rem;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            background: rgba(217, 48, 37, 0.1);
            border: 1px solid rgba(217, 48, 37, 0.25);
            color: #d93025;
            font-weight: 600;
        }

        .error-banner.show {
            display: block;
        }

        .menu-icon {
            margin-right: 0.75rem;
        }

        .animate-in {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(24px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .premium-header {
                margin: 0 -16px 20px -16px;
                border-radius: 0 0 20px 20px;
                padding: 1.5rem 1.25rem;
            }

            .balance-display {
                font-size: 32px;
                font-weight: 700;
            }

            .stepper {
                padding: 16px 0;
                margin-bottom: 20px;
                gap: 12px;
            }

            .step {
                font-size: 14px;
            }

            .step-number {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }

            .step-connector {
                width: 40px;
            }

            .confirm-card {
                margin-bottom: 16px;
                padding: 20px;
            }

            .detail-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                padding: 12px 0;
                border-bottom: 1px solid #f1f5f9;
            }

            .detail-item:last-child {
                border-bottom: none;
            }

            .detail-label {
                font-size: 14px;
                color: #64748b;
            }

            .detail-value {
                font-size: 16px;
                color: #1a1a1a;
                word-break: break-word;
            }

            .code-input {
                letter-spacing: 6px;
                font-size: 18px;
                padding: 16px;
            }

            .btn-premium {
                font-size: 16px;
                padding: 16px;
            }

            .error-banner {
                margin: 16px 0;
                padding: 16px;
                font-size: 14px;
            }
        }
    </style>

    <div class="confirm-section">
        <div class="premium-header text-center animate-in">
            <div class="balance-label"><?= t('account_balance') ?></div>
            <h1 class="balance-display"><?php echo $formatted_balance; ?> <span style="font-size:1.2rem;font-weight:500;"><?php echo $devise; ?></span></h1>
        </div>

        <div class="stepper animate-in">
            <div class="step completed">
                <div class="step-number">1</div>
                <span class="d-none d-md-inline"><?= t('step_details') ?></span>
            </div>
            <div class="step-connector"></div>
            <div class="step active">
                <div class="step-number">2</div>
                <span class="d-none d-md-inline"><?= t('step_confirmation') ?></span>
            </div>
            <div class="step-connector"></div>
            <div class="step">
                <div class="step-number">3</div>
                <span class="d-none d-md-inline">Vérification</span>
            </div>
        </div>

        <div class="confirm-grid container-fluid">
            <div class="row g-4 justify-content-center">
                <div class="col-12 col-lg-6 animate-in">
                    <div class="confirm-card">
                        <h2 class="card-title">Détails du transfert</h2>
                        <ul class="detail-list">
                            <li class="detail-item">
                                <span class="detail-label"><i class="<?php echo $icon_iban; ?>"></i> <?php echo $label_iban; ?></span>
                                <span class="detail-value"><?php echo $iban !== '' ? $iban : '—'; ?></span>
                            </li>
<?php if ($transfer_method !== 'mobilemoney') { ?>
                            <li class="detail-item">
                                <span class="detail-label"><i class="<?php echo $icon_bic; ?>"></i> <?php echo $label_bic; ?></span>
                                <span class="detail-value"><?php echo $bic !== '' ? $bic : '—'; ?></span>
                            </li>
<?php } ?>
                            <li class="detail-item">
                                <span class="detail-label"><i class="<?php echo $icon_bank; ?>"></i> <?php echo $label_bank; ?></span>
                                <span class="detail-value"><?php echo $bank_name !== '' ? $bank_name : '—'; ?></span>
                            </li>
                            <li class="detail-item">
                                <span class="detail-label"><i class="fas fa-user"></i> Bénéficiaire</span>
                                <span class="detail-value"><?php echo $beneficiary_name !== '' ? $beneficiary_name : '—'; ?></span>
                            </li>
                            <li class="detail-item">
                                <span class="detail-label"><i class="fas fa-comment"></i> Motif</span>
                                <span class="detail-value"><?php echo $reason_display; ?></span>
                            </li>
                            <li class="detail-item">
                                <span class="detail-label"><i class="fas fa-coins"></i> Montant</span>
                                <span class="detail-value amount-highlight"><?php echo $formatted_balance . ' ' . $devise; ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="col-12 col-lg-5 animate-in">
                    <div class="confirm-card">
                        <h2 class="card-title">Code de sécurité</h2>
                        <p class="code-intro">Votre compte est-il activé ? Si oui, saisissez le code de vérification reçu par mail pour finaliser votre transfert.</p>
                        <form id="virement-form" action="index.php?page=virementDetail" method="post" novalidate>
                            <?php if ($transfer_method === 'mobilemoney'): ?>
                                <input type="hidden" name="method" value="mobilemoney">
                                <input type="hidden" name="operator" value="<?php echo htmlspecialchars($operator_key, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="nom" value="<?php echo htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="prenom" value="<?php echo htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="mobile_number" value="<?php echo htmlspecialchars($mobile_number, ENT_QUOTES, 'UTF-8'); ?>">
                                <!-- Mapper les champs Mobile Money vers les noms attendus par virementDetail.php -->
                                <input type="hidden" name="iban" value="<?php echo $iban; ?>">
                                <input type="hidden" name="bic" value="<?php echo $bic; ?>">
                                <input type="hidden" name="bank_name" value="<?php echo $bank_name; ?>">
                                <input type="hidden" name="beneficiary_name" value="<?php echo $beneficiary_name; ?>">
                            <?php else: ?>
                                <input type="hidden" name="iban" value="<?php echo htmlspecialchars($rawIban, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="bic" value="<?php echo htmlspecialchars($rawBic, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="bank_name" value="<?php echo htmlspecialchars($rawBankName, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="beneficiary_name" value="<?php echo htmlspecialchars($rawBeneficiary, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                            <input type="hidden" name="reason" value="<?php echo htmlspecialchars($rawReason, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="compte_id" value="<?php echo htmlspecialchars((string)$compte_id, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="montant" value="<?php echo htmlspecialchars((string)$account_balance, ENT_QUOTES, 'UTF-8'); ?>">

                            <input type="text" class="code-input" name="codeVirement" id="codeVirement" inputmode="numeric" pattern="\d*" maxlength="6" autocomplete="one-time-code" placeholder="••••••" required>

                            <button type="submit" class="btn-premium mt-4">
                                <i class="fas fa-check-circle"></i>
                                Confirmer le transfert
                            </button>

                            <div id="error-message" class="error-banner" role="alert"></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../partials/footer_nav.php'; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('virement-form');
        const errorDiv = document.getElementById('error-message');
        const codeInput = document.getElementById('codeVirement');

        codeInput.focus();

        function showError(message) {
            errorDiv.textContent = message;
            errorDiv.classList.add('show');
        }

        form.addEventListener('submit', async function(event) {
            event.preventDefault();
            errorDiv.classList.remove('show');

            const code = codeInput.value.trim();
            if (!/^\d{6}$/.test(code)) {
                showError('Le code doit contenir 6 chiffres.');
                codeInput.focus();
                return;
            }

            const params = new URLSearchParams({
                codeVirement: code,
                compte_id: form.querySelector('input[name="compte_id"]').value
            });

            try {
                const response = await fetch('validate_code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                });

                if (!response.ok) {
                    throw new Error('network');
                }

                const payload = await response.json();
                if (payload.success) {
                    form.submit();
                } else {
                    showError(payload.error || 'Code incorrect.');
                }
            } catch (error) {
                showError('Erreur réseau. Veuillez réessayer.');
            }
        });
    });
</script>