<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('fonction.php');

if (!isset($_POST['montant']) || (float)$_POST['montant'] <= 0) {
    die('<div class="alert alert-danger">' . htmlspecialchars(t('critical_invalid_amount')) . '</div>');
}

$sessionUser = $_SESSION['utilisateur_connecter'] ?? [];
$accountId = $sessionUser['compte_id'] ?? $sessionUser['id'] ?? null;
$ownerUserId = $sessionUser['user_id'] ?? null;

$rawBic = trim((string)($_POST['bic'] ?? ''));
$rawIban = trim((string)($_POST['iban'] ?? ''));
$rawBankName = trim((string)($_POST['bank_name'] ?? ''));
$rawBeneficiary = trim((string)($_POST['beneficiary_name'] ?? ''));
$rawReason = trim((string)($_POST['reason'] ?? ''));

$bic = htmlspecialchars($rawBic, ENT_QUOTES, 'UTF-8');
$iban = htmlspecialchars($rawIban, ENT_QUOTES, 'UTF-8');
$bank_name = htmlspecialchars($rawBankName, ENT_QUOTES, 'UTF-8');
$beneficiary_name = htmlspecialchars($rawBeneficiary, ENT_QUOTES, 'UTF-8');
$reason = htmlspecialchars($rawReason, ENT_QUOTES, 'UTF-8');

$utilisateur_connecte = getUserDetails($accountId);
if (!is_array($utilisateur_connecte)) {
    $utilisateur_connecte = [];
}

$montant = (float)$_POST['montant'];
$account_balance = isset($utilisateur_connecte['account_balance']) ? (float)$utilisateur_connecte['account_balance'] : 0.0;
$transfer_amount = ($montant > 0 && $montant <= $account_balance) ? $montant : $account_balance;
$formatted_balance = number_format($transfer_amount, 0, ',', ' ');
$devise = htmlspecialchars($utilisateur_connecte['devise'] ?? 'EUR', ENT_QUOTES, 'UTF-8');
$transfer_amount_display = number_format($transfer_amount, 0, ',', ' ');
$date = date('Y-m-d H:i:s');
$date_display = date('d/m/Y H:i');
$failure_message = trim((string)($utilisateur_connecte['failure_message'] ?? ''));
$success_message = trim((string)($utilisateur_connecte['success_message'] ?? ''));
$success_message_display = $success_message !== ''
    ? htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8')
    : ($failure_message !== '' ? htmlspecialchars($failure_message, ENT_QUOTES, 'UTF-8') : htmlspecialchars(t('transfer_success'), ENT_QUOTES, 'UTF-8'));
$success_message_text_raw = $success_message !== ''
    ? $success_message
    : ($failure_message !== '' ? $failure_message : t('transfer_completed_success'));

