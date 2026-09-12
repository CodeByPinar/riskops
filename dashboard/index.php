<?php
declare(strict_types=1);

/**
 * RiskOps - Dashboard
 * /var/www/riskops/dashboard/index.php
 *
 * Bir risk yöneticisi ilk 10 saniyede şunları görmeli:
 * kaç kritik risk, kaç açık risk, kaç geciken aksiyon, hangi departman
 * en riskli, hangi riskler acil aksiyon gerektiriyor.
 *
 * TASARIM NOTU
 * ------------
 * 5x5 matris, seviye ve durum dağılımları SUNUCU TARAFINDA render edilir:
 * Chart.js yüklenmese bile bu üç görsel çalışır. Chart.js yalnızca trend,
 * kategori ve departman grafikleri için kullanılır.
 *
 * "Etkin seviye" = residual varsa residual, yoksa inherent. Risk yönetiminde
 * karar verilen değer kalan risktir.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_can('risk.view');

$isExec   = in_array(auth_role(), [ROLE_ADMIN, ROLE_MANAGER], true);
$me       = (int)auth_id();
$openList = "'" . implode("','", risk_open_statuses()) . "'";
$warnDays = max(1, (int)setting('overdue_warning_days', 7));

/* ------------------------------------------------------------------ */
/* Üst kartlar                                                         */
/* ------------------------------------------------------------------ */

$stats = db()->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(COALESCE(residual_severity, inherent_severity) = 'Critical'), 0) AS critical,
        COALESCE(SUM(COALESCE(residual_severity, inherent_severity) = 'High'), 0)     AS high,
        COALESCE(SUM(status IN ({$openList})), 0)                                     AS open_risks,
        COALESCE(SUM(status = 'Mitigated'), 0)                                        AS mitigated
     FROM risks
     WHERE deleted_at IS NULL"
)->fetch();

$overdueActions = (int)db()->query(
    "SELECT COUNT(*)
     FROM risk_actions a
     JOIN risks r ON r.id = a.risk_id AND r.deleted_at IS NULL
     WHERE a.status IN ('Open','In Progress')
       AND a.due_date IS NOT NULL AND a.due_date < CURDATE()"
)->fetchColumn();

/* ------------------------------------------------------------------ */
/* Dağılımlar (sunucu tarafında render edilir)                         */
/* ------------------------------------------------------------------ */

$severityCounts = [];
foreach (db()->query(
    "SELECT COALESCE(residual_severity, inherent_severity) AS sev, COUNT(*) AS adet
     FROM risks WHERE deleted_at IS NULL GROUP BY sev"
)->fetchAll() as $r) {
    $severityCounts[$r['sev']] = (int)$r['adet'];
}

$statusCounts = [];
foreach (db()->query(
    "SELECT status, COUNT(*) AS adet FROM risks WHERE deleted_at IS NULL GROUP BY status"
)->fetchAll() as $r) {
    $statusCounts[$r['status']] = (int)$r['adet'];
}

// 5x5 matris: etkin olasılık / etki kırılımı
$matrix = [];
foreach (db()->query(
    "SELECT COALESCE(residual_likelihood, likelihood) AS l,
            COALESCE(residual_impact, impact)         AS i,
            COUNT(*) AS adet
     FROM risks WHERE deleted_at IS NULL
     GROUP BY l, i"
)->fetchAll() as $r) {
    $matrix[(int)$r['l']][(int)$r['i']] = (int)$r['adet'];
}

/* ------------------------------------------------------------------ */
/* Listeler                                                            */
/* ------------------------------------------------------------------ */

$criticalOpen = db()->query(
    "SELECT r.id, r.risk_code, r.title, r.target_date, r.status,
            COALESCE(r.residual_severity, r.inherent_severity) AS severity,
            COALESCE(r.residual_score, r.inherent_score)       AS score,
            u.name AS owner, d.name AS department
     FROM risks r
     JOIN users u       ON u.id = r.owner_id
     JOIN departments d ON d.id = r.department_id
     WHERE r.deleted_at IS NULL
       AND r.status IN ({$openList})
       AND COALESCE(r.residual_severity, r.inherent_severity) IN ('Critical','High')
     ORDER BY FIELD(COALESCE(r.residual_severity, r.inherent_severity), 'Critical','High'),
              r.target_date IS NULL, r.target_date ASC
     LIMIT 8"
)->fetchAll();

