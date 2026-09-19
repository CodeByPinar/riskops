<?php
declare(strict_types=1);

/**
 * RiskOps - Yönetici Risk Özeti
 * /var/www/riskops/reports/executive_summary.php
 *
 * Tek sayfalık, yazdırılabilir yönetim özeti. Grafik YOKTUR: bu rapor
 * yazdırılıp toplantıya götürülmek üzere tasarlanmıştır ve tarayıcılar
 * canvas'ı güvenilir biçimde yazdırmaz. Her sayı metin olarak durur.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_can('report.view');

$open   = "'" . implode("','", risk_open_statuses()) . "'";
$effSev = 'COALESCE(residual_severity, inherent_severity)';

$stats = db_row_required(
    "SELECT
        COUNT(*) AS toplam,
        COALESCE(SUM({$effSev}='Critical'),0) AS kritik,
        COALESCE(SUM({$effSev}='High'),0)     AS yuksek,
        COALESCE(SUM({$effSev}='Medium'),0)   AS orta,
        COALESCE(SUM({$effSev}='Low'),0)      AS dusuk,
        COALESCE(SUM(status IN ({$open})),0)  AS acik,
        COALESCE(SUM(status='Mitigated'),0)   AS azaltilmis,
        COALESCE(SUM(status='Accepted'),0)    AS kabul,
        COALESCE(SUM(status='Transferred'),0) AS devredilen,
        COALESCE(SUM(status='Closed'),0)      AS kapali,
        COALESCE(SUM(status IN ({$open}) AND target_date IS NOT NULL
                 AND target_date < CURDATE()),0) AS geciken_risk,
        ROUND(AVG(COALESCE(residual_score, inherent_score)),1) AS ort_skor
     FROM risks WHERE deleted_at IS NULL"
);

$actions = db_row_required(
    "SELECT
        COUNT(*) AS toplam,
        COALESCE(SUM(a.status='Open'),0)        AS acik,
        COALESCE(SUM(a.status='In Progress'),0) AS devam,
        COALESCE(SUM(a.status='Completed'),0)   AS tamam,
        COALESCE(SUM(a.status='Cancelled'),0)   AS iptal,
        COALESCE(SUM(a.status IN ('Open','In Progress') AND a.due_date IS NOT NULL
                 AND a.due_date < CURDATE()),0) AS geciken
     FROM risk_actions a
     JOIN risks r ON r.id = a.risk_id AND r.deleted_at IS NULL"
);

$topRisks = db_all(
    "SELECT r.risk_code, r.title, d.name AS departman, u.name AS sahip,
            COALESCE(r.residual_score, r.inherent_score) AS skor,
            {$effSev} AS seviye, r.status, r.target_date,
            (SELECT COUNT(*) FROM risk_actions a WHERE a.risk_id = r.id
             AND a.status IN ('Open','In Progress')) AS acik_aksiyon
     FROM risks r
     JOIN departments d ON d.id = r.department_id
     JOIN users u       ON u.id = r.owner_id
     WHERE r.deleted_at IS NULL AND r.status IN ({$open})
     ORDER BY FIELD({$effSev},'Critical','High','Medium','Low'),
              COALESCE(r.residual_score, r.inherent_score) DESC
     LIMIT 10"
);

$byDept = db_all(
    "SELECT d.name AS departman, COUNT(r.id) AS toplam,
            COALESCE(SUM({$effSev} IN ('Critical','High')),0) AS onemli
     FROM departments d
     JOIN risks r ON r.department_id = d.id AND r.deleted_at IS NULL
     GROUP BY d.id, d.name
     HAVING toplam > 0
     ORDER BY onemli DESC, toplam DESC"
);

$byCat = db_all(
    "SELECT c.name AS kategori, COUNT(r.id) AS toplam,
            COALESCE(SUM({$effSev} IN ('Critical','High')),0) AS onemli
     FROM risk_categories c
     JOIN risks r ON r.category_id = c.id AND r.deleted_at IS NULL
     GROUP BY c.id, c.name
     HAVING toplam > 0
     ORDER BY onemli DESC, toplam DESC
     LIMIT 8"
);

/* Son 6 ay: açılan ve kapanan */
/* Ayni gun-tasmasi hatasi burada da vardi: 6 aylik tablo ayin
   29-31'inde 3 satira kadar dusuyordu. Bkz. includes/functions.php. */
