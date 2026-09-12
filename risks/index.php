<?php
declare(strict_types=1);

/**
 * RiskOps - Risk Register (liste)
 * /var/www/riskops/risks/index.php
 *
 * Filtre + siralama + sayfalama.
 *
 * GUVENLIK NOTU: ORDER BY kolonu kullanıcı girdisinden GELMEZ.
 * Gelen "sort" değeri yalnızca bir beyaz liste anahtaridir; gerçek SQL
 * ifadesi sunucuda sabit tanimlidir. Diger tüm değerler prepared
 * statement parametresi olarak baglanir.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_can('risk.view');

$categories  = categories_list(false);
$departments = departments_list(false);
$users       = users_list(false);

/* ------------------------------------------------------------------ */
/* 1) Filtreleri oku ve dogrula                                        */
/* ------------------------------------------------------------------ */

$f = [
    'q'          => input('q'),
    'severity'   => input_enum('severity', severities()),
    'status'     => input_enum('status', risk_statuses()),
    'treatment'  => input_enum('treatment', treatment_strategies()),
    'category'   => input_int('category'),
    'department' => input_int('department'),
    'owner'      => input_int('owner'),
    'from'       => input_date('from'),
    'to'         => input_date('to'),
    'overdue'    => input('overdue') === '1' ? '1' : null,
];

// Var olmayan id gelirse filtreyi yok say
if (!lookup_has($categories,  $f['category']))   { $f['category']   = null; }
if (!lookup_has($departments, $f['department'])) { $f['department'] = null; }
if (!lookup_has($users,       $f['owner']))      { $f['owner']      = null; }

$activeFilters = count(array_filter($f, static fn($v) => $v !== null && $v !== ''));

/* ------------------------------------------------------------------ */
/* 2) WHERE kur                                                        */
/* ------------------------------------------------------------------ */

$where  = ['r.deleted_at IS NULL'];
$params = [];

if ($f['q'] !== null) {
    // DIKKAT: EMULATE_PREPARES=false olduğu için MariaDB native prepared
    // statement'ta AYNI isimli placeholder birden fazla kez kullanilamaz
    // (SQLSTATE HY093). Her kosul için ayri placeholder gerekir.
    $where[] = '(r.risk_code LIKE :q1 OR r.title LIKE :q2 OR r.asset_name LIKE :q3)';
    // LIKE joker karakterleri (% _) ve ters bolu kacislanir; aksi halde
    // kullanıcı "%" yazarak tüm kayıtları cekebilir.
    $like = '%' . addcslashes($f['q'], '\\%_') . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
}
if ($f['severity'] !== null) {
    $where[] = 'COALESCE(r.residual_severity, r.inherent_severity) = :severity';
    $params[':severity'] = $f['severity'];
}
if ($f['status'] !== null) {
    $where[] = 'r.status = :status';
    $params[':status'] = $f['status'];
}
if ($f['treatment'] !== null) {
    $where[] = 'r.treatment_strategy = :treatment';
    $params[':treatment'] = $f['treatment'];
}
if ($f['category'] !== null) {
    $where[] = 'r.category_id = :category';
    $params[':category'] = $f['category'];
}
if ($f['department'] !== null) {
    $where[] = 'r.department_id = :department';
    $params[':department'] = $f['department'];
}
if ($f['owner'] !== null) {
    $where[] = 'r.owner_id = :owner';
    $params[':owner'] = $f['owner'];
}
if ($f['from'] !== null) {
    $where[] = 'r.target_date >= :dfrom';
    $params[':dfrom'] = $f['from'];
}
if ($f['to'] !== null) {
    $where[] = 'r.target_date <= :dto';
    $params[':dto'] = $f['to'];
}
if ($f['overdue'] !== null) {
    $openList = "'" . implode("','", risk_open_statuses()) . "'";
    $where[] = "(r.status IN ({$openList}) AND r.target_date IS NOT NULL AND r.target_date < CURDATE())";
}

$whereSql = implode("\n   AND ", $where);

/* ------------------------------------------------------------------ */
/* 3) Siralama (beyaz liste)                                           */
/* ------------------------------------------------------------------ */

