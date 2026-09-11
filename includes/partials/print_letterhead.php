<?php
declare(strict_types=1);

/**
 * RiskOps - Yazdırma antetli başlığı
 * /var/www/riskops/includes/partials/print_letterhead.php
 *
 * Ekranda GÖRÜNMEZ (.rk-print-only). Yalnızca yazdırma çıktısına girer.
 *
 * İki parça üretir:
 *   1. Sınıflandırma bandı — kurum, belge no ve gizlilik ibaresi
 *   2. Antet — logo, başlık ve künye tablosu
 *
 * İkisi de AKIŞIN İÇİNDEDİR. Sabit konumlandırma (position: fixed)
 * sayfalı ortamda güvenilir değildir: tarayıcı kenar boşluklarını
 * geçersiz kılınca şerit içeriğin üzerine biner.
 *
 * Sayfaya özel değerler istenirse şu değişkenler tanımlanabilir:
 *   $printDocCode  -> belge numarası (yoksa sayfa yolundan türetilir)
 *   $printScope    -> kapsam satırı (ör. tarih aralığı, filtre özeti)
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

$company        = trim((string)setting('company_name', ''));
$classification = trim((string)setting('report_classification', ''));
$docTitle       = $pageTitle ?? app_name();
$generatedAt    = date('Y-m-d H:i:s');

/* Belge numarası: sayfa yolunun son parçasından kısaltma + zaman damgası.
   Aynı raporun iki çıktısı birbirinden ayırt edilebilsin diye. */
if (!isset($printDocCode)) {
    $slug = basename(current_path(), '.php');
    if ($slug === '' || $slug === 'index') {
        $parts = array_values(array_filter(explode('/', current_path())));
        $slug  = $parts !== [] ? end($parts) : 'rapor';
    }
    $slug = mb_strtoupper(mb_substr(preg_replace('/[^a-zA-Z]/', '', $slug) ?: 'RPR', 0, 4), 'UTF-8');
    $printDocCode = 'RO-' . $slug . '-' . date('Ymd-Hi');
}
?>

<!-- Sınıflandırma bandı: belgenin en üstünde, akışın içinde -->
<div class="rk-print-only rk-class-band">
    <span><?= e($company !== '' ? $company : app_name()) ?> — <?= e($docTitle) ?></span>
    <span><?= e($printDocCode) ?></span>
    <?php if ($classification !== ''): ?>
        <span class="rk-class-tag"><?= e($classification) ?></span>
    <?php endif; ?>
</div>

<!-- Belgenin başındaki antet -->
<div class="rk-print-only rk-letterhead">
    <div class="rk-lh-top">
        <div class="rk-lh-brand">
            <?= brand_wordmark('light') ?>
        </div>
        <?php if ($classification !== ''): ?>
            <div class="rk-lh-class"><?= e($classification) ?></div>
        <?php endif; ?>
    </div>

    <div class="rk-lh-title">
        <h1><?= e($docTitle) ?></h1>
        <?php if ($company !== ''): ?>
            <div class="rk-lh-company"><?= e($company) ?></div>
        <?php endif; ?>
    </div>

    <table class="rk-lh-meta">
        <tbody>
            <tr>
                <th>Rapor tarihi</th>
                <td><?= e(format_datetime($generatedAt)) ?></td>
                <th>Belge no</th>
                <td><?= e($printDocCode) ?></td>
            </tr>
            <tr>
                <th>Hazırlayan</th>
                <td><?= e(auth_user()['name'] ?? '') ?></td>
                <th>Sistem</th>
                <td><?= e(app_name()) ?> — IT &amp; Cyber Risk Management</td>
            </tr>
            <?php if (!empty($printScope)): ?>
            <tr>
                <th>Kapsam</th>
                <td colspan="3"><?= e($printScope) ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
