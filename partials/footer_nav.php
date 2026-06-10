<?php
// Shared footer navigation partial
$activePage = isset($_GET['page']) ? (string)$_GET['page'] : '';
?>
<footer class="cards mt-5 footer-show">
    <a href="index.php?page=show"<?= $activePage === 'show' ? ' class="active"' : '' ?>>
        <i class="fas fa-coins"></i>
        <div><?= htmlspecialchars(t('footer_pay'), ENT_QUOTES, 'UTF-8') ?></div>
    </a>
    <a href="index.php?page=carte"<?= $activePage === 'carte' ? ' class="active"' : '' ?>>
        <i class="fas fa-credit-card"></i>
        <div><?= htmlspecialchars(t('footer_my_card'), ENT_QUOTES, 'UTF-8') ?></div>
    </a>
    <a href="index.php?page=transfert"<?= $activePage === 'transfert' ? ' class="active"' : '' ?>>
        <i class="fas fa-exchange-alt"></i>
        <div><?= htmlspecialchars(t('footer_payment'), ENT_QUOTES, 'UTF-8') ?></div>
    </a>
    <a href="index.php?page=info"<?= $activePage === 'info' ? ' class="active"' : '' ?>>
        <i class="fas fa-user"></i>
        <div><?= htmlspecialchars(t('footer_account'), ENT_QUOTES, 'UTF-8') ?></div>
    </a>
</footer>