$monthKeys = recent_months(6);

$trend = [];
foreach ($monthKeys as $key) {
    $trend[$key] = ['acilan' => 0, 'kapanan' => 0];
}

$since = $monthKeys[0] . '-01';

$q = db_stmt("SELECT DATE_FORMAT(created_at,'%Y-%m') AS ay, COUNT(*) AS n
                    FROM risks WHERE deleted_at IS NULL AND created_at >= :s GROUP BY ay",
    [':s' => $since]
);
foreach ($q->fetchAll() as $r) {
    if (isset($trend[$r['ay']])) {
    $trend[$r['ay']]['acilan'] = (int)$r['n'];
    }
}
$q = db_stmt("SELECT DATE_FORMAT(closed_at,'%Y-%m') AS ay, COUNT(*) AS n
                    FROM risks WHERE deleted_at IS NULL AND closed_at >= :s GROUP BY ay",
    [':s' => $since]
);
foreach ($q->fetchAll() as $r) {
    if (isset($trend[$r['ay']])) {
    $trend[$r['ay']]['kapanan'] = (int)$r['n'];
    }
}

$neverReviewed = (int)db_value(
    "SELECT COUNT(*) FROM risks r WHERE r.deleted_at IS NULL
     AND NOT EXISTS (SELECT 1 FROM risk_assessments a
                     WHERE a.risk_id = r.id AND a.assessment_type <> 'initial')"
);

$total = max(1, (int)$stats['toplam']);

$pageTitle    = 'Yönetici Risk Özeti';
$pageSubtitle = format_datetime(date('Y-m-d H:i:s'))
              . ((string)setting('company_name', '') !== ''
                    ? ' · ' . setting('company_name') : '');
$activeMenu   = 'reports';
$printDocCode = 'RO-EXEC-' . date('Ymd-Hi');
$printScope   = 'Tüm aktif risk portföyü — ' . (int)$stats['toplam'] . ' risk, '
              . (int)$actions['toplam'] . ' aksiyon planı';
$pageActions  = '<a class="rk-btn" href="' . e(url('/reports/')) . '">'
              . '<i class="bi bi-arrow-left"></i> Raporlar</a>'
              . '<button type="button" class="rk-btn rk-btn-primary" data-rk-print>'
              . '<i class="bi bi-printer"></i> Yazdır</button>';

require LAYOUT_PATH . '/header.php';
?>

<div class="rk-report-meta">
    <i class="bi bi-file-earmark-bar-graph"></i>
    <div>
        Kurumsal IT ve siber risk portföyünün tek sayfalık yönetim özeti.
        <div class="rk-cell-sub">
            Tüm seviyeler <strong>etkin seviye</strong>dir: kalan (residual) risk
            hesaplanmışsa o, yoksa ham (inherent) risk kullanılır.
        </div>
    </div>
</div>

