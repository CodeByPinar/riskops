<?php
declare(strict_types=1);

/**
 * RiskOps - Rapor görüntüleme
 * /var/www/riskops/reports/view.php?r=<anahtar>
 *
 * Tüm tablo ve kırılım raporları bu tek ekrandan render edilir.
 * Rapor tanımı _reports.php içindedir.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_reports.php';

require_login();
require_can('report.view');

$defs = report_definitions();
$key  = input_enum('r', array_keys($defs));

if ($key === null) {
    flash('error', 'Geçersiz rapor.');
    redirect('/reports/');
}

$def  = $defs[$key];
$from = input_date('from');
$to   = input_date('to');

if ($from !== null && $to !== null && $from > $to) {
    flash('warning', 'Başlangıç tarihi bitiş tarihinden sonra olamaz; tarih aralığı yok sayıldı.');
    $from = $to = null;
}

$result = report_run($def, $from, $to);
$rows   = $result['rows'];

$exportQuery = array_filter(
    ['r' => $key, 'from' => $from, 'to' => $to],
    static fn ($v) => $v !== null && $v !== ''
);
$exportExcel = url('/reports/export_csv.php?' . http_build_query($exportQuery + ['format' => 'excel']));
$exportRaw   = url('/reports/export_csv.php?' . http_build_query($exportQuery + ['format' => 'raw']));

$pageTitle    = $def['title'];
$pageSubtitle = count($rows) . ' satır'
              . ($from !== null || $to !== null
                    ? ' · ' . ($from !== null ? format_date($from) : '…')
                      . ' – ' . ($to !== null ? format_date($to) : '…')
                    : '');
$activeMenu   = 'reports';
$printDocCode = 'RO-' . mb_strtoupper(mb_substr(str_replace('_', '', $key), 0, 6), 'UTF-8')
              . '-' . date('Ymd-Hi');
$printScope   = $def['description'] . ' · ' . count($rows) . ' satır'
              . ($from !== null || $to !== null
                    ? ' · ' . ($from !== null ? format_date($from) : 'başlangıçtan')
                      . ' – ' . ($to !== null ? format_date($to) : 'bugüne')
                    : '');
$pageActions  = '<a class="rk-btn" href="' . e(url('/reports/')) . '">'
              . '<i class="bi bi-arrow-left"></i> Raporlar</a>'
              . '<button type="button" class="rk-btn" data-rk-print>'
              . '<i class="bi bi-printer"></i> Yazdır</button>'
              . '<a class="rk-btn rk-btn-primary" href="' . e($exportExcel) . '">'
              . '<i class="bi bi-file-earmark-excel"></i> Excel CSV</a>'
              . '<a class="rk-btn" href="' . e($exportRaw) . '" '
              . 'title="RFC 4180 — virgül ayraçlı, künyesiz, ISO tarihli">'
              . '<i class="bi bi-filetype-csv"></i> Ham CSV</a>';

require LAYOUT_PATH . '/header.php';
?>

<div class="rk-report-meta">
    <i class="bi <?= e($def['icon']) ?>"></i>
    <div>
        <?= e($def['description']) ?>
        <div class="rk-cell-sub">
            Oluşturulma: <?= e(format_datetime(date('Y-m-d H:i:s'))) ?>
            <?php if ((string)setting('company_name', '') !== ''): ?>
                · <?= e((string)setting('company_name')) ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($def['date_field'] !== null): ?>
<form method="get" class="rk-card rk-filters">
    <div class="rk-card-body">
        <input type="hidden" name="r" value="<?= e($key) ?>">
        <div class="rk-filter-grid">
            <div class="rk-field">
                <label class="rk-label" for="from"><?= te('Başlangıç') ?></label>
                <input class="rk-input" type="date" id="from" name="from" value="<?= e($from ?? '') ?>">
            </div>
            <div class="rk-field">
                <label class="rk-label" for="to"><?= te('Bitiş') ?></label>
                <input class="rk-input" type="date" id="to" name="to" value="<?= e($to ?? '') ?>">
            </div>
        </div>
        <div class="rk-filter-actions">
            <span class="rk-help">Filtre alanı: <code><?= e($def['date_field']) ?></code></span>
            <div class="ms-auto d-flex gap-2">
                <?php if ($from !== null || $to !== null): ?>
                    <a class="rk-btn" href="<?= e(url('/reports/view.php?r=' . $key)) ?>">
                        <i class="bi bi-x-lg"></i> <?= te('Temizle') ?></a>
                <?php endif; ?>
                <button type="submit" class="rk-btn rk-btn-primary">
                    <i class="bi bi-funnel"></i> <?= te('Uygula') ?></button>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>

<div class="rk-card">
    <div class="rk-card-body is-flush">
        <?php if ($rows === []): ?>
            <?= empty_state('Bu rapor için veri yok',
                            'Tarih aralığını genişletmeyi deneyin.', 'bi-clipboard-x') ?>
        <?php else: ?>
        <div class="rk-table-wrap">
            <table class="rk-table rk-report-table">
                <thead>
                    <tr>
                        <?php foreach ($def['columns'] as $col => $label): ?>
                            <th><?= e($label) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($def['columns'] as $col => $label): ?>
                            <td><?= report_cell_html($col, $row[$col] ?? null) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>

                <?php if (!empty($def['total_row'])): ?>
                <tfoot>
                    <tr class="rk-total-row">
                        <?php $first = true;
                        foreach ($def['columns'] as $col => $label):
                            if ($first) {
                                echo '<th>TOPLAM (' . count($rows) . ' satır)</th>';
                                $first = false;
                                continue;
                            }
                            if (in_array($col, $def['sum_columns'] ?? [], true)) {
                                echo '<th>' . array_sum(array_column($rows, $col)) . '</th>';
                            } else {
                                echo '<th></th>';
                            }
                        endforeach; ?>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require LAYOUT_PATH . '/footer.php'; ?>
