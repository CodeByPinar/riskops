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

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            // Hatalar exception olarak firlatilir -> merkezi handler yakalar
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Gerçek prepared statement -> SQL injection'a karsi en guclu koruma
            PDO::ATTR_EMULATE_PREPARES   => false,
            // INT kolonlar PHP int olarak gelsin (string degil)
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);

        // Sessiz veri kaybini engelle: tasan/geçersiz değer HATA versin
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
    } catch (PDOException $e) {
        // Kullaniciya ASLA bağlantı detayı gosterme
        app_log('critical', 'Database connection failed: ' . $e->getMessage());
        app_abort(500);
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
