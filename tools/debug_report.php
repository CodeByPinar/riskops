<?php

declare(strict_types=1);

/**
 * RiskOps - Sistem teşhis raporu
 * /var/www/riskops/tools/debug_report.php
 *
 * Çalıştırma:
 *     php tools/debug_report.php              # ekrana
 *     php tools/debug_report.php > rapor.txt  # dosyaya
 *
 * NE İŞE YARAR
 * ------------
 * "Bende çalışıyor" durumunu ortadan kaldırır. Bir hata bildirirken
 * bu raporun çıktısını eklemek, karşı tarafın ortamı tahmin etmesini
 * gereksiz kılar: PHP sürümü ve eklentileri, veritabanı sürümü ve
 * oturum ayarları, tablo satır sayıları, dizin izinleri, yapılandırma
 * bayrakları ve log kuyruğu tek yerde.
 *
 * HİÇBİR ŞEYİ DEĞİŞTİRMEZ. Yalnızca okur ve yazdırır.
 *
 * SIR BASMAZ. Veritabanı parolası, kullanıcı adı ve oturum kimlikleri
 * çıktıya girmez; bağlantı bilgisi "tanımlı / tanımsız" olarak
 * raporlanır. Raporu bir hata kaydına yapıştırmak güvenlidir.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../includes/bootstrap.php';

function sec(string $title): void
{
    echo "\n" . str_repeat('=', 72) . "\n  " . mb_strtoupper($title) . "\n" . str_repeat('=', 72) . "\n";
}

function row(string $label, string|int|float|bool|null $value, string $note = ''): void
{
    if (is_bool($value)) {
        $value = $value ? 'evet' : 'hayır';
    }
    printf("  %-30s %s%s\n", $label, (string)$value, $note !== '' ? '   (' . $note . ')' : '');
}

function warn(string $message): void
{
    echo "  !! " . $message . "\n";
}

$t0 = microtime(true);

echo "\nRiskOps teşhis raporu — " . date('Y-m-d H:i:s') . "\n";

/* ------------------------------------------------------------------ */
sec('yapılandırma');

row('APP_ENV', APP_ENV);
row('APP_DEBUG', APP_DEBUG, APP_DEBUG ? 'RISKOPS_DEBUG=1' : 'RISKOPS_DEBUG ayarlı değil');
row('APP_DEMO', APP_DEMO);
row('BASE_PATH', BASE_PATH === '' ? '(kök)' : BASE_PATH);
row('APP_ROOT', APP_ROOT);
row('Zaman dilimi', date_default_timezone_get() . ' (' . date('P') . ')');

if (APP_ENV === 'production' && APP_DEBUG) {
    warn('ÜRETİMDE HATA AYIKLAMA KİPİ AÇIK. Sorgu metinleri ve oturum');
    warn('içeriği tarayıcıya basılıyor. Canlıda bunu kapatın:');
    warn('  VirtualHost içinden RISKOPS_DEBUG_PRODUCTION satırını silin.');
}

/* ------------------------------------------------------------------ */
sec('php');

row('Sürüm', PHP_VERSION);
row('SAPI', PHP_SAPI);
row('İşletim sistemi', PHP_OS_FAMILY . ' / ' . php_uname('r'));
row('memory_limit', (string)ini_get('memory_limit'));
row('max_execution_time', (string)ini_get('max_execution_time'));
row('upload_max_filesize', (string)ini_get('upload_max_filesize'));
row('post_max_size', (string)ini_get('post_max_size'));
row('display_errors', ini_get('display_errors') ? 'açık' : 'kapalı');
row('error_log', (string)ini_get('error_log'));
row('opcache', extension_loaded('Zend OPcache') ? 'yüklü' : 'yok');

$required = ['pdo_mysql', 'mbstring', 'json', 'fileinfo', 'session', 'filter'];
$optional = ['gd', 'intl', 'zip', 'curl', 'openssl'];

$missing = array_values(array_filter($required, static fn (string $x): bool => !extension_loaded($x)));
row('Zorunlu eklentiler', $missing === [] ? 'tamam' : 'EKSİK: ' . implode(', ', $missing));
row('İsteğe bağlı', implode(', ', array_filter($optional, 'extension_loaded')) ?: '-');

if ($missing !== []) {
    warn('Eksik eklentiler uygulamanın bazı bölümlerini çalışmaz hâle getirir.');
}

/* upload_max_filesize, uygulamanın ek boyutu sınırını altta bırakıyor mu? */
$iniUpload = (int)filter_var((string)ini_get('upload_max_filesize'), FILTER_SANITIZE_NUMBER_INT);
$iniUnit   = strtoupper(substr(trim((string)ini_get('upload_max_filesize')), -1));
$iniBytes  = $iniUpload * match ($iniUnit) {
'G' => 1073741824, 'M' => 1048576, 'K' => 1024, default => 1
};
if (defined('ATTACH_MAX_BYTES') && $iniBytes > 0 && $iniBytes < ATTACH_MAX_BYTES) {
    warn(sprintf(
        'upload_max_filesize (%s) uygulamanın ek sınırından (%s) küçük;',
        ini_get('upload_max_filesize'),
        debug_bytes(ATTACH_MAX_BYTES)
    ));
    warn('büyük ekler PHP tarafından sessizce reddedilir.');
}

/* ------------------------------------------------------------------ */
sec('veritabanı');

