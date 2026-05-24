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
$_SESSION['utilisateur_connecter'] = is_array($_SESSION['utilisateur_connecter']) ? $_SESSION['utilisateur_connecter'] : [];
$utilisateur = $_SESSION['utilisateur_connecter'];
// Récupérez l'ID du compte connecté (préférer 'compte_id')
$accountId = $utilisateur['compte_id'] ?? $utilisateur['id'] ?? null;
if ($accountId === null) {
    header('Location: index.php?page=connexion');
    exit();
}

// Récupérez l'historique via l'utilisateur propriétaire
$ownerUserId = $utilisateur['user_id'] ?? null;
$historique_transactions = $ownerUserId !== null ? getTransactionHistory($ownerUserId, $accountId) : [];
$utilisateur_connecte = getUserDetails($accountId);
if (!is_array($utilisateur_connecte)) {
    $utilisateur_connecte = [];
}
// Formater le montant du solde du compte
$account_balance = isset($utilisateur_connecte['account_balance']) ? (float) $utilisateur_connecte['account_balance'] : 0;
$formatted_balance = number_format($account_balance, 2, ',', ' ');
$devise = $utilisateur['devise'] ?? 'EUR'; // ➜ AJOUTÉ : définir la devise par défaut

?>

<body>
    <div class="modal fade" id="failureModal" tabindex="-1" role="dialog" aria-labelledby="failureModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="failureModalLabel"><?= t('transfer_failed') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?= t('insufficient_balance') ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal"><?= t('close') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard">
        <nav class="pt-2">
            <div><i class="fas fa-bars menu-icon"></i> <strong class="fs-4">TRANSFERFLUX</strong></div>
            <?php $photoUrl = getUserPhotoUrl($utilisateur_connecte ?? []); ?>
            <a href="index.php?page=info" class="icon-circle" style="display:inline-flex;align-items:center;">
                <?php if (!empty($photoUrl)): ?>
                    <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="avatar" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid rgba(0,0,0,0.06);">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </a>
        </nav>

<!-- NOUVEAU CODE CENTRAL COMMENCE ICI -->

<style>
/* ===== TEMPLATE RESPONSIVE ===== */
.your-section-name {
    --card-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    --glass-bg: rgba(255, 255, 255, 0.85);
}

.container-fluid {
    padding: 0 15px;
}

.transfer-section {
    max-width: 1200px;
    margin: 0 auto;
}

/* Styles desktop first... */

/* MOBILE : Écrans < 576px */
@media (max-width: 576px) {
    .card-modern, .info-card, .transfer-card {
        padding: 1.25rem 1rem;
    }
    
    h2, h3, h4 {
        font-size: 1.2rem; /* Titres plus petits */
    }
    
    .btn-premium {
        width: 100% !important;
        padding: 12px 20px;
        font-size: 0.9rem;
    }
    
    /* Empiler les éléments flex */
    .row {
        margin: 0 -8px;
    }
    
    .col-md-6, .col-md-10, .col-lg-6 {
        padding: 0 8px;
        width: 100%;
    }

    .premium-header {
        padding: 1.6rem 1.2rem;
        border-radius: 0 0 16px 16px;
    }

    .balance-display {
        font-size: 2rem;
    }

    /* Stepper horizontal sur mobile */
    .stepper {
        flex-wrap: nowrap;
        justify-content: space-between;
        gap: 0;
        padding: 1rem 0;
        max-width: 100%;
    }

    .step {
        flex-direction: column;
        align-items: center;
        flex: 1;
        font-size: 0.75rem;
        gap: 0.5rem;
    }

    .step span {
        display: block !important;
        text-align: center;
        white-space: nowrap;
    }

    .step-number {
        margin-right: 0;
        margin-bottom: 0.3rem;
    }

    .step-connector {
        flex: 1;
        max-width: 60px;
        margin: 0 0.5rem;
        position: relative;
        top: -12px;
    }

    .transfer-card {
        padding: 1.2rem;
        margin-bottom: 1rem;
    }

    .transfer-icon {
        width: 54px;
        height: 54px;
        font-size: 1.35rem;
    }

    .alert-modern {
        flex-direction: row;
        align-items: center;
        padding: 0.75rem;
        font-family: 'Roboto', sans-serif;
        /* rely on global sizes, but ensure minimum readability here */
        font-size: 1.00rem;
        line-height: 1.4;
    }

    .tf-input-wrap input,
    .tf-input-wrap textarea {
        font-size: 0.9rem;
        padding: 12px 8px 12px 4px;
    }
    .tf-input-icon {
        padding: 12px 8px 12px 14px;
    }

    /* Titre principal plus petit */
    .text-center h2 {
        font-size: 1.1rem;
        margin-bottom: 0.1rem;
    }

    .text-center p.text-muted {
        font-size: 0.95rem; /* augmenté pour meilleure lisibilité */
        margin-bottom: 0.1rem;
    }

    .text-center.mb-4 {
        margin-bottom: 0.3rem !important;
    }

    /* Espacement supplémentaire pour le footer */
    .dashboard {
        padding-bottom: 100px !important;
    }

    /* Assurer que tout le contenu est visible */
    .row.mb-4 {
        margin-bottom: 0.5rem !important;
    }

    .transfer-section {
        padding-bottom: 10px;
    }

    /* Réduire l'espace entre les cartes et les formulaires */
    #bankTransferForm,
    #paypalTransferForm {
        margin-top: -3rem !important;
    }

    .alert-modern {
        margin-top: 0;
        margin-bottom: 0.3rem;
    }

    /* Réduire l'espace entre les cartes de transfert */
    .transfer-card {
        margin-bottom: 0rem;
    }

    .row.mb-4 {
        margin-bottom: -1rem !important;
    }

    /* Réduire le padding des cartes */
    .transfer-card {
        padding: 0.8rem;
    }
}

