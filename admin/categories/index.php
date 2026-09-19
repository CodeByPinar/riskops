<?php
declare(strict_types=1);

/**
 * RiskOps - Risk Kategorileri
 * /var/www/riskops/admin/categories/index.php
 *
 * Departmanlarla aynı desen: liste ve form tek sayfada, ?edit=N ile
 * form düzenleme moduna geçer.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
require_role(ROLE_ADMIN);

$editId  = input_int('edit');
$editing = null;

if ($editId !== null) {
    $editing = db_row('SELECT * FROM risk_categories WHERE id = :id LIMIT 1', [':id' => $editId]) ?: null;
    if ($editing === null) {
        flash('error', 'Kategori bulunamadı.');
        redirect('/admin/categories/');
    }
}

$rows = db_all(
    'SELECT c.*,
            (SELECT COUNT(*) FROM risks r WHERE r.category_id = c.id AND r.deleted_at IS NULL) AS risk_sayisi,
            (SELECT COUNT(*) FROM risks r WHERE r.category_id = c.id) AS risk_tumu
     FROM risk_categories c
     ORDER BY c.sort_order, c.name'
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

$pageTitle    = 'Risk Kategorileri';
$pageSubtitle = count($rows) . ' kategori';
$activeMenu   = 'admin.categories';

require LAYOUT_PATH . '/header.php';
?>

<div class="row g-3">

    <div class="col-12 col-xl-4">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title">
                    <i class="bi <?= $editing ? 'bi-pencil' : 'bi-plus-square' ?>"></i>
                    <?= $editing ? 'Kategoriyi Düzenle' : 'Yeni Kategori' ?>
                </h2>
            </div>
            <div class="rk-card-body">
                <form method="post" action="<?= e(url('/admin/categories/save.php')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?php if ($editing): ?>
                        <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
                    <?php endif; ?>

                    <div class="rk-field">
                        <label class="rk-label" for="name">Ad <span class="req">*</span></label>
                        <input class="<?= e($cls('name', 'rk-input')) ?>" type="text" id="name" name="name"
                               maxlength="100" required value="<?= e($val('name')) ?>"
                               placeholder="Örn: Uygulama Güvenliği">
                        <?= $err('name') ?>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="code">Kod <span class="req">*</span></label>
                        <input class="<?= e($cls('code', 'rk-input')) ?>" type="text" id="code" name="code"
                               maxlength="20" required value="<?= e($val('code')) ?>"
                               placeholder="Örn: APPSEC" class="rk-u-upper">
                        <?= $err('code') ?>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="description">Açıklama</label>
                        <textarea class="rk-textarea rk-u-minh60" id="description" name="description" rows="2"><?= e($val('description')) ?></textarea>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="color">Renk</label>
                        <div class="rk-color-field">
                            <input type="color" id="color" name="color" data-rk-color-sync="color_text"
                                   value="<?= e($val('color', '#64748b')) ?>">
                            <input class="<?= e($cls('color', 'rk-input')) ?>" type="text" name="color_text"
                                   value="<?= e($val('color', '#64748b')) ?>" maxlength="7"
                                   pattern="#[0-9a-fA-F]{6}" aria-label="Renk kodu">
                        </div>
                        <?= $err('color') ?>
                        <div class="rk-help">
                            Yalnızca listelerdeki küçük nokta için kullanılır; risk seviyesi
                            renkleriyle karışmaması adına ayırt edici bir ton seçin.
                        </div>
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
                            <a class="rk-btn" href="<?= e(url('/admin/categories/')) ?>">Vazgeç</a>
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
                    <?= empty_state('Kategori yok', 'Soldaki formdan ekleyin.', 'bi-tags') ?>
                <?php else: ?>
                <div class="rk-table-wrap">
                    <table class="rk-table">
                        <thead>
                            <tr><th>Kod</th><th>Ad</th><th>Risk</th><th>Sıra</th>
                                <th>Durum</th><th class="rk-u-shrink"></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $c): ?>
                            <tr<?= (int)$c['is_active'] === 0 ? ' class="is-muted-row"' : '' ?>>
                                <td class="rk-code"><?= e($c['code']) ?></td>
                                <td>
                                    <?= category_dot($c['color']) ?>
                                    <span class="rk-link-strong"><?= e($c['name']) ?></span>
                                    <?php if (($c['description'] ?? '') !== ''): ?>
                                        <div class="rk-cell-sub"><?= e(str_limit($c['description'], 58)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$c['risk_sayisi'] > 0): ?>
                                        <a href="<?= e(url('/risks/?category=' . (int)$c['id'])) ?>">
                                            <?= (int)$c['risk_sayisi'] ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$c['sort_order'] ?></td>
                                <td>
                                    <?php if ((int)$c['is_active'] === 1): ?>
                                        <span class="rk-badge sev-low">Aktif</span>
                                    <?php else: ?>
                                        <span class="rk-badge st-closed">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="rk-row-actions">
                                        <a class="rk-icon-btn" title="Düzenle"
                                           href="<?= e(url('/admin/categories/?edit=' . (int)$c['id'])) ?>">
                                            <i class="bi bi-pencil"></i></a>

                                        <form method="post" action="<?= e(url('/admin/categories/toggle.php')) ?>"
                                              class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="rk-icon-btn"
                                                    title="<?= (int)$c['is_active'] === 1 ? 'Pasifleştir' : 'Aktifleştir' ?>">
                                                <i class="bi <?= (int)$c['is_active'] === 1 ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                            </button>
                                        </form>

                                        <?php if ((int)$c['risk_tumu'] === 0): ?>
                                        <form method="post" action="<?= e(url('/admin/categories/delete.php')) ?>"
                                              class="d-inline"
                                              data-rk-confirm="<?= e($c['name'] . ' silinecek. Onaylıyor musunuz?') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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
                Kullanımda olan bir kategori <strong>silinemez</strong>. Kullanımdan kaldırmak
                için <strong>pasifleştirin</strong>: yeni risklerde seçilemez, mevcut riskler
                kategorisiz kalmaz.
            </div>
        </div>
    </div>

</div>
<?php require LAYOUT_PATH . '/footer.php'; ?>
