<?php

declare(strict_types=1);

/**
 * RiskOps - Hata ayıklama kipi testleri
 * /var/www/riskops/tools/debug_test.php
 *
 * Çalıştırma:
 *     php tools/debug_test.php                   # kip kapalıyken
 *     RISKOPS_DEBUG=1 php tools/debug_test.php   # kip açıkken
 *
 * İkisi de çalıştırılmalıdır: bazı testler kipin KAPALI olmasına
 * dayanır (hiçbir şey toplanmamalı), bazıları AÇIK olmasına.
 *
 * NEDEN BU TEST VAR
 * -----------------
 * Hata ayıklama araç çubuğu istek ve oturum içeriğini tarayıcıya
 * basar. Bu içerikte parola, CSRF jetonu, oturum kimliği ve
 * veritabanı parolası bulunabilir. Maskelemenin çalıştığı bir kere
 * gözle görülmekle kalmaz - her değişiklikten sonra yeniden
 * doğrulanabilir olmalıdır. Bu dosya o doğrulamadır.
 *
 * Çıkış kodu: 0 = hepsi geçti, 1 = en az bir test başarısız.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../includes/bootstrap.php';

$pass = 0;
$fail = 0;

function ok(bool $cond, string $label, string $detail = ''): void
{
    global $pass, $fail;

    if ($cond) {
        $pass++;
        printf("  [ OK ]  %s\n", $label);
    } else {
        $fail++;
        printf("  [FAIL]  %-52s %s\n", $label, $detail);
    }
}

function section(string $title): void
{
    echo "\n" . str_repeat('-', 72) . "\n  " . $title . "\n" . str_repeat('-', 72) . "\n";
}

echo "\nRiskOps - hata ayıklama kipi testleri\n";
echo "  kip: " . (APP_DEBUG ? 'AÇIK' : 'kapalı') . "   ortam: " . APP_ENV . "\n";

/* =====================================================================
 * 1) ANAHTAR
 * ===================================================================*/
section('1. anahtar ve görünürlük');

ok(debug_enabled() === APP_DEBUG, 'debug_enabled() APP_DEBUG ile aynı');
ok(debug_visible() === false, 'debug_visible() CLI\'da her zaman false');

/* Üretim kapısının MANTIĞI. config.php'deki ifadeyi burada aynı
   şekilde kurup farklı ortam değerleriyle sınıyoruz; amaç
   "production'da tek bayrak yetmez" kuralının bozulmadığını
   göstermek. */
$gate = static function (string $env, string $debug, string $prodFlag): bool {
    $on = ($debug === '1');
    if ($on && $env === 'production') {
        $on = ($prodFlag === '1');
    }

    return $on;
};

ok($gate('development', '1', '') === true, 'geliştirmede RISKOPS_DEBUG=1 yeterli');
ok($gate('development', '', '') === false, 'geliştirmede bayraksız kapalı');
ok($gate('production', '1', '') === false, 'ÜRETİMDE tek bayrak YETMEZ');
ok($gate('production', '1', '1') === true, 'üretimde iki bayrak birlikte açar');
ok($gate('production', '', '1') === false, 'üretimde yalnız PRODUCTION bayrağı açmaz');

/* =====================================================================
 * 1b) GEÇİCİ AÇMA BAYRAĞI  (yönetim ekranının yazdığı dosya)
 *
 * Yetki kontrolü burada test EDİLMEZ - o HTTP katmanındadır
 * (POST + CSRF + admin, bkz. admin/debug/toggle.php). Buradaki test
 * bayrağın kendi davranışını doğrular: süre dolunca yok sayılması,
 * bozuk dosyanın çökertmemesi, listede olmayan sürenin reddi.
 * ===================================================================*/
section('1b. geçici açma bayrağı');

/* Testin kendi yazdığı bayrak, çalıştıran kişinin açık bıraktığı bir
   bayrağı EZMEMELİ. Varsa yedekle, sonunda geri koy. */
$flagBackup = is_file(DEBUG_FLAG_FILE) ? (string)file_get_contents(DEBUG_FLAG_FILE) : null;
@unlink(DEBUG_FLAG_FILE);

ok(debug_flag_read() === null, 'bayrak yokken read() null');
ok(debug_flag_active() === false, 'bayrak yokken active() false');
ok(debug_flag_remaining() === 0, 'bayrak yokken kalan süre 0');

