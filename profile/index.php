<?php
declare(strict_types=1);

/**
 * RiskOps - Kullanıcı profili
 * /var/www/riskops/profile/index.php
 *
 * Kullanıcının KENDİ hesabı. Yetki gerektirmez; giriş yapmış olmak yeter.
 *
 * DÜZENLENEBİLİR : ad, unvan, telefon
 * SALT OKUNUR    : e-posta, rol, departman, durum
 *
 * Neden bu ayrım: rol ve departman yetki belirler, durum hesabı açıp
 * kapatır. Kullanıcının kendi yetkisini yükseltebilmesi ya da
 * departmanını değiştirerek başka kayıtlara erişebilmesi olmamalıdır -
 * bunlar admin'in işidir (admin/users/). E-posta ise giriş kimliğidir;
 * kendi kendine değiştirilmesi hesap devralma yüzeyi açar.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$stmt = db()->prepare(
    'SELECT u.id, u.name, u.email, u.role, u.title, u.phone, u.status,
            u.last_login_at, u.password_changed_at, u.created_at,
            d.name AS department_name
       FROM users u
       LEFT JOIN departments d ON d.id = u.department_id
      WHERE u.id = :id
      LIMIT 1'
);
$stmt->execute([':id' => auth_id()]);
$me = $stmt->fetch();

if ($me === false) {
    app_abort(404, 'User not found');
}

/* Demo hesabi paylasilan bir hesaptir: bir ziyaretcinin adini
   degistirmesi sonraki herkesi etkiler. change_password.php ile
   ayni gerekce. */
$isDemoAccount = APP_DEMO && strcasecmp((string)$me['email'], DEMO_EMAIL) === 0;

$err = static function (string $key): string {
    $m = field_error($key);
    return $m !== '' ? '<div class="rk-error"><i class="bi bi-exclamation-circle"></i> ' . e($m) . '</div>' : '';
};
$cls = static function (string $key): string {
    return 'rk-input' . (field_error($key) !== '' ? ' is-invalid' : '');
};
$val = static function (string $key, $fallback): string {
    return has_old($key) ? old($key) : (string)($fallback ?? '');
};

$pageTitle    = t('Profilim');
$pageSubtitle = t('Hesap bilgilerinizi görüntüleyin ve güncelleyin');
$activeMenu   = 'profile';

require LAYOUT_PATH . '/header.php';
?>

<?php if ($isDemoAccount): ?>
<div class="rk-alert rk-alert-info">
    <i class="bi bi-info-circle-fill"></i>
    <div>
        Bu <strong>paylaşılan bir deneme hesabıdır</strong>. Bilgileri
        değiştirilemez; yapılan bir değişiklik sonraki tüm ziyaretçileri
        etkilerdi.
    </div>
</div>
<?php endif; ?>

<div class="row g-3">

    <!-- ------------------------------------------------ düzenlenebilir -->
    <div class="col-12 col-lg-7">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-person-gear"></i> <?= te('Bilgilerim') ?></h2>
            </div>
            <div class="rk-card-body">
                <form method="post" action="<?= e(url('/profile/update.php')) ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="rk-field">
                        <label class="rk-label" for="name"><?= te('Ad Soyad') ?> <span class="req">*</span></label>
                        <input class="<?= e($cls('name')) ?>" type="text" id="name" name="name"
                               value="<?= e($val('name', $me['name'])) ?>"
                               maxlength="100" required
                               <?= $isDemoAccount ? 'disabled' : '' ?>>
                        <?= $err('name') ?>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="title"><?= te('Unvan') ?></label>
                        <input class="<?= e($cls('title')) ?>" type="text" id="title" name="title"
                               value="<?= e($val('title', $me['title'])) ?>"
                               maxlength="100" placeholder="Bilgi Güvenliği Uzmanı"
                               <?= $isDemoAccount ? 'disabled' : '' ?>>
                        <?= $err('title') ?>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="phone"><?= te('Telefon') ?></label>
                        <input class="<?= e($cls('phone')) ?>" type="text" id="phone" name="phone"
                               value="<?= e($val('phone', $me['phone'])) ?>"
                               maxlength="30" placeholder="+90 5xx xxx xx xx"
                               <?= $isDemoAccount ? 'disabled' : '' ?>>
                        <?= $err('phone') ?>
                    </div>

                    <?php if (!$isDemoAccount): ?>
                    <button type="submit" class="rk-btn rk-btn-primary">
                        <i class="bi bi-check-lg"></i> <?= te('Kaydet') ?>
                    </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- --------------------------------------------------- salt okunur -->
    <div class="col-12 col-lg-5">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-shield-lock"></i> <?= te('Hesap') ?></h2>
            </div>
            <div class="rk-card-body">
                <dl class="rk-dl">
                    <dt><?= te('E-posta') ?></dt>
                    <dd><?= e($me['email']) ?></dd>

                    <dt><?= te('Rol') ?></dt>
                    <dd><span class="rk-badge"><?= te(role_label($me['role'])) ?></span></dd>

                    <dt><?= te('Departman') ?></dt>
                    <dd><?= e($me['department_name'] ?? '-') ?></dd>

                    <dt><?= te('Durum') ?></dt>
                    <dd>
                        <?php if ((int)$me['status'] === 1): ?>
                            <span class="rk-badge st-open"><i class="bi bi-check-circle-fill"></i> <?= te('Aktif') ?></span>
                        <?php else: ?>
                            <span class="rk-badge sev-none"><?= te('Pasif') ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt><?= te('Son giriş') ?></dt>
                    <dd><?= e(format_datetime($me['last_login_at'])) ?></dd>

                    <dt><?= te('Parola değişimi') ?></dt>
                    <dd><?= e(format_datetime($me['password_changed_at'])) ?></dd>

                    <dt><?= te('Kayıt tarihi') ?></dt>
                    <dd><?= e(format_datetime($me['created_at'])) ?></dd>
                </dl>

                <div class="rk-help">
                    E-posta, rol, departman ve durum yalnızca sistem yöneticisi
                    tarafından değiştirilebilir. Rol ve departman yetki belirler;
                    e-posta ise giriş kimliğinizdir.
                </div>

                <?php if (!$isDemoAccount): ?>
                <a class="rk-btn" href="<?= e(url('/auth/change_password.php')) ?>">
                    <i class="bi bi-key"></i> <?= te('Parolamı değiştir') ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php require LAYOUT_PATH . '/footer.php'; ?>
