<?php
declare(strict_types=1);

/**
 * RiskOps - Giriş ekranı
 * /var/www/riskops/auth/login.php
 *
 * Bu sayfa ana layout'u KULLANMAZ (sidebar/navbar yok), kendi şablonu vardır.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Zaten giriş yapmışsa doğrudan panele gönder
if (auth_check()) {
    redirect('/dashboard/');
}

$assetVersion = '20260911r';
$flashes = flash_take();

$flashIcons = [
    'success' => 'bi-check-circle-fill',
    'error'   => 'bi-x-circle-fill',
    'warning' => 'bi-exclamation-triangle-fill',
    'info'    => 'bi-info-circle-fill',
];
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
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

<!--
    Arka plan katmanlari ayri ayri elemanlar: her biri kendi hizinda
    hareket edebilsin diye. Tek bir elemanin background'unu animate
    etmek hem katmanlari birbirine baglar hem de GPU'ya devredilemez.
-->
<div class="rk-lg-bg" aria-hidden="true">
    <span class="rk-lg-orb rk-lg-orb-1"></span>
    <span class="rk-lg-orb rk-lg-orb-2"></span>
    <span class="rk-lg-grid"></span>
</div>

<div class="rk-lg">

    <!-- =========================== üst şerit =========================== -->
    <header class="rk-lg-top">
        <div class="rk-lg-top-brand">
            <?= brand_wordmark('light') ?>
            <p>IT &amp; Cyber Risk Management Platform</p>
        </div>
        <div class="rk-lg-top-note">
            <?= te('Daha güvenli bir gelecek için') ?> <span class="rk-lg-rule"></span>
        </div>
    </header>

    <!-- =========================== orta blok =========================== -->
    <main class="rk-lg-main">

        <!-- ---------------------------- sol: anlatı --------------------- -->
        <section class="rk-lg-left rk-lg-in">
            <p class="rk-lg-eyebrow"><?= te('Riskleri bugün yönetin') ?></p>
            <h1 class="rk-lg-head">
                <?= te('Daha Güvenli') ?><br>
                <?= te('Daha Dayanıklı Bir Yarın') ?>
            </h1>
            <p class="rk-lg-lede">
                <?= te('RiskOps, kurumların BT ve siber risklerini bütüncül bir yaklaşımla yönetmelerine yardımcı olur.') ?>
            </p>

            <ul class="rk-lg-chips">
                <li>
                    <span class="rk-lg-chip-ic"><i class="bi bi-shield-check"></i></span>
                    <span><?= te('Daha Güvenli Operasyonlar') ?></span>
                </li>
                <li>
                    <span class="rk-lg-chip-ic"><i class="bi bi-bar-chart"></i></span>
                    <span><?= te('Uyumluluk ve Raporlama') ?></span>
                </li>
                <li>
                    <span class="rk-lg-chip-ic"><i class="bi bi-people"></i></span>
                    <span><?= te('Daha Güçlü Kurumlar') ?></span>
                </li>
            </ul>

            <!--
                İzometrik kalkan. Hazır bir 3B görsel yerine SVG tercih edildi:
                tek dosya, ölçeklenebilir, tema renklerini kullanır ve depoya
                ek bir ikili varlık girmez.
            -->
            <figure class="rk-lg-art" aria-hidden="true">
                <svg viewBox="0 0 420 320" role="presentation">
                    <defs>
                        <linearGradient id="lgPlate" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#dbeafe"/>
                            <stop offset="1" stop-color="#eff6ff"/>
                        </linearGradient>
                        <linearGradient id="lgShield" x1="0" y1="0" x2=".6" y2="1">
                            <stop offset="0" stop-color="#bfdbfe" stop-opacity=".95"/>
                            <stop offset="1" stop-color="#93c5fd" stop-opacity=".55"/>
                        </linearGradient>
                        <linearGradient id="lgShieldBack" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#e0ecff"/>
                            <stop offset="1" stop-color="#c7ddff"/>
                        </linearGradient>
                    </defs>

                    <!-- izometrik taban -->
                    <path d="M210 246 L358 202 L210 158 L62 202 Z" fill="url(#lgPlate)"/>
                    <path d="M62 202 L210 246 L210 268 L62 224 Z" fill="#cfe0f7"/>
                    <path d="M358 202 L210 246 L210 268 L358 224 Z" fill="#bcd3f2"/>

                    <!-- Kalkan ve kilit birlikte suzulur: parcalar
                         birbirinden ayrilmasin diye tek grup. -->
                    <g class="rk-lg-shield">
                        <!-- arka kalkan (derinlik) -->
                        <path d="M186 52 L268 78 L268 150 Q268 196 186 224 Q104 196 104 150 L104 78 Z"
                              fill="url(#lgShieldBack)" opacity=".7"/>
                        <!-- ön kalkan -->
                        <path d="M210 44 L292 70 L292 142 Q292 190 210 218 Q128 190 128 142 L128 70 Z"
                              fill="url(#lgShield)" stroke="#93c5fd" stroke-width="1.5"/>

                        <!-- kilit -->
                        <rect x="188" y="118" width="44" height="38" rx="7" fill="#2563eb" opacity=".9"/>
                        <path d="M197 118 v-12 a13 13 0 0 1 26 0 v12"
                              fill="none" stroke="#2563eb" stroke-width="7"
                              stroke-linecap="round" opacity=".9"/>
                        <circle cx="210" cy="135" r="4.5" fill="#eff6ff"/>
                    </g>

                    <!-- bağlantı düğümleri -->
                    <circle class="rk-lg-node n1" cx="46"  cy="176" r="5"   fill="#93c5fd"/>
                    <circle class="rk-lg-node n2" cx="376" cy="166" r="4"   fill="#93c5fd"/>
                    <circle class="rk-lg-node n3" cx="352" cy="268" r="5.5" fill="#bfdbfe"/>
                    <path d="M46 176 L104 150"  stroke="#bfdbfe" stroke-width="1.5" fill="none"/>
                    <path d="M376 166 L292 142" stroke="#bfdbfe" stroke-width="1.5" fill="none"/>
                </svg>

                <figcaption class="rk-lg-callout">
                    <?= te('Siber risklere karşı') ?><br><strong><?= te('bir adım önde') ?></strong>
                    <span class="rk-lg-rule"></span>
                </figcaption>
            </figure>
        </section>

        <!-- ---------------------------- orta: kart ---------------------- -->
        <section class="rk-lg-card rk-lg-in">

            <div class="rk-lg-card-head">
                <?= brand_wordmark('light') ?>
                <p>IT &amp; Cyber Risk Management Platform</p>
            </div>

            <h2 class="rk-lg-welcome"><?= te('Tekrar hoş geldiniz') ?></h2>
            <p class="rk-lg-welcome-sub"><?= te('Hesabınıza giriş yaparak devam edin.') ?></p>

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
                    <label class="rk-label" for="email"><?= te('E-posta') ?></label>
                    <div class="rk-input-icon">
                        <i class="bi bi-envelope"></i>
                        <input class="rk-input" type="email" id="email" name="email"
                               value="<?= e(APP_DEMO && old('email') === '' ? DEMO_EMAIL : old('email')) ?>"
                               autocomplete="username" required autofocus
                               placeholder="ornek@kurum.local">
                    </div>
                </div>

                <div class="rk-field">
                    <label class="rk-label" for="password"><?= te('Parola') ?></label>
                    <div class="rk-input-icon has-toggle">
                        <i class="bi bi-lock"></i>
                        <input class="rk-input" type="password" id="password" name="password"
                               autocomplete="current-password" required
                               placeholder="<?= te('Parolanız') ?>">
                        <button type="button" class="rk-pw-toggle"
                                data-rk-pw-toggle="password"
                                aria-label="<?= te('Parolayı göster') ?>" aria-pressed="false">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <?php
                /* "Parolamı unuttum" BİLEREK YOK: uygulamada self-service
                   sıfırlama akışı bulunmuyor, parolayı yönetici sıfırlıyor
                   (admin/users/reset_password.php). Çalışmayan bir bağlantı
                   koymaktansa doğru yönlendirmeyi yazmak daha dürüst. */
                ?>
                <p class="rk-lg-hint">
                    <?= te('Parolanızı mı unuttunuz? Sistem yöneticinize başvurun.') ?>
                </p>

                <button type="submit" class="rk-btn rk-btn-primary">
                    <i class="bi bi-box-arrow-in-right"></i> <?= te('Giriş yap') ?>
                </button>
            </form>

            <?php
            /* Güvenlik rozetleri: yalnızca uygulamada GERÇEKTEN olan önlemler
               yazılır. SSL/TLS, SSO ve KVKK rozetleri kasıtlı olarak yok -
               SSO hiç yok, HTTPS kuruluma bağlı, KVKK bir uyumluluk beyanı.
               Olmayanı yazmak kullanıcıya yanlış güvence verir. */
            ?>
            <div class="rk-lg-secure">
                <span class="rk-lg-secure-ic"><i class="bi bi-shield-lock-fill"></i></span>
                <div class="rk-lg-secure-txt">
                    <strong><?= te('Oturumunuz korunuyor') ?></strong>
                    <span><?= te('Her istek sunucuda yeniden doğrulanır.') ?></span>
                </div>
                <div class="rk-lg-secure-tags">
                    <span>CSRF</span><span>Audit log</span><span><?= te('Rol bazlı yetki') ?></span>
                </div>
            </div>

            <p class="rk-lg-audit">
                <i class="bi bi-record-circle"></i>
                <?= te('Bu sistemdeki tüm işlemler kayıt altına alınmaktadır.') ?>
            </p>

        </section>

        <!-- ---------------------------- sağ: şerit ---------------------- -->
        <aside class="rk-lg-right rk-lg-in" aria-hidden="true">
            <ul class="rk-lg-rail">
                <li><i class="bi bi-shield-check"></i><span>Riskleri<br>öngörün</span></li>
                <li><i class="bi bi-bar-chart-line"></i><span>Daha iyi<br>kararlar alın</span></li>
                <li><i class="bi bi-bullseye"></i><span>Sürekli<br>daha güçlü olun</span></li>
            </ul>
            <p class="rk-lg-motto">
                Teknoloji.<br>Güven.<br>Sürdürülebilirlik.
                <span class="rk-lg-rule"></span>
            </p>
        </aside>

    </main>

    <!-- =========================== alt şerit =========================== -->
    <footer class="rk-lg-foot">
        <span>&copy; <?= date('Y') ?> <strong>RiskOps</strong>. <?= te('Tüm hakları saklıdır.') ?></span>
        <?php
        /* Gizlilik / Kullanım Şartları / Destek bağlantıları YOK: karşılıkları
           olan sayfalar bulunmuyor. Ölü bağlantı bırakmak yerine kurulum
           türü gösteriliyor. */
        ?>
        <span class="rk-lg-foot-env">
            <?= te(APP_DEMO ? 'Deneme kurulumu' : 'Kurumsal kurulum') ?>
        </span>
    </footer>

</div>

<script src="<?= e(url('/assets/js/app.js?v=' . $assetVersion)) ?>"></script>
</body>
</html>
