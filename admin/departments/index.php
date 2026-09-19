<?php
declare(strict_types=1);

/**
 * RiskOps - Departmanlar
 * /var/www/riskops/admin/departments/index.php
 *
 * Liste ve form tek sayfada: departman sayısı azdır, ayrı bir create/edit
 * ekranı gereksiz gezinme yaratır. ?edit=N ile form düzenleme moduna geçer.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
require_role(ROLE_ADMIN);

$editId = input_int('edit');
$editing = null;

if ($editId !== null) {
    $editing = db_row('SELECT * FROM departments WHERE id = :id LIMIT 1', [':id' => $editId]) ?: null;
    if ($editing === null) {
        flash('error', 'Departman bulunamadı.');
        redirect('/admin/departments/');
    }
}

$rows = db_all(
    'SELECT d.*,
            m.name AS manager_name,
            (SELECT COUNT(*) FROM risks r WHERE r.department_id = d.id AND r.deleted_at IS NULL) AS risk_sayisi,
            (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id) AS kullanici_sayisi
     FROM departments d
     LEFT JOIN users m ON m.id = d.manager_id
     ORDER BY d.sort_order, d.name'
);

$val = static function (string $key, $default = '') use ($editing): string {
    if (has_old($key)) {
        return old($key);
    }
    return (string)($editing[$key] ?? $default);
};
$cls = static function (string $key, string $base): string {
    return $base . (field_error($key) !== '' ? ' is-invalid' : '');
};
$err = static function (string $key): string {
    $m = field_error($key);
    return $m !== '' ? '<div class="rk-error"><i class="bi bi-exclamation-circle"></i> ' . e($m) . '</div>' : '';
};

$pageTitle    = 'Departmanlar';
$pageSubtitle = count($rows) . ' departman';
$activeMenu   = 'admin.departments';

require LAYOUT_PATH . '/header.php';
?>

<div class="row g-3">

    <div class="col-12 col-xl-4">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title">
                    <i class="bi <?= $editing ? 'bi-pencil' : 'bi-plus-square' ?>"></i>
                    <?= $editing ? 'Departmanı Düzenle' : 'Yeni Departman' ?>
                </h2>
            </div>
            <div class="rk-card-body">
                <form method="post" action="<?= e(url('/admin/departments/save.php')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?php if ($editing): ?>
                        <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
                    <?php endif; ?>

                    <div class="rk-field">
                        <label class="rk-label" for="name">Ad <span class="req">*</span></label>
                        <input class="<?= e($cls('name', 'rk-input')) ?>" type="text" id="name" name="name"
                               maxlength="100" required value="<?= e($val('name')) ?>"
                               placeholder="Örn: Bilgi Teknolojileri">
                        <?= $err('name') ?>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="code">Kod <span class="req">*</span></label>
                        <input class="<?= e($cls('code', 'rk-input')) ?>" type="text" id="code" name="code"
                               maxlength="20" required value="<?= e($val('code')) ?>"
                               placeholder="Örn: IT" class="rk-u-upper">
                        <?= $err('code') ?>
                        <div class="rk-help">Kısa, benzersiz tanımlayıcı. Büyük harfe çevrilir.</div>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="description">Açıklama</label>
                        <textarea class="rk-textarea rk-u-minh60" id="description" name="description" rows="2"><?= e($val('description')) ?></textarea>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="manager_id">Yönetici</label>
                        <select class="<?= e($cls('manager_id', 'rk-select')) ?>" id="manager_id" name="manager_id">
                            <option value="">Belirtilmedi</option>
                            <?= options_html(users_list(), $val('manager_id') === '' ? null : (int)$val('manager_id')) ?>
                        </select>
                        <?= $err('manager_id') ?>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <div class="rk-field">
                                <label class="rk-label" for="sort_order">Sıra</label>
                                <input class="rk-input" type="number" id="sort_order" name="sort_order"
                                       value="<?= e($val('sort_order', '0')) ?>" min="0" max="9999">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="rk-field">
                                <label class="rk-label">Durum</label>
                                <label class="rk-check rk-u-h34">
                                    <input type="checkbox" name="is_active" value="1"
                                           <?= $val('is_active', '1') === '0' ? '' : 'checked' ?>>
                                    <span>Aktif</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="rk-form-actions rk-u-mb0">
                        <button type="submit" class="rk-btn rk-btn-primary">
                            <i class="bi bi-check-lg"></i> <?= $editing ? 'Kaydet' : 'Ekle' ?>
                        </button>
                        <?php if ($editing): ?>
                            <a class="rk-btn" href="<?= e(url('/admin/departments/')) ?>">Vazgeç</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="rk-card">
            <div class="rk-card-body is-flush">
                <?php if ($rows === []): ?>
                    <?= empty_state('Departman yok', 'Soldaki formdan ekleyin.', 'bi-diagram-3') ?>
                <?php else: ?>
                <div class="rk-table-wrap">
                    <table class="rk-table">
                        <thead>
                            <tr><th>Kod</th><th>Ad</th><th>Yönetici</th><th>Kullanım</th>
                                <th>Sıra</th><th>Durum</th><th class="rk-u-shrink"></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $d):
                            $inUse = (int)$d['risk_sayisi'] > 0 || (int)$d['kullanici_sayisi'] > 0;
                        ?>
                            <tr<?= (int)$d['is_active'] === 0 ? ' class="is-muted-row"' : '' ?>>
                                <td class="rk-code"><?= e($d['code']) ?></td>
                                <td>
                                    <span class="rk-link-strong"><?= e($d['name']) ?></span>
                                    <?php if (($d['description'] ?? '') !== ''): ?>
                                        <div class="rk-cell-sub"><?= e(str_limit($d['description'], 54)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string)($d['manager_name'] ?? '—')) ?></td>
                                <td>
                                    <span class="rk-cell-sub">
                                        <?= (int)$d['risk_sayisi'] ?> risk · <?= (int)$d['kullanici_sayisi'] ?> kullanıcı
                                    </span>
                                </td>
                                <td><?= (int)$d['sort_order'] ?></td>
                                <td>
                                    <?php if ((int)$d['is_active'] === 1): ?>
                                        <span class="rk-badge sev-low">Aktif</span>
                                    <?php else: ?>
                                        <span class="rk-badge st-closed">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="rk-row-actions">
                                        <a class="rk-icon-btn" title="Düzenle"
                                           href="<?= e(url('/admin/departments/?edit=' . (int)$d['id'])) ?>">
                                            <i class="bi bi-pencil"></i></a>

                                        <form method="post" action="<?= e(url('/admin/departments/toggle.php')) ?>"
                                              class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                            <button type="submit" class="rk-icon-btn"
                                                    title="<?= (int)$d['is_active'] === 1 ? 'Pasifleştir' : 'Aktifleştir' ?>">
                                                <i class="bi <?= (int)$d['is_active'] === 1 ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                            </button>
                                        </form>

                                        <?php if (!$inUse): ?>
                                        <form method="post" action="<?= e(url('/admin/departments/delete.php')) ?>"
                                              class="d-inline"
                                              data-rk-confirm="<?= e($d['name'] . ' silinecek. Onaylıyor musunuz?') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                            <button type="submit" class="rk-icon-btn is-danger" title="Sil">
                                                <i class="bi bi-trash"></i></button>
                                        </form>
                                        <?php else: ?>
                                            <span class="rk-icon-btn is-disabled"
                                                  title="Kullanımda olduğu için silinemez — pasifleştirebilirsiniz">
                                                <i class="bi bi-lock"></i></span>
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
        </div>

        <div class="rk-alert rk-alert-info">
            <i class="bi bi-info-circle-fill"></i>
            <div>
                Kullanımda olan bir departman <strong>silinemez</strong> (risk ve kullanıcı
                kayıtları ona bağlıdır). Kullanımdan kaldırmak için <strong>pasifleştirin</strong>:
                yeni kayıtlarda seçilemez, mevcut kayıtlar bozulmaz.
            </div>
        </div>
    </div>

</div>

<?php require LAYOUT_PATH . '/footer.php'; ?>