$upcomingStmt = db()->prepare(
    "SELECT r.id, r.risk_code, r.title, r.target_date, r.status,
            COALESCE(r.residual_severity, r.inherent_severity) AS severity
     FROM risks r
     WHERE r.deleted_at IS NULL
       AND r.status IN ({$openList})
       AND r.target_date IS NOT NULL
       AND r.target_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :d DAY)
     ORDER BY r.target_date ASC
     LIMIT 6"
);
$upcomingStmt->execute([':d' => $warnDays]);
$upcoming = $upcomingStmt->fetchAll();

$lateActions = db()->query(
    "SELECT a.id, a.title, a.priority, a.status, a.due_date,
            r.id AS risk_id, r.risk_code,
            u.name AS owner
     FROM risk_actions a
     JOIN risks r ON r.id = a.risk_id AND r.deleted_at IS NULL
     JOIN users u ON u.id = a.owner_id
     WHERE a.status IN ('Open','In Progress')
       AND a.due_date IS NOT NULL AND a.due_date < CURDATE()
     ORDER BY a.due_date ASC
     LIMIT 6"
)->fetchAll();

$myActionsStmt = db()->prepare(
    "SELECT a.id, a.title, a.priority, a.status, a.due_date,
            r.id AS risk_id, r.risk_code
     FROM risk_actions a
     JOIN risks r ON r.id = a.risk_id AND r.deleted_at IS NULL
     WHERE a.owner_id = :me AND a.status IN ('Open','In Progress')
     ORDER BY a.due_date IS NULL, a.due_date ASC
     LIMIT 6"
);
$myActionsStmt->execute([':me' => $me]);
$myActions = $myActionsStmt->fetchAll();

$myRiskStmt = db()->prepare(
    "SELECT COUNT(*) FROM risks
     WHERE deleted_at IS NULL AND owner_id = :me AND status IN ({$openList})"
);
$myRiskStmt->execute([':me' => $me]);
$myRiskCount = (int)$myRiskStmt->fetchColumn();

/* ------------------------------------------------------------------ */

$pageTitle    = t('Panel');
$pageSubtitle = 'Kurumsal IT ve siber risk durumu — ' . format_datetime(date('Y-m-d H:i:s'));
$activeMenu   = 'dashboard';
$needsCharts  = true;
$pageScripts  = '<script src="' . e(url('/assets/js/dashboard.js?v=20260911n')) . '"></script>';

require LAYOUT_PATH . '/header.php';

/** Bar listesi satırı. Sayı ve oran HER ZAMAN metin olarak görünür. */
$barRow = static function (string $label, int $count, int $total, string $cls, string $href = ''): void {
    $pct = $total > 0 ? (int)round($count * 100 / $total) : 0;
    echo '<div class="rk-barrow">'
       . '<div class="rk-barrow-label">'
       . ($href !== '' ? '<a href="' . e($href) . '">' . e($label) . '</a>' : e($label))
       . '</div>'
       /* Genislik satir ici stil DEGIL, utility sinifi: yuzde tam sayiya
          yuvarlandigi icin olasi tum degerler 0-100 arasi 101 tanedir ve
          hepsi app.css icinde tanimli (.rk-w-0 ... .rk-w-100). */
       . '<div class="rk-barrow-track"><div class="rk-barrow-fill ' . e($cls)
       . ' rk-w-' . (int)($count > 0 ? max(3, $pct) : 0) . '"></div></div>'
       . '<div class="rk-barrow-value"><strong>' . $count . '</strong>'
       . '<span class="rk-barrow-pct">%' . $pct . '</span></div>'
       . '</div>';
};
?>

<div id="rkDashboard" data-endpoint="<?= e(url('/api/dashboard_charts.php')) ?>">

