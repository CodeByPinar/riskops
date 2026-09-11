<?php
declare(strict_types=1);

/**
 * RiskOps - Uygulama yapilandirmasi
 * /var/www/riskops/config/config.php
 *
 * Bu dosya hiçbir sey yazdirmaz, sadece sabit tanimlar.
 */

// --- Ortam ------------------------------------------------------------
// 'development' -> hatalar ekranda + log dosyasinda
// 'production'  -> hatalar SADECE log dosyasinda, kullaniciya sade mesaj
//
// Ortam KOD DEĞİŞTİRMEDEN ayarlanır: Apache VirtualHost içindeki
//     SetEnv RISKOPS_ENV production
// satırı yeterlidir. Böylece üretime alma işlemi bir yapılandırma
// değişikliğidir, bir kod değişikliği değil - yanlışlıkla geliştirme
// ayarıyla canlıya çıkma riski ortadan kalkar.
//
// Tanımlı değilse güvenli varsayılan: development (hataları gizlemek
// yerine gösterir; sessizce bozuk çalışmaktan iyidir).
$riskopsEnv = $_SERVER['RISKOPS_ENV'] ?? getenv('RISKOPS_ENV') ?: '';
define('APP_ENV', in_array($riskopsEnv, ['development', 'production'], true)
    ? $riskopsEnv
    : 'development');
unset($riskopsEnv);

// --- Dizin sabitleri --------------------------------------------------
define('APP_ROOT',      dirname(__DIR__));
define('CONFIG_PATH',   APP_ROOT . '/config');
define('INCLUDES_PATH', APP_ROOT . '/includes');
define('LAYOUT_PATH',   INCLUDES_PATH . '/layout');
define('PARTIALS_PATH', INCLUDES_PATH . '/partials');
define('STORAGE_PATH',  APP_ROOT . '/storage');
define('LOG_PATH',      STORAGE_PATH . '/logs');
define('UPLOAD_PATH',   STORAGE_PATH . '/uploads');
define('LOG_FILE',      LOG_PATH . '/app.log');

// --- URL --------------------------------------------------------------
// DocumentRoot = /var/www/riskops olduğu için uygulama web kokunde duruyor.
// Alt dizine tasinirsa buraya '/riskops' gibi bir on ek yazılır.
define('BASE_PATH', '');

// --- Oturum -----------------------------------------------------------
define('SESSION_NAME', 'RISKOPS_SESSION');

// --- Fallback değerler ------------------------------------------------
// settings tablosu okunamazsa bunlar kullanılır.
define('FALLBACK_APP_NAME',        'RiskOps');
define('FALLBACK_TIMEZONE',        'Europe/Istanbul');
define('FALLBACK_DATE_FORMAT',     'd.m.Y');
define('FALLBACK_DATETIME_FORMAT', 'd.m.Y H:i');
define('FALLBACK_PER_PAGE',        25);
