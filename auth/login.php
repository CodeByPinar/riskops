<?php
declare(strict_types=1);

/**
 * RiskOps - Giriş ekrani
 * /var/www/riskops/auth/login.php
 *
 * Bu sayfa ana layout'u KULLANMAZ (sidebar/navbar yok), kendi sablonu vardir.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Zaten giriş yapmissa dogrudan panele gonder
if (auth_check()) {
    redirect('/dashboard/');
}

$assetVersion = '20260911n';
$flashes = flash_take();

$flashIcons = [
    'success' => 'bi-check-circle-fill',
    'error'   => 'bi-x-circle-fill',
    'warning' => 'bi-exclamation-triangle-fill',
    'info'    => 'bi-info-circle-fill',
];
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Giriş - <?= e(app_name()) ?></title>
<?= favicon_tags() ?>
<link rel="stylesheet" href="<?= e(url('/assets/vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(url('/assets/vendor/bootstrap-icons/bootstrap-icons.css')) ?>">
<link rel="stylesheet" href="<?= e(url('/assets/css/app.css?v=' . $assetVersion)) ?>">
</head>
<body class="rk-login-page">

<div class="rk-login-card">

    <div class="rk-login-head">
        <h1 class="rk-login-logo"><?= brand_wordmark('light') ?></h1>
        <p class="rk-login-sub">IT &amp; Cyber Risk Management Platform</p>
    </div>

    <div class="rk-login-body">

        <?php foreach ($flashes as $msg):
            $type = isset($msg['type'], $flashIcons[$msg['type']]) ? $msg['type'] : 'info'; ?>
            <div class="rk-alert rk-alert-<?= e($type) ?>" role="alert">
                <i class="bi <?= e($flashIcons[$type]) ?>"></i>
                <div><?= e((string)($msg['message'] ?? '')) ?></div>
            </div>
        <?php endforeach; ?>

        <?php if (APP_DEMO): ?>
        <div class="rk-demo-box">
            <div class="rk-demo-title">
                <i class="bi bi-play-circle-fill"></i> Deneme kurulumu
            </div>
            <dl class="rk-demo-creds">
                <dt>E-posta</dt><dd><code><?= e(DEMO_EMAIL) ?></code></dd>
                <dt>Parola</dt><dd><code><?= e(DEMO_PASSWORD) ?></code></dd>
            </dl>
            <p class="rk-demo-note">
                Hesap <strong>salt okunur</strong>dur. Veriler örnektir ve
                her gece 04:00'te sıfırlanır.
            </p>
        </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/auth/authenticate.php')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="rk-field">
                <label class="rk-label" for="email">E-posta</label>
                <input class="rk-input" type="email" id="email" name="email"
                       value="<?= e(APP_DEMO && old('email') === '' ? DEMO_EMAIL : old('email')) ?>"
                       autocomplete="username" required autofocus
                       placeholder="örnek@kurum.local">
            </div>

            <div class="rk-field">
                <label class="rk-label" for="password">Parola</label>
                <input class="rk-input" type="password" id="password" name="password"
                       autocomplete="current-password" required
                       placeholder="Parolanız">
            </div>

            <button type="submit" class="rk-btn rk-btn-primary">
                <i class="bi bi-box-arrow-in-right"></i> Giriş yap
            </button>
        </form>

    </div>

    <div class="rk-login-foot">
        Bu sistemdeki tüm işlemler kayıt altına alınmaktadır.
    </div>

</div>

<script src="<?= e(url('/assets/js/app.js?v=' . $assetVersion)) ?>"></script>
</body>
</html>