<!-- ===================== Üst kartlar ===================== -->
<div class="rk-stats">
    <a class="rk-stat is-primary" href="<?= e(url('/risks/')) ?>">
        <div class="rk-stat-icon"><i class="bi bi-collection"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$stats['total'] ?></div>
            <div class="rk-stat-label">Toplam Risk</div>
        </div>
    </a>

    <a class="rk-stat is-critical" href="<?= e(url('/risks/?severity=Critical')) ?>">
        <div class="rk-stat-icon"><i class="bi bi-exclamation-octagon-fill"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$stats['critical'] ?></div>
            <div class="rk-stat-label">Kritik</div>
        </div>
    </a>

    <a class="rk-stat is-high" href="<?= e(url('/risks/?severity=High')) ?>">
        <div class="rk-stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$stats['high'] ?></div>
            <div class="rk-stat-label">Yüksek</div>
        </div>
    </a>

    <a class="rk-stat is-neutral" href="<?= e(url('/risks/?status=Open')) ?>">
        <div class="rk-stat-icon"><i class="bi bi-folder2-open"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$stats['open_risks'] ?></div>
            <div class="rk-stat-label">Açık Risk</div>
        </div>
    </a>

    <a class="rk-stat <?= $overdueActions > 0 ? 'is-critical' : 'is-neutral' ?>"
       href="<?= e(url('/risks/?overdue=1')) ?>">
        <div class="rk-stat-icon"><i class="bi bi-clock-history"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= $overdueActions ?></div>
            <div class="rk-stat-label">Geciken Aksiyon</div>
        </div>
    </a>

    <a class="rk-stat is-low" href="<?= e(url('/risks/?status=Mitigated')) ?>">
        <div class="rk-stat-icon"><i class="bi bi-shield-check"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$stats['mitigated'] ?></div>
            <div class="rk-stat-label">Azaltılmış</div>
        </div>
    </a>
</div>

<div class="row g-3">

    <!-- ===================== 5x5 Risk Matrisi ===================== -->
    <div class="col-12 col-xl-7">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-grid-3x3"></i> Risk Matrisi (5&times;5)</h2>
                <div class="rk-card-tools"><span class="rk-help">Etkin olasılık &times; etki</span></div>
            </div>
            <div class="rk-card-body">
                <div class="rk-matrix-wrap">
                    <div class="rk-matrix-yaxis"><span>Etki</span></div>
                    <table class="rk-matrix">
                        <tbody>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <tr>
                                <th scope="row" title="<?= e(impact_labels()[$i]) ?>"><?= $i ?></th>
                                <?php for ($l = 1; $l <= 5; $l++):
                                    $count = $matrix[$l][$i] ?? 0;
                                    $sev   = severity_from_score($l * $i);
                                ?>
                                    <td>
                                        <div class="rk-mx-cell <?= e(severity_class($sev)) ?><?= $count === 0 ? ' is-empty' : '' ?>"
                                             title="Olasılık <?= $l ?> (<?= e(likelihood_labels()[$l]) ?>) × Etki <?= $i ?> (<?= e(impact_labels()[$i]) ?>) = <?= $l * $i ?> · <?= e($sev) ?> · <?= $count ?> risk">
                                            <span class="rk-mx-count"><?= $count > 0 ? $count : '' ?></span>
                                            <span class="rk-mx-score"><?= $l * $i ?></span>
                                        </div>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endfor; ?>
                        <tr class="rk-matrix-foot">
                            <th></th>
                            <?php for ($l = 1; $l <= 5; $l++): ?>
                                <th scope="col" title="<?= e(likelihood_labels()[$l]) ?>"><?= $l ?></th>
                            <?php endfor; ?>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="rk-matrix-xaxis">Olasılık</div>

                <div class="rk-matrix-legend">
                    <?php foreach (['Low', 'Medium', 'High', 'Critical'] as $sev): ?>
                        <span class="rk-legend-item">
                            <span class="rk-legend-swatch <?= e(severity_class($sev)) ?>"></span>
                            <?= e($sev) ?>
                            <strong><?= (int)($severityCounts[$sev] ?? 0) ?></strong>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== Seviye + Durum ===================== -->
    <div class="col-12 col-xl-5">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-bar-chart-steps"></i> Seviyeye Göre</h2>
            </div>
            <div class="rk-card-body">
                <?php if ((int)$stats['total'] === 0): ?>
                    <?= empty_state('Henüz risk kaydı yok', '', 'bi-pie-chart') ?>
                <?php else: ?>
                    <?php foreach (['Critical', 'High', 'Medium', 'Low'] as $sev): ?>
                        <?php $barRow(
                            $sev,
                            (int)($severityCounts[$sev] ?? 0),
                            (int)$stats['total'],
                            severity_class($sev),
                            url('/risks/?severity=' . $sev)
                        ); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-flag"></i> Duruma Göre</h2>
            </div>
            <div class="rk-card-body">
                <?php if ($statusCounts === []): ?>
                    <?= empty_state('Veri yok', '', 'bi-flag') ?>
                <?php else: ?>
                    <?php foreach (risk_statuses() as $st):
                        $c = (int)($statusCounts[$st] ?? 0);
                        if ($c === 0) { continue; }
                        $barRow($st, $c, (int)$stats['total'], 'is-neutral-fill',
                                url('/risks/?status=' . urlencode($st)));
                    endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php if ($isExec): ?>