@media (max-width: 420px) {
    .balance-display {
        font-size: 1.7rem;
    }

    .step {
        font-size: 0.7rem;
    }

    .step-number {
        width: 32px;
        height: 32px;
        font-size: 0.85rem;
    }

    .transfer-icon {
        width: 50px;
        height: 50px;
    }

    .tf-input-icon {
        font-size: 0.85rem;
    }

    .step-connector {
        max-width: 40px;
        margin: 0 0.25rem;
    }

    /* Meilleur espacement du contenu */
    .transfer-section {
        padding: 0 8px;
    }

    .text-center.mb-4 {
        margin-bottom: 1.5rem !important;
        padding: 0 8px;
    }

    .row.mb-4 {
        margin-bottom: 1.5rem !important;
    }

    /* Ajustements des cartes de transfert */
    .transfer-card h5 {
        font-size: 1rem;
    }

    .transfer-card p {
        font-size: 0.95rem; /* augmenter la taille des descriptions des cartes */
    }

    /* Centrer le contenu verticalement */
    .transfer-section {
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-height: calc(100vh - 200px);
    }
}

/* ===== STYLE MODERNE - INSPIRÉ DES NÉOBANQUES ===== */
.transfer-section {
    --primary-color: #6b48e7;
    --success-gradient: linear-gradient(135deg, #0f9d58 0%, #34a853 100%);
    --danger-gradient: linear-gradient(135deg, #d93025 0%, #ea4335 100%);
    --card-shadow: 0 8px 30px rgba(107, 72, 231, 0.12);
    --hover-shadow: 0 12px 40px rgba(107, 72, 231, 0.18);
    --glass-bg: rgba(255, 255, 255, 0.85);
    --glass-border: rgba(255, 255, 255, 0.4);
    /* Base typography for the whole transfer page */
    font-size: 16px; /* base size — increases most text on the page */
    line-height: 1.45;
}

/* Header Premium */
.premium-header {
    background: var(--primary-color);
    color: white;
    padding: 1.5rem 1rem;
    margin-top: 0; /* Attaché directement au menu sans espace blanc */
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px; /* Ancien modèle : arrondi seulement en bas */
    box-shadow: 0 10px 30px rgba(107, 72, 231, 0.2);
}

.balance-display {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.balance-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-bottom: 0.5rem;
}

/* Stepper */
.stepper {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 1.5rem 0;
    margin-bottom: 2rem;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #a0aec0;
    position: relative;
    gap: 0.5rem;
}

.step.active {
    color: var(--primary-color);
    font-weight: 600;
}

.step.completed {
    color: #0f9d58;
}

.step span {
    font-size: 0.9rem;
    text-align: center;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0;
    font-weight: 600;
    transition: all 0.3s ease;
}

.step.active .step-number {
    background: var(--primary-color);
    color: white;
    transform: scale(1.1);
}

.step.completed .step-number {
    background: var(--success-gradient);
    color: white;
}

.step-connector {
    width: 80px;
    height: 2px;
    background: #e2e8f0;
    margin: 0 1rem;
    position: relative;
    top: -25px;
    flex-shrink: 0;
}

.step.completed ~ .step-connector {
    background: var(--success-gradient);
}

/* Cards Glassmorphism */
.transfer-card {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--card-shadow);
    margin-bottom: 1.5rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.transfer-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--hover-shadow);
}

