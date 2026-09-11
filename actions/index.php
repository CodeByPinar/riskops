<?php
declare(strict_types=1);

/**
 * RiskOps - Action Plans (liste)
 * /var/www/riskops/actions/index.php
 *
 * GÜVENLİK NOTU: ORDER BY kolonu kullanıcı girdisinden GELMEZ; gelen "sort"
 * değeri yalnızca bir beyaz liste anahtarıdır. Diğer tüm değerler prepared
 * statement parametresi olarak bağlanır.
 *
 * NOT: EMULATE_PREPARES=false olduğu için aynı isimli placeholder birden
 * fazla kez kullanılamaz (SQLSTATE HY093) - arama üç ayrı placeholder alır.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_can('action.view');

$users = users_list(false);

/* ------------------------------------------------------------------ */
/* 1) Filtreler                                                        */
/* ------------------------------------------------------------------ */

$f = [
    'q'        => input('q'),
    'status'   => input_enum('status', action_statuses()),
    'priority' => input_enum('priority', action_priorities()),
    'owner'    => input_int('owner'),
    'risk'     => input_int('risk'),
    'overdue'  => input('overdue') === '1' ? '1' : null,
];

if (!lookup_has($users, $f['owner'])) {
    $f['owner'] = null;
}

$activeFilters = count(array_filter($f, static fn($v) => $v !== null && $v !== ''));

/* ------------------------------------------------------------------ */
/* 2) WHERE                                                            */
/* ------------------------------------------------------------------ */

$where  = ['r.deleted_at IS NULL'];
$params = [];

if ($f['q'] !== null) {
    $where[] = '(a.title LIKE :q1 OR a.description LIKE :q2 OR r.risk_code LIKE :q3)';
    $like = '%' . addcslashes($f['q'], '\\%_') . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
}
if ($f['status'] !== null) {
    $where[] = 'a.status = :status';
    $params[':status'] = $f['status'];
}
if ($f['priority'] !== null) {
    $where[] = 'a.priority = :priority';
    $params[':priority'] = $f['priority'];
}
if ($f['owner'] !== null) {
    $where[] = 'a.owner_id = :owner';
    $params[':owner'] = $f['owner'];
}
if ($f['risk'] !== null) {
    $where[] = 'a.risk_id = :risk';
    $params[':risk'] = $f['risk'];
}
if ($f['overdue'] !== null) {
    $where[] = "(a.status IN ('Open','In Progress') AND a.due_date IS NOT NULL AND a.due_date < CURDATE())";
}

$whereSql = implode("\n   AND ", $where);

/* ------------------------------------------------------------------ */
/* 3) Sıralama (beyaz liste)                                           */
/* ------------------------------------------------------------------ */

$sortMap = [
    'risk'     => 'r.risk_code',
    'title'    => 'a.title',
    'owner'    => 'u.name',
    'priority' => "FIELD(a.priority,'Low','Medium','High','Critical')",
    'status'   => "FIELD(a.status,'Open','In Progress','Completed','Cancelled')",
    'due'      => 'a.due_date',
    'created'  => 'a.created_at',
];

$sort = input_enum('sort', array_keys($sortMap), 'due');
$dir  = input_enum('dir', ['asc', 'desc'], 'asc');

$orderSql = $sortMap[$sort] . ' ' . strtoupper($dir);
if ($sort === 'due') {
    $orderSql = 'a.due_date IS NULL, ' . $orderSql;
}
$orderSql .= ', a.id DESC';

/* ------------------------------------------------------------------ */
/* 4) Say + sayfala                                                    */
/* ------------------------------------------------------------------ */

