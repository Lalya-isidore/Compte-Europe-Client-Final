<?php
$erreurs = [];
$donnees = [];
$success = '';
$erreur = '';

// Vérifiez si l'utilisateur est connecté
if (!isset($_SESSION['utilisateur_connecter']) || empty($_SESSION['utilisateur_connecter'])) {
    header('Location: index.php?page=connexion');
    exit();
}
// 1) session user (minimal)
$utilisateur = $_SESSION['utilisateur_connecter'];

// 2) Récupérez les IDs (compte et propriétaire) AVANT utilisation
$accountId = $utilisateur['compte_id'] ?? $utilisateur['id'] ?? null;
$ownerUserId = $utilisateur['user_id'] ?? null;

// 3) Récupérer les informations complètes du compte (peut contenir card_number, created_at, etc.)
$utilisateur_connecte = $accountId !== null ? getUserDetails($accountId) : [];
if (!is_array($utilisateur_connecte)) {
    $utilisateur_connecte = [];
}

// 4) Récupérez l'historique des transactions pour ce compte
$historique_transactions = $ownerUserId !== null ? getTransactionHistory($ownerUserId, $accountId) : [];

// Ajoutez deux ans à la date de création du compte, en étant tolérant aux valeurs manquantes
$created_at = $utilisateur_connecte['created_at'] ?? $utilisateur['created_at'] ?? null; // expected 'Y-m-d H:i:s'
$futureDate = '';
if (!empty($created_at)) {
    try {
        $date = new DateTime($created_at);
        $date->add(new DateInterval('P2Y'));
        $futureDate = $date->format('m/Y');
    } catch (Exception $e) {
        $futureDate = '';
    }
}

// Formater le solde pour le modal insuffisant
$account_balance = isset($utilisateur_connecte['account_balance']) ? (float) $utilisateur_connecte['account_balance'] : 0;
$formatted_balance = number_format($account_balance, 0, ',', ' ');
$devise = $utilisateur['devise'] ?? 'EUR';

?>

<body>
    <div class="dashboard">
        <nav>
            <div><i class="fas fa-bars menu-icon"></i> <strong class="fs-4">TRANSFERFLUX</strong></div>
            <?php $photoUrl = getUserPhotoUrl($utilisateur_connecte ?? $utilisateur ?? []); ?>
            <a href="index.php?page=info" class="icon-circle" style="display:inline-flex;align-items:center;">
                <?php if ($photoUrl): ?>
                    <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="avatar" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid rgba(0,0,0,0.06);">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </a>
        </nav>

<!-- CODE FINAL - LIGNES RÉSEAU UNIQUEMENT -->

<style>
/* ===== TEMPLATE RESPONSIVE ===== */
.your-section-name {
    --card-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    --glass-bg: rgba(255, 255, 255, 0.85);
}

.container-fluid {
    padding: 0 15px;
}

/* Styles desktop first... */

