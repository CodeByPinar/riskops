<?php
declare(strict_types=1);

/**
 * RiskOps - Audit Logs
 * /var/www/riskops/admin/audit_logs/index.php
 *
 * SALT OKUNUR. Audit kaydı arayüzden düzenlenemez veya silinemez;
 * denetim izinin değiştirilebilir olması onu değersiz kılar.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_filters.php';

require_login();
require_role(ROLE_ADMIN);

$users = users_list(false);

/* Mevcut action ve entity değerleri veritabanından gelir: yeni bir işlem
   türü eklendiğinde filtre listesi kendiliğinden güncellenir. */
/* Filtreler ORTAK dosyadan: CSV disa aktarma da ayni mantigi
   kullaniyor. Iki yere kopyalansaydi ekranda gorunen ile indirilen
   dosya ayrisabilirdi - denetim ciktisinda bu kabul edilemez. */
$flt = audit_filters();

$f             = $flt['f'];
$actionOptions = $flt['counts'];
$entityOptions = $flt['entities'];
$activeFilters = $flt['active'];
$params        = $flt['params'];
$whereSql      = $flt['where'];

$total = (int)db_value("SELECT COUNT(*) FROM audit_logs l WHERE {$whereSql}", $params);

$page = paginate($total, per_page(), input_int('page', 1) ?? 1);

$rows = db_all(
    "SELECT l.*, u.name AS current_name
     FROM audit_logs l
     LEFT JOIN users u ON u.id = l.user_id
     WHERE {$whereSql}
     ORDER BY l.id DESC
     LIMIT {$page['per_page']} OFFSET {$page['offset']}",
    $params
);

$oldest = db_value('SELECT MIN(created_at) FROM audit_logs');
$retention = (int)setting('audit_retention_days', 730);

/** İşlem adına göre rozet sınıfı: yıkıcı işlemler göze çarpsın. */
$actionClass = static function (string $action): string {
    if (str_contains($action, 'deleted') || str_contains($action, 'rate_limited')
        || str_contains($action, 'failed') || str_contains($action, 'disabled')) {
        return 'sev-critical';
    }
    if (str_contains($action, 'created')) {
        return 'sev-low';
    }
    if (str_contains($action, 'password') || str_contains($action, 'status_changed')) {
        return 'sev-medium';
    }
    return 'sev-none';
};

/** Varlık türünden detay bağlantısı üretir (kayıt hâlâ duruyorsa). */
$entityLink = static function (?string $type, ?int $id): ?string {
    if ($type === null || $id === null || $id < 1) {
        return null;
    }
    return match ($type) {
        'risk'          => url('/risks/view.php?id=' . $id),
        'user'          => url('/admin/users/edit.php?id=' . $id),
        'risk_action'   => null,
        'department'    => url('/admin/departments/?edit=' . $id),
        'risk_category' => url('/admin/categories/?edit=' . $id),
        default         => null,
    };
};

$pageTitle    = t('Denetim Kaydı');
$pageSubtitle = number_format((float)$total, 0, ',', '.') . ' kayıt'
              . ($activeFilters > 0 ? ' — ' . $activeFilters . ' filtre aktif' : '')
              . ($oldest ? ' · en eski: ' . format_date($oldest) : '');
$activeMenu   = 'admin.audit';

require LAYOUT_PATH . '/header.php';
?>

<?php
/* Disa aktarma AYNI filtrelerle calisir: ekranda ne goruyorsan onu
   indirirsin. Mevcut sorgu dizesi oldugu gibi aktariliyor. */
$qs = $_GET;
unset($qs['page'], $qs['format']);
$qsStr = $qs !== [] ? '&' . http_build_query($qs) : '';
?>
<div class="rk-page-actions">
    <a class="rk-btn" href="<?= e(url('/admin/audit_logs/export_csv.php?format=excel' . $qsStr)) ?>"
       title="<?= te('Türkçe Excel\'de çift tıkla açılır') ?>">
        <i class="bi bi-file-earmark-excel"></i> <?= te('Excel') ?>
    </a>
    <a class="rk-btn" href="<?= e(url('/admin/audit_logs/export_csv.php?format=raw' . $qsStr)) ?>"
       title="<?= te('RFC 4180 — sistem entegrasyonu için') ?>">
        <i class="bi bi-filetype-csv"></i> <?= te('Ham CSV') ?>
    </a>
    <span class="rk-help rk-u-m0">
        Dışa aktarma, ekrandaki filtrelerin aynısını kullanır ve
        <strong>audit log'a yazılır</strong>.
    </span>
</div>

