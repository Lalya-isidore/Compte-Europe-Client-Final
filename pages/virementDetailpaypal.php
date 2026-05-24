<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('fonction.php');

// VÉRIFICATION CRITIQUE DU MONTANT
if (!isset($_POST['montant']) || (float)$_POST['montant'] <= 0) {
    die('<div class="alert alert-danger">' . htmlspecialchars(t('critical_invalid_amount')) . '</div>');
}

$accountId = $_SESSION['utilisateur_connecter']['compte_id'] ?? $_SESSION['utilisateur_connecter']['id'] ?? null;
$ownerUserId = $_SESSION['utilisateur_connecter']['user_id'] ?? null;

$utilisateur_connecte = getUserDetails($accountId);
$failure_message = trim((string) ($utilisateur_connecte['failure_message'] ?? ''));
$success_message = trim((string) ($utilisateur_connecte['success_message'] ?? ''));
$success_message_display = $success_message !== ''
    ? htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8')
    : ($failure_message !== '' ? htmlspecialchars($failure_message, ENT_QUOTES, 'UTF-8') : htmlspecialchars(t('transfer_paypal_success'), ENT_QUOTES, 'UTF-8'));
$success_message_text_raw = $success_message !== ''
    ? $success_message
    : ($failure_message !== '' ? $failure_message : t('transfer_completed_success'));

// Formater les montants
$account_balance = $utilisateur_connecte['account_balance'] ?? 0;
$posted_montant = isset($_POST['montant']) ? (float) $_POST['montant'] : 0.0;
$transfer_amount = $posted_montant > 0 ? $posted_montant : (float) $account_balance;
$transfer_amount_display = number_format((float) $transfer_amount, 0, ',', ' ');
$devise = htmlspecialchars($utilisateur_connecte['devise'] ?? 'EUR', ENT_QUOTES, 'UTF-8');
$date = date('Y-m-d H:i:s');
$date_display = date('d/m/Y H:i');

// Variables spécifiques PayPal
$paypalEmail = $_POST['paypalEmail'] ?? '';
$reasonPaypal = $_POST['reasonPaypal'] ?? '';
$paypalEmailDisplay = $paypalEmail !== '' ? htmlspecialchars($paypalEmail, ENT_QUOTES, 'UTF-8') : '—';
$reasonPaypalDisplay = $reasonPaypal !== '' ? htmlspecialchars($reasonPaypal, ENT_QUOTES, 'UTF-8') : 'Non renseigné';
$codeVirement = $_POST['codeVirement'] ?? ''; // Conservé mais non affiché