$photoUrl = null;
if (!empty($sessionUser)) {
    $photoUrl = getUserPhotoUrl($sessionUser);
}
if ($photoUrl === null && $accountId) {
    $photoUrl = getUserPhotoUrl($utilisateur_connecte);
}
?>
<div class="dashboard">
    <nav class="pt-2 d-flex justify-content-between align-items-center">
        <div><i class="fas fa-bars menu-icon"></i> <strong class="fs-4">TRANSFERFLUX</strong></div>
        <a href="index.php?page=info" class="icon-circle d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;">
            <?php if (!empty($photoUrl)): ?>
                <span class="avatar-sm" style="width:40px;height:40px;">
                    <img src="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="avatar">
                </span>
            <?php else: ?>
                <i class="fas fa-user"></i>
            <?php endif; ?>
        </a>
    </nav>
    <hr>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="index.php?page=transfert" class="back-btn"><i class="fas fa-arrow-left"></i> <?= t('back') ?></a>
            <span class="verification-tag"><i class="fas fa-shield-alt"></i> <?= t('verification_in_progress') ?></span>
    </div>

    <style>
        .verify-section {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #0f9d58 0%, #34a853 100%);
            --danger-gradient: linear-gradient(135deg, #d93025 0%, #ea4335 100%);
            --card-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            --hover-shadow: 0 12px 40px rgba(0, 0, 0, 0.18);
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.4);
        }

        .verify-section .premium-header {
            background: var(--primary-gradient);
            color: #fff;
            padding: 2rem 1rem;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }

        .verify-section .balance-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .verify-section .balance-display {
            font-size: 2.5rem;
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
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .step.completed {
            color: #0f9d58;
        }

        .step.completed .step-number {
            background: var(--success-gradient);
            color: #fff;
        }

        .step.active {
            color: #667eea;
        }

        .step.active .step-number {
            background: var(--primary-gradient);
            color: #fff;
            transform: scale(1.05);
        }

        .step-connector {
            width: 60px;
            height: 2px;
            background: #e2e8f0;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        .progress-card h2 .fa-spinner {
            animation: spin 1s linear infinite;
            display: inline-block;
        }

        .progress-card h2 .fa-spinner.stopped {
            animation: none;
        }

        .summary-card,
        .progress-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            padding: 1.75rem;
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(12px);
            transition: all 0.3s ease;
        }

        .summary-card:hover,
        .progress-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--hover-shadow);
        }

        /* Ensure consistent site font and alert sizing */
        body { font-family: 'Roboto', Arial, sans-serif; }
        .alert-modern, .alert-premium { font-family: 'Roboto', Arial, sans-serif; }
        .alert-title { font-size: 1.05rem; font-weight: 700; }
        .alert-message { font-size: 1.00rem; line-height: 1.5; font-weight: 500; }

        .summary-card h2,
        .progress-card h2 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            color: #1f2937;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .summary-item {
            background: rgba(241, 245, 249, 0.7);
            border-radius: 12px;
            padding: 1rem;
        }

        .summary-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 0.35rem;
        }

        .summary-value {
            font-weight: 700;
            color: #1f2937;
        }

        .cta-soft {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.85rem 1.6rem;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.12), rgba(127, 85, 246, 0.18));
            color: #4c1d95;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.18);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid rgba(99, 102, 241, 0.25);
        }

        .cta-soft:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 40px rgba(99, 102, 241, 0.25);
            color: #312e81;
        }

        .cta-soft i {
            font-size: 1rem;
        }

        .amount-highlight {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .progress-bar-wrapper {
            border-radius: 50px;
            background: rgba(226, 232, 240, 0.6);
            overflow: hidden;
            height: 48px;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(15, 23, 42, 0.08);
        }

        .progress-bar-custom {
            height: 100%;
            background-image: repeating-linear-gradient(-45deg, #0f9d58 0, #0f9d58 12px, #34a853 12px, #34a853 24px);
            transition: width 0.4s ease;
            color: #fff;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .progress-bar-custom.failed {
            background-image: repeating-linear-gradient(-45deg, #d93025 0, #d93025 12px, #ea4335 12px, #ea4335 24px);
        }

        .status-message {
            margin-top: 1rem;
            font-weight: 600;
            padding: 0.85rem 1rem;
            border-radius: 12px;
        }

        .status-message.status-error {
            background: rgba(217, 48, 37, 0.1);
            color: #d93025;
            border: 1px solid rgba(217, 48, 37, 0.2);
        }

        .status-message.status-success {
            background: rgba(16, 185, 129, 0.12);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        .verification-tag {
            background: rgba(102, 126, 234, 0.12);
            color: #4f46e5;
            border-radius: 999px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-btn {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.6rem 1.2rem;
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            color: #4f46e5;
            border-color: #4f46e5;
            transform: translateY(-2px);
        }

        .menu-icon {
            margin-right: 0.75rem;
        }

        .animate-in {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .premium-header {
                margin: 0 -16px 20px -16px;
                border-radius: 0 0 20px 20px;
                padding: 24px 20px;
            }

            .balance-display {
                font-size: 32px;
                font-weight: 700;
            }

            .d-flex.justify-content-between {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .verification-tag {
                width: 100%;
                justify-content: center;
                font-size: 14px;
                padding: 12px 16px;
            }

            .back-btn {
                width: 100%;
                justify-content: center;
                font-size: 14px;
                padding: 12px 16px;
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

            .summary-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .summary-card,
            .progress-card {
                padding: 20px;
                margin-bottom: 16px;
            }

            .summary-item {
                padding: 12px 0;
                border-bottom: 1px solid #f1f5f9;
            }

            .summary-item:last-child {
                border-bottom: none;
            }

            .summary-label {
                font-size: 14px;
                color: #64748b;
            }

            .summary-value {
                font-size: 16px;
                color: #1a1a1a;
                word-break: break-word;
            }

            .progress-step {
                padding: 16px 0;
                font-size: 14px;
            }

            .btn-premium {
                font-size: 16px;
                padding: 16px;
            }

            .progress-bar-wrapper {
                height: 44px;
            }

            .progress-bar-custom {
                font-size: 0.95rem;
                line-height: 44px;
                padding: 0 12px;
            }

            .status-message {
                font-size: 0.95rem;
                padding: 0.75rem 0.9rem;
            }

            .cta-soft {
                width: 100%;
                justify-content: center;
            }

            .modal-dialog {
                margin: 16px auto;
            }
        }

        @media (max-width: 420px) {
            .verify-section .balance-display {
                font-size: 1.75rem;
            }

            .step-number {
                width: 30px;
                height: 30px;
            }

            .progress-bar-custom {
                font-size: 0.88rem;
            }

            .summary-item {
                font-size: 0.92rem;
            }
        }

        .modern-modal .modal-dialog {
            max-width: 420px;
        }

        .modern-modal .modal-dialog.modal-narrow {
            width: min(420px, 100%);
            max-width: 420px;
        }

        .modern-modal .modal-content {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 24px 50px rgba(15, 23, 42, 0.25);
            background: #fff;
        }

        .modern-modal .modal-header {
            border: none;
            padding: 1.5rem 1.75rem 1.25rem;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1.5rem;
            position: relative;
            color: #fff;
            background: var(--modal-gradient, linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%));
        }

        .modern-modal .modal-header::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0) 100%);
            pointer-events: none;
        }

        .modern-modal .modal-header-content {
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            position: relative;
            z-index: 1;
        }

        .modern-modal .modal-icon-bubble {
            width: 60px;
            min-width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.6rem;
            background: rgba(255, 255, 255, 0.18);
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: inset 0 5px 15px rgba(255, 255, 255, 0.18);
        }

        .modern-modal .modal-title {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.3;
        }

        .modern-modal .modal-subtitle {
            margin: 0.35rem 0 0;
            font-size: 0.88rem;
            opacity: 0.9;
        }

        .modern-modal .modern-close {
            position: relative;
            z-index: 1;
            filter: brightness(0) invert(1);
            opacity: 0.85;
        }

        .modern-modal .modern-close:hover {
            opacity: 1;
        }

        .modern-modal .modal-body {
            padding: 1.75rem;
            margin-top: -1rem;
        }

        .modern-modal .modal-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .modern-modal .modal-info-item {
            background: #f8fafc;
            border-radius: 14px;
            padding: 0.9rem 1rem;
            border: 1px solid rgba(226, 232, 240, 0.7);
        }

        .modern-modal .modal-info-label {
            display: block;
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 0.2rem;
        }

        .modern-modal .modal-info-value {
            font-size: 1rem;
            color: #0f172a;
            font-weight: 600;
        }

        .modern-modal .modal-status {
            border-radius: 16px;
            padding: 1rem 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .modern-modal .modal-status i {
            font-size: 1.25rem;
        }

        .modern-modal .modal-footer {
            border: none;
            padding: 1.5rem 2.2rem 2rem;
            background: #fff;
            justify-content: flex-end;
        }

        .modern-modal .btn-modal-primary {
            border: none;
            border-radius: 999px;
            padding: 0.75rem 1.75rem;
            font-weight: 600;
            color: #fff;
            background: var(--modal-gradient, linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%));
            box-shadow: 0 12px 25px rgba(79, 70, 229, 0.25);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .modern-modal .btn-modal-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 35px rgba(79, 70, 229, 0.28);
        }

        .modal-success {
            --modal-gradient: linear-gradient(135deg, #10b981 0%, #047857 100%);
        }

        .modal-success .modal-icon-bubble {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-success .modal-status {
            background: rgba(16, 185, 129, 0.15);
            color: #0f766e;
        }

        .modal-success .btn-modal-primary {
            box-shadow: 0 12px 30px rgba(4, 120, 87, 0.35);
        }

        .modal-success .btn-modal-primary:hover {
            box-shadow: 0 18px 36px rgba(4, 120, 87, 0.45);
        }

        .modal-failure {
            --modal-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .modal-failure .modal-icon-bubble {
            background: rgba(255, 255, 255, 0.25);
        }

        .modal-failure .modal-status {
            background: rgba(248, 113, 113, 0.18);
            color: #b91c1c;
        }

        .modal-failure .btn-modal-primary {
            box-shadow: 0 12px 30px rgba(239, 68, 68, 0.35);
        }

        .modal-failure .btn-modal-primary:hover {
            box-shadow: 0 18px 36px rgba(220, 38, 38, 0.45);
        }

        .modal-insuffit {
            --modal-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .modal-insuffit .modal-icon-bubble {
            background: rgba(255, 255, 255, 0.22);
        }

        .modal-insuffit .modal-status {
            background: rgba(250, 204, 21, 0.2);
            color: #b45309;
        }

        .modal-insuffit .btn-modal-primary {
            box-shadow: 0 12px 30px rgba(217, 119, 6, 0.35);
        }

        .modal-insuffit .btn-modal-primary:hover {
            box-shadow: 0 18px 36px rgba(180, 83, 9, 0.45);
        }

        @media (max-width: 575px) {
            .modern-modal .modal-dialog {
                margin: 1rem auto;
                max-width: calc(100% - 2rem);
            }

            .modern-modal .modal-header {
                padding: 1.5rem 1.25rem 1rem;
            }

            .modern-modal .modal-body {
                padding: 1.25rem;
                font-size: 0.95rem;
            }

            .modern-modal .modal-footer {
                padding: 1rem 1.25rem 1.5rem;
            }

            .modern-modal .modal-icon-bubble {
                width: 56px;
                height: 56px;
                font-size: 1.4rem;
            }

            .modern-modal .modal-title {
                font-size: 1.2rem;
            }

            .modern-modal .modal-subtitle {
                font-size: 0.85rem;
            }

            .summary-item {
                padding: 0.85rem;
            }

            .summary-label {
                font-size: 0.7rem;
            }

            .summary-value {
                font-size: 0.9rem;
            }

            .btn-modal-primary {
                padding: 12px 24px;
                font-size: 0.95rem;
            }

            .summary-grid {
                grid-template-columns: 1fr;
                gap: 0.85rem;
            }
        }
    </style>

    <div class="verify-section">
        <div class="premium-header text-center animate-in">
            <div class="balance-label"><?= t('transfer_amount_title') ?></div>
            <h1 class="balance-display"><?php echo $formatted_balance; ?> <span style="font-size:1.2rem;font-weight:500;"><?php echo $devise; ?></span></h1>
        </div>

        <div class="stepper animate-in">
            <div class="step completed" id="step-details">
                <div class="step-number">1</div>
                <span class="d-none d-md-inline"><?= t('step_details') ?></span>
            </div>
            <div class="step-connector"></div>
            <div class="step completed" id="step-confirmation">
                <div class="step-number">2</div>
                <span class="d-none d-md-inline"><?= t('step_confirmation') ?></span>
            </div>
            <div class="step-connector"></div>
            <div class="step active" id="step-verification">
                <div class="step-number">3</div>
                <span class="d-none d-md-inline"><?= t('step_verification') ?></span>
            </div>
        </div>

        <div class="container-fluid">
            <div class="row g-4 justify-content-center">
                <div class="col-12 col-lg-7 animate-in">
                    <div class="summary-card">
                        <h2><i class="fas fa-check-circle text-success me-2"></i><?= t('card_alert_title') ?></h2>
                        <p class="mb-4 text-secondary"><?= htmlspecialchars(t('transfer_verification_message'), ENT_QUOTES, 'UTF-8') ?></p>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <div class="summary-label"><?= t('beneficiary_name') ?></div>
                                <div class="summary-value"><?php echo $beneficiary_name !== '' ? $beneficiary_name : '—'; ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label"><?= t('bank_name') ?></div>
                                <div class="summary-value"><?php echo $bank_name !== '' ? $bank_name : '—'; ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label"><?= t('iban_label') ?></div>
                                <div class="summary-value"><?php echo $iban !== '' ? $iban : '—'; ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label"><?= t('bic_label') ?></div>
                                <div class="summary-value"><?php echo $bic !== '' ? $bic : '—'; ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label"><?= t('reason_label') ?></div>
                                <div class="summary-value"><?php echo $reason !== '' ? $reason : 'Non renseigné'; ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label"><?= t('amount_to_receive') ?></div>
                                <div class="summary-value amount-highlight"><?php echo $formatted_balance . ' ' . $devise; ?></div>
                            </div>
                        </div>
                        <div class="text-end mt-4">
                            <a href="index.php?page=transfert" class="cta-soft" id="retry-transfer-button" style="display:none;">
                                <i class="fas fa-rotate-right"></i>
                                <span><?= t('perform_another_transfer') ?></span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-5 animate-in">
                    <div class="progress-card">
                        <h2><i class="fas fa-spinner me-2"></i><?= t('transfer_status') ?></h2>
                        <div class="progress-bar-wrapper">
                            <div id="progress-bar" class="progress-bar-custom" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                        </div>
                        <div id="transfer-status-message" class="status-message text-secondary mt-3"><?= t('transfer_in_progress') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../partials/footer_nav.php'; ?>
</div>

<div class="modal fade modern-modal modal-success" id="successModal" tabindex="-1" role="dialog" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-narrow" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon-bubble"><i class="fas fa-check"></i></div>
                    <div>
                        <h3 class="modal-title" id="successModalLabel"><?= t('transfer_success_title') ?></h3>
                        <p class="modal-subtitle"><?= htmlspecialchars(t('transfer_success_subtitle', array('amount' => $transfer_amount_display, 'currency' => $devise)), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <button type="button" class="btn-close modern-close manual-modal-close" aria-label="<?php echo htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8'); ?>"></button>
            </div>
            <div class="modal-body">
                <div class="modal-info-grid">
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= t('beneficiary_name') ?></span>
                        <span class="modal-info-value"><?php echo $beneficiary_name !== '' ? $beneficiary_name : '—'; ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= t('bank_name') ?></span>
                        <span class="modal-info-value"><?php echo $bank_name !== '' ? $bank_name : '—'; ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= t('iban_label') ?></span>
                        <span class="modal-info-value"><?php echo $iban !== '' ? $iban : '—'; ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= t('bic_label') ?></span>
                        <span class="modal-info-value"><?php echo $bic !== '' ? $bic : '—'; ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= t('amount_sent') ?></span>
                        <span class="modal-info-value"><?php echo $transfer_amount_display; ?> <?php echo $devise; ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= htmlspecialchars(t('reason'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="modal-info-value"><?php echo $reason !== '' ? $reason : 'Non renseigné'; ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= t('date_sent_label') ?></span>
                        <span class="modal-info-value"><?php echo $date_display; ?></span>
                    </div>
                </div>
                <div class="modal-status"><i class="fas fa-check-circle"></i> <?php echo $success_message_display; ?></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-primary manual-modal-close"><?php echo htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modern-modal modal-failure" id="failureModal" tabindex="-1" role="dialog" aria-labelledby="failureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-narrow" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon-bubble"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <h3 class="modal-title" id="failureModalLabel"><?= t('transfer_failed') ?></h3>
                        <p class="modal-subtitle"><?= htmlspecialchars(t('transfer_failed_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <button type="button" class="btn-close modern-close manual-modal-close" aria-label="<?php echo htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8'); ?>"></button>
            </div>
            <div class="modal-body">
                <div class="modal-info-grid">
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= t('beneficiary_name') ?></span>
                        <span class="modal-info-value"><?php echo $beneficiary_name !== '' ? $beneficiary_name : '—'; ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= t('bank_name') ?></span>
                        <span class="modal-info-value"><?php echo $bank_name !== '' ? $bank_name : '—'; ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= t('iban_label') ?></span>
                        <span class="modal-info-value"><?php echo $iban !== '' ? $iban : '—'; ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= t('bic_label') ?></span>
                        <span class="modal-info-value"><?php echo $bic !== '' ? $bic : '—'; ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= t('amount_sent') ?></span>
                        <span class="modal-info-value"><?php echo $transfer_amount_display; ?> <?php echo $devise; ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= t('date_sent_label') ?></span>
                        <span class="modal-info-value"><?php echo $date_display; ?></span>
                    </div>
                </div>
                <div class="modal-status"><i class="fas fa-exclamation-circle"></i> <?php echo $failure_message !== '' ? htmlspecialchars($failure_message, ENT_QUOTES, 'UTF-8') : htmlspecialchars(t('please_try_again'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-primary manual-modal-close"><?php echo htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modern-modal modal-insuffit" id="insuffitModal" tabindex="-1" role="dialog" aria-labelledby="insuffitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-narrow" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon-bubble"><i class="fas fa-wallet"></i></div>
                    <div>
                        <h3 class="modal-title" id="insuffitModalLabel"><?= t('insufficient_balance') ?></h3>
                        <p class="modal-subtitle"><?= htmlspecialchars(t('insufficient_balance_message'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <button type="button" class="btn-close modern-close manual-modal-close" aria-label="<?php echo htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8'); ?>"></button>
            </div>
            <div class="modal-body">
                <div class="modal-info-grid">
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= t('beneficiary_name') ?></span>
                        <span class="modal-info-value"><?php echo $beneficiary_name !== '' ? $beneficiary_name : '—'; ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= t('bank_name') ?></span>
                        <span class="modal-info-value"><?php echo $bank_name !== '' ? $bank_name : '—'; ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label"><?= t('amount_sent') ?></span>
                        <span class="modal-info-value"><?php echo $transfer_amount_display; ?> <?php echo $devise; ?></span>
                    </div>
                </div>
                <div class="modal-status"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars(t('insufficient_balance_message'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-primary manual-modal-close"><?php echo htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const progressBar = document.getElementById('progress-bar');
    const statusMessage = document.getElementById('transfer-status-message');
    const retryButton = document.getElementById('retry-transfer-button');
    const stepVerification = document.getElementById('step-verification');
    const successMessageText = <?php echo json_encode($success_message_text_raw, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

    // Localized strings for client-side updates
    const t_retry_another = <?php echo json_encode(t('perform_another_transfer'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const t_retry_try_again = <?php echo json_encode(t('please_try_again'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const t_insufficient_balance_status = <?php echo json_encode(t('insufficient_balance_message'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const t_transfer_failed_status = <?php echo json_encode(t('transfer_failed_status', []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

        const startPercentage = parseInt('<?php echo (int)($utilisateur_connecte['start_percentage'] ?? 0); ?>', 10);
        const endPercentage = parseInt('<?php echo (int)($utilisateur_connecte['end_percentage'] ?? 100); ?>', 10);
        const accountBalance = <?php echo json_encode($account_balance, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

        function showModalById(modalId) {
            const element = document.getElementById(modalId);
            if (!element) {
                return;
            }
            if (typeof window.jQuery !== 'undefined' && typeof jQuery(element).modal === 'function') {
                jQuery(element).modal('show');
            } else {
                element.classList.add('show');
                element.style.display = 'block';
                element.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                document.body.appendChild(backdrop);
            }
        }

        const manualCloseButtons = document.querySelectorAll('.manual-modal-close');
        manualCloseButtons.forEach(btn => {
            btn.addEventListener('click', function(event) {
                event.preventDefault();
                const modalElement = btn.closest('.modal');
                if (modalElement) {
                    if (typeof window.jQuery !== 'undefined' && typeof jQuery(modalElement).modal === 'function') {
                        jQuery(modalElement).modal('hide');
                    } else {
                        modalElement.classList.remove('show');
                        modalElement.setAttribute('aria-hidden', 'true');
                        modalElement.style.display = 'none';
                        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
                        document.body.classList.remove('modal-open');
                        document.body.style.removeProperty('padding-right');
                    }
                }
            });
        });

        function closeModalById(modalId) {
            const element = document.getElementById(modalId);
            if (!element) {
                return;
            }
            if (typeof window.jQuery !== 'undefined' && typeof jQuery(element).modal === 'function') {
                jQuery(element).modal('hide');
            } else {
                element.classList.remove('show');
                element.setAttribute('aria-hidden', 'true');
                element.style.display = 'none';
                document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
            }
        }

        function stopProgressSpinner() {
            const spinner = document.querySelector('.progress-card h2 .fa-spinner');
            if (spinner) {
                spinner.classList.add('stopped');
            }
        }

        function markTransferSuccessUI() {
            stopProgressSpinner();
            showModalById('successModal');
            if (statusMessage) {
                statusMessage.textContent = successMessageText;
                statusMessage.classList.remove('text-secondary');
                statusMessage.classList.remove('status-error');
                statusMessage.classList.add('status-success');
            }
            if (progressBar) {
                progressBar.classList.remove('failed');
            }
            const balanceDisplay = document.querySelector('.balance-display');
            if (balanceDisplay) {
                balanceDisplay.innerHTML = '0,00 <span style="font-size:1.2rem;font-weight:500;">' + <?php echo json_encode($devise, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?> + '</span>';
            }
            stepVerification.classList.remove('active');
            stepVerification.classList.add('completed');
            updateVisualProgress(100);
            if (retryButton) {
                retryButton.style.display = 'inline-flex';
                retryButton.querySelector('span').textContent = t_retry_another;
                retryButton.href = 'index.php?page=transfert';
            }
        }

        function persistTransfer() {
            const payload = {
                bic: <?php echo json_encode($rawBic); ?>,
                iban: <?php echo json_encode($rawIban); ?>,
                bank_name: <?php echo json_encode($rawBankName); ?>,
                beneficiary_name: <?php echo json_encode($rawBeneficiary); ?>,
                reason: <?php echo json_encode($rawReason); ?>,
                user_id: <?php echo json_encode($ownerUserId ?? ''); ?>,
                solidvire: <?php echo json_encode($transfer_amount); ?>,
                devise: <?php echo json_encode($utilisateur_connecte['devise'] ?? 'EUR'); ?>,
                token: <?php echo json_encode($utilisateur_connecte['token'] ?? ''); ?>,
                status: 'completed',
                created_at: <?php echo json_encode($date); ?>,
                updated_at: <?php echo json_encode($date); ?>
            };

            return fetch('insert_transfer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(response => response.json());
        }

        const startDisplay = Math.max(0, Math.min(100, startPercentage));
        const targetDisplay = Math.max(startDisplay, Math.min(100, endPercentage));
        const PROGRESS_DURATION_PER_PERCENT_MS = 1000; // 1s per percentage point
        const deltaPercent = Math.max(1, targetDisplay - startDisplay);
        const totalDuration = deltaPercent * PROGRESS_DURATION_PER_PERCENT_MS;
        let width = startDisplay;
        updateVisualProgress(width);

        const requestFrame = (window.requestAnimationFrame || function(cb) { return setTimeout(() => cb(Date.now()), 16); }).bind(window);
        const cancelFrame = (window.cancelAnimationFrame || clearTimeout).bind(window);
        let progressAnimationId = null;
        let progressAnimationStart = null;
        let progressComplete = false;

        function cancelProgressAnimation() {
            if (progressAnimationId !== null) {
                cancelFrame(progressAnimationId);
                progressAnimationId = null;
            }
        }

        function finalizeProgressSuccess() {
            if (progressComplete) {
                return;
            }
            progressComplete = true;
            cancelProgressAnimation();
            if (accountBalance > 0) {
                markTransferSuccessUI();
                persistTransfer()
                    .then(data => {
                        if (data && data.success) {
                            fetch('deduct_balance.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ amount: <?php echo json_encode($transfer_amount); ?> })
                            }).catch(() => {});
                        } else {
                            throw new Error(data && data.message ? data.message : 'Erreur inconnue');
                        }
                    })
                    .catch(error => {
                        console.error('Erreur lors du virement:', error);
                        handleFailure();
                    });
            } else {
                handleFailure(true);
            }
        }

        function animateProgress(timestamp) {
            if (progressComplete) {
                return;
            }
            if (!progressAnimationStart) {
                progressAnimationStart = timestamp;
            }

            const elapsed = timestamp - progressAnimationStart;
            const fraction = Math.min(1, elapsed / totalDuration);
            if (targetDisplay > startDisplay) {
                width = startDisplay + fraction * (targetDisplay - startDisplay);
            } else {
                width = startDisplay;
            }
            updateVisualProgress(width);

            if (fraction >= 1) {
                if (targetDisplay >= 100) {
                    finalizeProgressSuccess();
                } else {
                    progressComplete = true;
                    cancelProgressAnimation();
                    handleFailure();
                }
                return;
            }

            progressAnimationId = requestFrame(animateProgress);
        }

        progressAnimationId = requestFrame(animateProgress);

        function handleFailure(insufficient = false) {
            progressComplete = true;
            cancelProgressAnimation();
            stopProgressSpinner();
            closeModalById('successModal');
            showModalById(insufficient ? 'insuffitModal' : 'failureModal');
            if (statusMessage) {
                statusMessage.textContent = insufficient ? t_insufficient_balance_status : (t_transfer_failed_status || 'Transfer failed');
                statusMessage.classList.remove('text-secondary');
                statusMessage.classList.remove('status-success');
                statusMessage.classList.add('status-error');
            }
            if (progressBar) {
                progressBar.classList.add('failed');
            }
            if (retryButton) {
                retryButton.style.display = 'inline-flex';
                retryButton.querySelector('span').textContent = t_retry_try_again;
                retryButton.href = 'index.php?page=transfert';
            }
        }

        function updateVisualProgress(value) {
            const clamped = Math.max(0, Math.min(100, Math.round(value)));
            if (progressBar) {
                progressBar.style.width = clamped + '%';
                progressBar.setAttribute('aria-valuenow', String(clamped));
                progressBar.textContent = clamped + '%';
            }
        }
    });
</script>