<!-- ===================== Trend ===================== -->
<div class="rk-card">
    <div class="rk-card-head">
        <h2 class="rk-card-title"><i class="bi bi-graph-up"></i> Risk Trendi</h2>
        <div class="rk-card-tools"><span class="rk-help">Son 12 ay</span></div>
    </div>
    <div class="rk-card-body">
        <div class="rk-chart rk-chart-lg" data-rk-chart>
            <canvas id="rkTrendChart" aria-label="Aylara göre açılan ve kapanan risk sayısı"></canvas>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- ===================== Kategori ===================== -->
    <div class="col-12 <?= $isExec ? 'col-xl-6' : '' ?>">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-tags"></i> Kategoriye Göre</h2>
            </div>
            <div class="rk-card-body">
                <div class="rk-chart rk-chart-tall" data-rk-chart>
                    <canvas id="rkCategoryChart" aria-label="Kategoriye göre risk sayısı"></canvas>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isExec): ?>
    <!-- ===================== Departman ===================== -->
    <div class="col-12 col-xl-6">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-diagram-3"></i> Departmana Göre</h2>
                <div class="rk-card-tools"><span class="rk-help">Kritik + Yüksek sayısı ipucunda</span></div>
            </div>
            <div class="rk-card-body">
                <div class="rk-chart rk-chart-tall" data-rk-chart>
                    <canvas id="rkDepartmentChart" aria-label="Departmana göre risk sayısı"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ===================== Acil aksiyon ===================== -->
