<?php
declare(strict_types=1);

/**
 * RiskOps - Veritabani bağlantısı
 * /var/www/riskops/includes/db.php
 */

/**
 * Tekil (singleton) PDO bağlantısı döndürür.
 * İlk cagrilisinda baglanir, sonraki cagrilarda ayni nesneyi döndürür.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    /** @var array{host:string,name:string,user:string,pass:string,charset:string} $cfg */
    $cfg = require CONFIG_PATH . '/database.php';

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['name'],
        $cfg['charset']
    );

    $options = [
        // Hatalar exception olarak firlatilir -> merkezi handler yakalar
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Gerçek prepared statement -> SQL injection'a karsi en guclu koruma
        PDO::ATTR_EMULATE_PREPARES   => false,
        // INT kolonlar PHP int olarak gelsin (string degil)
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    /**
     * Hata ayiklama kipinde sorgular kaydedilsin.
     *
     * Sarmalayicilar YALNIZCA kip acikken devreye girer: kapaliyken bu
     * dosya hic okunmaz, nesne duz PDO olur ve sorgu yolu bayrak
     * eklenmeden onceki haliyle bire bir aynidir. Boylece "hata
     * ayiklama araci uretimde performansi dusuruyor mu" sorusu hic
     * dogmaz.
     */
    $debugPdo = defined('APP_DEBUG') && APP_DEBUG === true;
    if ($debugPdo) {
        require_once INCLUDES_PATH . '/db_debug.php';
        $options[PDO::ATTR_STATEMENT_CLASS] = [RiskOpsDebugStatement::class, []];
    }

    try {
        $pdo = $debugPdo
            ? new RiskOpsDebugPdo($dsn, $cfg['user'], $cfg['pass'], $options)
            : new PDO($dsn, $cfg['user'], $cfg['pass'], $options);

    } catch (PDOException $e) {
        // Kullaniciya ASLA bağlantı detayı gosterme
        app_log('critical', 'Database connection failed: ' . $e->getMessage());
        app_abort(500);
    }

    /**
     * Sessiz veri kaybini engelle: tasan/gecersiz deger HATA versin.
     *
     * AYRI try/catch: bu ifade baglantinin KENDISI degil, uzerine
     * konan bir iyilestirmedir. Bazi paylasilan hostingler SET SESSION
     * sql_mode'u reddeder; bunu baglanti hatasi sayip uygulamayi 500'e
     * dusurmek, calisabilecek bir kurulumu calismaz hale getirir.
     * Reddedilirse sunucunun kendi sql_mode'u gecerli olur ve durum
     * loglanir - sessizce yutulmaz.
     */
    try {
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
    } catch (Throwable $e) {
        app_log('warning', 'sql_mode could not be set: ' . $e->getMessage());
    }

    return $pdo;
}

/**
 * MySQL oturum saat dilimini PHP'nin saat dilimiyle hizalar.
 *
 * NEDEN GEREKLI: Sunucunun MySQL'i UTC, uygulama Europe/Istanbul
 * calisiyordu. created_at, updated_at, last_login_at ve audit zaman
 * damgalari MySQL tarafindan (NOW/CURRENT_TIMESTAMP) yaziliyor, PHP ise
 * onlari kendi saat diliminde okuyor. Olculen fark: 3 saat.
 *
 * Sonuclari yalnizca kozmetik degildi:
 *   - Tum ekranlarda tarihler 3 saat geri gorunuyordu.
 *   - Brute-force penceresi PHP tarafinda hesaplaniyor
 *     (date(..., time() - lockout)) ve MySQL'in yazdigi attempted_at ile
 *     karsilastiriliyordu. Pencere baslangici kayitlardan ILERIDE
 *     kaldigi icin kosul hicbir satiri yakalamiyor, hesap kilidi hic
 *     devreye girmiyordu. Olcumle dogrulandi: 8 basarisiz denemede kilit
 *     olusmadi.
 *   - auth_revalidate() icindeki "parola benden sonra mi degisti"
 *     karsilastirmasi da ayni nedenle calismiyordu.
 *
 * Baglanti kurulurken PHP'nin saat dilimi henuz ayarlanmamis olabilir
 * (settings tablosundan okunuyor), bu yuzden hizalama bootstrap icinde
 * date_default_timezone_set() cagrisindan SONRA yapilir.
 */
function db_sync_timezone(?PDO $pdo = null): void
{
    static $applied = '';

    $offset = (new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get())))
        ->format('P');

    if ($applied === $offset) {
        return;
    }

    try {
        ($pdo ?? db())->prepare('SET time_zone = ?')->execute([$offset]);
        $applied = $offset;
    } catch (Throwable $e) {
        // Hizalama basarisizsa uygulama calismaya devam etmeli; ancak
        // sessiz kalmamali - tarihler kayik olacak.
        app_log('error', 'MySQL time_zone alignment failed: ' . $e->getMessage(),
            ['offset' => $offset]);
    }
}
