<?php
declare(strict_types=1);

/**
 * RiskOps - Parola değiştirme
 * /var/www/riskops/auth/change_password.php
 *
 * GET  : formu gösterir
 * POST : parolayı değiştirir (CSRF zorunlu)
 *
 * must_change_password = 1 olan kullanıcılar bootstrap tarafından buraya
 * yönlendirilir ve değiştirmeden başka sayfaya geçemez.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

/**
 * DEMO KİPİ - paylaşılan hesabın parolası değiştirilemez.
 *
 * Deneme hesabının bilgileri giriş ekranında herkese açıktır. Bir
 * ziyaretçi parolayı değiştirirse, tanıtım bağlantısına tıklayan
 * HERKES gece sıfırlamasına kadar içeri giremez. Rol kontrolü bunu
 * yakalamaz: parola değiştirmek bir yetki değil, kendi hesabı üzerinde
 * yapılan bir işlemdir - viewer da yapabilir.
 *
 * Kısıt YALNIZCA demo hesabına ve YALNIZCA demo kipinde uygulanır;
 * gerçek kullanıcılar her koşulda parolasını değiştirebilir.
 */
if (APP_DEMO && strcasecmp((string)(auth_user()['email'] ?? ''), DEMO_EMAIL) === 0) {
    flash('info', 'Deneme hesabının parolası değiştirilemez.');
    redirect('/dashboard/');
}

$minLength = max(8, (int)setting('password_min_length', 10));
$forced    = !empty($_SESSION['must_change_password']);

if (is_post()) {
    csrf_require();

    $current = isset($_POST['current_password']) && is_string($_POST['current_password'])
        ? $_POST['current_password'] : '';
    $new     = isset($_POST['new_password']) && is_string($_POST['new_password'])
        ? $_POST['new_password'] : '';
    $confirm = isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])
        ? $_POST['confirm_password'] : '';

    $errors = [];

    $hash = (string)db_value('SELECT password FROM users WHERE id = :id LIMIT 1', [':id' => auth_id()]);

    if ($current === '' || !password_verify($current, $hash)) {
        $errors['current_password'] = 'Mevcut parolanız hatalı.';
    }

    if (mb_strlen($new) < $minLength) {
        $errors['new_password'] = 'Yeni parola en az ' . $minLength . ' karakter olmalıdır.';
    } elseif (preg_match('/^[a-zA-Z]+$/', $new) === 1 || preg_match('/^[0-9]+$/', $new) === 1) {
        $errors['new_password'] = 'Parola yalnızca harf veya yalnızca rakamdan oluşamaz.';
    } elseif ($new === $current) {
        $errors['new_password'] = 'Yeni parola mevcut parolanızla aynı olamaz.';
    }

    if ($new !== $confirm) {
        $errors['confirm_password'] = 'Parola tekrarı eşleşmiyor.';
    }

    if ($errors !== []) {
        errors_set($errors);
        flash('error', 'Parola değiştirilemedi. İşaretli alanları düzeltin.');
        redirect('/auth/change_password.php');
    }

    db_run(
        'UPDATE users SET password = :p, must_change_password = 0, password_changed_at = NOW()
         WHERE id = :id',
        [':p' => password_hash($new, PASSWORD_DEFAULT), ':id' => auth_id()]
    );

    unset($_SESSION['must_change_password']);

    // Kendi parolasini degistiren kullanicinin oturumu KAPANMAMALI.
    // auth_revalidate() password_changed_at > login_at karsilastirmasi
    // yaptigi icin giris damgasini ileri almak gerekiyor.
    $_SESSION['login_at'] = time();

    // Parola değişti: oturum kimliğini yenile (oturum sabitleme koruması)
    session_regenerate_id(true);
    csrf_rotate();

    audit('user_password_changed', 'user', auth_id(), null, ['self' => true]);

    flash('success', 'Parolanız güncellendi.');
    redirect('/dashboard/');
}

/* --------------------------------------------------------------- GET */

$err = static function (string $key): string {
    $m = field_error($key);
    return $m !== '' ? '<div class="rk-error"><i class="bi bi-exclamation-circle"></i> ' . e($m) . '</div>' : '';
};
$cls = static function (string $key): string {
    return 'rk-input' . (field_error($key) !== '' ? ' is-invalid' : '');
};

$pageTitle    = 'Parola Değiştir';
$pageSubtitle = $forced
    ? 'Devam etmeden önce parolanızı değiştirmelisiniz'
    : 'Hesabınızın parolasını güncelleyin';
$activeMenu   = '';

require LAYOUT_PATH . '/header.php';
?>

<?php if ($forced): ?>
<div class="rk-alert rk-alert-warning">
    <i class="bi bi-shield-exclamation"></i>
    <div>
        Hesabınıza <strong>geçici bir parola</strong> ile giriş yaptınız.
        Sistemi kullanabilmek için yeni bir parola belirlemeniz gerekiyor.
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-lg-6 col-xl-5">
        <div class="rk-card">
            <div class="rk-card-head">
                <h2 class="rk-card-title"><i class="bi bi-key"></i> Yeni Parola</h2>
            </div>
            <div class="rk-card-body">
                <form method="post" action="<?= e(url('/auth/change_password.php')) ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="rk-field">
                        <label class="rk-label" for="current_password">Mevcut Parola <span class="req">*</span></label>
                        <input class="<?= e($cls('current_password')) ?>" type="password"
                               id="current_password" name="current_password"
                               autocomplete="current-password" required>
                        <?= $err('current_password') ?>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="new_password">Yeni Parola <span class="req">*</span></label>
                        <input class="<?= e($cls('new_password')) ?>" type="password"
                               id="new_password" name="new_password"
                               autocomplete="new-password" required minlength="<?= $minLength ?>">
                        <?= $err('new_password') ?>
                        <div class="rk-help">
                            En az <?= $minLength ?> karakter. Yalnızca harf veya yalnızca rakam olamaz.
                        </div>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="confirm_password">Yeni Parola (tekrar) <span class="req">*</span></label>
                        <input class="<?= e($cls('confirm_password')) ?>" type="password"
                               id="confirm_password" name="confirm_password"
                               autocomplete="new-password" required>
                        <?= $err('confirm_password') ?>
                    </div>

                    <button type="submit" class="rk-btn rk-btn-primary">
                        <i class="bi bi-check-lg"></i> Parolayı Değiştir
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require LAYOUT_PATH . '/footer.php'; ?>