if (!is_writable(STORAGE_PATH)) {
    ok(true, 'bayrak yazma testleri atlandı (storage yazılamıyor)');
} else {
    ok(debug_flag_enable(60), 'bayrak yazıldı');

    $f = debug_flag_read();
    ok($f !== null, 'yazılan bayrak okunuyor');
    ok(($f['minutes'] ?? 0) === 60, 'süre kaydedildi');
    ok(($f['until'] ?? 0) > time(), 'bitiş zamanı gelecekte');
    ok(debug_flag_active(), 'active() true');
    ok(debug_flag_remaining() > 3500, 'kalan süre ~1 saat', (string)debug_flag_remaining());

    /* SÜRE DOLMASI: dosya silinmese bile yok sayılmalı. Bu, "açık
       unutuldu" durumunun neden oluşamadığının kanıtı. */
    $expired = json_decode((string)file_get_contents(DEBUG_FLAG_FILE), true);
    $expired['until'] = time() - 1;
    file_put_contents(DEBUG_FLAG_FILE, json_encode($expired));

    ok(debug_flag_read() === null, 'süresi dolan bayrak YOK SAYILIYOR');
    ok(debug_flag_active() === false, 'süresi dolunca active() false');
    ok(is_file(DEBUG_FLAG_FILE), 'dosya duruyor (silinmesi beklenmiyor)');

    /* BOZUK DOSYA: çökertmemeli, kapalı sayılmalı. */
    file_put_contents(DEBUG_FLAG_FILE, 'bu json degil {{{');
    ok(debug_flag_read() === null, 'bozuk bayrak dosyası kapalı sayılıyor');

    file_put_contents(DEBUG_FLAG_FILE, '');
    ok(debug_flag_read() === null, 'boş bayrak dosyası kapalı sayılıyor');

    /* LİSTEDE OLMAYAN SÜRE: en kısasına düşmeli, keyfi süre kabul
       edilmemeli (bir saldırgan formu değiştirip 10 yıl yazamasın). */
    debug_flag_enable(999999);
    $f = debug_flag_read();
    ok(in_array((int)($f['minutes'] ?? -1), array_keys(DEBUG_FLAG_DURATIONS), true),
       'listede olmayan süre reddedildi', (string)($f['minutes'] ?? -1));

    ok(debug_flag_disable(), 'bayrak silindi');
    ok(!is_file(DEBUG_FLAG_FILE), 'dosya gerçekten yok');
    ok(debug_flag_disable(), 'yokken silmek hata vermiyor');
}

ok(in_array(debug_source(), ['ortam', 'panel', 'ortam+panel', 'kapalı'], true),
   'debug_source() bilinen bir değer döndürüyor', debug_source());
ok(debug_human_duration(0) === '-', 'süre biçimi: sıfır');
ok(str_contains(debug_human_duration(90), 'dk'), 'süre biçimi: dakika');
ok(str_contains(debug_human_duration(7200), 'sa'), 'süre biçimi: saat');

/* Yedeği geri koy */
if ($flagBackup !== null) {
    file_put_contents(DEBUG_FLAG_FILE, $flagBackup);
} else {
    @unlink(DEBUG_FLAG_FILE);
}

/* =====================================================================
 * 2) MASKELEME - ANAHTAR ADINA GÖRE
 * ===================================================================*/
section('2. maskeleme: anahtar adı');

$shouldMask = ['password', 'user_password', 'parola', 'yeni_parola', 'csrf_token',
               '_csrf', 'api_key', 'apiKey', 'AUTHORIZATION', 'Cookie',
               'PHPSESSID', 'session_id', 'password_hash', 'secret', 'salt',
               'db_credentials', 'private_key'];

foreach ($shouldMask as $k) {
    ok(debug_mask_key($k), 'maskelenir: ' . $k);
}

$shouldNotMask = ['title', 'risk_id', 'email', 'department_id', 'status',
                  'severity', 'page', 'sort', 'description', 'owner_id'];

foreach ($shouldNotMask as $k) {
    ok(!debug_mask_key($k), 'maskelenmez: ' . $k);
}

/* =====================================================================
 * 3) MASKELEME - DEĞERE GÖRE
 * ===================================================================*/
section('3. maskeleme: değerin kendisi');

/** @var array<string, mixed> $dbCfg */
$dbCfg  = require CONFIG_PATH . '/database.php';
$dbPass = (string)($dbCfg['pass'] ?? '');

if ($dbPass === '' || strlen($dbPass) < 6) {
    ok(true, 'veritabanı parolası testi atlandı (parola boş/çok kısa)');
} else {
    /* Anahtar adı tamamen masum; yalnızca DEĞER sırdır. */
    $masked = debug_mask_array(['not' => $dbPass, 'derin' => ['x' => $dbPass]]);
    ok($masked['not'] === '***', 'masum anahtar altındaki DB parolası maskelendi');
    ok($masked['derin']['x'] === '***', 'iç içe dizide de maskelendi');

    $exported = debug_export(['a' => ['b' => ['c' => $dbPass]]]);
    ok(!str_contains($exported, $dbPass), 'debug_export() çıktısında DB parolası yok');
}