$countStmt = db()->prepare(
    "SELECT COUNT(*)
     FROM risk_actions a
     JOIN risks r ON r.id = a.risk_id
     JOIN users u ON u.id = a.owner_id
     WHERE {$whereSql}"
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$page = paginate($total, per_page(), input_int('page', 1) ?? 1);

$listStmt = db()->prepare(
    "SELECT a.id, a.title, a.priority, a.status, a.due_date, a.completed_at,
            r.id AS risk_id, r.risk_code, r.title AS risk_title,
            u.name AS owner
     FROM risk_actions a
     JOIN risks r ON r.id = a.risk_id
     JOIN users u ON u.id = a.owner_id
     WHERE {$whereSql}
     ORDER BY {$orderSql}
     LIMIT {$page['per_page']} OFFSET {$page['offset']}"
);
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

/* Özet sayaçlar - filtreden bağımsız, genel durum */
$summary = db()->query(
    "SELECT
        COALESCE(SUM(a.status = 'Open'), 0)        AS acik,
        COALESCE(SUM(a.status = 'In Progress'), 0) AS devam,
        COALESCE(SUM(a.status = 'Completed'), 0)   AS tamam,
        COALESCE(SUM(a.status IN ('Open','In Progress')
                 AND a.due_date IS NOT NULL AND a.due_date < CURDATE()), 0) AS geciken
     FROM risk_actions a
     JOIN risks r ON r.id = a.risk_id AND r.deleted_at IS NULL"
)->fetch();

/* ------------------------------------------------------------------ */

$th = static function (string $key, string $label) use ($sort, $dir): string {
    $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    $icon = $sort !== $key
        ? '<i class="bi bi-arrow-down-up" style="opacity:.35"></i>'
        : ($dir === 'asc' ? '<i class="bi bi-arrow-up"></i>' : '<i class="bi bi-arrow-down"></i>');

    return '<th><a href="' . e(query_url(['sort' => $key, 'dir' => $nextDir], ['page']))
         . '">' . e($label) . ' ' . $icon . '</a></th>';
};

$pageTitle    = 'Action Plans';
$pageSubtitle = $total . ' aksiyon'
              . ($activeFilters > 0 ? ' — ' . $activeFilters . ' filtre aktif' : '');
$activeMenu   = 'actions';
$pageActions  = can('action.create')
    ? '<a class="rk-btn rk-btn-primary" href="' . e(url('/actions/create.php'))
      . '"><i class="bi bi-plus-lg"></i> Yeni Aksiyon</a>'
    : '';

require LAYOUT_PATH . '/header.php';
?>

<div class="rk-stats">
    <a class="rk-stat is-neutral" href="<?= e(url('/actions/?status=Open')) ?>">
        <div class="rk-stat-icon"><i class="bi bi-circle"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$summary['acik'] ?></div>
            <div class="rk-stat-label">Açık</div>
        </div>
    </a>
    <a class="rk-stat is-primary" href="<?= e(url('/actions/?status=' . urlencode('In Progress'))) ?>">
        <div class="rk-stat-icon"><i class="bi bi-hourglass-split"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$summary['devam'] ?></div>
            <div class="rk-stat-label">Devam Ediyor</div>
        </div>
    </a>
    <a class="rk-stat <?= (int)$summary['geciken'] > 0 ? 'is-critical' : 'is-neutral' ?>"
       href="<?= e(url('/actions/?overdue=1')) ?>">
        <div class="rk-stat-icon"><i class="bi bi-clock-history"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$summary['geciken'] ?></div>
            <div class="rk-stat-label">Geciken</div>
        </div>
    </a>
    <a class="rk-stat is-low" href="<?= e(url('/actions/?status=Completed')) ?>">
        <div class="rk-stat-icon"><i class="bi bi-check2-circle"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$summary['tamam'] ?></div>
            <div class="rk-stat-label">Tamamlanan</div>
        </div>
    </a>
    <a class="rk-stat is-primary" href="<?= e(url('/actions/?owner=' . (int)auth_id())) ?>">
        <div class="rk-stat-icon"><i class="bi bi-person-check"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><i class="bi bi-arrow-right-short"></i></div>
            <div class="rk-stat-label">Bana Atananlar</div>
        </div>
    </a>
</div>

<form method="get" class="rk-card rk-filters">
    <div class="rk-card-body">
        <div class="rk-filter-grid">
            <div class="rk-field rk-filter-wide">
                <label class="rk-label" for="q">Arama</label>
                <input class="rk-input" type="search" id="q" name="q"
                       value="<?= e($f['q'] ?? '') ?>"
                       placeholder="Aksiyon başlığı, açıklama veya risk kodu">
            </div>

            <div class="rk-field">
                <label class="rk-label" for="status">Durum</label>
                <select class="rk-select" id="status" name="status">
                    <option value="">Tümü</option>
                    <?= options_from_values(action_statuses(), $f['status']) ?>
                </select>
            </div>

            <div class="rk-field">
                <label class="rk-label" for="priority">Öncelik</label>
                <select class="rk-select" id="priority" name="priority">
                    <option value="">Tümü</option>
                    <?= options_from_values(action_priorities(), $f['priority']) ?>
                </select>
            </div>

            <div class="rk-field">
                <label class="rk-label" for="owner">Sorumlu</label>
                <select class="rk-select" id="owner" name="owner">
                    <option value="">Tümü</option>
                    <?= options_html($users, $f['owner']) ?>
                </select>
            </div>
        </div>

        <div class="rk-filter-actions">
            <label class="rk-check">
                <input type="checkbox" name="overdue" value="1" <?= $f['overdue'] ? 'checked' : '' ?>>
                <span>Yalnızca gecikenler</span>
            </label>

            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <input type="hidden" name="dir" value="<?= e($dir) ?>">
            <?php if ($f['risk'] !== null): ?>
                <input type="hidden" name="risk" value="<?= (int)$f['risk'] ?>">
            <?php endif; ?>

            <div class="ms-auto d-flex gap-2">
                <?php if ($activeFilters > 0): ?>
                    <a class="rk-btn" href="<?= e(url('/actions/')) ?>"><i class="bi bi-x-lg"></i> Temizle</a>
                <?php endif; ?>
                <button type="submit" class="rk-btn rk-btn-primary"><i class="bi bi-funnel"></i> Filtrele</button>
            </div>
        </div>
    </div>
</form>

<div class="rk-card">
    <div class="rk-card-body is-flush">
        <?php if ($rows === []): ?>
            <?= empty_state(
                $activeFilters > 0 ? 'Filtrelere uyan aksiyon bulunamadı' : 'Henüz aksiyon planı yok',
                $activeFilters > 0 ? 'Filtreleri genişletmeyi deneyin.' : 'Bir riske aksiyon ekleyerek başlayın.',
                $activeFilters > 0 ? 'bi-funnel' : 'bi-check2-square',
                ($activeFilters === 0 && can('action.create'))
                    ? '<a class="rk-btn rk-btn-primary" href="' . e(url('/actions/create.php'))
                      . '"><i class="bi bi-plus-lg"></i> Yeni Aksiyon</a>'
                    : ''
            ) ?>
        <?php else: ?>
        <div class="rk-table-wrap">
            <table class="rk-table">
                <thead>
                    <tr>
                        <?= $th('risk', 'Risk') ?>
                        <?= $th('title', 'Aksiyon') ?>
                        <?= $th('owner', 'Sorumlu') ?>
                        <?= $th('priority', 'Öncelik') ?>
                        <?= $th('status', 'Durum') ?>
                        <?= $th('due', 'Termin') ?>
                        <th style="width:1%"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $a): ?>
                    <tr>
                        <td class="rk-code">
                            <a href="<?= e(url('/risks/view.php?id=' . (int)$a['risk_id'])) ?>"
                               title="<?= e($a['risk_title']) ?>"><?= e($a['risk_code']) ?></a>
                        </td>
                        <td>
                            <span class="rk-link-strong"><?= e(str_limit($a['title'], 56)) ?></span>
                            <?php if ($a['completed_at'] !== null): ?>
                                <div class="rk-cell-sub">
                                    <i class="bi bi-check2"></i> <?= e(format_datetime($a['completed_at'])) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($a['owner']) ?></td>
                        <td><?= priority_badge($a['priority']) ?></td>
                        <td><?= status_badge($a['status']) ?></td>
                        <td><?= due_date_cell($a['due_date'], $a['status']) ?></td>
                        <td>
                            <div class="rk-row-actions">
                                <?php if (can('action.complete')
                                          && in_array($a['status'], action_open_statuses(), true)): ?>
                                    <form method="post" action="<?= e(url('/actions/complete.php')) ?>"
                                          class="d-inline"
                                          data-rk-confirm="Bu aksiyon tamamlandı olarak işaretlenecek. Onaylıyor musunuz?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                        <button type="submit" class="rk-icon-btn is-good" title="Tamamla">
                                            <i class="bi bi-check2"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (can('action.update')): ?>
                                    <a class="rk-icon-btn" title="Düzenle"
                                       href="<?= e(url('/actions/edit.php?id=' . (int)$a['id'])) ?>">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if (auth_role() === ROLE_ADMIN): ?>
                                    <form method="post" action="<?= e(url('/actions/delete.php')) ?>"
                                          class="d-inline"
                                          data-rk-confirm="Bu aksiyon kalıcı olarak silinecek. Onaylıyor musunuz?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                        <button type="submit" class="rk-icon-btn is-danger" title="Sil">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
