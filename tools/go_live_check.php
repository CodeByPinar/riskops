<?php
declare(strict_types=1);

/**
 * RiskOps - Üretime alma ön kontrolü
 * /var/www/riskops/tools/go_live_check.php
 *
 * Çalıştırma:  php tools/go_live_check.php
 *
 * Bu betik HİÇBİR ŞEYİ DEĞİŞTİRMEZ. Yalnızca sistemin üretime hazır
 * olup olmadığını raporlar. Veri silmek, parola değiştirmek gibi geri
 * alınamaz işleri bilerek yapmaz - onlar operatörün kararıdır.
 *
 * Çıkış kodu: 0 = FAIL yok, 1 = en az bir FAIL var (CI'da kullanılabilir)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../includes/bootstrap.php';

$fail = 0;
$warn = 0;
$pass = 0;

function result(string $level, string $label, string $detail = '', string $fix = ''): void
{
    global $fail, $warn, $pass;

    $tag = match ($level) {
        'FAIL' => '[FAIL]',
        'WARN' => '[WARN]',
        default => '[ OK ]',
    };
    if ($level === 'FAIL') { $fail++; } elseif ($level === 'WARN') { $warn++; } else { $pass++; }

    printf("  %s  %-44s %s\n", $tag, $label, $detail);
    if ($fix !== '' && $level !== 'OK') {
        printf("          -> %s\n", $fix);
    }
}

function section(string $title): void
{
    echo "\n" . str_repeat('-', 74) . "\n  " . $title . "\n" . str_repeat('-', 74) . "\n";
}

/** Yerel HTTP isteği: başlık ve durum kodu döndürür. */
function probe(string $path): array
{
    $ch = curl_init('http://127.0.0.1' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_NOBODY         => false,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $raw  = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hlen = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['code' => $code, 'headers' => strtolower(substr($raw, 0, $hlen))];
}

echo "\n================= RiskOps — Üretime Alma Ön Kontrolü =================\n";
echo "  " . date('d.m.Y H:i') . "  ·  " . APP_ROOT . "\n";

/* ------------------------------------------------------------------ */
section('1. Ortam ve hata gösterimi');

if (APP_ENV === 'production') {
    result('OK', 'APP_ENV', 'production');
} else {
    result('FAIL', 'APP_ENV', APP_ENV,
        'VirtualHost içinde: SetEnv RISKOPS_ENV production  → systemctl reload apache2');
}

$display = (string)ini_get('display_errors');
if (APP_ENV === 'production' && ($display === '1' || strtolower($display) === 'on')) {
    result('FAIL', 'display_errors', $display, 'Hata detayları kullanıcıya sızar');
} else {
    result('OK', 'display_errors', $display === '' ? 'off' : $display);
}

/* Hata ayıklama kipi: üretimde KAPALI olmalı.
   Açıkken sorgu metinleri, dosya yolları, oturum içeriği ve yığın izi
   tarayıcıya basılır. config.php'deki çift bayrak kuralı kazayla
   açılmasını zorlaştırır ama bilerek açılmış olabilir - üretime
   almadan önce burada görünsün. */
if (APP_DEBUG) {
    /* Nereden açıldığı, nasıl kapatılacağını belirler: panelden
       açıldıysa bir düğme yeter, ortamdan geldiyse VirtualHost
       düzenlenmeli. Yanlış tarafı söylemek zaman kaybettirir. */
    $how = match (debug_source()) {
        'panel'       => 'Yönetim > Hata Ayıklama ekranından "Şimdi kapat" (kalan süre: '
                       . debug_human_duration(debug_flag_remaining()) . ')',
        'ortam'       => 'VirtualHost içinden RISKOPS_DEBUG satırını silin',
        'ortam+panel' => 'Hem panel bayrağını kapatın hem VirtualHost içinden RISKOPS_DEBUG satırını silin',
        default       => 'Kapatın',
    };

    result(
        APP_ENV === 'production' ? 'FAIL' : 'WARN',
        'APP_DEBUG',
        'AÇIK (' . debug_source() . ')',
        $how
    );
} else {
    result('OK', 'APP_DEBUG', 'kapalı');
}

result(is_writable(LOG_PATH) ? 'OK' : 'FAIL', 'Log dizini yazılabilir', LOG_PATH);

if (is_file(DEBUG_LOG_FILE)) {
    $mb = filesize(DEBUG_LOG_FILE) / 1048576;
    result($mb > 20 ? 'WARN' : 'OK', 'debug.log boyutu', number_format($mb, 1) . ' MB',
        $mb > 20 ? 'Hata ayıklama kipi kapatıldıysa bu dosya silinebilir' : '');
}

if (is_file(LOG_FILE)) {
    $mb = filesize(LOG_FILE) / 1048576;
    result($mb > 50 ? 'WARN' : 'OK', 'app.log boyutu', number_format($mb, 1) . ' MB',
        $mb > 50 ? 'logrotate kuralı tanımlayın' : '');
}

/* ------------------------------------------------------------------ */
section('2. Varsayılan kimlik bilgileri');

$stmt = db()->query("SELECT id, name, email, password FROM users WHERE role = 'admin' AND status = 1");
$defaults = ['Admin123456', 'admin', 'password', '123456', 'RiskOps2026Demo'];
$weak = [];

foreach ($stmt->fetchAll() as $u) {
    foreach ($defaults as $candidate) {
        if (password_verify($candidate, (string)$u['password'])) {
            $weak[] = $u['email'] . ' (' . $candidate . ')';
            break;
        }
    }
}
if ($weak !== []) {
    result('FAIL', 'Admin hesabında varsayılan parola', implode(', ', $weak),
        'Parolayı değiştirin veya Kullanıcılar ekranından sıfırlayın');
} else {
    result('OK', 'Admin parolaları', 'varsayılan parola bulunamadı');
}

$demoEmails = ['manager@riskops.local', 'analyst@riskops.local',
               'analyst2@riskops.local', 'viewer@riskops.local'];
$ph = implode(',', array_fill(0, count($demoEmails), '?'));
$stmt = db()->prepare("SELECT email FROM users WHERE email IN ({$ph})");
$stmt->execute($demoEmails);
$foundDemo = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($foundDemo !== []) {
    result('WARN', 'Demo kullanıcı hesapları', count($foundDemo) . ' adet',
        'Üretimde silin veya pasifleştirin: ' . implode(', ', $foundDemo));
} else {
    result('OK', 'Demo kullanıcı hesabı', 'yok');
}

$mustChange = (int)db()->query('SELECT COUNT(*) FROM users WHERE must_change_password = 1 AND status = 1')
    ->fetchColumn();
result($mustChange > 0 ? 'WARN' : 'OK', 'Parola değiştirmesi bekleyen', $mustChange . ' kullanıcı');

/* ------------------------------------------------------------------ */
section('3. Demo verisi');

$demoTitles = db()->query(
    "SELECT COUNT(*) FROM risks WHERE title LIKE 'İnternete açık RDP%'
        OR title LIKE 'Ayrıcalıklı hesaplarda MFA%'
        OR title LIKE 'Siber sigorta kapsamının%'"
)->fetchColumn();

if ((int)$demoTitles > 0) {
    $total = (int)db()->query('SELECT COUNT(*) FROM risks WHERE deleted_at IS NULL')->fetchColumn();
    result('WARN', 'Örnek risk verisi', $total . ' risk (demo işaretleri bulundu)',
        'Temizlemek için: php tools/seed_demo.php --purge --yes');
} else {
    result('OK', 'Örnek risk verisi', 'demo işareti yok');
}

/* ------------------------------------------------------------------ */
section('4. Dosya ve dizin erişimi');

foreach ([
    '/config/database.php'        => 403,
    '/includes/bootstrap.php'     => 403,
    '/database/schema.sql'        => 403,
    '/storage/logs/app.log'       => 403,
    '/tools/go_live_check.php'    => 403,
    '/deploy/riskops.conf'        => 403,
    '/errors/404.php'             => 404,
    '/'                           => 302,
] as $path => $expected) {
    $r = probe($path);
    result($r['code'] === $expected ? 'OK' : 'FAIL',
        'HTTP ' . $path, $r['code'] . ' (beklenen ' . $expected . ')',
        $r['code'] !== $expected ? 'VirtualHost korumalarını kurun: deploy/riskops.conf' : '');
}

$dbPerm = substr(sprintf('%o', fileperms(CONFIG_PATH . '/database.php')), -3);
result(in_array($dbPerm, ['600', '640'], true) ? 'OK' : 'WARN',
    'config/database.php izni', $dbPerm, 'chmod 640 config/database.php');

if (is_dir(APP_ROOT . '/tools')) {
    result('WARN', 'tools/ dizini sunucuda', count(glob(APP_ROOT . '/tools/*.php')) . ' dosya',
        'Üretimde kaldırın: seed_demo.php --purge tüm risk verisini siler');
} else {
    result('OK', 'tools/ dizini', 'kaldırılmış');
}

/* ------------------------------------------------------------------ */
section('5. Güvenlik başlıkları ve taşıma');

$r = probe('/auth/login.php');
foreach ([
    'x-content-type-options'    => 'nosniff',
    'x-frame-options'           => 'sameorigin',
    'referrer-policy'           => 'strict-origin',
    'content-security-policy'   => 'default-src',
] as $header => $needle) {
    $has = str_contains($r['headers'], $header) && str_contains($r['headers'], $needle);
    result($has ? 'OK' : ($header === 'content-security-policy' ? 'WARN' : 'FAIL'),
        'Başlık: ' . $header, $has ? 'var' : 'yok',
        $has ? '' : 'sudo a2enmod headers && deploy/riskops.conf kurulumu');
}

$httpsReady = !empty($_SERVER['HTTPS']);
result('WARN', 'HTTPS', 'uygulama HTTP üzerinden sunuluyor',
    'Oturum çerezi yalnızca HTTPS altında Secure işaretlenir; sertifika kurun');

/* ------------------------------------------------------------------ */
section('6. Veritabanı ve şema');

$live = (int)db()->query(
    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
)->fetchColumn();
result($live === 10 ? 'OK' : 'WARN', 'Tablo sayısı', $live . ' (beklenen 10)');

$liveSettings = (int)db()->query('SELECT COUNT(*) FROM settings')->fetchColumn();
$seedFile     = APP_ROOT . '/database/seed.sql';
$seedSettings = is_file($seedFile)
    ? preg_match_all("/^\('[a-z_]+',/m", file_get_contents($seedFile))
    : 0;
result($seedSettings >= $liveSettings ? 'OK' : 'FAIL',
    'seed.sql ayar kapsaması', $seedSettings . ' / ' . $liveSettings . ' canlı',
    $seedSettings < $liveSettings ? 'Sıfırdan kurulumda ayar eksik kalır' : '');

$badCollation = (int)db()->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_COLLATION <> 'utf8mb4_unicode_ci'"
)->fetchColumn();
result($badCollation === 0 ? 'OK' : 'FAIL', 'Karakter kümesi', $badCollation . ' tablo farklı');

