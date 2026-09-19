<?php
declare(strict_types=1);

/**
 * RiskOps - Değerlendirmeler
 * /var/www/riskops/assessments/index.php
 *
 * Risk detayındaki sekme tek bir riskin geçmişini gösterir; bu ekran
 * tüm riskler genelinde değerlendirme faaliyetini gösterir: kim,
 * ne zaman, hangi riski nasıl değerlendirdi.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_can('risk.view');

$users = users_list(false);

$f = [
    'q'        => input('q'),
    'type'     => input_enum('type', ['initial', 'review', 'residual']),
    'severity' => input_enum('severity', severities()),
    'assessor' => input_int('assessor'),
    'risk'     => input_int('risk'),
    'from'     => input_date('from'),
    'to'       => input_date('to'),
];
if (!lookup_has($users, $f['assessor'])) {
    $f['assessor'] = null;
}
$activeFilters = count(array_filter($f, static fn ($v) => $v !== null && $v !== ''));

$where  = ['r.deleted_at IS NULL'];
$params = [];

if ($f['q'] !== null) {
    $where[] = '(r.risk_code LIKE :q1 OR r.title LIKE :q2 OR a.notes LIKE :q3)';
    $like = '%' . addcslashes($f['q'], '\\%_') . '%';
    $params += [':q1' => $like, ':q2' => $like, ':q3' => $like];
}
if ($f['type'] !== null) {
    $where[] = 'a.assessment_type = :type';
    $params[':type'] = $f['type'];
}
if ($f['severity'] !== null) {
    $where[] = 'a.severity = :severity';
    $params[':severity'] = $f['severity'];
}
if ($f['assessor'] !== null) {
    $where[] = 'a.assessed_by = :assessor';
    $params[':assessor'] = $f['assessor'];
}
if ($f['risk'] !== null) {
    $where[] = 'a.risk_id = :risk';
    $params[':risk'] = $f['risk'];
}
if ($f['from'] !== null) {
    $where[] = 'a.assessed_at >= :dfrom';
    $params[':dfrom'] = $f['from'] . ' 00:00:00';
}
if ($f['to'] !== null) {
    $where[] = 'a.assessed_at <= :dto';
    $params[':dto'] = $f['to'] . ' 23:59:59';
}
$whereSql = implode("\n   AND ", $where);

$sortMap = [
    'date'     => 'a.assessed_at',
    'risk'     => 'r.risk_code',
    'type'     => "FIELD(a.assessment_type,'initial','review','residual')",
    'score'    => 'a.score',
    'severity' => "FIELD(a.severity,'Low','Medium','High','Critical')",
    'assessor' => 'u.name',
];
$sort = input_enum('sort', array_keys($sortMap), 'date');
$dir  = input_enum('dir', ['asc', 'desc'], 'desc');
$orderSql = $sortMap[$sort] . ' ' . strtoupper($dir) . ', a.id DESC';

$total = (int)db_value(
    "SELECT COUNT(*)
     FROM risk_assessments a
     JOIN risks r ON r.id = a.risk_id
     JOIN users u ON u.id = a.assessed_by
     WHERE {$whereSql}",
    $params
);

$page = paginate($total, per_page(), input_int('page', 1) ?? 1);

$rows = db_all(
    "SELECT a.id, a.assessment_type, a.likelihood, a.impact, a.score, a.severity,
            a.notes, a.assessed_at,
            r.id AS risk_id, r.risk_code, r.title AS risk_title,
            u.name AS assessor
     FROM risk_assessments a
     JOIN risks r ON r.id = a.risk_id
     JOIN users u ON u.id = a.assessed_by
     WHERE {$whereSql}
     ORDER BY {$orderSql}
     LIMIT {$page['per_page']} OFFSET {$page['offset']}",
    $params
);

/* Özet: son 30 günde kaç risk değerlendirildi, kaçı hiç gözden geçirilmedi */
$summary = db_row_required(
    "SELECT
        (SELECT COUNT(*) FROM risk_assessments a2
         JOIN risks r2 ON r2.id = a2.risk_id AND r2.deleted_at IS NULL
         WHERE a2.assessed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS son30,
        (SELECT COUNT(*) FROM risks r3 WHERE r3.deleted_at IS NULL) AS risk_sayisi,
        (SELECT COUNT(*) FROM risks r4 WHERE r4.deleted_at IS NULL
         AND NOT EXISTS (SELECT 1 FROM risk_assessments a4
                         WHERE a4.risk_id = r4.id AND a4.assessment_type <> 'initial')) AS hic_gozden_gecirilmemis"
);

$th = static function (string $key, string $label) use ($sort, $dir): string {
    $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    $icon = $sort !== $key
        ? '<i class="bi bi-arrow-down-up rk-u-dim"></i>'
        : ($dir === 'asc' ? '<i class="bi bi-arrow-up"></i>' : '<i class="bi bi-arrow-down"></i>');
    return '<th><a href="' . e(query_url(['sort' => $key, 'dir' => $nextDir], ['page']))
         . '">' . e($label) . ' ' . $icon . '</a></th>';
};

$typeClass = [
    'initial'  => 'st-open',
    'review'   => 'st-progress',
    'residual' => 'st-mitigated',
];
$typeShort = ['initial' => 'İlk', 'review' => 'Gözden geçirme', 'residual' => 'Residual'];

$pageTitle    = 'Değerlendirmeler';
$pageSubtitle = $total . ' kayıt'
              . ($activeFilters > 0 ? ' — ' . $activeFilters . ' filtre aktif' : '');
$activeMenu   = 'assessments';
$pageActions  = can('risk.assess')
    ? '<a class="rk-btn rk-btn-primary" href="' . e(url('/assessments/create.php'))
      . '"><i class="bi bi-plus-lg"></i> Yeni Değerlendirme</a>'
    : '';

require LAYOUT_PATH . '/header.php';
?>

<div class="rk-stats">
    <div class="rk-stat is-primary">
        <div class="rk-stat-icon"><i class="bi bi-clipboard-data"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$summary['son30'] ?></div>
            <div class="rk-stat-label"><?= te('Son 30 Günde') ?></div>
        </div>
    </div>
    <div class="rk-stat is-neutral">
        <div class="rk-stat-icon"><i class="bi bi-collection"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$summary['risk_sayisi'] ?></div>
            <div class="rk-stat-label"><?= te('Toplam Risk') ?></div>
        </div>
    </div>
    <div class="rk-stat <?= (int)$summary['hic_gozden_gecirilmemis'] > 0 ? 'is-high' : 'is-low' ?>">
        <div class="rk-stat-icon"><i class="bi bi-hourglass"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$summary['hic_gozden_gecirilmemis'] ?></div>
            <div class="rk-stat-label"><?= te('Hiç Gözden Geçirilmemiş') ?></div>
        </div>
    </div>
</div>

<form method="get" class="rk-card rk-filters">
    <div class="rk-card-body">
        <div class="rk-filter-grid">
            <div class="rk-field rk-filter-wide">
                <label class="rk-label" for="q"><?= te('Arama') ?></label>
                <input class="rk-input" type="search" id="q" name="q" value="<?= e($f['q'] ?? '') ?>"
                       placeholder="<?= te('Risk kodu, başlık veya not') ?>">
            </div>
            <div class="rk-field">
                <label class="rk-label" for="type"><?= te('Tür') ?></label>
                <select class="rk-select" id="type" name="type">
                    <option value=""><?= te('Tümü') ?></option>
                    <?php foreach (['initial', 'review', 'residual'] as $t): ?>
                        <option value="<?= e($t) ?>" <?= $f['type'] === $t ? 'selected' : '' ?>>
                            <?= e($typeShort[$t]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rk-field">
                <label class="rk-label" for="severity"><?= te('Seviye') ?></label>
                <select class="rk-select" id="severity" name="severity">
                    <option value=""><?= te('Tümü') ?></option>
                    <?= options_from_values(severities(), $f['severity']) ?>
                </select>
            </div>
            <div class="rk-field">
                <label class="rk-label" for="assessor"><?= te('Değerlendiren') ?></label>
                <select class="rk-select" id="assessor" name="assessor">
                    <option value=""><?= te('Tümü') ?></option>
                    <?= options_html($users, $f['assessor']) ?>
                </select>
            </div>
            <div class="rk-field">
                <label class="rk-label" for="from"><?= te('Başlangıç') ?></label>
                <input class="rk-input" type="date" id="from" name="from" value="<?= e($f['from'] ?? '') ?>">
            </div>
            <div class="rk-field">
                <label class="rk-label" for="to"><?= te('Bitiş') ?></label>
                <input class="rk-input" type="date" id="to" name="to" value="<?= e($f['to'] ?? '') ?>">
            </div>
        </div>
        <div class="rk-filter-actions">
            <span class="rk-help">
                <i class="bi bi-lock"></i> Değerlendirme kayıtları append-only — düzenlenemez, silinemez.
            </span>
            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <input type="hidden" name="dir" value="<?= e($dir) ?>">
            <?php if ($f['risk'] !== null): ?>
                <input type="hidden" name="risk" value="<?= (int)$f['risk'] ?>">
            <?php endif; ?>
            <div class="ms-auto d-flex gap-2">
                <?php if ($activeFilters > 0): ?>
                    <a class="rk-btn" href="<?= e(url('/assessments/')) ?>"><i class="bi bi-x-lg"></i> <?= te('Temizle') ?></a>
                <?php endif; ?>
                <button type="submit" class="rk-btn rk-btn-primary"><i class="bi bi-funnel"></i> <?= te('Filtrele') ?></button>
            </div>
        </div>
    </div>
</form>

<div class="rk-card">
    <div class="rk-card-body is-flush">
        <?php if ($rows === []): ?>
            <?= empty_state(
                $activeFilters > 0 ? 'Filtrelere uyan değerlendirme bulunamadı' : 'Henüz değerlendirme yok',
                '', $activeFilters > 0 ? 'bi-funnel' : 'bi-clipboard-data'
            ) ?>
        <?php else: ?>
        <div class="rk-table-wrap">
            <table class="rk-table">
                <thead>
                    <tr>
                        <?= $th('date', 'Tarih') ?>
                        <?= $th('risk', 'Risk') ?>
                        <?= $th('type', 'Tür') ?>
                        <th><?= te('Olasılık × Etki') ?></th>
                        <?= $th('score', 'Skor') ?>
                        <?= $th('severity', 'Seviye') ?>
                        <?= $th('assessor', 'Değerlendiren') ?>
                        <th><?= te('Not') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $a): ?>
                    <tr>
                        <td class="rk-u-nowrap"><?= e(format_datetime($a['assessed_at'])) ?></td>
                        <td>
                            <a class="rk-code" href="<?= e(url('/risks/view.php?id=' . (int)$a['risk_id'])) ?>">
                                <?= e($a['risk_code']) ?></a>
                            <div class="rk-cell-sub"><?= e(str_limit($a['risk_title'], 40)) ?></div>
                        </td>
                        <td><span class="rk-badge <?= e($typeClass[$a['assessment_type']] ?? 'st-none') ?>">
                            <?= e($typeShort[$a['assessment_type']] ?? $a['assessment_type']) ?></span></td>
                        <td class="rk-code"><?= (int)$a['likelihood'] ?> × <?= (int)$a['impact'] ?></td>
                        <td><?= score_chip((int)$a['score'], $a['severity']) ?></td>
                        <td><?= severity_badge($a['severity']) ?></td>
                        <td><?= e($a['assessor']) ?></td>
                        <td><?= $a['notes'] !== null && $a['notes'] !== ''
                                ? '<span title="' . e($a['notes']) . '">' . e(str_limit($a['notes'], 44)) . '</span>'
                                : '<span class="text-muted">—</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($page['pages'] > 1): ?>
    <div class="rk-pagination">
        <div class="rk-pagination-info"><?= $page['from'] ?>-<?= $page['to'] ?> / <?= $page['total'] ?> kayıt</div>
        <div class="rk-pagination-links">
            <?php if ($page['has_prev']): ?>
                <a class="rk-btn rk-btn-sm" href="<?= e(query_url(['page' => $page['current'] - 1])) ?>">Önceki</a>
            <?php endif; ?>
            <?php
            $start = max(1, $page['current'] - 2);
            $end   = min($page['pages'], $start + 4);
            $start = max(1, $end - 4);
            for ($p = $start; $p <= $end; $p++): ?>
                <a class="rk-btn rk-btn-sm<?= $p === $page['current'] ? ' is-current' : '' ?>"
                   href="<?= e(query_url(['page' => $p])) ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page['has_next']): ?>
                <a class="rk-btn rk-btn-sm" href="<?= e(query_url(['page' => $page['current'] + 1])) ?>">Sonraki</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require LAYOUT_PATH . '/footer.php'; ?>