.transfer-card.active {
    border: 2px solid var(--primary-color);
    background: rgba(107, 72, 231, 0.05);
}

.transfer-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin: 0 auto 1rem auto;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.bank-icon {
    background: var(--primary-color);
    color: white;
    box-shadow: 0 4px 12px rgba(107, 72, 231, 0.2);
}

.paypal-icon {
    background: linear-gradient(135deg, #0070ba 0%, #003087 100%);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 12px rgba(0, 112, 186, 0.3);
}


/* Input fields with icon */
.tf-input-wrap {
    display: flex;
    align-items: center;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 0.75rem;
    background: #fff;
    overflow: hidden;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.tf-input-wrap:focus-within {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(107, 72, 231, 0.08);
}
.tf-input-icon {
    padding: 14px 10px 14px 16px;
    color: #9ca3af;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.tf-input-wrap input,
.tf-input-wrap textarea {
    flex: 1;
    border: none;
    outline: none;
    padding: 14px 8px 14px 4px;
    font-size: 0.9rem;
    color: #374151;
    background: transparent;
    min-width: 0;
}
.tf-input-wrap input::placeholder,
.tf-input-wrap textarea::placeholder {
    color: #9ca3af;
}
.tf-error-icon {
    display: none;
    padding: 14px 14px 14px 4px;
    color: #e53e3e;
    font-size: 1rem;
    flex-shrink: 0;
}
form.was-submitted .tf-input-wrap:has(input:invalid) {
    border-color: #e53e3e;
}
form.was-submitted .tf-input-wrap:has(input:invalid) .tf-error-icon {
    display: block;
}

/* Bouton Premium */
.btn-premium {
    background: var(--primary-color);
    border: none;
    padding: 14px 32px;
    font-weight: 600;
    border-radius: 12px;
    color: white;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(107, 72, 231, 0.4);
    width: 100%;
}

.btn-premium:hover {
    transform: translateY(-2px);
    background: #5b39c9 !important; /* Violet plus profond au survol */
    box-shadow: 0 8px 25px rgba(107, 72, 231, 0.6);
    color: white !important;
}

.btn-premium:disabled {
    background: #cbd5e0;
    box-shadow: none;
    cursor: not-allowed;
    transform: none;
}

/* Transfer form section card */
.vir-section-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e8e8f4;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 10px rgba(107, 72, 231, 0.06);
}
.vir-section-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin-bottom: 1.25rem;
}
.vir-section-header i {
    font-size: 1.35rem;
    color: var(--primary-color);
}
.vir-section-header h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: #1a202c;
}
.vir-field {
    margin-bottom: 0.1rem;
}
.vir-label {
    display: block;
    position: static;
    top: auto;
    left: auto;
    font-size: 0.82rem;
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 0.3rem;
    pointer-events: auto;
    transition: none;
}
.vir-required {
    color: var(--primary-color);
}
.vir-max-badge {
    display: inline-flex;
    align-items: center;
    padding: 0 12px;
    background: #f7f8fc;
    border-left: 1px solid #e2e8f0;
    color: #4a5568;
    font-size: 0.78rem;
    font-weight: 500;
    white-space: nowrap;
    flex-shrink: 0;
    height: 100%;
}
.vir-required-note {
    font-size: 0.8rem;
    color: #718096;
    margin-top: 1rem;
    margin-bottom: 0;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.vir-required-note i {
    color: var(--primary-color);
    flex-shrink: 0;
}

/* Alert Box */
.alert-modern {
    background: rgba(255, 251, 240, 0.9);
    border: 1px solid rgba(255, 234, 167, 0.5);
    border-radius: 12px;
    padding: 1rem;
    margin: 1.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Animation au chargement */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-in {
    animation: fadeInUp 0.6s ease-out;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard {
        padding: 0 12px 90px 12px;
    }

    .premium-header {
        margin: 0 -12px 20px -12px; /* Full width sur mobile (recollé aux bords) */
        border-radius: 0 0 20px 20px; /* Ancien modèle */
        padding: 1.5rem 1.25rem;
    }

    .balance-display {
        font-size: 32px;
        font-weight: 700;
    }

    .stepper {
        padding: 16px 0;
        margin-bottom: 20px;
        justify-content: space-around;
    }

    .step {
        font-size: 0.8rem;
    }

    .step-number {
        width: 36px;
        height: 36px;
        font-size: 0.9rem;
    }

    .step-connector {
        width: 60px;
        max-width: 60px;
        top: -23px;
        margin: 0 0.75rem;
    }

    .transfer-card {
        margin: 0 0 16px 0;
        padding: 20px;
    }

    .transfer-icon {
        width: 56px;
        height: 56px;
        font-size: 20px;
    }

    .form-floating input,
    .form-floating textarea {
        font-size: 16px;
        padding: 16px 12px;
    }

    .form-floating label {
        font-size: 14px;
        padding: 16px 12px;
    }

    .btn-premium {
        font-size: 16px;
        padding: 16px;
    }

    .modal-dialog {
        margin: 20px 16px;
    }

    .text-center h2 {
        font-size: 1.3rem;
    }

    .text-center p.text-muted {
        font-size: 0.9rem;
    }

    /* Assurer que les cartes sont complètement visibles */
    .row.mb-4 {
        margin-bottom: 1.5rem !important;
        padding-bottom: 20px;
    }
}
</style>

<!-- Modal fond insuffisant -->
<div class="modal fade" id="insuffitModal" tabindex="-1" role="dialog" aria-labelledby="insuffitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #d93025 0%, #ea4335 100%); color: white; padding: 2rem;">
                <h5 class="modal-title w-100 text-center" id="insuffitModalLabel">
                        <i class="fas fa-exclamation-circle" style="font-size: 3rem; display: block; margin-bottom: 1rem;"></i>
                        <?= htmlspecialchars(t('insufficient_balance'), ENT_QUOTES, 'UTF-8') ?>
                    </h5>
            </div>
            <div class="modal-body text-center p-4">
                <p><?= htmlspecialchars(t('insufficient_balance'), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mb-0"><strong><?= htmlspecialchars(t('account_balance'), ENT_QUOTES, 'UTF-8') ?> : <?= $formatted_balance ?> <?= $devise ?></strong></p>
            </div>
            <div class="modal-footer border-0 p-3">
                <button type="button" class="btn btn-danger w-100" data-dismiss="modal" id="closeInsufficientModalBtn" style="border-radius: 12px;"><?= htmlspecialchars(t('close'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </div>
</div>

<div class="transfer-section">
    <!-- Header Premium -->
    <div class="premium-header animate-in">
        <div class="text-center">
            <div class="balance-label"><?= htmlspecialchars(t('account_balance'), ENT_QUOTES, 'UTF-8') ?></div>
            <h1 class="balance-display"><?= $formatted_balance ?> <span style="font-size: 1.5rem; font-weight: 400;"><?= $devise ?></span></h1>
        </div>
    </div>

    <!-- Stepper -->
    <div class="stepper animate-in">
        <div class="step active">
            <div class="step-number">1</div>
            <span><?= t('step_details') ?></span>
        </div>
        <div class="step-connector"></div>
        <div class="step">
            <div class="step-number">2</div>
            <span><?= t('step_confirmation') ?></span>
        </div>
        <div class="step-connector"></div>
        <div class="step">
            <div class="step-number">3</div>
            <span><?= t('step_verification') ?></span>
        </div>
    </div>

    <!-- Titre principal -->
    <?php require_once __DIR__ . '/../lib/i18n.php'; ?>
    <div class="text-center mb-4 animate-in">
        <h2 class="fw-bold" style="color: #2d3748;">
            <i class="fas fa-paper-plane" style="color: var(--primary-color);"></i> <?= htmlspecialchars(t('perform_transfer'), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p class="text-muted"><?= htmlspecialchars(t('choose_method'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <!-- Options de virement -->
    <div class="row mb-4 animate-in">
        <div class="col-md-6 mb-3">
            <div class="transfer-card" id="bankCard" onclick="selectTransferType('bank')">
                <div class="transfer-icon bank-icon">
                    <i class="fas fa-university"></i>
                </div>
                <h5 class="fw-bold mb-2"><?= htmlspecialchars(t('bank_transfer'), ENT_QUOTES, 'UTF-8') ?></h5>
                <p class="text-muted small mb-0"><?= htmlspecialchars(t('bank_transfer'), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(t('iban_label'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="transfer-card" id="paypalCard" onclick="selectTransferType('paypal')">
                <div class="transfer-icon paypal-icon">
                    <i class="fab fa-paypal"></i>
                </div>
                <h5 class="fw-bold mb-2"><?= htmlspecialchars(t('paypal'), ENT_QUOTES, 'UTF-8') ?></h5>
                <p class="text-muted small mb-0"><?= htmlspecialchars(t('paypal'), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(t('pay'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </div>

    <!-- Formulaire Virement Bancaire -->
    <form id="bankTransferForm" action="index.php?page=confirmVirement&method=bank" method="post" style="display: none; margin-top: -0.5rem;" class="animate-in" novalidate>
        
        <div class="vir-section-card">
            <div class="vir-section-header">
                <i class="fas fa-university"></i>
                <h3><?= htmlspecialchars(t('virement_info_title'), ENT_QUOTES, 'UTF-8') ?></h3>
            </div>

            <div class="row g-2 mb-0">
                <div class="col-md-6">
                    <div class="vir-field">
                        <label class="vir-label"><?= htmlspecialchars(t('iban_label'), ENT_QUOTES, 'UTF-8') ?> <span class="vir-required">*</span></label>
                        <div class="tf-input-wrap">
                            <span class="tf-input-icon"><i class="fas fa-hashtag"></i></span>
                            <input type="text" id="iban" name="iban" placeholder="<?= htmlspecialchars(t('iban_placeholder'), ENT_QUOTES, 'UTF-8') ?>" required>
                            <span class="tf-error-icon"><i class="fas fa-exclamation-circle"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="vir-field">
                        <label class="vir-label"><?= htmlspecialchars(t('bic_label'), ENT_QUOTES, 'UTF-8') ?> <span class="vir-required">*</span></label>
                        <div class="tf-input-wrap">
                            <span class="tf-input-icon"><i class="fas fa-code"></i></span>
                            <input type="text" id="bic" name="bic" placeholder="<?= htmlspecialchars(t('bic_placeholder'), ENT_QUOTES, 'UTF-8') ?>" required>
                            <span class="tf-error-icon"><i class="fas fa-exclamation-circle"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="vir-field">
                <label class="vir-label"><?= htmlspecialchars(t('bank_name'), ENT_QUOTES, 'UTF-8') ?> <span class="vir-required">*</span></label>
                <div class="tf-input-wrap">
                    <span class="tf-input-icon"><i class="fas fa-university"></i></span>
                    <input type="text" id="bank_name" name="bank_name" placeholder="<?= htmlspecialchars(t('bank_name_placeholder'), ENT_QUOTES, 'UTF-8') ?>" required>
                    <span class="tf-error-icon"><i class="fas fa-exclamation-circle"></i></span>
                </div>
            </div>

            <div class="vir-field">
                <label class="vir-label"><?= htmlspecialchars(t('beneficiary_name'), ENT_QUOTES, 'UTF-8') ?> <span class="vir-required">*</span></label>
                <div class="tf-input-wrap">
                    <span class="tf-input-icon"><i class="fas fa-user"></i></span>
                    <input type="text" id="beneficiary_name" name="beneficiary_name" placeholder="<?= htmlspecialchars(t('beneficiary_placeholder'), ENT_QUOTES, 'UTF-8') ?>" required>
                    <span class="tf-error-icon"><i class="fas fa-exclamation-circle"></i></span>
                </div>
            </div>

            <div class="vir-field">
                <label class="vir-label"><?= htmlspecialchars(t('amount_label'), ENT_QUOTES, 'UTF-8') ?> <span class="vir-required">*</span></label>
                <div class="tf-input-wrap">
                    <span class="tf-input-icon"><i class="fas fa-coins"></i></span>
                    <input type="number" id="amount" name="amount" placeholder="<?= htmlspecialchars(t('amount_placeholder'), ENT_QUOTES, 'UTF-8') ?>" min="1" step="1" max="<?= (int)$account_balance ?>" required>
                    <span class="vir-max-badge">Max&nbsp;: <?= number_format($account_balance, 0, ',', ' ') ?>&nbsp;<?= htmlspecialchars($devise, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="tf-error-icon"><i class="fas fa-exclamation-circle"></i></span>
                </div>
            </div>

            <div class="vir-field">
                <label class="vir-label"><?= htmlspecialchars(t('reason'), ENT_QUOTES, 'UTF-8') ?></label>
                <div class="tf-input-wrap">
                    <span class="tf-input-icon"><i class="fas fa-comment"></i></span>
                    <input type="text" id="reason" name="reason" placeholder="<?= htmlspecialchars(t('reason_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <p class="vir-required-note"><i class="fas fa-info-circle"></i> <?= htmlspecialchars(t('required_fields_note'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <button type="submit" class="btn btn-premium mt-4" id="bankSubmitBtn">
            <i class="fas fa-arrow-right"></i> <?= htmlspecialchars(t('confirm_transfer'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </form>

    <!-- Formulaire PayPal -->
    <form id="paypalTransferForm" action="index.php?page=confirmVirementpaypal&method=paypal" method="post" style="display: none; margin-top: -0.5rem;" class="animate-in" novalidate>
        
        <div class="vir-section-card">
            <div class="vir-section-header">
                <i class="fab fa-paypal" style="color: #003087;"></i>
                <h3><?= htmlspecialchars(t('paypal'), ENT_QUOTES, 'UTF-8') ?></h3>
            </div>

            <div class="vir-field">
                <label class="vir-label"><?= htmlspecialchars(t('paypal_email_label'), ENT_QUOTES, 'UTF-8') ?> <span class="vir-required">*</span></label>
                <div class="tf-input-wrap">
                    <span class="tf-input-icon"><i class="fas fa-envelope"></i></span>
                    <input type="email" id="paypalEmail" name="paypalEmail" placeholder="exemple@email.com" required>
                    <span class="tf-error-icon"><i class="fas fa-exclamation-circle"></i></span>
                </div>
            </div>

            <div class="vir-field">
                <label class="vir-label"><?= htmlspecialchars(t('amount_label'), ENT_QUOTES, 'UTF-8') ?> <span class="vir-required">*</span></label>
                <div class="tf-input-wrap">
                    <span class="tf-input-icon"><i class="fas fa-coins"></i></span>
                    <input type="number" id="amountPaypal" name="amount" placeholder="<?= htmlspecialchars(t('amount_placeholder'), ENT_QUOTES, 'UTF-8') ?>" min="1" step="1" max="<?= (int)$account_balance ?>" required>
                    <span class="vir-max-badge">Max&nbsp;: <?= number_format($account_balance, 0, ',', ' ') ?>&nbsp;<?= htmlspecialchars($devise, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="tf-error-icon"><i class="fas fa-exclamation-circle"></i></span>
                </div>
            </div>

            <div class="vir-field">
                <label class="vir-label"><?= htmlspecialchars(t('reason'), ENT_QUOTES, 'UTF-8') ?></label>
                <div class="tf-input-wrap">
                    <span class="tf-input-icon"><i class="fas fa-comment"></i></span>
                    <input type="text" id="reasonPaypal" name="reasonPaypal" placeholder="<?= htmlspecialchars(t('reason_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <p class="vir-required-note"><i class="fas fa-info-circle"></i> <?= htmlspecialchars(t('required_fields_note'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <button type="submit" class="btn btn-premium mt-4" id="paypalSubmitBtn">
            <i class="fas fa-arrow-right"></i> <?= htmlspecialchars(t('confirm_transfer'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </form>
</div>

<script>
// Gestion de la sélection du type de virement
function selectTransferType(type) {
    // Réinitialiser les cards
    document.getElementById('bankCard').classList.remove('active');
    document.getElementById('paypalCard').classList.remove('active');
    
    // Cacher les formulaires
    document.getElementById('bankTransferForm').style.display = 'none';
    document.getElementById('paypalTransferForm').style.display = 'none';
    
    if (type === 'bank') {
        document.getElementById('bankCard').classList.add('active');
        document.getElementById('bankTransferForm').style.display = 'block';
        // Animation
        document.getElementById('bankTransferForm').classList.add('animate-in');
    } else {
        document.getElementById('paypalCard').classList.add('active');
        document.getElementById('paypalTransferForm').style.display = 'block';
        // Animation
        document.getElementById('paypalTransferForm').classList.add('animate-in');
    }
    
    // Mettre à jour le stepper
    document.querySelectorAll('.step')[0].classList.add('completed');
    document.querySelectorAll('.step')[1].classList.add('active');
}

// Smooth-scroll vers le formulaire sélectionné et placer le focus sur le premier champ
function scrollToForm(type) {
    var form = (type === 'bank') ? document.getElementById('bankTransferForm') : document.getElementById('paypalTransferForm');
    if (!form) return;
    // small delay so CSS animations can start and layout stabilizes
    setTimeout(function () {
        try {
            var rect = form.getBoundingClientRect();
            var headerOffset = 80; // adjust if you have a fixed header
            var targetY = window.pageYOffset + rect.top - headerOffset;
            window.scrollTo({ top: targetY, behavior: 'smooth' });
            // focus the first input inside the form for faster data entry
            var firstInput = form.querySelector('input, textarea, select');
            if (firstInput) {
                firstInput.focus({ preventScroll: true });
            }
        } catch (e) {
            // fallback to simple scrollIntoView
            try { form.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (_) {}
        }
    }, 180);
}

// Update selectTransferType to scroll after showing the form
function selectTransferType(type) {
    // Réinitialiser les cards
    document.getElementById('bankCard').classList.remove('active');
    document.getElementById('paypalCard').classList.remove('active');
    
    // Cacher les formulaires
    document.getElementById('bankTransferForm').style.display = 'none';
    document.getElementById('paypalTransferForm').style.display = 'none';
    
    if (type === 'bank') {
        document.getElementById('bankCard').classList.add('active');
        document.getElementById('bankTransferForm').style.display = 'block';
        // Animation
        document.getElementById('bankTransferForm').classList.add('animate-in');
    } else {
        document.getElementById('paypalCard').classList.add('active');
        document.getElementById('paypalTransferForm').style.display = 'block';
        // Animation
        document.getElementById('paypalTransferForm').classList.add('animate-in');
    }
    
    // Mettre à jour le stepper
    document.querySelectorAll('.step')[0].classList.add('completed');
    document.querySelectorAll('.step')[1].classList.add('active');

    // Smooth scroll to the newly revealed form
    scrollToForm(type);
}

// Validation en temps réel
['iban', 'bic', 'bank_name', 'beneficiary_name', 'reason'].forEach(fieldId => {
    const field = document.getElementById(fieldId);
    if (field) {
        field.addEventListener('blur', function() {
            if (this.value.trim() === '') {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    }
});

// Vérification du solde avant soumission
document.getElementById('bankSubmitBtn')?.addEventListener('click', function(e) {
    if (<?= $account_balance ?> <= 0) {
        e.preventDefault();
        $('#insuffitModal').modal('show');
    }
});

document.getElementById('paypalSubmitBtn')?.addEventListener('click', function(e) {
    if (<?= $account_balance ?> <= 0) {
        e.preventDefault();
        $('#insuffitModal').modal('show');
    }
});

// Animation au chargement
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.animate-in');
    elements.forEach((el, index) => {
        el.style.animationDelay = `${index * 0.1}s`;
    });

    // Gestionnaire pour le bouton Fermer du modal
    const closeModalBtn = document.getElementById('closeInsufficientModalBtn');
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function() {
            $('#insuffitModal').modal('hide');
        });
    }
});
</script>

<!-- NOUVEAU CODE CENTRAL SE TERMINE ICI -->

        <?php include __DIR__ . '/../partials/footer_nav.php'; ?>
    </div>

    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var bankTransferOption = document.getElementById('bankTransferOption');
            var paypalTransferOption = document.getElementById('paypalTransferOption');
            var bankTransferForm = document.getElementById('bankTransferForm');
            var paypalTransferForm = document.getElementById('paypalTransferForm');

            bankTransferOption.addEventListener('click', function() {
                bankTransferForm.style.display = 'block';
                paypalTransferForm.style.display = 'none';
            });

            paypalTransferOption.addEventListener('click', function() {
                bankTransferForm.style.display = 'none';
                paypalTransferForm.style.display = 'block';
            });

            bankTransferForm.addEventListener('submit', function(event) {
                bankTransferForm.classList.add('was-submitted');
                if (!bankTransferForm.checkValidity()) {
                    event.preventDefault();
                }
            });

            paypalTransferForm.addEventListener('submit', function(event) {
                paypalTransferForm.classList.add('was-submitted');
                if (!paypalTransferForm.checkValidity()) {
                    event.preventDefault();
                }
            });
        });

    </script>
</body>

</html>