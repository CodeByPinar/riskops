<?php
declare(strict_types=1);

/**
 * RiskOps - Üst bar
 * /var/www/riskops/includes/layout/navbar.php
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}
?>
<header class="rk-topbar">

    <button type="button" class="rk-burger" data-rk-sidebar-toggle aria-label="Menüyü aç/kapat">
        <i class="bi bi-list"></i>
    </button>

    <form class="rk-search" method="get" action="<?= e(url('/risks/')) ?>" role="search">
        <i class="bi bi-search"></i>
        <input type="search" name="q" autocomplete="off"
               value="<?= e($_GET['q'] ?? '') ?>"
               placeholder="Risk kodu, başlık veya varlık ara...">
    </form>

    <div class="rk-topbar-right">
        <?php if (APP_ENV !== 'production'): ?>
            <span class="rk-env-tag" title="APP_ENV = <?= e(APP_ENV) ?>"><?= e(APP_ENV) ?></span>
        <?php endif; ?>

        <?php if (can('risk.create')): ?>
            <a class="rk-btn rk-btn-primary rk-btn-sm" href="<?= e(url('/risks/create.php')) ?>">
                <i class="bi bi-plus-lg"></i> Yeni Risk
            </a>
        <?php endif; ?>
    </div>

</header>
