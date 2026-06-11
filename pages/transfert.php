<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

// Liste des opérateurs Mobile Money avec leurs logos
$mobileMoneyOperators = [
    'mtn' => [
        'name' => 'MTN Money',
        'logo' => 'image/MTN.jpg',
        'color' => 'linear-gradient(135deg, #FFCC00 0%, #FF9900 100%)',
        'bg_color' => 'rgba(255, 204, 0, 0.1)'
    ],
    'moov' => [
        'name' => 'Moov Money',
        'logo' => 'image/Moov.jpg',
        'color' => 'linear-gradient(135deg, #0066CC 0%, #004499 100%)',
        'bg_color' => 'rgba(0, 102, 204, 0.1)'
    ],
    'orange' => [
        'name' => 'Orange Money',
        'logo' => 'image/Orange.jpg',
        'color' => 'linear-gradient(135deg, #FF6600 0%, #FF3300 100%)',
        'bg_color' => 'rgba(255, 102, 0, 0.1)'
    ],
    'wave' => [
        'name' => 'Wave',
        'logo' => 'image/Wave.jpg',
        'color' => 'linear-gradient(135deg, #00D9FF 0%, #00A8CC 100%)',
        'bg_color' => 'rgba(0, 217, 255, 0.1)'
    ],
    'airtel' => [
        'name' => 'Airtel Money',
        'logo' => 'image/Airtel.jpg',
        'color' => 'linear-gradient(135deg, #FF0000 0%, #CC0000 100%)',
        'bg_color' => 'rgba(255, 0, 0, 0.1)'
    ],
    'mvola' => [
        'name' => 'MVola',
        'logo' => 'image/MVola.webp',
        'color' => 'linear-gradient(135deg, #FFD700 0%, #FFA500 100%)',
        'bg_color' => 'rgba(255, 215, 0, 0.1)'
    ],
    'mpesa' => [
        'name' => 'M-Pesa',
        'logo' => 'image/m-pesa.webp',
        'color' => 'linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%)',
        'bg_color' => 'rgba(76, 175, 80, 0.1)'
    ]
];

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
        font-size: 1.2rem;
    }
    .btn-premium {
        width: 100% !important;
        padding: 12px 20px;
        font-size: 0.9rem;
    }
    .row.mb-4 {
        display: flex;
        flex-wrap: wrap;
        margin-left: -8px;
        margin-right: -8px;
    }
    .col-md-6 {
        width: 50%;
        padding-left: 8px;
        padding-right: 8px;
        box-sizing: border-box;
    }
    .col-lg-4 {
        width: 50%;
        padding-left: 8px;
        padding-right: 8px;
        box-sizing: border-box;
    }
    .col-md-10, .col-lg-6 {
        width: 100%;
        padding: 0 8px;
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
        font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        /* rely on global sizes, but ensure minimum readability here */
        font-size: 1.00rem;
        line-height: 1.4;
    }

    .form-floating label {
        font-size: 0.85rem;
    }

    .form-floating input,
    .form-floating textarea {
        font-size: 0.95rem;
        padding: 0.9rem 0.75rem;
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
    #mobileMoneyForm,
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

    .form-floating label {
        font-size: 0.8rem;
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
    margin-top: 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
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

/* === LOGOS OPERATORS CSS === */
.transfer-icon {
    width: 70px;
    height: 70px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem auto;
    overflow: hidden;
    transition: all 0.3s ease;
    /* Ombre portée pour effet de profondeur */
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Style pour l'image dans le conteneur */
.transfer-icon img {
    max-width: 85%;
    max-height: 85%;
    object-fit: contain;
    border-radius: 12px;
    background: white; /* Fond blanc pour que le logo se détache */
    padding: 4px; /* Légère marge intérieure */
}

/* Dégradés de fond pour chaque opérateur (comme PayPal) */
.mobile-money-mtn {
    background: linear-gradient(135deg, #FFCC00 0%, #FF9900 100%);
}

.mobile-money-moov {
    background: linear-gradient(135deg, #0066CC 0%, #004499 100%);
}

.mobile-money-orange {
    background: linear-gradient(135deg, #FF6600 0%, #FF3300 100%);
}

.mobile-money-wave {
    background: linear-gradient(135deg, #00D9FF 0%, #00A8CC 100%);
}

.mobile-money-airtel {
    background: linear-gradient(135deg, #FF0000 0%, #CC0000 100%);
}

.mobile-money-mvola {
    background: white;
}

.mobile-money-mvola img {
    background: transparent;
    border: 4px solid #FFD700;
    border-radius: 14px;
    padding: 4px;
    max-width: 80%;
    max-height: 80%;
}

.mobile-money-mpesa {
    background: white;
}

.mobile-money-mpesa img {
    background: transparent;
    border: 4px solid #4CAF50;
    border-radius: 14px;
    padding: 4px;
    max-width: 80%;
    max-height: 80%;
}

.paypal-icon {
    background: linear-gradient(135deg, #0070ba 0%, #003087 100%);
    color: white;
    font-size: 2.5rem; /* Taille de l'icône PayPal */
}

/* Floating Labels */
.form-floating {
    position: relative;
    margin-bottom: 1.5rem;
}

.form-floating input,
.form-floating textarea {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: none;
    padding: 1rem 0.75rem;
    height: auto;
    transition: all 0.3s ease;
    color: #1a202c;
    font-weight: 500;
}

.form-floating input:focus,
.form-floating textarea:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-floating label {
    padding: 1rem 0.75rem;
    color: #718096;
    transition: all 0.2s ease;
    pointer-events: none;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    width: auto;
    max-width: calc(100% - 1rem);
}

.form-floating input:focus ~ label,
.form-floating input:not(:placeholder-shown) ~ label,
.form-floating textarea:focus ~ label,
.form-floating textarea:not(:placeholder-shown) ~ label {
    transform: scale(0.72) translateY(-3.05rem) translateX(0.15rem);
    color: #667eea;
    background: rgba(255,255,255,0.3);
    padding: 0 0.3rem;
    line-height: 1;
    border-radius: 0.5rem;
}

/* Bouton Premium */
.btn-premium {
    background: #6b48e7;
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
    background: #5b39c9 !important;
    box-shadow: 0 8px 25px rgba(107, 72, 231, 0.6);
    color: white !important;
}

.btn-premium:disabled {
    background: #cbd5e0;
    box-shadow: none;
    cursor: not-allowed;
    transform: none;
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
        margin: 0 -12px 20px -12px;
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
            <i class="fas fa-paper-plane" style="color: #667eea;"></i> Effectuer un transfert
        </h2>
        <p class="text-muted"><?= htmlspecialchars(t('choose_method'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <!-- Options de transfert -->
    <div class="row mb-4 animate-in">
        <?php foreach ($mobileMoneyOperators as $key => $operator): ?>
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="transfer-card" id="card-<?= $key ?>" onclick="selectTransferType('<?= $key ?>')">
                <div class="transfer-icon mobile-money-<?= $key ?>">
                    <img src="<?= htmlspecialchars($operator['logo'], ENT_QUOTES, 'UTF-8') ?>" 
                         alt="<?= htmlspecialchars($operator['name'], ENT_QUOTES, 'UTF-8') ?>" 
                         style="max-width:85%;max-height:85%;object-fit:contain;border-radius:10px;">
                </div>
                <h5 class="fw-bold mb-2"><?= htmlspecialchars($operator['name']) ?></h5>
                <p class="text-muted small mb-0">Transfert Mobile Money</p>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="transfer-card" id="card-paypal" onclick="selectTransferType('paypal')">
                <div class="transfer-icon paypal-icon">
                    <i class="fab fa-paypal"></i>
                </div>
                <h5 class="fw-bold mb-2"><?= htmlspecialchars(t('paypal')) ?></h5>
                <p class="text-muted small mb-0">PayPal — <?= htmlspecialchars(t('pay')) ?></p>
            </div>
            </div>
            <!-- Formulaire PayPal -->
            <form id="paypalTransferForm" action="index.php?page=confirmVirementpaypal&method=paypal" method="post" style="display: none; margin-top: -0.5rem;" class="animate-in">
                <div class="alert-modern">
                    <i class="fab fa-paypal" style="color: #003087;"></i>
                    <div>
                        <strong>Paiement PayPal</strong><br>
                        Temps de traitement : instantané <span class="badge bg-success">Gratuit</span>
                    </div>
                </div>
                <div class="form-floating">
                    <input type="email" class="form-control" id="paypalEmail" name="paypalEmail" placeholder="Adresse e-mail PayPal" required>
                    <label for="paypalEmail"><i class="fas fa-envelope"></i> Adresse e-mail PayPal</label>
                </div>
                <div class="form-floating">
                    <textarea class="form-control" id="reasonPaypal" name="reasonPaypal" placeholder="Motif de transfert" style="height: 48px;resize:vertical;" required></textarea>
                    <label for="reasonPaypal"><i class="fas fa-comment"></i> Motif de transfert</label>
                </div>
                <button type="submit" class="btn-premium mt-3" id="paypalSubmitBtn">
                    <i class="fas fa-arrow-right"></i> Confirmer le transfert
                </button>
            </form>
        </div>
    </div>

    <!-- Formulaire Mobile Money -->
    <form id="mobileMoneyForm" action="index.php?page=confirmVirement" method="post" style="display: none;">
        <input type="hidden" name="operator" id="selectedOperator" value="">
        <input type="hidden" name="method" value="mobilemoney">

        <div class="alert-modern">
            <i class="fas fa-info-circle" style="color: #667eea;"></i>
            <div>
                <strong>Informations</strong><br>
                Temps de traitement : instantané <span class="badge bg-success">Gratuit</span>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-floating">
                    <input type="text" class="form-control" id="nom" name="nom" placeholder="Nom" required>
                    <label for="nom"><i class="fas fa-user"></i> Nom</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-floating">
                    <input type="text" class="form-control" id="prenom" name="prenom" placeholder="Prénom" required>
                    <label for="prenom"><i class="fas fa-user"></i> Prénom</label>
                </div>
            </div>
        </div>

        <div class="form-floating">
            <input type="tel" class="form-control" id="mobile_number" name="mobile_number" placeholder="+226 00000000" required>
            <label for="mobile_number"><i class="fas fa-mobile-alt"></i> Numéro Mobile Money (+226 00000000)</label>
        </div>

        <div class="form-floating">
            <textarea class="form-control" id="reason" name="reason" placeholder="Motif du transfert" style="height: 48px;resize:vertical;" required></textarea>
            <label for="reason"><i class="fas fa-comment"></i> Motif du transfert</label>
        </div>

        <button type="submit" class="btn-premium mt-3">
            <i class="fas fa-arrow-right"></i> Confirmer le transfert
        </button>
    </form>
</div>

<?php include __DIR__ . '/../partials/footer_nav.php'; ?>

<script>
function selectTransferType(type) {
    // Réinitialiser les cartes
    document.querySelectorAll('.transfer-card').forEach(card => card.classList.remove('active'));

    // Masquer les deux formulaires
    document.getElementById('mobileMoneyForm').style.display = 'none';
    document.getElementById('paypalTransferForm').style.display = 'none';

    // Activer la carte sélectionnée et afficher le bon formulaire
    if (type === 'paypal') {
        document.getElementById('card-paypal').classList.add('active');
        document.getElementById('paypalTransferForm').style.display = 'block';
        document.getElementById('paypalTransferForm').classList.add('animate-in');
    } else {
        document.getElementById('card-' + type).classList.add('active');
        document.getElementById('mobileMoneyForm').style.display = 'block';
        document.getElementById('selectedOperator').value = type;
        document.getElementById('mobileMoneyForm').classList.add('animate-in');
    }

    // Mettre à jour le stepper
    document.querySelectorAll('.step')[0].classList.add('completed');
    document.querySelectorAll('.step')[1].classList.add('active');

    // Scroll vers le formulaire
    document.getElementById(type === 'paypal' ? 'paypalTransferForm' : 'mobileMoneyForm').scrollIntoView({ behavior: 'smooth' });
}
</script>
</body>
</html>