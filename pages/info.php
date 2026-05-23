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
$utilisateur = $_SESSION['utilisateur_connecter'];
// Récupérez l'ID du compte et celui du propriétaire (préférer 'compte_id')
$accountId = $utilisateur['compte_id'] ?? $utilisateur['id'] ?? null;
$ownerUserId = $utilisateur['user_id'] ?? null;

$historique_transactions = $ownerUserId !== null ? getTransactionHistory($ownerUserId, $accountId) : [];
$utilisateur_connecte = getUserDetails($accountId);
if (!is_array($utilisateur_connecte)) {
    $utilisateur_connecte = [];
}

// Formater le montant du solde du compte
$account_balance = isset($utilisateur_connecte['account_balance']) ? (float) $utilisateur_connecte['account_balance'] : 0;
$formatted_balance = number_format($account_balance, 2, ',', ' ');

// Récupérer la devise pour l'affichage
$devise = $utilisateur['devise'] ?? 'EUR';

$statusLabels = [
    'active' => 'active',
    'activé' => 'active',
    'actif' => 'active',
    'exam' => 'exam',
    'examen' => 'exam',
    'suspended' => 'suspended',
    'suspendu' => 'suspended',
    'blocked' => 'blocked',
    'bloqué' => 'blocked',
];
$statusColors = [
    // Vert : statut optimal, tout fonctionne
    'active' => '#22c55e',
    'activé' => '#22c55e',
    'actif' => '#22c55e',
    
    // Bleu : statut neutre/informatif, en cours de traitement
    'exam' => '#3b82f6',
    'examen' => '#3b82f6',
    
    // Orange : avertissement, action requise
    'suspended' => '#f97316',
    'suspendu' => '#f97316',
    
    // Rouge : erreur, blocage, situation critique
    'blocked' => '#dc2626',
    'bloqué' => '#dc2626',
];

$account_status = $utilisateur_connecte['account_status'] ?? '';
$statusKey = trim($account_status);
if ($statusKey !== '') {
    $statusKey = function_exists('mb_strtolower') ? mb_strtolower($statusKey) : strtolower($statusKey);
}
$statusLabel = isset($statusLabels[$statusKey]) ? t($statusLabels[$statusKey]) : $account_status;
$statusColor = $statusColors[$statusKey] ?? '#6c757d';

?>

<body>
    <div class="dashboard">
        <nav class="pt-2">
            <div><i class="fas fa-bars menu-icon"></i> <strong class="fs-4">TRANSFERFLUX</strong></div>
            <?php
            // Prefer DB details but fallback to session values when photo is missing
            $sessionUser = $_SESSION['utilisateur_connecter'] ?? [];
            $photoUrl = null;
            if (!empty($utilisateur_connecte)) {
                $photoUrl = getUserPhotoUrl($utilisateur_connecte);
            }
            if (empty($photoUrl) && !empty($sessionUser)) {
                $photoUrl = getUserPhotoUrl($sessionUser);
            }
            ?>
            <a href="index.php?page=info" class="icon-circle" style="display:inline-flex;align-items:center;">
                <?php if ($photoUrl): ?>
                    <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="avatar" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid rgba(0,0,0,0.06);">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </a>

        </nav>
        <hr>

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
        margin: 0;
    }
    
    .col-md-6, .col-md-10, .col-lg-6 {
        padding: 0;
        width: 100%;
    }

    .alert-premium {
        align-items: flex-start;
        flex-direction: column;
        gap: 0.5rem;
        padding: 1rem 1rem;
    }

    .info-card {
        padding: 1.3rem 1.1rem;
    }

    .info-item {
        font-size: 0.95rem;
        gap: 0.45rem;
    }

    .info-item strong {
        font-size: 0.9rem;
    }

    .info-item span {
        overflow-wrap: anywhere;
        color: #0f172a; /* darker, purer ink */
        font-weight: 700; /* bolder for clarity */
        font-size: 1.05rem; /* slightly larger for readability */
    }

    .status-badge {
        width: 100%;
        justify-content: center;
    }

    .btn-deconnexion {
        padding: 12px 20px;
        font-size: 0.95rem;
    }
}

@media (max-width: 420px) {
    .info-card h5 {
        font-size: 1.05rem;
    }

    .info-item {
        font-size: 0.9rem;
    }

    .status-badge {
        font-size: 0.85rem;
    }
}

