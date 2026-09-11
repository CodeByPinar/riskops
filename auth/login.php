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

$assetVersion = '20260911p';
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

<main class="rk-login-shell">

    <!-- =============================================================
         SOL PANEL - marka tarafi
         Dekoratif gorsel olarak urunun KENDI 5x5 matrisi kullaniliyor:
         jenerik bir soyut sekil "bu ne ise yarar" sorusunu cevaplamaz,
         matris cevaplar. Hucre renkleri uygulamanin dogrulanmis seviye
         paletinden gelir, uydurma degil.
         ============================================================= -->
    <section class="rk-login-brand" aria-hidden="true">

        <div class="rk-login-brand-top">
            <?= brand_wordmark('dark') ?>
        </div>

        <div class="rk-login-brand-mid">
            <h2 class="rk-login-pitch">
                BT ve siber güvenlik risklerini<br>tek yerden yönetin.
            </h2>
            <ul class="rk-login-points">
                <li><i class="bi bi-grid-3x3"></i> 5&times;5 olasılık / etki değerlendirmesi</li>
                <li><i class="bi bi-check2-square"></i> Azaltıcı aksiyon ve termin takibi</li>
                <li><i class="bi bi-file-earmark-bar-graph"></i> Yönetime sunulabilir raporlama</li>
            </ul>
        </div>

        <?php
        /* Dekoratif matris. Hucre seviyesi gercek kuralla belirlenir
           (skor = olasilik x etki), boylece desen rastgele degil,
           urunun mantigiyla ayni. */
        $decoThresholds = ['low' => 4, 'medium' => 9, 'high' => 16];
        ?>
        <div class="rk-login-matrix">
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <?php for ($l = 1; $l <= 5; $l++):
                    $score = $l * $i;
                    $sev = $score <= $decoThresholds['low'] ? 'low'
                         : ($score <= $decoThresholds['medium'] ? 'medium'
                         : ($score <= $decoThresholds['high'] ? 'high' : 'critical')); ?>
                    <span class="rk-login-mx sev-<?= $sev ?>"></span>
                <?php endfor; ?>
            <?php endfor; ?>
        </div>

    </section>

    <!-- =============================================================
         SAG PANEL - form
         ============================================================= -->
    <section class="rk-login-panel">
        <div class="rk-login-form">

            <div class="rk-login-mobile-brand">
                <?= brand_wordmark('light') ?>
            </div>

            <h1 class="rk-login-title">Giriş yap</h1>
            <p class="rk-login-sub">Hesabınızla devam edin</p>

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
                    <div class="rk-input-icon">
                        <i class="bi bi-envelope"></i>
                        <input class="rk-input" type="email" id="email" name="email"
                               value="<?= e(APP_DEMO && old('email') === '' ? DEMO_EMAIL : old('email')) ?>"
                               autocomplete="username" required autofocus
                               placeholder="ornek@kurum.local">
                    </div>
                </div>

                <div class="rk-field">
                    <label class="rk-label" for="password">Parola</label>
                    <div class="rk-input-icon">
                        <i class="bi bi-lock"></i>
                        <input class="rk-input" type="password" id="password" name="password"
                               autocomplete="current-password" required
                               placeholder="Parolanız">
                    </div>
                </div>

                <button type="submit" class="rk-btn rk-btn-primary">
                    <i class="bi bi-box-arrow-in-right"></i> Giriş yap
                </button>
            </form>

            <p class="rk-login-foot">
                <i class="bi bi-shield-check"></i>
                Bu sistemdeki tüm işlemler kayıt altına alınmaktadır.
            </p>

        </div>
    </section>

</main>

<script src="<?= e(url('/assets/js/app.js?v=' . $assetVersion)) ?>"></script>
</body>
</html>