$fake = 'Ab12Cd34Ef56Gh78';
$kept = debug_mask_array(['title' => $fake]);
ok($kept['title'] === $fake, 'sıradan değer maskelenmez (yanlış pozitif yok)');

/* =====================================================================
 * 4) MASKELEME - GERÇEKÇİ İSTEK
 * ===================================================================*/
section('4. gerçekçi bir istek gövdesi');

$request = [
    'email'            => 'admin@riskops.local',
    'password'         => 'CokGizliParola123',
    '_csrf'            => str_repeat('a', 64),
    'title'            => 'İnternete açık RDP',
    'remember'         => '1',
    'nested'           => [
        'current_password' => 'EskiParola',
        'new_password'     => 'YeniParola',
        'note'             => 'sıradan metin',
    ],
];

$out  = debug_mask_array($request);
$flat = debug_export($out);

ok($out['password'] === '***', 'password maskelendi');
ok($out['_csrf'] === '***', '_csrf maskelendi');
ok($out['nested']['current_password'] === '***', 'nested current_password maskelendi');
ok($out['nested']['new_password'] === '***', 'nested new_password maskelendi');
ok($out['title'] === 'İnternete açık RDP', 'title korundu');
ok($out['email'] === 'admin@riskops.local', 'email korundu');
ok($out['nested']['note'] === 'sıradan metin', 'nested note korundu');

foreach (['CokGizliParola123', 'EskiParola', 'YeniParola'] as $secret) {
    ok(!str_contains($flat, $secret), 'çıktıda görünmüyor: ' . $secret);
}

/* =====================================================================
 * 5) TOPLAYICI
 * ===================================================================*/
section('5. toplayıcı davranışı');

$before = count(debug_store()['queries']);

/* Gerçek bir sorgu çalıştır; kip açıksa kaydedilmeli, kapalıysa
   hiçbir şey birikmemeli. */
$st = db()->prepare('SELECT COUNT(*) FROM risks WHERE id > ?');
$st->execute([0]);
$st->fetchColumn();

$after   = count(debug_store()['queries']);
$recorded = $after - $before;

if (APP_DEBUG) {
    ok($recorded >= 1, 'sorgu kaydedildi', 'kaydedilen: ' . $recorded);

    $q = debug_store()['queries'][$after - 1];
    ok(str_contains($q['sql'], 'FROM risks'), 'SQL metni kaydedildi');
    ok($q['params'] === [0], 'parametreler kaydedildi', json_encode($q['params']));
    ok($q['ms'] >= 0, 'süre ölçüldü');
    /* Kaydedilen kaynak, db.php/db_debug.php degil, sorguyu ACAN
       dosya olmali - "bu sorguyu hangi sayfa calistirdi" sorusunun
       cevabi budur. */
    ok(str_starts_with($q['origin'], 'tools/debug_test.php:'),
       'çağıran dosya tespit edildi', $q['origin']);
    ok($q['error'] === null, 'hatasız sorguda error null');
} else {
    ok($recorded === 0, 'kip kapalıyken sorgu TOPLANMAZ', 'kaydedilen: ' . $recorded);
}

/* İşaret, not, döküm ve sayaçlar da aynı kurala uymalı. */
$m0 = count(debug_store()['marks']);
$n0 = count(debug_store()['notes']);
$d0 = count(debug_store()['dumps']);

debug_mark('test işareti');
debug_note('test', 'test notu', ['parola' => 'gizli']);
dbg(['parola' => 'gizli'], 'test dökümü');
debug_count('test sayacı');

$expected = APP_DEBUG ? 1 : 0;
ok(count(debug_store()['marks']) - $m0 === $expected, 'debug_mark() kipe uyuyor');
ok(count(debug_store()['notes']) - $n0 === $expected, 'debug_note() kipe uyuyor');
ok(count(debug_store()['dumps']) - $d0 === $expected, 'dbg() kipe uyuyor');

if (APP_DEBUG) {
    $note = debug_store()['notes'][$n0];
    ok($note['context']['parola'] === '***', 'not bağlamı maskelendi');

    $dump = debug_store()['dumps'][$d0];
    ok(!str_contains($dump['value'], 'gizli'), 'döküm maskelendi');
}

/* =====================================================================
 * 6) SÜREÖLÇER VE SAYAÇ
 * ===================================================================*/
section('6. süreölçer');

debug_timer_start('deneme');
usleep(3000);
debug_timer_stop('deneme');
debug_timer_start('deneme');
usleep(3000);
debug_timer_stop('deneme');