$sortMap = [
    'code'       => 'r.risk_code',
    'title'      => 'r.title',
    'category'   => 'c.name',
    'department' => 'd.name',
    'owner'      => 'u.name',
    'inherent'   => 'r.inherent_score',
    'residual'   => 'r.residual_score',
    'severity'   => "FIELD(COALESCE(r.residual_severity, r.inherent_severity),'Low','Medium','High','Critical')",
    'status'     => 'r.status',
    'target'     => 'r.target_date',
    'created'    => 'r.created_at',
    'updated'    => 'r.updated_at',
];

$sort = input_enum('sort', array_keys($sortMap), 'created');
$dir  = input_enum('dir', ['asc', 'desc'], 'desc');

// NULL terminler her zaman en sona
$orderSql = $sortMap[$sort] . ' ' . strtoupper($dir);
if ($sort === 'target' || $sort === 'residual') {
    $orderSql = $sortMap[$sort] . ' IS NULL, ' . $orderSql;
}
$orderSql .= ', r.id DESC';

/* ------------------------------------------------------------------ */
/* 4) Say + sayfala                                                    */
/* ------------------------------------------------------------------ */

$countStmt = db()->prepare(
    "SELECT COUNT(*)
     FROM risks r
     JOIN risk_categories c ON c.id = r.category_id
     JOIN departments     d ON d.id = r.department_id
     JOIN users           u ON u.id = r.owner_id
     WHERE {$whereSql}"
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$page = paginate($total, per_page(), input_int('page', 1) ?? 1);

$listStmt = db()->prepare(
    "SELECT r.id, r.risk_code, r.title, r.asset_name, r.status, r.target_date,
            r.inherent_score, r.inherent_severity,
            r.residual_score, r.residual_severity,
            r.treatment_strategy, r.updated_at,
            c.name AS category, c.color AS category_color,
            d.name AS department,
            u.name AS owner
     FROM risks r
     JOIN risk_categories c ON c.id = r.category_id
     JOIN departments     d ON d.id = r.department_id
     JOIN users           u ON u.id = r.owner_id
     WHERE {$whereSql}
     ORDER BY {$orderSql}
     LIMIT {$page['per_page']} OFFSET {$page['offset']}"
);
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

/* ------------------------------------------------------------------ */
/* 5) Siralanabilir kolon başlığı                                      */
/* ------------------------------------------------------------------ */

$th = static function (string $key, string $label) use ($sort, $dir): string {
    $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    $icon = $sort !== $key
        ? '<i class="bi bi-arrow-down-up rk-u-dim"></i>'
        : ($dir === 'asc' ? '<i class="bi bi-arrow-up"></i>' : '<i class="bi bi-arrow-down"></i>');

    return '<th><a href="' . e(query_url(['sort' => $key, 'dir' => $nextDir], ['page']))
         . '">' . e($label) . ' ' . $icon . '</a></th>';
};

/* ------------------------------------------------------------------ */

$pageTitle    = 'Risk Register';
$pageSubtitle = $total . ' kayıt'
              . ($activeFilters > 0 ? ' - ' . $activeFilters . ' filtre aktif' : '');
$activeMenu   = 'risks';
$pageActions  = can('risk.create')
    ? '<a class="rk-btn rk-btn-primary" href="' . e(url('/risks/create.php'))
      . '"><i class="bi bi-plus-lg"></i> Yeni Risk</a>'
    : '';

require LAYOUT_PATH . '/header.php';
?>

<form method="get" class="rk-card rk-filters">
    <div class="rk-card-body">
        <div class="rk-filter-grid">

            <div class="rk-field rk-filter-wide">
                <label class="rk-label" for="q">Arama</label>
                <input class="rk-input" type="search" id="q" name="q"
                       value="<?= e($f['q'] ?? '') ?>"
                       placeholder="Risk kodu, başlık veya varlık">
            </div>

            <div class="rk-field">
                <label class="rk-label" for="severity">Seviye</label>
                <select class="rk-select" id="severity" name="severity">
                    <option value="">Tümü</option>
                    <?= options_from_values(severities(), $f['severity']) ?>
                </select>
            </div>

            <div class="rk-field">
                <label class="rk-label" for="status">Durum</label>
                <select class="rk-select" id="status" name="status">
                    <option value="">Tümü</option>
                    <?= options_from_values(risk_statuses(), $f['status']) ?>
                </select>
            </div>

            <div class="rk-field">
                <label class="rk-label" for="category">Kategori</label>
                <select class="rk-select" id="category" name="category">
                    <option value="">Tümü</option>
                    <?= options_html($categories, $f['category']) ?>
                </select>
            </div>

            <div class="rk-field">
                <label class="rk-label" for="department">Departman</label>
                <select class="rk-select" id="department" name="department">
                    <option value="">Tümü</option>
                    <?= options_html($departments, $f['department']) ?>
                </select>
            </div>

            <div class="rk-field">
                <label class="rk-label" for="owner">Risk Sahibi</label>
                <select class="rk-select" id="owner" name="owner">
                    <option value="">Tümü</option>
                    <?= options_html($users, $f['owner']) ?>
                </select>
            </div>

            <div class="rk-field">
                <label class="rk-label" for="treatment">Strateji</label>
                <select class="rk-select" id="treatment" name="treatment">
                    <option value="">Tümü</option>
                    <?= options_from_values(treatment_strategies(), $f['treatment']) ?>
                </select>
            </div>

            <div class="rk-field">
                <label class="rk-label" for="from">Termin (başlangıç)</label>
                <input class="rk-input" type="date" id="from" name="from" value="<?= e($f['from'] ?? '') ?>">
            </div>

            <div class="rk-field">
                <label class="rk-label" for="to">Termin (bitiş)</label>
                <input class="rk-input" type="date" id="to" name="to" value="<?= e($f['to'] ?? '') ?>">
            </div>

        </div>

        <div class="rk-filter-actions">
            <label class="rk-check">
                <input type="checkbox" name="overdue" value="1" <?= $f['overdue'] ? 'checked' : '' ?>>
                <span>Yalnızca gecikenler</span>
            </label>

            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <input type="hidden" name="dir" value="<?= e($dir) ?>">

            <div class="ms-auto d-flex gap-2">
                <?php if ($activeFilters > 0): ?>
                    <a class="rk-btn" href="<?= e(url('/risks/')) ?>">
                        <i class="bi bi-x-lg"></i> Temizle
                    </a>
                <?php endif; ?>
                <button type="submit" class="rk-btn rk-btn-primary">
                    <i class="bi bi-funnel"></i> Filtrele
                </button>
            </div>
        </div>
    </div>
</form>

<div class="rk-card">
    <div class="rk-card-body is-flush">
        <?php if ($rows === []): ?>
            <?= empty_state(
                $activeFilters > 0 ? 'Filtrelere uyan risk bulunamadı' : 'Henüz risk kaydı yok',
                $activeFilters > 0
                    ? 'Filtreleri genişletmeyi deneyin.'
                    : 'İlk risk kaydını oluşturarak başlayın.',
                $activeFilters > 0 ? 'bi-funnel' : 'bi-inbox',
                ($activeFilters === 0 && can('risk.create'))
                    ? '<a class="rk-btn rk-btn-primary" href="' . e(url('/risks/create.php'))
                      . '"><i class="bi bi-plus-lg"></i> Yeni Risk</a>'
                    : ''
            ) ?>
        <?php else: ?>
        <?php
        /* Toplu islem YALNIZCA risk.update yetkisi olana gosterilir.
           Gostermemek bir guvenlik onlemi degil (asil kontrol
           risks/bulk.php icinde); yetkisi olmayana calismayan bir
           arayuz sunmamak icin. */
        $canBulk = can('risk.update');
        ?>
        <form method="post" action="<?= e(url('/risks/bulk.php')) ?>"
              <?= $canBulk ? 'data-rk-bulk' : '' ?>>
            <?= csrf_field() ?>

            <?php if ($canBulk): ?>
            <div class="rk-bulk-bar" data-rk-bulk-bar hidden>
                <div class="rk-bulk-count">
                    <i class="bi bi-check2-square"></i>
                    <strong data-rk-bulk-count>0</strong> risk seçildi
                </div>

                <div class="rk-bulk-actions">
                    <select class="rk-input rk-input-sm" name="bulk_owner_id" data-rk-bulk-input="assign">
                        <option value="">Sahip seç...</option>
                        <?= options_html(users_list(), null) ?>
                    </select>
                    <button type="submit" class="rk-btn rk-btn-sm"
                            name="bulk_action" value="assign">
                        <i class="bi bi-person-check"></i> Ata
                    </button>

                    <select class="rk-input rk-input-sm" name="bulk_status" data-rk-bulk-input="status">
                        <option value="">Durum seç...</option>
                        <?= options_from_values(risk_statuses(), null) ?>
                    </select>
                    <button type="submit" class="rk-btn rk-btn-sm"
                            name="bulk_action" value="status">
                        <i class="bi bi-arrow-repeat"></i> Değiştir
                    </button>

                    <button type="submit" class="rk-btn rk-btn-sm"
                            name="bulk_action" value="close"
                            data-rk-confirm="Seçili riskler kapatılacak. Onaylıyor musunuz?">
                        <i class="bi bi-check2-circle"></i> Kapat
                    </button>
                </div>
            </div>
            <?php endif; ?>

        <div class="rk-table-wrap">
            <table class="rk-table">
                <thead>
                    <tr>
                        <?php if ($canBulk): ?>
                        <th class="rk-u-shrink">
                            <input type="checkbox" class="rk-check" data-rk-bulk-all
                                   aria-label="Tümünü seç">
                        </th>
                        <?php endif; ?>
                        <?= $th('code', 'Kod') ?>
                        <?= $th('title', 'Başlık') ?>
                        <?= $th('category', 'Kategori') ?>
                        <?= $th('department', 'Departman') ?>
                        <?= $th('owner', 'Sahip') ?>
                        <?= $th('inherent', 'Inherent') ?>
                        <?= $th('residual', 'Residual') ?>
                        <?= $th('severity', 'Seviye') ?>
                        <?= $th('status', 'Durum') ?>
                        <?= $th('target', 'Termin') ?>
                        <th class="rk-u-shrink"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $effSeverity = $r['residual_severity'] ?? $r['inherent_severity'];
                ?>
                    <tr>
                        <?php if ($canBulk): ?>
                        <td>
                            <input type="checkbox" class="rk-check" name="ids[]"
                                   value="<?= (int)$r['id'] ?>" data-rk-bulk-item
                                   aria-label="<?= e($r['risk_code']) ?> seç">
                        </td>
                        <?php endif; ?>
                        <td class="rk-code"><?= e($r['risk_code']) ?></td>
                        <td>
                            <a class="rk-link-strong" href="<?= e(url('/risks/view.php?id=' . (int)$r['id'])) ?>">
                                <?= e(str_limit($r['title'], 58)) ?>
                            </a>
                            <?php if (($r['asset_name'] ?? '') !== ''): ?>
                                <div class="rk-cell-sub"><i class="bi bi-hdd"></i> <?= e(str_limit($r['asset_name'], 42)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= category_dot($r['category_color']) ?>
                            <?= e($r['category']) ?>
                        </td>
                        <td><?= e($r['department']) ?></td>
                        <td><?= e($r['owner']) ?></td>
                        <td><?= score_chip((int)$r['inherent_score'], $r['inherent_severity']) ?></td>
                        <td><?= score_chip(
                                $r['residual_score'] !== null ? (int)$r['residual_score'] : null,
                                $r['residual_severity']
                            ) ?></td>
                        <td><?= severity_badge($effSeverity) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td><?= due_date_cell($r['target_date'], $r['status'], risk_open_statuses()) ?></td>
                        <td>
                            <div class="rk-row-actions">
                                <a class="rk-icon-btn" title="Detay"
                                   href="<?= e(url('/risks/view.php?id=' . (int)$r['id'])) ?>">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (can('risk.update')): ?>
                                <a class="rk-icon-btn" title="Düzenle"
                                   href="<?= e(url('/risks/edit.php?id=' . (int)$r['id'])) ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($page['pages'] > 1): ?>
    <div class="rk-pagination">
        <div class="rk-pagination-info">
            <?= $page['from'] ?>-<?= $page['to'] ?> / <?= $page['total'] ?> kayıt
        </div>
        <div class="rk-pagination-links">
            <?php if ($page['has_prev']): ?>
                <a class="rk-btn rk-btn-sm" href="<?= e(query_url(['page' => 1])) ?>">&laquo;</a>
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
                <a class="rk-btn rk-btn-sm" href="<?= e(query_url(['page' => $page['pages']])) ?>">&raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require LAYOUT_PATH . '/footer.php'; ?>
