<?php
declare(strict_types=1);

/**
 * RiskOps - Flash mesaj cikti bileseni
 * /var/www/riskops/includes/partials/alerts.php
 *
 * flash_take() mesajlari döndürür VE siler; bu yuzden istek başına
 * yalnızca BIR kez (header.php icinde) cagrilmalidir.
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

$rkFlashIcons = [
    'success' => 'bi-check-circle-fill',
    'error'   => 'bi-x-circle-fill',
    'warning' => 'bi-exclamation-triangle-fill',
    'info'    => 'bi-info-circle-fill',
];

foreach (flash_take() as $rkMsg) {
    $type = isset($rkMsg['type']) && isset($rkFlashIcons[$rkMsg['type']]) ? $rkMsg['type'] : 'info';
    ?>
    <div class="rk-alert rk-alert-<?= e($type) ?>" role="alert">
        <i class="bi <?= e($rkFlashIcons[$type]) ?>"></i>
        <div><?= e((string)($rkMsg['message'] ?? '')) ?></div>
        <button type="button" class="btn-close" aria-label="Kapat" data-rk-dismiss></button>
    </div>
    <?php
}
unset($rkFlashIcons, $rkMsg);