<form method="get" class="rk-card rk-filters">
    <div class="rk-card-body">
        <div class="rk-filter-grid">
            <div class="rk-field rk-filter-wide">
                <label class="rk-label" for="q"><?= te('Arama') ?></label>
                <input class="rk-input" type="search" id="q" name="q" value="<?= e($f['q'] ?? '') ?>"
                       placeholder="<?= te('Kullanıcı adı, IP veya değişen değer') ?>">
            </div>
            <div class="rk-field">
                <label class="rk-label" for="action"><?= te('İşlem') ?></label>
                <select class="rk-select" id="action" name="action">
                    <option value=""><?= te('Tümü') ?></option>
                    <?php foreach ($actionOptions as $a): ?>
                        <option value="<?= e($a['action']) ?>" <?= $f['action'] === $a['action'] ? 'selected' : '' ?>>
                            <?= e($a['action']) ?> (<?= (int)$a['adet'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rk-field">
                <label class="rk-label" for="entity"><?= te('Varlık') ?></label>
                <select class="rk-select" id="entity" name="entity">
                    <option value=""><?= te('Tümü') ?></option>
                    <?php foreach ($entityOptions as $en): ?>
                        <option value="<?= e($en) ?>" <?= $f['entity'] === $en ? 'selected' : '' ?>>
                            <?= e($en) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rk-field">
                <label class="rk-label" for="user"><?= te('Kullanıcı') ?></label>
                <select class="rk-select" id="user" name="user">
                    <option value=""><?= te('Tümü') ?></option>
                    <?= options_html($users, $f['user']) ?>
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
                <i class="bi bi-lock"></i> <?= te('Salt okunur — audit kayıtları arayüzden değiştirilemez.') ?>
            </span>
            <div class="ms-auto d-flex gap-2">
                <?php if ($activeFilters > 0): ?>
                    <a class="rk-btn" href="<?= e(url('/admin/audit_logs/')) ?>"><i class="bi bi-x-lg"></i> <?= te('Temizle') ?></a>
                <?php endif; ?>
                <button type="submit" class="rk-btn rk-btn-primary"><i class="bi bi-funnel"></i> <?= te('Filtrele') ?></button>
            </div>
        </div>
    </div>
</form>

<div class="rk-card">
    <div class="rk-card-body is-flush">
        <?php if ($rows === []): ?>
            <?= empty_state('Filtrelere uyan kayıt bulunamadı', '', 'bi-journal-text') ?>
        <?php else: ?>
        <div class="rk-table-wrap">
            <table class="rk-table">
                <thead>
                    <tr><th><?= te('Zaman') ?></th><th><?= te('Kullanıcı') ?></th><th><?= te('İşlem') ?></th><th><?= te('Varlık') ?></th>
                        <th><?= te('Değişiklik') ?></th><th><?= te('IP') ?></th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $log):
                    $old  = json_decode((string)$log['old_values'], true) ?: [];
                    $new  = json_decode((string)$log['new_values'], true) ?: [];
                    $keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));
                    $link = $entityLink($log['entity_type'], $log['entity_id'] !== null ? (int)$log['entity_id'] : null);
                ?>
                    <tr>
                        <td class="rk-u-nowrap"><?= e(format_datetime($log['created_at'])) ?></td>
                        <td>
                            <?= e((string)($log['user_name_snapshot'] ?? 'Sistem')) ?>
                            <?php if ($log['user_id'] === null && $log['user_name_snapshot'] !== null): ?>
                                <div class="rk-cell-sub" title="<?= te('Kullanıcı sonradan silindi') ?>">hesap yok</div>
                            <?php endif; ?>
                        </td>
                        <td><span class="rk-badge <?= e($actionClass($log['action'])) ?>">
                            <?= e($log['action']) ?></span></td>
                        <td>
                            <?php if ($log['entity_type'] !== null): ?>
                                <span class="rk-cell-sub"><?= e($log['entity_type']) ?></span>
                                <?php if ($link !== null): ?>
                                    <a class="rk-code" href="<?= e($link) ?>">#<?= (int)$log['entity_id'] ?></a>
                                <?php elseif ($log['entity_id'] !== null): ?>
                                    <span class="rk-code">#<?= (int)$log['entity_id'] ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($keys === []): ?>
                                <span class="text-muted">—</span>
                            <?php else: ?>
                                <ul class="rk-diff">
                                <?php foreach (array_slice($keys, 0, 4) as $k): ?>
                                    <li>
                                        <code><?= e($k) ?></code>:
                                        <?php if (array_key_exists($k, $old)): ?>
                                            <span class="rk-diff-old"><?= e(audit_value_short($old[$k])) ?></span>
                                            <i class="bi bi-arrow-right"></i>
                                        <?php endif; ?>
                                        <span class="rk-diff-new"><?= e(array_key_exists($k, $new) ? audit_value_short($new[$k]) : '—') ?></span>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (count($keys) > 4): ?>
                                    <li class="text-muted">+<?= count($keys) - 4 ?> alan daha</li>
                                <?php endif; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                        <td class="rk-code"><?= e((string)($log['ip_address'] ?? '—')) ?></td>
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

<div class="rk-alert rk-alert-info">
    <i class="bi bi-info-circle-fill"></i>
    <div>
        Saklama süresi ayarlarda <strong><?= $retention === 0 ? 'sınırsız' : $retention . ' gün' ?></strong>
        olarak tanımlı. Kullanıcı silindiğinde kaydı kaybolmaz: <code>user_id</code> boşalır
        ama <code>user_name_snapshot</code> alanında adı korunur.
    </div>
</div>

<?php require LAYOUT_PATH . '/footer.php'; ?>
