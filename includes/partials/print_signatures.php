<?php
declare(strict_types=1);

/**
 * RiskOps - İmza bloğu (yalnızca yazdırmada)
 * /var/www/riskops/includes/partials/print_signatures.php
 *
 * Yönetim raporlarının ıslak imza ile onaylanması gereken hâlleri için.
 * Ekranda görünmez; yazdırma çıktısında son bölüm olarak gelir.
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

$roles = $printSignatureRoles ?? ['Hazırlayan', 'Gözden Geçiren', 'Onaylayan'];
?>
<div class="rk-print-only rk-signatures">
    <div class="rk-sig-title"><?= te('Onay') ?></div>
    <table class="rk-sig-table">
        <thead>
            <tr>
                <?php foreach ($roles as $r): ?>
                    <th><?= e($r) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <?php foreach ($roles as $i => $r): ?>
                    <td>
                        <?php if ($i === 0): ?>
                            <div class="rk-sig-name"><?= e(auth_user()['name'] ?? '') ?></div>
                            <div class="rk-sig-sub"><?= te(role_label(auth_role())) ?></div>
                        <?php else: ?>
                            <div class="rk-sig-name">&nbsp;</div>
                            <div class="rk-sig-sub">&nbsp;</div>
                        <?php endif; ?>
                        <div class="rk-sig-line"></div>
                        <div class="rk-sig-hint"><?= te('Ad Soyad / İmza / Tarih') ?></div>
                    </td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>
</div>