$softDeleted = (int)db()->query('SELECT COUNT(*) FROM risks WHERE deleted_at IS NOT NULL')->fetchColumn();
result($softDeleted > 0 ? 'WARN' : 'OK', 'Soft-delete edilmiş risk', (string)$softDeleted,
    $softDeleted > 0 ? 'Arayüzde geri alma ekranı henüz yok' : '');

/* ------------------------------------------------------------------ */
section('7. Bilinen sınırlar');

/* Ayri bir sessions tablosu YOK; gecersizlestirme auth_revalidate()
   icinde her istekte yapilan tek sorgu ile saglaniyor. Kontrolun
   tablonun varligina degil, mekanizmanin varligina bakmasi gerekir. */
$hasRevalidate = function_exists('auth_revalidate');
result($hasRevalidate ? 'OK' : 'FAIL', 'Oturum gecersizlestirme',
    $hasRevalidate ? 'auth_revalidate() her istekte dogruluyor' : 'mekanizma yok',
    $hasRevalidate ? '' : 'Pasiflestirme ve parola sifirlama aktif oturumu etkilemez');

$skew = 0;
try {
    $mysqlNow = (string)db()->query('SELECT NOW()')->fetchColumn();
    $skew = abs(strtotime(date('Y-m-d H:i:s')) - strtotime($mysqlNow));
} catch (Throwable $e) { $skew = 9999; }
result($skew <= 2 ? 'OK' : 'FAIL', 'MySQL/PHP saat dilimi', 'fark ' . $skew . ' sn',
    $skew > 2 ? 'Brute-force penceresi ve oturum kontrolleri sessizce calismaz' : '');

$retention = (int)setting('audit_retention_days', 0);
result($retention > 0 ? 'OK' : 'WARN', 'Audit saklama süresi',
    $retention > 0 ? $retention . ' gün' : 'sınırsız',
    $retention > 0 ? 'Arşivleme görevi henüz otomatik değil' : '');

$oldestAudit = db()->query('SELECT MIN(created_at) FROM audit_logs')->fetchColumn();
$auditCount  = (int)db()->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
result('OK', 'Audit kaydı', $auditCount . ' kayıt, en eski: ' . ($oldestAudit ?: '—'));

/* ------------------------------------------------------------------ */

echo "\n" . str_repeat('=', 74) . "\n";
printf("  %d uygun · %d uyarı · %d engel\n", $pass, $warn, $fail);
echo str_repeat('=', 74) . "\n";

if ($fail > 0) {
    echo "\n  ÜRETİME HAZIR DEĞİL — yukarıdaki [FAIL] maddelerini kapatın.\n\n";
} elseif ($warn > 0) {
    echo "\n  Engelleyici sorun yok. [WARN] maddeleri operatör kararıdır.\n\n";
} else {
    echo "\n  Üretime hazır.\n\n";
}

exit($fail > 0 ? 1 : 0);