<div class="rk-card">
    <div class="rk-card-head">
        <h2 class="rk-card-title"><i class="bi bi-exclamation-octagon"></i> Acil Aksiyon Gerektiren Riskler</h2>
        <div class="rk-card-tools">
            <a class="rk-btn rk-btn-sm" href="<?= e(url('/risks/?severity=Critical')) ?>">Tümü</a>
        </div>
    </div>
    <div class="rk-card-body is-flush">
        <?php if ($criticalOpen === []): ?>
            <?= empty_state('Kritik veya yüksek seviyeli açık risk yok',
                            'Risk kaydı oluşturuldukça burada listelenecek.', 'bi-shield-check') ?>
        <?php else: ?>
        <div class="rk-table-wrap">
            <table class="rk-table">
                <thead>
                    <tr><th>Kod</th><th>Başlık</th><th>Departman</th><th>Sahip</th>
                        <th>Skor</th><th>Seviye</th><th>Termin</th></tr>
                </thead>
                <tbody>
                <?php foreach ($criticalOpen as $r): ?>
                    <tr>
                        <td class="rk-code"><?= e($r['risk_code']) ?></td>
                        <td><a class="rk-link-strong" href="<?= e(url('/risks/view.php?id=' . (int)$r['id'])) ?>">
                            <?= e(str_limit($r['title'], 52)) ?></a></td>
                        <td><?= e($r['department']) ?></td>
                        <td><?= e($r['owner']) ?></td>
                        <td><?= score_chip((int)$r['score'], $r['severity']) ?></td>
                        <td><?= severity_badge($r['severity'], true) ?></td>
                        <td><?= due_date_cell($r['target_date'], $r['status'], risk_open_statuses()) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <!-- ===================== Yaklaşan terminler ===================== -->
    <div class="col-12 col-xl-6">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-calendar-event"></i> Yaklaşan Terminler</h2>
                <div class="rk-card-tools"><span class="rk-help"><?= $warnDays ?> gün içinde</span></div>
            </div>
            <div class="rk-card-body is-flush">
                <?php if ($upcoming === []): ?>
                    <?= empty_state('Yaklaşan termin yok', '', 'bi-calendar-check') ?>
                <?php else: ?>
                <table class="rk-table">
                    <tbody>
                    <?php foreach ($upcoming as $r): ?>
                        <tr>
                            <td class="rk-code"><?= e($r['risk_code']) ?></td>
                            <td><a href="<?= e(url('/risks/view.php?id=' . (int)$r['id'])) ?>">
                                <?= e(str_limit($r['title'], 40)) ?></a></td>
                            <td><?= severity_badge($r['severity']) ?></td>
                            <td><?= e(format_date($r['target_date'])) ?>
                                <div class="rk-cell-sub"><?= (int)days_until($r['target_date']) ?> gün kaldı</div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===================== Geciken aksiyonlar ===================== -->
    <div class="col-12 col-xl-6">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-clock-history"></i> Geciken Aksiyonlar</h2>
            </div>
            <div class="rk-card-body is-flush">
                <?php if ($lateActions === []): ?>
                    <?= empty_state('Geciken aksiyon yok', '', 'bi-check2-circle') ?>
                <?php else: ?>
                <table class="rk-table">
                    <tbody>
                    <?php foreach ($lateActions as $a): ?>
                        <tr>
                            <td class="rk-code"><?= e($a['risk_code']) ?></td>
                            <td><a href="<?= e(url('/risks/view.php?id=' . (int)$a['risk_id'])) ?>">
                                <?= e(str_limit($a['title'], 38)) ?></a>
                                <div class="rk-cell-sub"><?= e($a['owner']) ?></div></td>
                            <td><?= priority_badge($a['priority']) ?></td>
                            <td><?= due_date_cell($a['due_date'], $a['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ===================== Bana atananlar ===================== -->
<div class="rk-card">
    <div class="rk-card-head">
        <h2 class="rk-card-title"><i class="bi bi-person-check"></i> Bana Atananlar</h2>
        <div class="rk-card-tools">
            <a class="rk-btn rk-btn-sm" href="<?= e(url('/risks/?owner=' . $me)) ?>">
                <?= $myRiskCount ?> açık risk
            </a>
        </div>
    </div>
    <div class="rk-card-body is-flush">
        <?php if ($myActions === []): ?>
            <?= empty_state('Size atanmış açık aksiyon yok', '', 'bi-person-check') ?>
        <?php else: ?>
        <table class="rk-table">
            <thead>
                <tr><th>Risk</th><th>Aksiyon</th><th>Öncelik</th><th>Durum</th><th>Termin</th></tr>
            </thead>
            <tbody>
            <?php foreach ($myActions as $a): ?>
                <tr>
                    <td class="rk-code">
                        <a href="<?= e(url('/risks/view.php?id=' . (int)$a['risk_id'])) ?>">
                            <?= e($a['risk_code']) ?></a></td>
                    <td><?= e(str_limit($a['title'], 52)) ?></td>
                    <td><?= priority_badge($a['priority']) ?></td>
                    <td><?= status_badge($a['status']) ?></td>
                    <td><?= due_date_cell($a['due_date'], $a['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

</div><!-- /#rkDashboard -->

<?php require LAYOUT_PATH . '/footer.php'; ?>