/* ===== STYLE MODERNE - INSPIRÉ DES NÉOBANQUES ===== */
.info-section {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #0f9d58 0%, #34a853 100%);
    --danger-gradient: linear-gradient(135deg, #d93025 0%, #ea4335 100%);
    --card-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    --hover-shadow: 0 12px 40px rgba(0, 0, 0, 0.18);
    --glass-bg: rgba(255, 255, 255, 0.85);
    --glass-border: rgba(255, 255, 255, 0.4);
}

/* Alert Premium */
.alert-premium {
    background: rgba(255, 251, 240, 0.9);
    border: 1px solid rgba(255, 234, 167, 0.5);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    backdrop-filter: blur(10px);
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

/* Cartes Glassmorphism */
.info-card {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--card-shadow);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--hover-shadow);
}

.info-card h5 {
    color: #4a5568;
    font-weight: 600;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Items d'information */
.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px dashed #e2e8f0;
}

.info-item:last-child {
    border-bottom: none;
}

.info-item strong {
    color: #718096;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-item span {
    color: #0f172a; /* darker, purer ink */
    font-weight: 700; /* bolder for clarity */
    font-size: 1.05rem; /* slightly larger for readability */
}

/* Badge de statut modernisé */
.status-badge {
    color: white !important;
    padding: 0.5rem 1rem;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

/* Déconnexion */
.btn-deconnexion {
    background: var(--danger-gradient);
    border: none;
    padding: 14px 32px;
    font-weight: 600;
    border-radius: 12px;
    color: white;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(217, 48, 37, 0.4);
    width: 100%;
    margin-top: 1rem;
}

.btn-deconnexion:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(217, 48, 37, 0.6);
    color: white;
    filter: brightness(1.1);
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
    .premium-header {
        margin: 0 -16px 20px -16px;
        border-radius: 0 0 20px 20px;
        padding: 24px 20px;
    }

    .alert-premium {
        margin: 0 0 20px 0;
        padding: 16px;
        font-size: 14px;
    }

    .info-card {
        padding: 20px;
        margin-bottom: 16px;
    }
    
    .info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .info-item:last-child {
        border-bottom: none;
    }

    .info-item strong {
        font-size: 14px;
        color: #64748b;
    }

    .info-item span {
        font-size: 1.05rem;
        color: #0f172a;
        font-weight: 700;
    }

    .status-badge {
        font-size: 14px;
        padding: 8px 16px;
    }

    .btn-deconnexion {
        font-size: 16px;
        padding: 16px;
    }
}
</style>

