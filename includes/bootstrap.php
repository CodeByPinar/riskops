<?php
declare(strict_types=1);

/**
 * RiskOps - Uygulama onyukleyici
 * /var/www/riskops/includes/bootstrap.php
 *
 * HER sayfanin ilk satırı:
 *   require_once __DIR__ . '/../includes/bootstrap.php';
 */

if (defined('RISKOPS_BOOTSTRAPPED')) {
    return;
}
define('RISKOPS_BOOTSTRAPPED', true);

require_once __DIR__ . '/../config/config.php';

/* ---------------------------------------------------------------------
 * 1) Hata raporlama
 * -------------------------------------------------------------------*/
error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', LOG_FILE);

require_once INCLUDES_PATH . '/functions.php';

// Uyarilari/notice'lari exception'a cevir -> sessiz hata kalmasin
set_error_handler(static function (int $no, string $str, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    throw new ErrorException($str, 0, $no, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    app_log('critical', get_class($e) . ': ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    if (APP_ENV === 'development') {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "EXCEPTION: " . get_class($e) . "\n"
           . $e->getMessage() . "\n"
           . $e->getFile() . ':' . $e->getLine() . "\n\n"
           . $e->getTraceAsString() . "\n";
        exit(1);
    }

    app_abort(500);
});

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        app_log('critical', 'Fatal error: ' . $err['message'], [
            'file' => $err['file'],
            'line' => $err['line'],
        ]);
    }
});

/* ---------------------------------------------------------------------
 * 2) Cekirdek moduller
 * -------------------------------------------------------------------*/
require_once INCLUDES_PATH . '/db.php';
require_once INCLUDES_PATH . '/settings.php';
require_once INCLUDES_PATH . '/csrf.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/audit.php';
require_once INCLUDES_PATH . '/risk.php';
require_once INCLUDES_PATH . '/ui.php';
require_once INCLUDES_PATH . '/lookups.php';

/* ---------------------------------------------------------------------
 * 3) Zaman dilimi  (settings tablosundan)
 * -------------------------------------------------------------------*/
$riskopsTz = (string)setting('timezone', FALLBACK_TIMEZONE);
if (!in_array($riskopsTz, DateTimeZone::listIdentifiers(), true)) {
    $riskopsTz = FALLBACK_TIMEZONE;
}
date_default_timezone_set($riskopsTz);
unset($riskopsTz);

// MySQL oturumunu ayni saat dilimine al. Sunucu UTC, uygulama
// Europe/Istanbul olabilir; NOW() ile date() ayni ani gostermezse
// tum zaman damgalari ve zamana dayali kontroller kayar.
db_sync_timezone();

/* ---------------------------------------------------------------------
 * 4) Güvenli oturum  (CLI'da atlanir)
 * -------------------------------------------------------------------*/
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {

    $riskopsSecure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
                  || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');

    ini_set('session.use_strict_mode', '1');   // sunucunun uretmedigi ID kabul edilmez
    ini_set('session.use_only_cookies', '1');  // URL'de session ID tasinmaz
    ini_set('session.cookie_httponly', '1');   // JS cookie'yi okuyamaz

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_PATH === '' ? '/' : BASE_PATH . '/',
        'domain'   => '',
        'secure'   => $riskopsSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    unset($riskopsSecure);

    // --- Hareketsizlik zaman asimi ---
    $riskopsLifetime = (int)setting('session_lifetime', 3600);
    if ($riskopsLifetime > 0
        && isset($_SESSION['last_activity'])
        && (time() - (int)$_SESSION['last_activity']) > $riskopsLifetime) {

        auth_destroy();
        session_start();
        flash('warning', 'Oturumunuz zaman aşımına uğradı. Lütfen tekrar giriş yapın.');
    }
    $_SESSION['last_activity'] = time();
    unset($riskopsLifetime);

    // --- Önceki istekten gelen form girdileri (istek kapsaminda) ---
    $GLOBALS['_old_input'] = is_array($_SESSION['_old'] ?? null) ? $_SESSION['_old'] : [];
    unset($_SESSION['_old']);

    // --- Önceki istekten gelen alan bazlı doğrulama hatalari ---
    $GLOBALS['_errors'] = is_array($_SESSION['_errors'] ?? null) ? $_SESSION['_errors'] : [];
    unset($_SESSION['_errors']);

    // --- Güvenlik başlıkları (mod_headers yoksa da garanti) ---
    //
    // NEDEN PHP TARAFINDA DA VAR: Apache yapılandırmasındaki Header
    // yönergeleri mod_headers yüklü değilse SESSİZCE yok sayılır.
    // Paylaşımlı hostinglerde bu sık görülür - o kurulumda uygulama
    // hiçbir güvenlik başlığı olmadan çalışırdı. Buradakiler her
    // koşulda gider.
    //
    // Apache tarafındaki kopyalar statik dosyaları (CSS, JS, font,
    // görsel) da kapsadığı için kaldırılmadı. İki taraf AYNI olmalı;
    // biri değişirse diğeri de değişmeli - tarayıcı birden fazla CSP
    // başlığı görürse hepsinin KESİŞİMİNİ uygular ve ayrışan iki
    // politika hata ayıklaması zor bir kısıtlamaya dönüşür.
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()');

        // script-src'de 'unsafe-inline' YOKTUR: tüm betikler harici
        // dosyadadır (assets/js/app.js) ve davranışlar data-rk-*
        // öznitelikleriyle bağlanır. Satır içi <script> veya onclick
        // ekleyen yeni kod sessizce çalışmaz.
        //
        // style-src'de 'unsafe-inline' KALDI ve bu kasıtlıdır:
        // uygulama dinamik ölçü taşıyan style="" öznitelikleri
        // kullanıyor (örn. ilerleme çubuğu genişliği), bunlar statik
        // sınıfa çevrilemez. XSS yükü betik enjekte eder, stil değil.
        header(
            "Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
            . "style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; "
            . "connect-src 'self'; form-action 'self'; frame-ancestors 'self'; "
            . "base-uri 'self'; object-src 'none'"
        );
    }

    // --- Oturumu veritabanıyla doğrula ---
    // Pasifleştirilen hesap, düşürülen rol ve sıfırlanan parola aktif
    // oturuma AN INDA yansır. Tek birincil anahtar sorgusu.
    auth_revalidate();

    // --- Geçici parola zorunluluğu ---
    // Geçici parolayla giren kullanıcı, parolasını değiştirmeden
    // başka hiçbir sayfaya erişemez.
    require_password_change_if_needed();
}
