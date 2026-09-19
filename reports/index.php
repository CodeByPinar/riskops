<?php
declare(strict_types=1);

/**
 * RiskOps - Raporlar
 * /var/www/riskops/reports/index.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_reports.php';

require_login();
require_can('report.view');

$defs = report_definitions();

/* Her rapor için satır sayısını göster: boş bir raporu açmak zaman kaybı */
$counts = [];
foreach ($defs as $key => $def) {
    try {
        $counts[$key] = count(report_run($def, null, null)['rows']);
    } catch (Throwable $ex) {
        app_log('error', 'Report count failed: ' . $ex->getMessage(), ['report' => $key]);
        $counts[$key] = null;
    }
}

$pageTitle    = 'Raporlar';
$pageSubtitle = (count($defs) + 1) . ' rapor · tümü CSV olarak indirilebilir';
$activeMenu   = 'reports';

require LAYOUT_PATH . '/header.php';
?>

<div class="row g-3">

    <!-- Yönetici özeti öne çıkarılır -->
    <div class="col-12">
        <a class="rk-report-card is-featured" href="<?= e(url('/reports/executive_summary.php')) ?>">
            <div class="rk-report-icon"><i class="bi bi-file-earmark-bar-graph"></i></div>
            <div class="rk-report-body">
                <h3><?= te('Yönetici Risk Özeti') ?></h3>
                <p>
                    Tek sayfalık yönetim özeti: seviye dağılımı, en yüksek 10 açık risk,
                    departman ve tehdit alanı yoğunluğu, aksiyon durumu ve 6 aylık trend.
                    Yazdırmaya uygun.
                </p>
            </div>
            <div class="rk-report-go"><i class="bi bi-arrow-right"></i></div>
        </a>
    </div>

    <?php foreach ($defs as $key => $def): ?>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="rk-report-card">
            <a class="rk-report-main" href="<?= e(url('/reports/view.php?r=' . $key)) ?>">
                <div class="rk-report-icon"><i class="bi <?= e($def['icon']) ?>"></i></div>
                <div class="rk-report-body">
                    <h3><?= e($def['title']) ?></h3>
                    <p><?= e($def['description']) ?></p>
                    <?php if ($counts[$key] !== null): ?>
                        <span class="rk-badge <?= $counts[$key] > 0 ? 'st-open' : 'sev-none' ?>">
                            <?= (int)$counts[$key] ?> satır
                        </span>
                    <?php endif; ?>
                </div>
            </a>
            <div class="rk-report-actions">
                <a class="rk-btn rk-btn-sm" href="<?= e(url('/reports/view.php?r=' . $key)) ?>">
                    <i class="bi bi-table"></i> <?= te('Görüntüle') ?>
                </a>
                <a class="rk-btn rk-btn-sm"
                   href="<?= e(url('/reports/export_csv.php?r=' . $key . '&format=excel')) ?>"
                   title="<?= te('Türkçe Excel\'de çift tıkla açılır') ?>">
                    <i class="bi bi-file-earmark-excel"></i> <?= te('Excel') ?>
                </a>
                <a class="rk-btn rk-btn-sm"
                   href="<?= e(url('/reports/export_csv.php?r=' . $key . '&format=raw')) ?>"
                   title="<?= te('RFC 4180 — sistem entegrasyonu için') ?>">
                    <i class="bi bi-filetype-csv"></i> <?= te('Ham') ?>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<div class="rk-alert rk-alert-info">
    <i class="bi bi-info-circle-fill"></i>
    <div>
        <strong>Excel</strong> biçimi Türkçe Excel için hazırlanmıştır: UTF-8 BOM,
        <code>sep=;</code> yönergesi, noktalı virgül ayraç, <strong>virgüllü ondalık</strong>
        (8,8), yerel tarih biçimi, sıra numarası ve künye bloğu. Çift tıkla açılır,
        karakterler bozulmaz, sayılar sayı olarak okunur.
        <br>
        <strong>Ham</strong> biçim RFC 4180'dir: virgül ayraç, künye yok, ISO tarih
        (2026-10-26), noktalı ondalık. Başka bir sisteme veri besleyecekseniz bunu kullanın.
        <br>
        Her iki biçimde de <code>=</code>, <code>+</code>, <code>-</code>, <code>@</code>
        ile başlayan hücreler metne zorlanır (formül enjeksiyonu koruması).
        Dışa aktarma işlemleri audit log'a yazılır.
        <br><br>
        <strong>Yazdırma:</strong> Raporlar antetli, gizlilik ibareli ve imza bloklu
        bir belge olarak basılır. Her sayfada tekrar eden üstbilgi ve
        <strong>sayfa numarası</strong> için tarayıcının yazdırma penceresindeki
        &laquo;Üstbilgi ve altbilgi&raquo; kutusunu işaretleyin; en doğru sonuç için
        kenar boşluğunu &laquo;Varsayılan&raquo; bırakın.
    </div>
</div>

<?php require LAYOUT_PATH . '/footer.php'; ?>