if (APP_DEBUG) {
    $t = debug_store()['timers']['deneme'] ?? null;
    ok($t !== null, 'süreölçer kaydedildi');
    ok(($t['calls'] ?? 0) === 2, 'iki çağrı sayıldı', (string)($t['calls'] ?? 0));
    ok(($t['total'] ?? 0) >= 5, 'süreler toplandı', number_format((float)($t['total'] ?? 0), 2) . ' ms');
    ok((debug_store()['counters']['test sayacı'] ?? 0) === 1, 'sayaç arttı');
} else {
    ok(!isset(debug_store()['timers']['deneme']), 'kip kapalıyken süreölçer yok');
}

/* =====================================================================
 * 7) BİÇİMLENDİRME
 * ===================================================================*/
section('7. biçimlendirme yardımcıları');

ok(debug_bytes(512) === '512 B', 'debug_bytes: bayt');
ok(str_contains(debug_bytes(2048), 'KB'), 'debug_bytes: KB');
ok(str_contains(debug_bytes(5242880), 'MB'), 'debug_bytes: MB');
ok(str_contains(debug_ms(12.3), 'ms'), 'debug_ms: milisaniye');
ok(str_contains(debug_ms(2500), 's'), 'debug_ms: saniye');

$sql = 'SELECT id, title FROM risks WHERE severity = ? ORDER BY id LIMIT 10';
$fmt = debug_format_sql($sql);
foreach (['id, title', 'risks', 'severity = ?', '10'] as $piece) {
    ok(str_contains($fmt, $piece), 'SQL biçimlendirmesi içeriği korudu: ' . $piece);
}
ok(substr_count($fmt, "\n") >= 3, 'SQL satırlara bölündü');

/* =====================================================================
 * 8) ÖZET
 * ===================================================================*/
section('8. özet');

$s = debug_summary();
foreach (['env', 'php', 'time_ms', 'memory_peak', 'query_count', 'query_ms', 'duplicates'] as $key) {
    ok(array_key_exists($key, $s), 'özet anahtarı var: ' . $key);
}
ok($s['env'] === APP_ENV, 'özet ortamı doğru');
ok($s['time_ms'] > 0, 'özet süresi pozitif');
ok($s['memory_peak'] > 0, 'özet belleği pozitif');

$flatSummary = json_encode($s, JSON_UNESCAPED_UNICODE);
if ($dbPass !== '' && strlen($dbPass) >= 6) {
    ok(!str_contains((string)$flatSummary, $dbPass), 'özette DB parolası yok');
}

/* =====================================================================
 * 9) DOSYA BÜTÜNLÜĞÜ
 * ===================================================================*/
section('9. dosya bütünlüğü');

$files = [
    'includes/debug.php',
    'includes/db_debug.php',
    'includes/partials/debug_toolbar.php',
    'errors/debug_exception.php',
    'assets/css/debug.css',
    'assets/js/debug.js',
    'tools/debug_report.php',
    'admin/debug/index.php',
    'admin/debug/toggle.php',
];
foreach ($files as $f) {
    ok(is_file(APP_ROOT . '/' . $f), 'dosya yerinde: ' . $f);
}

/* Araç çubuğu ve istisna sayfası satır içi <script> veya style=""
   KULLANMAMALI: ikisi de CSP tarafından bloklanır ve sessizce
   çalışmaz. */
/* Dosyanin TAMAMINA degil, BASILAN HTML'e bakilir: bu dosyalarin kendi
   aciklama satirlarinda "style=" ya da "<script>" gecmesi bir ihlal
   degildir. token_get_all() ile PHP kodu ve yorumlar ayiklanir. */
$inlineHtml = static function (string $path): string {
    $out = '';
    foreach (token_get_all((string)file_get_contents($path)) as $token) {
        if (is_array($token) && $token[0] === T_INLINE_HTML) {
            $out .= $token[1];
        }
    }

    return $out;
};

foreach (['includes/partials/debug_toolbar.php', 'errors/debug_exception.php'] as $f) {
    $html = $inlineHtml(APP_ROOT . '/' . $f);
    ok(!preg_match('/\sstyle\s*=\s*"/', $html), 'satır içi style yok: ' . $f);
    /* <style> BLOGU serbesttir: style_block() ona nonce veriyor.
       Yasak olan <script> blogu - script-src'de nonce yok, 'self' var. */
    ok(!preg_match('/<script(?![^>]*\bsrc=)/i', $html), 'satır içi <script> yok: ' . $f);
}

/* =====================================================================
 * SONUÇ
 * ===================================================================*/
echo "\n" . str_repeat('=', 72) . "\n";
printf("  SONUÇ:  %d başarılı,  %d başarısız   (kip: %s)\n",
    $pass, $fail, APP_DEBUG ? 'AÇIK' : 'kapalı');
echo str_repeat('=', 72) . "\n";

if (!APP_DEBUG) {
    echo "\n  Açık kiple de çalıştırın:  RISKOPS_DEBUG=1 php tools/debug_test.php\n\n";
}

exit($fail === 0 ? 0 : 1);