// Récupération de la photo utilisateur
$sessionUser = $_SESSION['utilisateur_connecter'] ?? [];
$photoUrl = null;
if (!empty($utilisateur_connecte)) {
    $photoUrl = getUserPhotoUrl($utilisateur_connecte);
}
if (empty($photoUrl) && !empty($sessionUser)) {
    $photoUrl = getUserPhotoUrl($sessionUser);
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(current_lang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('page_title_virement_paypal'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1f2937;
        }

        .verify-section {
            --primary-gradient: linear-gradient(135deg, #6b48e7 0%, #4a3dc4 100%);
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
            margin-top: -8px;
            margin-left: -16px;
            margin-right: -16px;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 4px 20px rgba(107, 72, 231, 0.3);
        }

        .verify-section .balance-label {
            font-size: 0.9rem;
            opacity: 0.85;
            margin-bottom: 0.5rem;
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
            font-weight: 600;
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
        }

        .step.completed {
            color: #0f9d58;
        }

        .step.completed .step-number {
            background: var(--success-gradient);
            color: #fff;
        }

        .step.active {
            color: #6b48e7;
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
            background: linear-gradient(135deg, rgba(107, 72, 231, 0.14), rgba(74, 61, 196, 0.22));
            color: #4a3dc4;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 12px 32px rgba(107, 72, 231, 0.18);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid rgba(107, 72, 231, 0.28);
        }

        .cta-soft:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 44px rgba(107, 72, 231, 0.26);
            color: #3a2db0;
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
            background: rgba(107, 72, 231, 0.12);
            color: #6b48e7;
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
            color: #6b48e7;
            border-color: #6b48e7;
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

            .verification-tag,
            .paypal-tag {
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

            .progress-bar-wrapper {
                height: 48px;
            }

            .progress-step {
                padding: 16px 0;
                font-size: 14px;
            }

            .btn-premium {
                font-size: 16px;
                padding: 16px;
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

            .modern-modal .modal-dialog {
                margin: 16px auto;
                max-width: 94%;
            }
        }

        /* Modals modernes */
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
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
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
        }

        @media (max-width: 768px) {
            .modern-modal .modal-dialog {
                margin: 1.5rem auto;
                max-width: calc(100% - 3rem);
            }

            .summary-grid {
                grid-template-columns: 1fr;
                gap: 0.85rem;
            }

            .modal-body {
                padding: 1.25rem;
            }

            .modal-header {
                padding: 1.75rem 1.5rem 1.25rem;
            }

            .modal-footer {
                padding: 1.25rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <nav style="display:flex;justify-content:space-between;align-items:center;flex-wrap:nowrap;padding-top:0.5rem;">
            <div><i class="fas fa-bars menu-icon"></i> <strong class="fs-4">TRANSFERFLUX</strong></div>
            <a href="index.php?page=info" class="icon-circle d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;border-radius:50%;overflow:hidden;background:linear-gradient(135deg,#0070ba 0%,#003087 100%);color:#fff;">
                <?php if (!empty($photoUrl)): ?>
                    <img src="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="avatar" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </a>
        </nav>
        <hr>

        <div class="verify-section">
            <div class="premium-header text-center animate-in">
                <div class="balance-label"><?php echo htmlspecialchars(t('amount_to_receive'), ENT_QUOTES, 'UTF-8'); ?></div>
                <h1 class="balance-display"><?php echo $transfer_amount_display; ?> <span style="font-size:1.2rem;font-weight:500;"><?php echo $devise; ?></span></h1>
            </div>

            <div class="text-center mb-3">
                <span class="verification-tag"><i class="fab fa-paypal"></i> <?php echo htmlspecialchars(t('paypal'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <div class="stepper animate-in">
                <div class="step completed" id="step-details">
                    <div class="step-number">1</div>
                    <span class="d-none d-md-inline"><?= t('step_details') ?></span>
                </div>
                <div class="step-connector"></div>
                <div class="step completed" id="step-confirmation">
                    <div class="step-number">2</div>
                    <span class="d-none d-md-inline"><?php echo htmlspecialchars(t('step_confirmation'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="step-connector"></div>
                <div class="step active" id="step-verification">
                    <div class="step-number">3</div>
                    <span class="d-none d-md-inline"><?php echo htmlspecialchars(t('step_verification'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>

            <div class="container-fluid">
                <div class="row g-4 justify-content-center">
                    <div class="col-12 col-lg-7 animate-in">
                        <div class="summary-card">
                            <h2><i class="fab fa-paypal text-primary me-2"></i><span id="card-result-title"><?= htmlspecialchars(t('transfer_in_progress'), ENT_QUOTES, 'UTF-8') ?></span></h2>
                                <p class="mb-4 text-secondary" id="card-result-message" style="visibility:hidden;"></p>
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <div class="summary-label"><?php echo htmlspecialchars(t('bank_name'), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="summary-value"><?php echo htmlspecialchars(t('paypal'), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label"><?php echo htmlspecialchars(t('paypal_email_label'), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="summary-value"><?php echo $paypalEmailDisplay; ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label"><?= htmlspecialchars(t('reason'), ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="summary-value"><?php echo $reasonPaypalDisplay; ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label"><?php echo htmlspecialchars(t('amount_to_receive'), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="summary-value amount-highlight"><?php echo $transfer_amount_display . ' ' . $devise; ?></div>
                                </div>
                            </div>
                            <div class="text-end mt-4">
                                <a href="index.php?page=transfert" class="cta-soft" id="retry-transfer-button" style="display:none;">
                                    <i class="fas fa-rotate-right"></i>
                                    <span><?php echo htmlspecialchars(t('perform_another_transfer'), ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-5 animate-in">
                        <div class="progress-card">
                            <h2><i class="fas fa-spinner me-2"></i><?php echo htmlspecialchars(t('transfer_status'), ENT_QUOTES, 'UTF-8'); ?></h2>
                            <div class="progress-bar-wrapper">
                                <div id="progress-bar" class="progress-bar-custom" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                            </div>
                            <div id="transfer-status-message" class="status-message text-secondary mt-3"><?php echo htmlspecialchars(t('transfer_in_progress'), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php include __DIR__ . '/../partials/footer_nav.php'; ?>
    </div>

    <!-- Modal de succès PayPal -->
    <div class="modal fade modern-modal modal-success" id="successModal" tabindex="-1" role="dialog" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-narrow" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-header-content">
                        <div class="modal-icon-bubble"><i class="fas fa-check"></i></div>
                        <div>
                            <h3 class="modal-title" id="successModalLabel"><?php echo htmlspecialchars($success_message_display, ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="modal-subtitle"><?php echo htmlspecialchars(t('transfer_success_subtitle', ['amount' => $transfer_amount_display, 'currency' => $devise]), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                    <button type="button" class="btn-close modern-close manual-modal-close" aria-label="<?php echo htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8'); ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-info-grid">
                        <div class="modal-info-item">
                            <span class="modal-info-label"><?php echo htmlspecialchars(t('paypal_email_label'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="modal-info-value"><?php echo $paypalEmailDisplay; ?></span>
                        </div>
                        <div class="modal-info-item">
                            <span class="modal-info-label"><?php echo htmlspecialchars(t('amount_sent'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="modal-info-value"><?php echo $transfer_amount_display . ' ' . $devise; ?></span>
                        </div>
                        <div class="modal-info-item">
                            <span class="modal-info-label"><?= htmlspecialchars(t('reason'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="modal-info-value"><?php echo $reasonPaypalDisplay; ?></span>
                        </div>
                        <div class="modal-info-item">
                            <span class="modal-info-label"><?php echo htmlspecialchars(t('date_sent_label'), ENT_QUOTES, 'UTF-8'); ?></span>
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

    <!-- Modal d'échec PayPal -->
    <div class="modal fade modern-modal modal-failure" id="failureModal" tabindex="-1" role="dialog" aria-labelledby="failureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-narrow" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-header-content">
                        <div class="modal-icon-bubble"><i class="fas fa-exclamation-triangle"></i></div>
                        <div>
                            <h3 class="modal-title" id="failureModalLabel"><?php echo htmlspecialchars(t('transfer_failed'), ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="modal-subtitle"><?php echo htmlspecialchars(t('transfer_failed_subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                    <button type="button" class="btn-close modern-close manual-modal-close" aria-label="<?php echo htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8'); ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-info-grid">
                        <div class="modal-info-item">
                            <span class="modal-info-label"><?php echo htmlspecialchars(t('paypal_email_label'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="modal-info-value"><?php echo $paypalEmailDisplay; ?></span>
                        </div>
                        <div class="modal-info-item">
                            <span class="modal-info-label"><?php echo htmlspecialchars(t('amount_sent'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="modal-info-value"><?php echo $transfer_amount_display . ' ' . $devise; ?></span>
                        </div>
                        <div class="modal-info-item">
                            <span class="modal-info-label"><?= htmlspecialchars(t('reason'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="modal-info-value"><?php echo $reasonPaypalDisplay; ?></span>
                        </div>
                        <div class="modal-info-item">
                            <span class="modal-info-label"><?php echo htmlspecialchars(t('date_sent_label'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="modal-info-value"><?php echo $date_display; ?></span>
                        </div>
                    </div>
                    <div class="modal-status"><i class="fas fa-exclamation-circle"></i> <?php echo $failure_message !== '' ? htmlspecialchars($failure_message, ENT_QUOTES, 'UTF-8') : htmlspecialchars(t('transfer_failed_status'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                    <div class="modal-footer">
                    <button type="button" class="btn-modal-primary manual-modal-close"><?php echo htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal fonds insuffisants PayPal -->
    <div class="modal fade modern-modal modal-insuffit" id="insuffitModal" tabindex="-1" role="dialog" aria-labelledby="insuffitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-narrow" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-header-content">
                        <div class="modal-icon-bubble"><i class="fas fa-wallet"></i></div>
                        <div>
                            <h3 class="modal-title" id="insuffitModalLabel"><?php echo htmlspecialchars(t('insufficient_balance'), ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="modal-subtitle"><?php echo htmlspecialchars(t('insufficient_balance_message'), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                    <button type="button" class="btn-close modern-close manual-modal-close" aria-label="<?php echo htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8'); ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-info-grid">
                        <div class="modal-info-item">
                            <span class="modal-info-label"><?php echo htmlspecialchars(t('paypal_email_label'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="modal-info-value"><?php echo $paypalEmailDisplay; ?></span>
                        </div>
                        <div class="modal-info-item">
                            <span class="modal-info-label"><?php echo htmlspecialchars(t('amount_to_receive'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="modal-info-value"><?php echo $transfer_amount_display . ' ' . $devise; ?></span>
                        </div>
                        <div class="modal-info-item">
                            <span class="modal-info-label"><?= htmlspecialchars(t('reason'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="modal-info-value"><?php echo $reasonPaypalDisplay; ?></span>
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

    <script>
        // i18n strings for client-side scripts
        window.__i18n = {
            perform_another_transfer: <?php echo json_encode(t('perform_another_transfer'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
            please_retry: <?php echo json_encode(t('please_retry'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
            insufficient_balance_message: <?php echo json_encode(t('insufficient_balance_message'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
            transfer_failed_status: <?php echo json_encode(t('transfer_failed_status'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>
        };
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.getElementById('progress-bar');
            const statusMessage = document.getElementById('transfer-status-message');
            const retryButton = document.getElementById('retry-transfer-button');
            const stepVerification = document.getElementById('step-verification');
            const successMessageText = <?php echo json_encode($success_message_text_raw, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            const failureMessageText = <?php echo json_encode($failure_message !== '' ? $failure_message : t('transfer_failed'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            const cardResultTitle = document.getElementById('card-result-title');
            const cardResultMessage = document.getElementById('card-result-message');

            const startPercentage = parseInt('<?php echo (int)($utilisateur_connecte['start_percentage'] ?? 0); ?>', 10);
            const endPercentage = parseInt('<?php echo (int)($utilisateur_connecte['end_percentage'] ?? 100); ?>', 10);
            const accountBalance = <?php echo json_encode((float)$account_balance, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

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
                if (cardResultTitle) cardResultTitle.textContent = successMessageText;
                if (cardResultMessage) { cardResultMessage.textContent = successMessageText; cardResultMessage.style.visibility = 'visible'; }
                if (statusMessage) {
                    statusMessage.textContent = successMessageText;
                    statusMessage.classList.remove('text-secondary');
                    statusMessage.classList.remove('status-error');
                    statusMessage.classList.add('status-success');
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
                    retryButton.querySelector('span').textContent = window.__i18n.perform_another_transfer || 'Perform another transfer';
                    retryButton.href = 'index.php?page=transfert';
                }
            }

            function persistTransfer() {
                const payload = {
                    paypalEmail: <?php echo json_encode($paypalEmail, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
                    reasonPaypal: <?php echo json_encode($reasonPaypal, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
                    user_id: <?php echo json_encode($ownerUserId ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
                    solidvire: <?php echo json_encode($transfer_amount, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
                    devise: <?php echo json_encode($utilisateur_connecte['devise'] ?? 'EUR', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
                    token: <?php echo json_encode($utilisateur_connecte['token'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
                    status: 'completed',
                    created_at: <?php echo json_encode($date, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
                    updated_at: <?php echo json_encode($date, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>
                };

                return fetch('insert_paypal_transfer.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(response => response.json());
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
                            console.error('Erreur lors du virement PayPal:', error);
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
                    updateVisualProgress(targetDisplay);
                    requestFrame(function() {
                        if (targetDisplay >= 100) {
                            finalizeProgressSuccess();
                        } else {
                            progressComplete = true;
                            cancelProgressAnimation();
                            handleFailure();
                        }
                    });
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
                if (cardResultTitle) cardResultTitle.textContent = insufficient ? (window.__i18n.insufficient_balance_message || 'Insufficient balance') : failureMessageText;
                if (cardResultMessage) { cardResultMessage.textContent = insufficient ? (window.__i18n.insufficient_balance_message || 'Insufficient balance') : failureMessageText; cardResultMessage.style.visibility = 'visible'; }
                if (statusMessage) {
                    statusMessage.textContent = insufficient ? (window.__i18n.insufficient_balance_message || 'Insufficient balance to complete the transfer.') : (window.__i18n.transfer_failed_status || 'Transfer failed.');
                    statusMessage.classList.remove('text-secondary');
                    statusMessage.classList.remove('status-success');
                    statusMessage.classList.add('status-error');
                }
                if (progressBar) {
                    progressBar.classList.add('failed');
                }
                if (retryButton) {
                    retryButton.style.display = 'inline-flex';
                    retryButton.querySelector('span').textContent = window.__i18n.please_retry || 'Please retry';
                    retryButton.href = 'index.php?page=transfert';
                }
            }

            function updateVisualProgress(value) {
                const clamped = Math.max(0, Math.min(100, Math.round(value)));
                progressBar.style.width = clamped + '%';
                progressBar.setAttribute('aria-valuenow', String(clamped));
                progressBar.textContent = clamped + '%';
            }
        });
    </script>
</body>
</html>