try {
    $pdo = db();
    row('Sürücü', (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    row('Sunucu sürümü', (string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION));
    row('İstemci sürümü', (string)$pdo->getAttribute(PDO::ATTR_CLIENT_VERSION));
    row('Bağlantı bilgisi', is_file(CONFIG_PATH . '/database.php') ? 'tanımlı' : 'TANIMSIZ');

    foreach (['sql_mode', 'time_zone', 'character_set_connection', 'collation_connection',
              'innodb_lock_wait_timeout', 'max_allowed_packet'] as $var) {
        $st = $pdo->prepare('SELECT @@' . $var);
        $st->execute();
        row($var, (string)$st->fetchColumn());
    }

    /* PHP ve MySQL aynı ana mı bakıyor? Kaymış olması audit zaman
       damgalarını ve hesap kilidi penceresini bozar. */
    $mysqlNow = (string)db_value('SELECT NOW()');
    $phpNow   = date('Y-m-d H:i:s');
    $drift    = abs(strtotime($mysqlNow) - strtotime($phpNow));
    row('MySQL NOW()', $mysqlNow);
    row('PHP date()', $phpNow);
    row('Fark', $drift . ' sn', $drift <= 2 ? 'hizalı' : 'KAYIK');
    if ($drift > 2) {
        warn('PHP ile MySQL saatleri kaymış. Zaman damgaları ve hesap');
        warn('kilidi penceresi yanlış çalışır. Bkz. db_sync_timezone().');
    }

    sec('tablolar');

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    printf("  %-28s %10s %12s %12s\n", 'TABLO', 'SATIR', 'VERİ', 'İNDEKS');
    echo '  ' . str_repeat('-', 66) . "\n";

    $st = $pdo->prepare(
        'SELECT table_rows, data_length, index_length
           FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_name = ?'
    );

    foreach ($tables as $table) {
        /* Kesin satır sayısı: information_schema.table_rows InnoDB'de
           tahminîdir, küçük tablolarda 0 gösterebilir. */
        $count = (int)db_value('SELECT COUNT(*) FROM `' . $table . '`');
        $st->execute([$table]);
        $meta = $st->fetch() ?: ['data_length' => 0, 'index_length' => 0];
        printf(
            "  %-28s %10d %12s %12s\n",
            $table,
            $count,
            debug_bytes((int)$meta['data_length']),
            debug_bytes((int)$meta['index_length'])
        );
    }
    row('Toplam tablo', count($tables));
} catch (Throwable $e) {
    warn('Veritabanına ulaşılamadı: ' . $e->getMessage());
}

/* ------------------------------------------------------------------ */
sec('dosya sistemi');

$paths = [
    'APP_ROOT'     => APP_ROOT,
    'storage'      => STORAGE_PATH,
    'storage/logs' => LOG_PATH,
    'uploads'      => UPLOAD_PATH,
    'config'       => CONFIG_PATH,
];

foreach ($paths as $label => $path) {
    if (!is_dir($path)) {
        row($label, 'YOK', $path);
        continue;
    }
    row($label, sprintf(
        '%s  %s%s',
        substr(sprintf('%o', fileperms($path)), -4),
        is_readable($path) ? 'r' : '-',
        is_writable($path) ? 'w' : '-'
    ), $path);
}

foreach ([LOG_PATH, UPLOAD_PATH] as $mustWrite) {
    if (is_dir($mustWrite) && !is_writable($mustWrite)) {
        warn('Yazılamıyor: ' . $mustWrite);
    }
}

/* storage web'den erişilebilir mi? .htaccess olmadan yüklenen
   dosyalar doğrudan indirilebilir hâle gelir. */
row('storage/.htaccess', is_file(STORAGE_PATH . '/.htaccess') ? 'var' : 'YOK');
if (!is_file(STORAGE_PATH . '/.htaccess')) {
    warn('storage/.htaccess yok. Sunucu yapılandırması bu dizini');
    warn('kapatmıyorsa yüklenen dosyalar doğrudan indirilebilir.');
}

$free = @disk_free_space(APP_ROOT);
if ($free !== false) {
    row('Boş disk', debug_bytes((int)$free));
    if ($free < 209715200) {
        warn('200 MB altı boş alan: log ve yükleme yazımları başarısız olabilir.');
    }
}

/* ------------------------------------------------------------------ */
sec('log dosyaları');

foreach (['app.log' => LOG_FILE, 'debug.log' => DEBUG_LOG_FILE] as $name => $file) {
    if (!is_file($file)) {
        row($name, 'yok');
        continue;
    }
    row($name, debug_bytes((int)filesize($file)), 'son değişiklik ' . date('Y-m-d H:i', (int)filemtime($file)));
    if (filesize($file) > 52428800) {
        warn($name . ' 50 MB üstünde; döndürmeyi (logrotate) düşünün.');
    }
}

if (is_file(LOG_FILE)) {
    echo "\n  app.log — son 20 satır:\n";
    $lines = @file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach (array_slice($lines, -20) as $l) {
        echo '    ' . $l . "\n";
    }
    if ($lines === []) {
        echo "    (boş)\n";
    }
}

/* ------------------------------------------------------------------ */
sec('bu raporun kendi ölçümü');

$sum = debug_summary();
row('Süre', debug_ms((microtime(true) - $t0) * 1000));
row('Sorgu', APP_DEBUG ? (string)$sum['query_count'] : 'sayılmadı (kip kapalı)');
row('Tepe bellek', debug_bytes($sum['memory_peak']));

echo "\n";
if (!APP_DEBUG) {
    echo "  Not: hata ayıklama kipi kapalı olduğu için sorgular sayılmadı.\n";
    echo "       Sorgu dökümü için:  RISKOPS_DEBUG=1 php tools/debug_report.php\n\n";
}
