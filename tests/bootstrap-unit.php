<?php

declare(strict_types=1);

/**
 * RiskOps - Birim testleri için ön yükleyici
 * /var/www/riskops/tests/bootstrap-unit.php
 *
 * VERİTABANI GEREKTİRMEZ. Amacı, uygulamanın saf mantığını -
 * dil devri kuralı, maskeleme, skor hesabı, ay penceresi, sayfalama -
 * hiçbir kurulum yapmadan sınanabilir kılmak.
 *
 * NEDEN UYGULAMANIN KENDİ BOOTSTRAP'I KULLANILMIYOR
 * -------------------------------------------------
 * includes/bootstrap.php açılışta ayar tablosunu okur
 * (setting('timezone')) ve MySQL oturum saatini hizalar; yani her
 * zaman veritabanına gider. Saf bir fonksiyonu sınamak için MariaDB
 * kurmak zorunda kalmak, katkı vermenin önündeki gereksiz bir engel
 * olurdu. Burada yalnızca sabitler ve veritabanına dokunmayan
 * modüller yükleniyor.
 *
 * VERİTABANI GEREKTİREN TESTLER tests/Integration/ altındadır ve
 * tests/bootstrap-app.php ile çalışır (gerçek bootstrap).
 */

require_once __DIR__ . '/../config/config.php';

/* ---------------------------------------------------------------------
 * db() TUZAĞI
 *
 * includes/db.php BİLEREK yüklenmiyor. Bir birim testi yanlışlıkla
 * veritabanına uzanırsa sessizce bağlanmaya çalışıp zaman aşımına
 * uğramasın; ne yapması gerektiğini söyleyen bir istisna alsın.
 * -------------------------------------------------------------------*/
function db(): PDO
{
    throw new RuntimeException(
        'Birim testleri veritabanına erişemez. Bu testin yeri tests/Integration/ '
        . '(ön yükleyici: tests/bootstrap-app.php).'
    );
}

function db_sync_timezone(?PDO $pdo = null): void
{
    // Birim testlerinde yapacak bir şey yok.
}

/* ---------------------------------------------------------------------
 * AYARLAR
 *
 * includes/settings.php yerine geçen sürüm: değerler bellekte durur,
 * test onları değiştirebilir. Varsayılanlar database/seed.sql ile
 * AYNI olmalıdır - testin gerçekten kurulumdaki davranışı sınaması
 * için.
 * -------------------------------------------------------------------*/

/**
 * Test ayarlarını okur; dizi verilirse üzerine yazar.
 *
 * @param array<string, mixed>|null $override
 * @return array<string, mixed>
 */
function riskops_test_settings(?array $override = null): array
{
    static $defaults = [
        'app_name'            => 'RiskOps',
        'company_name'        => '',
        'timezone'            => 'Europe/Istanbul',
        'date_format'         => 'd.m.Y',
        'datetime_format'     => 'd.m.Y H:i',
        'per_page'            => 25,
        'default_locale'      => 'tr',
        'session_lifetime'    => 3600,
        'severity_thresholds' => [
            'Low'      => [1, 4],
            'Medium'   => [5, 9],
            'High'     => [10, 16],
            'Critical' => [17, 25],
        ],
    ];

    static $values = null;

    if ($values === null) {
        $values = $defaults;
    }
    if ($override !== null) {
        $values = $override === [] ? $defaults : ($override + $values);
    }

    return $values;
}

/** Ayarları seed varsayılanlarına döndürür. Testler tearDown'da çağırır. */
function riskops_test_settings_reset(): void
{
    riskops_test_settings([]);
}

function settings_all(bool $refresh = false): array
{
    return riskops_test_settings();
}

function settings_cast(?string $value, string $type): mixed
{
    return match ($type) {
        'int'   => (int)$value,
        'bool'  => in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true),
        'json'  => json_decode((string)$value, true) ?? [],
        default => (string)$value,
    };
}

function setting(string $key, mixed $default = null): mixed
{
    $all = riskops_test_settings();

    return array_key_exists($key, $all) ? $all[$key] : $default;
}

function app_name(): string
{
    return (string)setting('app_name', FALLBACK_APP_NAME);
}

function per_page(): int
{
    $n = (int)setting('per_page', FALLBACK_PER_PAGE);

    return $n > 0 && $n <= 200 ? $n : FALLBACK_PER_PAGE;
}

/* ---------------------------------------------------------------------
 * VERİTABANINA DOKUNMAYAN GERÇEK MODÜLLER
 * -------------------------------------------------------------------*/
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/csrf.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/audit.php';
require_once INCLUDES_PATH . '/risk.php';
require_once INCLUDES_PATH . '/ui.php';
require_once INCLUDES_PATH . '/i18n.php';
require_once INCLUDES_PATH . '/debug.php';
require_once INCLUDES_PATH . '/plugins.php';

date_default_timezone_set((string)setting('timezone', FALLBACK_TIMEZONE));

/* Testler arasında sızmaması gereken istek durumu. */
$_SESSION = [];
$_GET     = [];
$_POST    = [];