<!-- ===================== Ana göstergeler ===================== -->
<div class="rk-stats">
    <div class="rk-stat is-primary">
        <div class="rk-stat-icon"><i class="bi bi-collection"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$stats['toplam'] ?></div>
            <div class="rk-stat-label">Toplam Risk</div>
        </div>
    </div>
    <div class="rk-stat is-critical">
        <div class="rk-stat-icon"><i class="bi bi-exclamation-octagon-fill"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$stats['kritik'] ?></div>
            <div class="rk-stat-label">Kritik</div>
        </div>
    </div>
    <div class="rk-stat is-high">
        <div class="rk-stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$stats['yuksek'] ?></div>
            <div class="rk-stat-label">Yüksek</div>
        </div>
    </div>
    <div class="rk-stat is-neutral">
        <div class="rk-stat-icon"><i class="bi bi-folder2-open"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$stats['acik'] ?></div>
            <div class="rk-stat-label">Açık Risk</div>
        </div>
    </div>
    <div class="rk-stat <?= (int)$actions['geciken'] > 0 ? 'is-critical' : 'is-low' ?>">
        <div class="rk-stat-icon"><i class="bi bi-clock-history"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$actions['geciken'] ?></div>
            <div class="rk-stat-label">Geciken Aksiyon</div>
        </div>
    </div>
    <div class="rk-stat is-neutral">
        <div class="rk-stat-icon"><i class="bi bi-speedometer"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= e((string)($stats['ort_skor'] ?? '—')) ?></div>
            <div class="rk-stat-label">Ortalama Skor</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- ===================== Seviye + durum ===================== -->
    <div class="col-12 col-lg-6">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-bar-chart-steps"></i> <?= te('Seviye Dağılımı') ?></h2>
            </div>
            <div class="rk-card-body is-flush">
                <table class="rk-table">
                    <thead><tr><th><?= te('Seviye') ?></th><th><?= te('Adet') ?></th><th><?= te('Oran') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ([['Critical','kritik'],['High','yuksek'],['Medium','orta'],['Low','dusuk']] as [$sev,$k]):
                        $n = (int)$stats[$k]; ?>
                        <tr>
                            <td><?= severity_badge($sev, true) ?></td>
                            <td><strong><?= $n ?></strong></td>
                            <td><?= round($n * 100 / $total) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-flag"></i> <?= te('Risk Durumu') ?></h2>
            </div>
            <div class="rk-card-body is-flush">
                <table class="rk-table">
                    <tbody>
                        <tr><td><?= te('Açık (Open / Under Review / In Progress)') ?></td>
                            <td><strong><?= (int)$stats['acik'] ?></strong></td></tr>
                        <tr><td>Azaltılmış (Mitigated)</td>
                            <td><strong><?= (int)$stats['azaltilmis'] ?></strong></td></tr>
                        <tr><td>Kabul edilmiş (Accepted)</td>
                            <td><strong><?= (int)$stats['kabul'] ?></strong></td></tr>
                        <tr><td>Devredilmiş (Transferred)</td>
                            <td><strong><?= (int)$stats['devredilen'] ?></strong></td></tr>
                        <tr><td>Kapatılmış (Closed)</td>
                            <td><strong><?= (int)$stats['kapali'] ?></strong></td></tr>
                        <tr><td class="rk-overdue">Termini geçmiş açık risk</td>
                            <td><strong class="rk-overdue"><?= (int)$stats['geciken_risk'] ?></strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ===================== En yüksek riskler ===================== -->