<div class="info-section">
    <!-- Alert Premium -->
    <div class="alert-premium animate-in" style="flex-direction: column; align-items: center; text-align: center; gap: 0.5rem;">
        <i class="fas fa-info-circle" style="color: #667eea; font-size: 1.5rem;"></i>
        <div>
            <strong style="display: block; font-size: 1.1rem; margin-bottom: 0.25rem;"><?= t('info_update') ?></strong>
            <span style="font-size: 0.9rem;"><?= t('info_contact_support') ?></span>
        </div>
    </div>

    <!-- Informations personnelles -->
    <div class="info-card animate-in">
        <h5>
            <i class="fas fa-user-circle" style="color: #667eea;"></i>
            <?= t('info_personal_information') ?>
        </h5>
        
        <div class="info-item">
            <strong><i class="fas fa-user"></i> <?= t('info_account_holder') ?></strong>
            <span><?= htmlspecialchars(trim(($utilisateur_connecte['nom'] ?? $utilisateur['nom'] ?? '') . ' ' . ($utilisateur_connecte['prenom'] ?? $utilisateur['prenom'] ?? '')), ENT_QUOTES, 'UTF-8') ?: t('not_provided') ?></span>
        </div>
        
        <div class="info-item">
            <strong><i class="fas fa-envelope"></i> <?= t('email_label') ?></strong>
            <span><?= htmlspecialchars($utilisateur_connecte['email'] ?? $utilisateur['email'] ?? t('not_provided'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        
        <div class="info-item">
            <strong><i class="fas fa-phone"></i> <?= t('phone_label') ?></strong>
            <span><?= htmlspecialchars($utilisateur_connecte['phone_number'] ?? $utilisateur['phone_number'] ?? t('not_provided'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        
        <div class="info-item">
            <strong><i class="fas fa-flag"></i> <?= t('country_label') ?></strong>
            <span><?= htmlspecialchars($utilisateur_connecte['country'] ?? $utilisateur['country'] ?? t('not_provided'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        
        <div class="info-item">
            <strong><i class="fas fa-map-marker-alt"></i> <?= t('address_label') ?></strong>
            <span><?= htmlspecialchars($utilisateur_connecte['address'] ?? $utilisateur['address'] ?? t('not_provided'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <!-- Compte et virements -->
    <div class="info-card animate-in">
        <h5>
            <i class="fas fa-university" style="color: #667eea;"></i>
            <?= t('account_and_transfers') ?>
        </h5>
        
        <div class="info-item">
            <strong><i class="fas fa-coins"></i> <?= t('account_balance') ?></strong>
            <span class="fw-bold" style="color: #0f9d58;"><?= $formatted_balance ?> <?= htmlspecialchars($devise, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        
        <div class="info-item">
            <strong><i class="fas fa-credit-card"></i> <?= t('account_type_label') ?></strong>
            <span>
                <?php
                $rawType = $utilisateur_connecte['account_type'] ?? 'Standard';
                // normalize into a safe slug for lookup e.g. 'Standard' -> 'standard'
                $slug = strtolower(trim((string)$rawType));
                $slug = preg_replace('/[^a-z0-9]+/i', '_', $slug);
                $tryKey = 'account_type_' . $slug;
                $translated = t($tryKey);
                if ($translated === $tryKey) {
                    // no translation available, fall back to raw value
                    echo htmlspecialchars($rawType, ENT_QUOTES, 'UTF-8');
                } else {
                    echo htmlspecialchars($translated, ENT_QUOTES, 'UTF-8');
                }
                ?>
            </span>
        </div>
        
        <div class="info-item">
            <strong><i class="fas fa-shield-alt"></i> <?= t('account_status_label') ?></strong>
            <span>
                <?php if ($account_status): ?>
                    <span class="status-badge" style="background: <?= htmlspecialchars($statusColor) ?>;">
                        <?php
                        $icon = '';
                        switch(strtolower($statusKey)) {
                            case 'active':
                            case 'activé':
                            case 'actif':
                                $icon = '<i class="fas fa-check"></i>';
                                break;
                            case 'exam':
                            case 'examen':
                                $icon = '<i class="fas fa-clock"></i>';
                                break;
                            case 'suspended':
                            case 'suspendu':
                                $icon = '<i class="fas fa-exclamation-triangle"></i>';
                                break;
                            case 'blocked':
                            case 'bloqué':
                                $icon = '<i class="fas fa-ban"></i>';
                                break;
                        }
                        echo $icon;
                        ?>
                        <?= htmlspecialchars($statusLabel) ?>
                    </span>
                <?php else: ?>
                    <span class="text-muted">Non défini</span>
                <?php endif; ?>
            </span>
        </div>
        
        <div class="info-item">
            <strong><i class="fas fa-exchange-alt"></i> <?= t('transfer_supported_label') ?></strong>
            <span>
                <?php
                $ts = $utilisateur_connecte['transfer_supported'] ?? '';
                $tsLower = strtolower((string)$ts);
                // try to map common sources to translation keys
                if (strpos($tsLower, 'paypal') !== false) {
                    echo htmlspecialchars(t('paypal'), ENT_QUOTES, 'UTF-8');
                } elseif (strpos($tsLower, 'bank') !== false || strpos($tsLower, 'virement') !== false) {
                    echo htmlspecialchars(t('bank_transfer'), ENT_QUOTES, 'UTF-8');
                } elseif ($ts === '') {
                    echo htmlspecialchars(t('bank_transfer'), ENT_QUOTES, 'UTF-8');
                } else {
                    // try a slugged key like transfer_supported_<slug>
                    $slug2 = preg_replace('/[^a-z0-9]+/i', '_', $tsLower);
                    $key2 = 'transfer_supported_' . $slug2;
                    $tr2 = t($key2);
                    if ($tr2 === $key2) {
                        echo htmlspecialchars($ts, ENT_QUOTES, 'UTF-8');
                    } else {
                        echo htmlspecialchars($tr2, ENT_QUOTES, 'UTF-8');
                    }
                }
                ?>
            </span>
        </div>
        
        <div class="info-item">
            <strong><i class="fas fa-hashtag"></i> <?= t('iban_label') ?></strong>
            <?php $ibanValue = trim((string)($utilisateur_connecte['iban'] ?? '')); ?>
            <?php if ($ibanValue !== ''): ?>
                <span><?= htmlspecialchars($ibanValue, ENT_QUOTES, 'UTF-8') ?></span>
            <?php else: ?>
                <span class="text-danger"><?= t('not_provided') ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bouton de déconnexion -->
    <form action="index.php?page=deconnexion" method="POST" class="animate-in">
        <button type="submit" class="btn btn-deconnexion">
            <i class="fas fa-sign-out-alt me-2"></i><?= t('logout_button') ?>
        </button>
    </form>
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

<!-- NOUVEAU CODE CENTRAL SE TERMINE ICI -->

        <?php include __DIR__ . '/../partials/footer_nav.php'; ?>
    </div>

    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>