/* MOBILE : Écrans < 576px */
@media (max-width: 576px) {
    .container-fluid {
        padding: 0;
    }

    .card-modern, .info-card, .transfer-card {
        padding: 1.25rem 0.5rem;
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
        margin: 0;
    }
    
    .col-md-6, .col-md-10, .col-lg-6 {
        padding: 0;
        width: 100%;
        max-width: 100%;
    }

    .row {
        margin-left: 0;
        margin-right: 0;
    }

    .alert-premium {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .card-modern {
        padding: 0.5rem 0;
    }

    .credit-card {
        padding: 1.5rem;
        height: auto;
        aspect-ratio: auto;
        border-radius: 16px;
        width: 100%;
    }

    .card-number {
        font-family: 'Space Grotesk', sans-serif !important;
        font-size: 1.25rem;
        font-weight: 500;
        margin: 0.4rem 0 0.8rem 0;
        display: flex;
        justify-content: space-between;
        width: 100%;
        letter-spacing: normal;
    }

    .btn-premium + .btn-premium {
        margin-top: 0.75rem;
    }
}

@media (max-width: 420px) {
    .credit-card { padding: 1.3rem; aspect-ratio: auto; }
    .card-number { font-size: 1.05rem; margin: 0.2rem 0 0.8rem 0; display: flex; justify-content: space-between; width: 100%; letter-spacing: normal; }
    .card-name { font-size: 0.9rem; }
    .card-value { font-size: 0.9rem;
    }

    .card-visa {
        font-size: 1.3rem;
    }

    .btn-premium {
        font-size: 0.92rem;
    }
}

/* ===== STYLE MODERNE - INSPIRÉ DES NÉOBANQUES ===== */
.carte-section {
    --primary-color: #6b48e7;
    --success-gradient: linear-gradient(135deg, #0f9d58 0%, #34a853 100%);
    --danger-gradient: linear-gradient(135deg, #d93025 0%, #ea4335 100%);
    --card-shadow: 0 20px 60px rgba(107, 72, 231, 0.25);
    --hover-shadow: 0 25px 70px rgba(107, 72, 231, 0.35);
}

/* Alert Premium */
.alert-premium {
    background: rgba(255, 251, 240, 0.9);
    border: 1px solid rgba(255, 234, 167, 0.5);
    border-radius: 12px;
    padding: 1rem;
    margin: 1.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    backdrop-filter: blur(10px);
}

.btn-premium {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 0.8rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    width: 100%;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

/* Ensure premium alerts use site font and readable sizes */
.alert-premium .alert-title {
    font-family: 'Roboto', sans-serif;
    font-size: 1.03rem;
    font-weight: 700;
}
.alert-premium .alert-message {
    font-family: 'Roboto', sans-serif;
    font-size: 1.00rem;
    line-height: 1.45;
    font-weight: 500;
}

/* Carte container */
.card-modern {
    background: transparent;
    border: none;
    border-radius: 0;
    padding: 0;
    box-shadow: none;
}

/* Design carte bancaire - Copie fidèle Movicredo */
.credit-card {
    position: relative;
    width: 100%;
    aspect-ratio: 1.586;
    border-radius: 20px;
    padding: 1.5rem 1.5rem 2rem 1.5rem;
    color: white;
    background: #6b48e7;
    box-shadow: 0 20px 60px rgba(107, 72, 231, 0.3);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 220px;
}
.credit-card::before {
    display: none; /* Supprime tout reflet ou halo radial */
}

/* Header : Banque + VISA */
.card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 1;
    margin-bottom: 0.2rem;
}
.card-bank-name {
    font-family: 'Montserrat', sans-serif;
    font-weight: 900;
    font-size: 1.2rem;
    letter-spacing: 0.5px;
    font-style: italic;
    color: #FFFFFF;
}
.card-visa {
    font-size: 1.5rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: 2px;
}

/* Puce EMV réaliste en SVG */
.card-chip {
    width: 40px;
    height: 32px;
    z-index: 1;
    margin: 4px 0 8px 0;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

/* Numéro de carte */
.card-number {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.45rem;
    font-weight: 500;
    margin: 0.1rem 0 0.1rem 0;
    white-space: nowrap;
    position: relative;
    z-index: 1;
    color: #ffffff;
    -webkit-font-smoothing: antialiased;
    width: 100%;
    display: flex;
    justify-content: space-between; /* Perfect horizontal distribution */
    letter-spacing: normal; /* Managed by flex-between now */
}

/* Footer de la carte */
.card-footer-zone {
    display: grid;
    grid-template-columns: 1.8fr 1fr 1.2fr; /* Ajusté pour laisser un peu plus de place aux chiffres sur mobile */
    grid-template-rows: auto auto;
    position: relative;
    z-index: 1;
    margin-top: auto;
    padding-top: 0.5rem;
    column-gap: 0.75rem; /* Gap réduit sur mobile */
    align-items: baseline;
}
@media (min-width: 400px) {
    .card-footer-zone {
        grid-template-columns: 2fr 1fr 1.2fr;
        column-gap: 1.5rem;
    }
}
.card-footer-left, .card-footer-center, .card-footer-right, .card-info-item {
    display: contents;
}
/* Placement manuel et alignement horizontal précis */
.card-footer-left .card-label { grid-column: 1; grid-row: 1; justify-self: start; }
.card-footer-left .card-name  { grid-column: 1; grid-row: 2; justify-self: start; }

.card-footer-center .card-label { grid-column: 2; grid-row: 1; justify-self: center; }
.card-footer-center .card-value { grid-column: 2; grid-row: 2; justify-self: center; }

.card-footer-right .card-label { grid-column: 3; grid-row: 1; justify-self: end; }
.card-footer-right .card-value { grid-column: 3; grid-row: 2; justify-self: end; }
.card-info-item {
    /* display: contents gère l'alignement via la grille parente */
}
.card-label {
    font-family: 'Inter', sans-serif;
    font-size: 0.6rem;
    text-transform: uppercase;
    font-weight: 700;
    opacity: 0.9;
    letter-spacing: 0.12rem;
    color: rgba(255, 255, 255, 0.95);
    margin-bottom: 4px; /* Espace constant entre libellé et valeur */
}
.card-name {
    font-family: 'Inter', sans-serif;
    font-size: 0.95rem; /* Réduit par défaut */
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.02rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1;
    max-width: 100%; /* Empêche tout débordement */
}
@media (min-width: 400px) {
    .card-name {
        font-size: 1.1rem;
    }
}
.card-value {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 0.95rem; /* Réduit par défaut */
    font-weight: 700;
    line-height: 1;
}
@media (min-width: 400px) {
    .card-value {
        font-size: 1.1rem;
    }
}

/* BOUTONS CORRIGÉS - TOUJOURS COLORÉS */
.btn-premium {
    border: none;
    padding: 14px 32px;
    font-weight: 600;
    border-radius: 12px;
    color: white;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    width: 100%;
    margin-bottom: 1rem;
}

/* FORCER les couleurs de fond pour éviter le blanc Bootstrap */
.btn-success {
    background: var(--success-gradient) !important;
}

.btn-danger {
    background: var(--danger-gradient) !important;
}

/* EFFET AU SURVOL lumineux */
.btn-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    color: white;
    filter: brightness(1.1); /* Légère augmentation de luminosité */
}

/* Désactivé */
.btn-premium:disabled {
    background: #cbd5e0 !important;
    box-shadow: none;
    cursor: not-allowed;
    transform: none;
    filter: none;
}

/* Transactions */
.transaction-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    color: #4a5568;
}

.transaction-header h5 {
    margin: 0;
    font-weight: 600;
}

/* Loader */
.loader-modern {
    width: 40px;
    height: 40px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 2rem auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Animation au chargement */
@-webkit-keyframes fadeInUp {
    from { opacity: 0; -webkit-transform: translateY(30px); transform: translateY(30px); }
    to   { opacity: 1; -webkit-transform: translateY(0);    transform: translateY(0); }
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
}

.animate-in {
    -webkit-animation: fadeInUp 0.6s ease-out both;
    animation: fadeInUp 0.6s ease-out both;
    -webkit-animation-fill-mode: both;
    animation-fill-mode: both;
}

/* Responsive */
@media (max-width: 768px) {
    .premium-header {
        margin: 0 -16px 20px -16px;
        border-radius: 0 0 20px 20px;
        padding: 24px 20px;
    }

    .credit-card {
        padding: 24px;
        margin-bottom: 20px;
    }
    
    .card-number {
        font-size: 20px;
        letter-spacing: 2px;
    }
    
    .card-footer-zone {
        gap: 0;
    }
    .card-footer-right {
        gap: 1rem;
    }
    
    .card-visa {
        font-size: 22px;
    }

    .info-card {
        margin-bottom: 16px;
        padding: 20px;
    }

    .btn-premium {
        font-size: 16px;
        padding: 16px;
    }

    .transaction-item {
        padding: 16px;
        margin-bottom: 12px;
    }
}
/* ===== FOOTER ===== */
        footer {
            position: fixed;
            display: flex;
            flex-wrap: nowrap;
            max-width: 840px;
            width: calc(100% - 80px);
            left: 50%;
            transform: translateX(-50%);
            height: 64px;
            border-radius: 20px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .06);
            border: 1px solid #e9f2ff;
            bottom: 16px;
            overflow: hidden;
            background-color: white;
            z-index: 1000;
            padding: 0 12px;
            box-sizing: border-box;
        }
        @media screen and (max-width: 800px) {
            footer {
                left: 20px;
                right: 20px;
                transform: none;
                width: auto;
                border-radius: 16px;
                bottom: 14px;
            }
        }
        @media screen and (max-width: 768px) {
            footer {
                width: calc(100% - 24px);
                max-width: 100%;
                left: 12px;
                right: 12px;
                transform: none;
                bottom: 12px;
                padding: 10px 8px;
                border-radius: 24px;
                height: auto;
            }
            footer a {
                padding: 8px 6px;
                font-size: 1.05rem;
                font-weight: 700;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }
            footer a i {
                font-size: 1.6rem;
                height: auto;
                margin-bottom: 4px;
                line-height: 1;
            }
        }
        @media screen and (max-width: 576px) {
            footer {
                padding: 8px 6px;
                bottom: 10px;
                border-radius: 20px;
            }
            footer a {
                font-size: 0.88rem;
                padding: 8px 6px;
                line-height: 1.1;
                white-space: normal;
                font-weight: 700;
            }
            footer a i {
                font-size: 1.35rem;
            }
            footer a.active {
                border-bottom-width: 2.5px;
            }
        }
        @media screen and (max-width: 480px) {
            footer a {
                font-size: 1.1rem;
                padding: 10px 6px;
            }
            footer a i {
                font-size: 1.8rem;
                margin-bottom: 6px;
            }
        }
        @media screen and (min-width: 901px) {
            footer {
                width: min(840px, calc(100% - 80px));
                left: 50%;
                transform: translateX(-50%);
            }
        }
        footer a i {
            display: block;
            color: #007bff;
            margin-bottom: 6px;
            font-size: 1.5rem;
            line-height: 1;
            height: 26px;
        }
        footer, footer a, footer a i {
            font-family: var(--ui-font);
        }
        footer a {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            width: 25%;
            text-align: center;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding: 11px 6px;
            transition: all 120ms ease;
            color: #1f2937;
            position: relative;
            font-weight: 700;
        }
        footer a:hover { background-color: #ccebf5; }
        footer a, footer a:hover, footer a.active { background: none !important; }
        footer a.active { border-bottom: 3px solid #6f63ff; }
        .footer-show a i {
            font-size: 1.41rem !important;
            height: auto;
            color: #6b7280 !important;
            margin-bottom: 4px;
        }
        .footer-show a {
            color: #6b7280 !important;
            flex: 1 1 0%;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .footer-show a.active i, .footer-show a.active { color: #6f63ff !important; }
        footer.footer-show {
            max-width: 780px;
            width: min(780px, calc(100% - 120px));
            left: 50%;
            transform: translateX(-50%);
            bottom: 12px;
        }
        @media (max-width: 800px) {
            footer.footer-show { left: 20px; right: 20px; transform: none; width: auto; }
        }
        @media (max-width: 768px) {
            footer.footer-show { width: calc(100% - 24px); left: 12px; right: 12px; bottom: 12px; border-radius: 25px; }
        }
        @media (max-width: 576px) {
            footer.footer-show { padding: 8px 6px; bottom: 10px; border-radius: 25px; }
            footer.footer-show a div { font-size: 0.73rem !important; }
            footer.footer-show a i { font-size: 1.6rem; }
        }
        @media screen and (max-width: 768px) {
            footer a { font-size: 0.81rem; }
            footer a i { font-size: 1.9rem; margin-bottom: 4px; }
        }
        @media screen and (max-width: 480px) {
            footer a { font-size: 0.85rem; }
            footer a i { font-size: 2.0rem; margin-bottom: 4px; }
        }

/* ===== LIGNES RÉSEAU EN HAUT UNIQUEMENT ===== */

/* Lignes décoratives réseau en haut */
.card-network-lines {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: rgba(255,255,255,0.15);
    overflow: hidden;
    z-index: 2;
}

.card-network-lines::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 200%;
    height: 100%;
    background: repeating-linear-gradient(
        90deg,
        rgba(255,255,255,0.4) 0px,
        rgba(255,255,255,0.4) 3px,
        transparent 3px,
        transparent 12px
    );
    animation: networkSlide 4s linear infinite;
}

@keyframes networkSlide {
    0% { transform: translateX(0); }
    100% { transform: translateX(48px); }
}
</style>

<!-- Modal fond insuffisant -->
<div class="modal fade" id="insuffitModal" tabindex="-1" role="dialog" aria-labelledby="insuffitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #d93025 0%, #ea4335 100%); color: white; padding: 2rem;">
                <h5 class="modal-title w-100 text-center" id="insuffitModalLabel">
                    <i class="fas fa-exclamation-circle" style="font-size: 3rem; display: block; margin-bottom: 1rem;"></i>
                    <?php echo htmlspecialchars(t('insufficient_balance'), ENT_QUOTES, 'UTF-8'); ?>
                </h5>
            </div>
            <div class="modal-body text-center p-4">
                <p><?php echo htmlspecialchars(t('insufficient_balance_message'), ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="mb-0"><strong>Montant disponible : <?= $formatted_balance ?> <?= $devise ?></strong></p>
            </div>
                <div class="modal-footer border-0 p-3">
                <button type="button" class="btn btn-light w-100" data-dismiss="modal" style="border-radius: 12px;"><?php echo htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid carte-section">
    <!-- Alert Premium -->
    <div class="alert-premium animate-in">
        <i class="fas fa-info-circle" style="color: #667eea; font-size: 1.5rem;"></i>
        <div>
            <strong><?= htmlspecialchars(t('card_alert_title'), ENT_QUOTES, 'UTF-8') ?></strong> <?= htmlspecialchars(t('card_alert_message'), ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <!-- Carte et actions -->
    <div class="row">
        <div class="col-lg-6 mb-4 animate-in">
            <div class="card-modern">
                <h5 class="fw-bold mb-3" style="color: #4a5568;"><?= htmlspecialchars(t('card_section_title'), ENT_QUOTES, 'UTF-8') ?></h5>
                
                <!-- Carte visuelle -->
                <?php
                    $rawCardNum = $utilisateur_connecte['card_number'] ?? $utilisateur['card_number'] ?? '•••• •••• •••• ••••';
                    // Masquer le numéro : garder les 4 premiers et 4 derniers, masquer le reste
                    $cleanNum = preg_replace('/[^0-9•]/', '', $rawCardNum);
                    if (strlen($cleanNum) >= 16 && strpos($cleanNum, '•') === false) {
                        $maskedNum = substr($cleanNum, 0, 4) . '  ' . substr($cleanNum, 4, 4) . '  ' . str_repeat('•', 4) . '  ' . substr($cleanNum, -4);
                    } else {
                        $maskedNum = $rawCardNum;
                    }
                    $fullNum = substr($cleanNum, 0, 4) . '  ' . substr($cleanNum, 4, 4) . '  ' . substr($cleanNum, 8, 4) . '  ' . substr($cleanNum, 12, 4);
                    $prenom = trim($utilisateur_connecte['prenom'] ?? $utilisateur['prenom'] ?? '');
                    $nom = trim($utilisateur_connecte['nom'] ?? $utilisateur['nom'] ?? '');
                    $holderName = strtoupper(mb_substr($prenom, 0, 1) . '. ' . $nom);
                    $cvvVal = $utilisateur_connecte['cvv'] ?? $utilisateur['cvv'] ?? '***';
                ?>
                <div class="credit-card">
                        <div class="card-top">
                            <div class="card-bank-name">TRANSFERFLUX</div>
                            <div class="card-visa">VISA</div>
                        </div>

                        <!-- Puce EMV SVG Détaillée -->
                        <svg class="card-chip" viewBox="0 0 100 80" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="chip-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#fcd34d;stop-opacity:1" />
                                    <stop offset="50%" style="stop-color:#fbbf24;stop-opacity:1" />
                                    <stop offset="100%" style="stop-color:#d97706;stop-opacity:1" />
                                </linearGradient>
                            </defs>
                            <rect width="100" height="80" rx="12" fill="url(#chip-grad)" />
                            <path d="M0 25h100M0 55h100M30 0v80M70 0v80" stroke="rgba(0,0,0,0.2)" stroke-width="1.5" fill="none" />
                            <rect x="35" y="30" width="30" height="20" rx="4" fill="rgba(0,0,0,0.08)" stroke="rgba(0,0,0,0.1)" stroke-width="1" />
                            <path d="M30 40h10M60 40h10" stroke="rgba(0,0,0,0.2)" stroke-width="1.5" />
                        </svg>

                        <div class="card-number" id="cardNumber" data-full="<?= htmlspecialchars($fullNum, ENT_QUOTES, 'UTF-8') ?>" data-masked="<?= htmlspecialchars($maskedNum, ENT_QUOTES, 'UTF-8') ?>">
                            <?php 
                                // On enlève les espaces existants pour laisser le flexbox gérer la distribution
                                $numToSplit = str_replace(' ', '', $maskedNum);
                                $chars = mb_str_split($numToSplit);
                                foreach($chars as $char) {
                                    echo '<span>' . htmlspecialchars($char, ENT_QUOTES, 'UTF-8') . '</span>';
                                }
                            ?>
                        </div>

                        <div class="card-footer-zone">
                            <!-- Bloc 1 : Titulaire -->
                            <div class="card-footer-left">
                                <div class="card-info-item">
                                    <span class="card-label"><?= htmlspecialchars(t('card_holder_label') !== 'CARD_HOLDER_LABEL' ? t('card_holder_label') : 'TITULAIRE', ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="card-name"><?= htmlspecialchars($holderName, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </div>

                            <!-- Bloc 2 : CVV -->
                            <div class="card-footer-center">
                                <div class="card-info-item">
                                    <span class="card-label"><?= htmlspecialchars(t('cvv_label'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="card-value"><?= htmlspecialchars($cvvVal, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </div>

                            <!-- Bloc 3 : Date -->
                            <div class="card-footer-right">
                                <div class="card-info-item">
                                    <span class="card-label"><?= htmlspecialchars(t('valid_until_label'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="card-value"><?= htmlspecialchars($futureDate, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </div>
                        </div>
                </div>


                <!-- Boutons d'action -->
                <div class="mt-4">
                    <button class="btn btn-premium btn-success" data-toggle="modal" data-target="#carteActive">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars(t('activate_card'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button class="btn btn-premium btn-danger" data-toggle="modal" data-target="#carteBloque">
                        <i class="fas fa-lock me-2"></i><?= htmlspecialchars(t('block_card'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4 animate-in">
            <div class="card-modern">
                <div class="transaction-header">
                    <i class="fas fa-chart-line" style="color: #667eea;"></i>
                    <h5><?= htmlspecialchars(t('transactions_by_card'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="text-center">
                    <div class="loader-modern"></div>
                    <p class="text-muted"><?= htmlspecialchars(t('loading_transactions'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal activer la carte -->
<div class="modal fade" id="carteActive" tabindex="-1" role="dialog" aria-labelledby="carteActiveLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title w-100 text-center" id="carteActiveLabel" style="color: white;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                    <?= htmlspecialchars(t('modal_alert_title'), ENT_QUOTES, 'UTF-8') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="position: absolute; right: 1rem; top: 1rem; color: white; opacity: 0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><?= htmlspecialchars(t('modal_activation_unavailable'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light w-100" data-dismiss="modal" style="border-radius: 12px;"><?= htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal bloquer la carte -->
<div class="modal fade" id="carteBloque" tabindex="-1" role="dialog" aria-labelledby="carteBloqueLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title w-100 text-center" id="carteBloqueLabel" style="color: white;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                    <?= htmlspecialchars(t('modal_alert_title'), ENT_QUOTES, 'UTF-8') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="position: absolute; right: 1rem; top: 1rem; color: white; opacity: 0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><?= htmlspecialchars(t('modal_block_requires_activation'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light w-100" data-dismiss="modal" style="border-radius: 12px;"><?= htmlspecialchars(t('modal_close'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
// Animation au chargement
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.animate-in');
    elements.forEach((el, index) => {
        el.style.animationDelay = `${index * 0.1}s`;
    });
});
</script>

<!-- CODE FINAL SE TERMINE ICI -->

        <?php include __DIR__ . '/../partials/footer_nav.php'; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    </div>
    </div>
</body>

</html>