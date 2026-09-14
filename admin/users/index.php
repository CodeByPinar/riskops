<?php
declare(strict_types=1);

/**
 * RiskOps - Kullanıcılar
 * /var/www/riskops/admin/users/index.php
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
require_role(ROLE_ADMIN);

$departments = departments_list(false);

$f = [
    'q'          => input('q'),
    'role'       => input_enum('role', all_roles()),
    'status'     => input_enum('status', ['1', '0']),
    'department' => input_int('department'),
];
if (!lookup_has($departments, $f['department'])) {
    $f['department'] = null;
}
$activeFilters = count(array_filter($f, static fn ($v) => $v !== null && $v !== ''));

$where  = ['1=1'];
$params = [];

if ($f['q'] !== null) {
    // Aynı isimli placeholder tekrar kullanılamaz (EMULATE_PREPARES=false)
    $where[] = '(u.name LIKE :q1 OR u.email LIKE :q2 OR u.title LIKE :q3)';
    $like = '%' . addcslashes($f['q'], '\\%_') . '%';
    $params += [':q1' => $like, ':q2' => $like, ':q3' => $like];
}
if ($f['role'] !== null) {
    $where[] = 'u.role = :role';
    $params[':role'] = $f['role'];
}
if ($f['status'] !== null) {
    $where[] = 'u.status = :status';
    $params[':status'] = (int)$f['status'];
}
if ($f['department'] !== null) {
    $where[] = 'u.department_id = :dept';
    $params[':dept'] = $f['department'];
}
$whereSql = implode(' AND ', $where);

$sortMap = [
    'name'       => 'u.name',
    'email'      => 'u.email',
    'role'       => "FIELD(u.role,'viewer','analyst','manager','admin')",
    'department' => 'd.name',
    'status'     => 'u.status',
    'last_login' => 'u.last_login_at',
    'created'    => 'u.created_at',
];
$sort = input_enum('sort', array_keys($sortMap), 'name');
$dir  = input_enum('dir', ['asc', 'desc'], 'asc');
$orderSql = $sortMap[$sort] . ' ' . strtoupper($dir) . ', u.id ASC';

$countStmt = db()->prepare(
    "SELECT COUNT(*) FROM users u
     LEFT JOIN departments d ON d.id = u.department_id
     WHERE {$whereSql}"
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$page = paginate($total, per_page(), input_int('page', 1) ?? 1);

$listStmt = db()->prepare(
    "SELECT u.id, u.name, u.email, u.role, u.status, u.title,
            u.last_login_at, u.must_change_password, u.created_at,
            d.name AS department,
            (SELECT COUNT(*) FROM risks r WHERE r.owner_id = u.id AND r.deleted_at IS NULL) AS risk_sayisi,
            (SELECT COUNT(*) FROM risk_actions a WHERE a.owner_id = u.id) AS aksiyon_sayisi
     FROM users u
     LEFT JOIN departments d ON d.id = u.department_id
     WHERE {$whereSql}
     ORDER BY {$orderSql}
     LIMIT {$page['per_page']} OFFSET {$page['offset']}"
);
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

$counts = db()->query(
    "SELECT
        COUNT(*) AS toplam,
        COALESCE(SUM(status = 1), 0) AS aktif,
        COALESCE(SUM(role = 'admin' AND status = 1), 0) AS admin_sayisi
     FROM users"
)->fetch();

$th = static function (string $key, string $label) use ($sort, $dir): string {
    $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    $icon = $sort !== $key
        ? '<i class="bi bi-arrow-down-up rk-u-dim"></i>'
        : ($dir === 'asc' ? '<i class="bi bi-arrow-up"></i>' : '<i class="bi bi-arrow-down"></i>');
    return '<th><a href="' . e(query_url(['sort' => $key, 'dir' => $nextDir], ['page']))
         . '">' . e($label) . ' ' . $icon . '</a></th>';
};

$roleClass = [
    ROLE_ADMIN   => 'rl-admin',
    ROLE_MANAGER => 'rl-manager',
    ROLE_ANALYST => 'rl-analyst',
    ROLE_VIEWER  => 'rl-viewer',
];

$pageTitle    = 'Kullanıcılar';
$pageSubtitle = $counts['toplam'] . ' kullanıcı · ' . $counts['aktif'] . ' aktif · '
              . $counts['admin_sayisi'] . ' admin';
$activeMenu   = 'admin.users';
$pageActions  = '<a class="rk-btn rk-btn-primary" href="' . e(url('/admin/users/create.php'))
              . '"><i class="bi bi-person-plus"></i> Yeni Kullanıcı</a>';

require LAYOUT_PATH . '/header.php';
?>

<form method="get" class="rk-card rk-filters">
    <div class="rk-card-body">
        <div class="rk-filter-grid">
            <div class="rk-field rk-filter-wide">
                <label class="rk-label" for="q">Arama</label>
                <input class="rk-input" type="search" id="q" name="q" value="<?= e($f['q'] ?? '') ?>"
                       placeholder="Ad, e-posta veya ünvan">
            </div>
            <div class="rk-field">
                <label class="rk-label" for="role">Rol</label>
                <select class="rk-select" id="role" name="role">
                    <option value="">Tümü</option>
                    <?php foreach (all_roles() as $r): ?>
                        <option value="<?= e($r) ?>" <?= $f['role'] === $r ? 'selected' : '' ?>>
                            <?= e(role_label($r)) ?>
                        </option>
                    <?php endforeach; ?>
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
                <label class="rk-label" for="status">Durum</label>
                <select class="rk-select" id="status" name="status">
                    <option value="">Tümü</option>
                    <option value="1" <?= $f['status'] === '1' ? 'selected' : '' ?>>Aktif</option>
                    <option value="0" <?= $f['status'] === '0' ? 'selected' : '' ?>>Pasif</option>
                </select>
            </div>
        </div>
        <div class="rk-filter-actions">
            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <input type="hidden" name="dir" value="<?= e($dir) ?>">
            <div class="ms-auto d-flex gap-2">
                <?php if ($activeFilters > 0): ?>
                    <a class="rk-btn" href="<?= e(url('/admin/users/')) ?>"><i class="bi bi-x-lg"></i> Temizle</a>
                <?php endif; ?>
                <button type="submit" class="rk-btn rk-btn-primary"><i class="bi bi-funnel"></i> Filtrele</button>
            </div>
        </div>
    </div>
</form>

<div class="rk-card">
    <div class="rk-card-body is-flush">
        <?php if ($rows === []): ?>
            <?= empty_state('Filtrelere uyan kullanıcı bulunamadı', '', 'bi-people') ?>
        <?php else: ?>
        <div class="rk-table-wrap">
            <table class="rk-table">
                <thead>
                    <tr>
                        <?= $th('name', 'Ad Soyad') ?>
                        <?= $th('email', 'E-posta') ?>
                        <?= $th('role', 'Rol') ?>
                        <?= $th('department', 'Departman') ?>
                        <th>Yük</th>
                        <?= $th('last_login', 'Son Giriş') ?>
                        <?= $th('status', 'Durum') ?>
                        <th class="rk-u-shrink"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $u):
                    $isSelf = (int)$u['id'] === auth_id();
                ?>
                    <tr<?= (int)$u['status'] === 0 ? ' class="is-muted-row"' : '' ?>>
                        <td>
                            <div class="rk-user-cell">
                                <span class="rk-avatar rk-avatar-sm"><?= e(initials($u['name'])) ?></span>
                                <div>
                                    <a class="rk-link-strong"
                                       href="<?= e(url('/admin/users/edit.php?id=' . (int)$u['id'])) ?>">
                                        <?= e($u['name']) ?></a>
                                    <?php if ($isSelf): ?><span class="rk-badge sev-none">siz</span><?php endif; ?>
                                    <?php if ((int)$u['must_change_password'] === 1): ?>
                                        <span class="rk-badge st-accepted" title="İlk girişte parola değiştirmeli">
                                            <i class="bi bi-key"></i> parola bekliyor</span>
                                    <?php endif; ?>
                                    <?php if (($u['title'] ?? '') !== ''): ?>
                                        <div class="rk-cell-sub"><?= e($u['title']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="rk-code"><?= e($u['email']) ?></td>
                        <td><span class="rk-badge <?= e($roleClass[$u['role']] ?? 'sev-none') ?>">
                            <?= e(role_label($u['role'])) ?></span></td>
                        <td><?= e((string)($u['department'] ?? '—')) ?></td>
                        <td>
                            <span class="rk-cell-sub">
                                <?= (int)$u['risk_sayisi'] ?> risk · <?= (int)$u['aksiyon_sayisi'] ?> aksiyon
                            </span>
                        </td>
                        <td><?= $u['last_login_at'] !== null
                                ? e(format_datetime($u['last_login_at']))
                                : '<span class="text-muted">hiç</span>' ?></td>
                        <td>
                            <?php if ((int)$u['status'] === 1): ?>
                                <span class="rk-badge sev-low">Aktif</span>
                            <?php else: ?>
                                <span class="rk-badge st-closed">Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="rk-row-actions">
                                <a class="rk-icon-btn" title="Düzenle"
                                   href="<?= e(url('/admin/users/edit.php?id=' . (int)$u['id'])) ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if (!$isSelf): ?>
                                <form method="post" action="<?= e(url('/admin/users/toggle_status.php')) ?>"
                                      class="d-inline"
                                      data-rk-confirm="<?= e($u['name'] . ((int)$u['status'] === 1
                                            ? ' devre dışı bırakılacak.' : ' aktifleştirilecek.')
                                            . ' Onaylıyor musunuz?') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit"
                                            class="rk-icon-btn <?= (int)$u['status'] === 1 ? 'is-danger' : 'is-good' ?>"
                                            title="<?= (int)$u['status'] === 1 ? 'Devre dışı bırak' : 'Aktifleştir' ?>">
                                        <i class="bi <?= (int)$u['status'] === 1 ? 'bi-person-slash' : 'bi-person-check' ?>"></i>
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
        <div class="rk-pagination-info"><?= $page['from'] ?>-<?= $page['to'] ?> / <?= $page['total'] ?> kayıt</div>
        <div class="rk-pagination-links">
            <?php if ($page['has_prev']): ?>
                <a class="rk-btn rk-btn-sm" href="<?= e(query_url(['page' => $page['current'] - 1])) ?>">Önceki</a>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $page['pages']; $p++): ?>
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
