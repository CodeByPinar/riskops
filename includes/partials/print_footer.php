<?php
declare(strict_types=1);

/**
 * RiskOps - Yazdırma altbilgisi
 * /var/www/riskops/includes/partials/print_footer.php
 *
 * Belgenin sonundaki kapanış bloğu.
 *
 * HER SAYFADA TEKRAR EDEN ALT BİLGİ YOKTUR. Denenen iki yol da
 * güvenilir değil:
 *   - position: fixed  -> tarayıcı kenar boşluklarını geçersiz kılınca
 *                         şerit içeriğin üzerine biniyor
 *   - @page kenar kutuları (@bottom-right) -> Chrome ve Edge desteklemiyor
 *
 * Her sayfada üstbilgi/altbilgi ve sayfa numarası isteyen kullanıcı
 * tarayıcının yazdırma penceresindeki "Üstbilgi ve altbilgi" kutusunu
 * işaretler; doğru numarayı tarayıcı üretir.
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

$company        = trim((string)setting('company_name', ''));
$classification = trim((string)setting('report_classification', ''));
$footerNote     = trim((string)setting('report_footer_note', ''));
?>

<!-- Belgenin sonundaki kapanış notu -->
<?php if ($footerNote !== '' || $classification !== ''): ?>
<div class="rk-print-only rk-print-closing">
    <p class="rk-closing-id">
        <?= e($company !== '' ? $company : app_name()) ?>
        &middot; <?= e($pageTitle ?? app_name()) ?>
        <?php if (!empty($printDocCode)): ?>
            &middot; <?= e($printDocCode) ?>
        <?php endif; ?>
        &middot; <?= e(format_datetime(date('Y-m-d H:i:s'))) ?>
    </p>
    <?php if ($footerNote !== ''): ?>
        <p><?= e($footerNote) ?></p>
    <?php endif; ?>
    <?php if ($classification !== ''): ?>
        <p class="rk-print-classline">
            Bu belge <strong><?= e($classification) ?></strong> sınıfındadır.
            Yetkisiz kişilerle paylaşılmamalıdır.
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>