<div class="rk-card">
    <div class="rk-card-head">
        <h2 class="rk-card-title"><i class="bi bi-list-ol"></i> <?= te('Yönetim Dikkatine Sunulan Riskler') ?></h2>
        <div class="rk-card-tools"><span class="rk-help"><?= te('En yüksek etkin skorlu 10 açık risk') ?></span></div>
    </div>
    <div class="rk-card-body is-flush">
        <?php if ($topRisks === []): ?>
            <?= empty_state('Açık risk yok', '', 'bi-shield-check') ?>
        <?php else: ?>
        <div class="rk-table-wrap">
            <table class="rk-table">
                <thead>
                    <tr><th>#</th><th><?= te('Kod') ?></th><th><?= te('Başlık') ?></th><th><?= te('Departman') ?></th><th><?= te('Sahip') ?></th>
                        <th>Skor</th><th>Seviye</th><th><?= te('Durum') ?></th><th><?= te('Termin') ?></th><th><?= te('Açık Aksiyon') ?></th></tr>
                </thead>
                <tbody>
                <?php foreach ($topRisks as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="rk-code"><?= e($r['risk_code']) ?></td>
                        <td class="rk-cell-wrap"><?= e(str_limit($r['title'], 50)) ?></td>
                        <td><?= e($r['departman']) ?></td>
                        <td><?= e($r['sahip']) ?></td>
                        <td><?= score_chip((int)$r['skor'], $r['seviye']) ?></td>
                        <td><?= severity_badge($r['seviye']) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td><?= due_date_cell($r['target_date'], $r['status'], risk_open_statuses()) ?></td>
                        <td<?= (int)$r['acik_aksiyon'] === 0 ? ' class="rk-overdue"' : '' ?>>
                            <?= (int)$r['acik_aksiyon'] ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="rk-help rk-u-pad-cell">
            Açık aksiyonu <strong>0</strong> olan yüksek riskler, planı olmayan risklerdir.
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <!-- ===================== Departman ===================== -->
    <div class="col-12 col-lg-6">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-diagram-3"></i> <?= te('Departman Yoğunluğu') ?></h2>
            </div>
            <div class="rk-card-body is-flush">
                <table class="rk-table">
                    <thead><tr><th><?= te('Departman') ?></th><th><?= te('Toplam') ?></th><th><?= te('Kritik + Yüksek') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($byDept as $d): ?>
                        <tr>
                            <td><?= e($d['departman']) ?></td>
                            <td><?= (int)$d['toplam'] ?></td>
                            <td><?= (int)$d['onemli'] > 0
                                    ? '<span class="rk-badge sev-high">' . (int)$d['onemli'] . '</span>'
                                    : '<span class="text-muted">0</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===================== Kategori ===================== -->
    <div class="col-12 col-lg-6">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-tags"></i> <?= te('Tehdit Alanları') ?></h2>
            </div>
            <div class="rk-card-body is-flush">
                <table class="rk-table">
                    <thead><tr><th><?= te('Kategori') ?></th><th><?= te('Toplam') ?></th><th><?= te('Kritik + Yüksek') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($byCat as $c): ?>
                        <tr>
                            <td><?= e($c['kategori']) ?></td>
                            <td><?= (int)$c['toplam'] ?></td>
                            <td><?= (int)$c['onemli'] > 0
                                    ? '<span class="rk-badge sev-high">' . (int)$c['onemli'] . '</span>'
                                    : '<span class="text-muted">0</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- ===================== Aksiyon durumu ===================== -->
    <div class="col-12 col-lg-6">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-check2-square"></i> <?= te('Aksiyon Planları') ?></h2>
            </div>
            <div class="rk-card-body is-flush">
                <table class="rk-table">
                    <tbody>
                        <tr><td><?= te('Toplam aksiyon') ?></td><td><strong><?= (int)$actions['toplam'] ?></strong></td></tr>
                        <tr><td><?= te('Açık') ?></td><td><strong><?= (int)$actions['acik'] ?></strong></td></tr>
                        <tr><td><?= te('Devam ediyor') ?></td><td><strong><?= (int)$actions['devam'] ?></strong></td></tr>
                        <tr><td>Tamamlanan</td><td><strong><?= (int)$actions['tamam'] ?></strong></td></tr>
                        <tr><td><?= te('İptal edilen') ?></td><td><strong><?= (int)$actions['iptal'] ?></strong></td></tr>
                        <tr><td class="rk-overdue">Geciken</td>
                            <td><strong class="rk-overdue"><?= (int)$actions['geciken'] ?></strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===================== Trend ===================== -->
    <div class="col-12 col-lg-6">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-graph-up"></i> <?= te('Son 6 Ay') ?></h2>
            </div>
            <div class="rk-card-body is-flush">
                <table class="rk-table">
                    <thead><tr><th><?= te('Ay') ?></th><th><?= te('Açılan') ?></th><th><?= te('Kapanan') ?></th><th><?= te('Net') ?></th></tr></thead>
                    <tbody>
                    <?php
                    $aylar = ['01'=>'Ocak','02'=>'Şubat','03'=>'Mart','04'=>'Nisan','05'=>'Mayıs',
                              '06'=>'Haziran','07'=>'Temmuz','08'=>'Ağustos','09'=>'Eylül',
                              '10'=>'Ekim','11'=>'Kasım','12'=>'Aralık'];
                    foreach ($trend as $ay => $v):
                        [$yil, $m] = explode('-', $ay);
                        $net = $v['acilan'] - $v['kapanan']; ?>
                        <tr>
                            <td><?= e($aylar[$m] . ' ' . $yil) ?></td>
                            <td><?= $v['acilan'] ?></td>
                            <td><?= $v['kapanan'] ?></td>
                            <td<?= $net > 0 ? ' class="rk-overdue"' : '' ?>>
                                <?= $net > 0 ? '+' : '' ?><?= $net ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="rk-help rk-u-pad-cell">
                    Net pozitifse portföy büyüyor: kapatılandan fazla risk açılıyor.
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($neverReviewed > 0): ?>
<div class="rk-alert rk-alert-warning">
    <i class="bi bi-hourglass-split"></i>
    <div>
        <strong><?= $neverReviewed ?> risk</strong> oluşturulduğundan beri hiç gözden
        geçirilmemiş (yalnızca ilk değerlendirmesi var). Düzenli gözden geçirme
        olmadan risk kaydı zamanla gerçeği yansıtmaz.
        <a href="<?= e(url('/assessments/')) ?>"><?= te('Değerlendirmeler') ?></a>
    </div>
</div>
<?php endif; ?>

<?php require PARTIALS_PATH . '/print_signatures.php'; ?>

<?php require LAYOUT_PATH . '/footer.php'; ?>
