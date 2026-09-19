<?php

declare(strict_types=1);

/**
 * RiskOps - Hata ayıklama kipi (toplayıcı katman)
 * /var/www/riskops/includes/debug.php
 *
 * NE İŞE YARAR
 * ------------
 * Bir istek boyunca olup biteni toplar: çalışan SQL sorguları ve
 * süreleri, zaman çizelgesi işaretleri, bellek kullanımı, istek ve
 * oturum içeriği, uygulama notları. Toplanan veri sayfanın altındaki
 * araç çubuğunda gösterilir (bkz. includes/partials/debug_toolbar.php)
 * ve ayrıca storage/logs/debug.log dosyasına tek satır özet yazılır.
 *
 * NEDEN BÖYLE
 * -----------
 * 1) KAPALIYKEN HİÇBİR MALİYETİ YOK.
 *    debug_enabled() false ise buradaki her fonksiyon ilk satırında
 *    döner, PDO alt sınıfları hiç yüklenmez, araç çubuğu hiç basılmaz.
 *    Üretimde davranış bayrak eklenmeden önceki haliyle aynıdır.
 *
 * 2) SIR SIZDIRMAZ.
 *    Araç çubuğu istek/oturum içeriğini gösterir; bunların içinde
 *    parola, CSRF jetonu, oturum kimliği ve veritabanı parolası
 *    bulunabilir. debug_mask_array() bunları anahtar adına bakarak
 *    maskeler, ayrıca veritabanı parolasının kendisi değerce aranır.
 *    Maskeleme testle doğrulanır (tools/debug_test.php).
 *
 * 3) ÜRETİMDE KAZAYLA AÇILAMAZ.
 *    APP_DEBUG, APP_ENV='production' iken ikinci bir ortam değişkeni
 *    olmadan açılmaz (bkz. config/config.php). Açılsa bile araç çubuğu
 *    yalnızca admin rolüne gösterilir.
 *
 * 4) CSP'Yİ BOZMAZ.
 *    Araç çubuğunun stili ve betiği harici dosyadadır
 *    (assets/css/debug.css, assets/js/debug.js), satır içi <script>
 *    veya style="" kullanılmaz. Panellerin içeriği sunucuda HTML olarak
 *    basılır; JS'e veri taşınmaz.
 */

/* =====================================================================
 * ANAHTAR
 * ===================================================================*/

/** Hata ayıklama kipi açık mı? Tek doğruluk kaynağı. */
function debug_enabled(): bool
{
    return defined('APP_DEBUG') && APP_DEBUG === true;
}

/**
 * Araç çubuğu bu isteğe basılsın mı?
 *
 * Açık olmak yetmez:
 *   - CLI'da araç çubuğu diye bir şey yok.
 *   - Üretimde yalnızca admin görür (kip yine de açılmış olmalı).
 *   - Kullanıcı ?rkdebug=off ile gizlemiş olabilir (yalnızca kozmetik,
 *     ekran görüntüsü alırken işe yarar).
 */
function debug_visible(): bool
{
    if (!debug_enabled() || PHP_SAPI === 'cli') {
        return false;
    }
    if (APP_ENV === 'production' && auth_role() !== ROLE_ADMIN) {
        return false;
    }

    return empty($_SESSION['_rk_debug_hidden']);
}

/**
 * ?rkdebug=off / ?rkdebug=on
 *
 * Kipi AÇMAZ veya KAPATMAZ - yalnızca araç çubuğunun görünürlüğünü
 * ayarlar. Kipin kendisi ortam değişkenindedir ve bir istek
 * parametresiyle değiştirilemez; aksi halde herkes üretimde hata
 * ayıklama çıktısı isteyebilirdi.
 */
function debug_handle_toggle(): void
{
    if (!debug_enabled() || !isset($_GET['rkdebug'])) {
        return;
    }
    $_SESSION['_rk_debug_hidden'] = ($_GET['rkdebug'] === 'off');
}

/* =====================================================================
 * GEÇİCİ AÇMA BAYRAĞI  (storage/debug.flag)
 *
 * Yönetim ekranındaki (/admin/debug/) düğmeler bu dosyayı yazar ve
 * siler. Dosyanın OKUNMASI config.php içinde, henüz hiçbir fonksiyon
 * yüklenmemişken yapılır (bkz. APP_DEBUG_FROM_PANEL); burada yalnızca
 * yazma, silme ve durum sorgulama var.
 *
 * NEDEN SÜRELİ
 * ------------
 * Hata ayıklama kipinin en büyük riski açık kalmasıdır. Bayrak bir
 * BİTİŞ ZAMANI taşır; süresi dolduğunda dosya silinmemiş olsa bile
 * yok sayılır. "Açık unutuldu" durumu bu yüzden oluşamaz.
 *
 * NEDEN OTURUMDA DEĞİL
 * --------------------
 * Kip, sorgu kaydedici PDO sarmalayıcılarını devreye sokuyor ve bu
 * karar bağlantı kurulurken, oturum açılmadan önce verilmek zorunda.
 * Ayrıca cron işleri ve CLI araçları da aynı kipi görmelidir.
 * ===================================================================*/

/**
 * Bayrak dosyasının içeriği. Yoksa, bozuksa veya süresi dolmuşsa null.
 *
 * @return array{until:int, at:int, by:?int, by_name:string, minutes:int}|null
 */
function debug_flag_read(): ?array
{
    $raw = @file_get_contents(DEBUG_FLAG_FILE);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || (int)($data['until'] ?? 0) <= time()) {
        return null;
    }

    return [
        'until'   => (int)$data['until'],
        'at'      => (int)($data['at'] ?? 0),
        'by'      => isset($data['by']) ? (int)$data['by'] : null,
        'by_name' => (string)($data['by_name'] ?? ''),
        'minutes' => (int)($data['minutes'] ?? 0),
    ];
}

/** Bayrak şu an geçerli mi? */
function debug_flag_active(): bool
{
    return debug_flag_read() !== null;
}

/** Bayrağın bitmesine kalan saniye (geçerli değilse 0). */
function debug_flag_remaining(): int
{
    $f = debug_flag_read();

    return $f === null ? 0 : max(0, $f['until'] - time());
}

/**
 * Kipi $minutes dakikalığına açar.
 *
 * Yalnızca /admin/debug/toggle.php tarafından, POST + CSRF + admin
 * kontrolünden SONRA çağrılır. Yetki denetimi burada DEĞİL, orada
 * yapılır - bu fonksiyon yalnızca dosyayı yazar.
 */
function debug_flag_enable(int $minutes): bool
{
    $allowed = array_keys(DEBUG_FLAG_DURATIONS);
    if (!in_array($minutes, $allowed, true)) {
        $minutes = (int)$allowed[0];
    }

    if (!is_dir(STORAGE_PATH)) {
        @mkdir(STORAGE_PATH, 0775, true);
    }

    $payload = json_encode([
        'until'   => time() + ($minutes * 60),
        'at'      => time(),
        'by'      => auth_id(),
        'by_name' => auth_name(),
        'minutes' => $minutes,
    ], JSON_UNESCAPED_UNICODE);

    return @file_put_contents(DEBUG_FLAG_FILE, $payload, LOCK_EX) !== false;
}

/** Kipi hemen kapatır (dosyayı siler). */
function debug_flag_disable(): bool
{
    if (!is_file(DEBUG_FLAG_FILE)) {
        return true;
    }

    return @unlink(DEBUG_FLAG_FILE);
}

/**
 * Kip hangi yoldan açık?  'ortam' | 'panel' | 'ortam+panel' | 'kapalı'
 *
 * Ayrım önemli: panelden açılan kip düğmeyle kapatılabilir, ortam
 * değişkeninden gelen kapatılamaz - onun için sunucu yapılandırması
 * değişmelidir. Yönetim ekranı bunu açıkça söyler.
 */
function debug_source(): string
{
    $env   = defined('APP_DEBUG_FROM_ENV')   && APP_DEBUG_FROM_ENV;
    $panel = defined('APP_DEBUG_FROM_PANEL') && APP_DEBUG_FROM_PANEL;

    return match (true) {
        $env && $panel => 'ortam+panel',
        $env           => 'ortam',
        $panel         => 'panel',
        default        => 'kapalı',
    };
}

/** "1 sa 23 dk" biçiminde kalan süre. */
function debug_human_duration(int $seconds): string
{
    if ($seconds <= 0) {
        return '-';
    }
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);

    if ($h > 0) {
        return $h . ' sa ' . $m . ' dk';
    }

    return $m > 0 ? $m . ' dk' : ($seconds . ' sn');
}

/* =====================================================================
 * TOPLAYICI
 * ===================================================================*/

/**
 * İstek boyunca biriken veri. Referansla döner ki çağıran yazabilsin.
 *
 * @return array{
 *     queries: list<array<string, mixed>>,
 *     marks: list<array<string, mixed>>,
 *     notes: list<array<string, mixed>>,
 *     dumps: list<array<string, mixed>>,
 *     timers: array<string, array<string, float|int>>,
 *     counters: array<string, int>
 * }
 */
function &debug_store(): array
{
    static $store = [
        'queries'  => [],   // çalışan SQL'ler
        'marks'    => [],   // zaman çizelgesi
        'notes'    => [],   // uygulama notları
        'dumps'    => [],   // dbg() ile basılan değerler
        'timers'   => [],   // adlandırılmış süreölçerler
        'counters' => [],   // debug_count() sayaçları
    ];

    return $store;
}

/** İsteğin başlangıcından bu yana geçen süre (ms). */
function debug_elapsed_ms(): float
{
    $start = (float)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));

    return (microtime(true) - $start) * 1000;
}

/**
 * Zaman çizelgesine bir işaret koyar.
 *
 * Kullanım:  debug_mark('rapor sorgusu bitti');
 */
function debug_mark(string $label): void
{
    if (!debug_enabled()) {
        return;
    }
    $store = &debug_store();
    $store['marks'][] = [
        'label'  => $label,
        'at_ms'  => debug_elapsed_ms(),
        'memory' => memory_get_usage(true),
    ];
}

/**
 * Kanal bazlı not. app_log()'un araç çubuğunda görünen hafif kardeşi:
 * diske yazmaz, yalnızca bu isteğin çıktısında durur.
 *
 * @param array<string, mixed> $context
 */
function debug_note(string $channel, string $message, array $context = []): void
{
    if (!debug_enabled()) {
        return;
    }
    $store = &debug_store();
    $store['notes'][] = [
        'channel' => $channel,
        'message' => $message,
        'context' => debug_mask_array($context),
        'at_ms'   => debug_elapsed_ms(),
        'origin'  => debug_caller(),
    ];
}

/**
 * Bir değeri araç çubuğuna basar.
 *
 * var_dump() YERİNE BUNU KULLANIN: var_dump çıktıyı sayfanın ortasına
 * basar, düzeni bozar, üretimde unutulursa görünür kalır. dbg() kapalı
 * kipte hiçbir şey yapmaz.
 */
function dbg(mixed $value, string $label = ''): void
{
    if (!debug_enabled()) {
        return;
    }
    $store = &debug_store();
    $store['dumps'][] = [
        'label'  => $label,
        'value'  => debug_export($value),
        'at_ms'  => debug_elapsed_ms(),
        'origin' => debug_caller(),
    ];
}

/** Adlandırılmış süreölçer başlatır. */
function debug_timer_start(string $name): void
{
    if (!debug_enabled()) {
        return;
    }
    $store = &debug_store();
    $store['timers'][$name]['started'] = microtime(true);
    $store['timers'][$name]['total']   = $store['timers'][$name]['total'] ?? 0.0;
    $store['timers'][$name]['calls']   = ($store['timers'][$name]['calls'] ?? 0) + 1;
}

/** Süreölçeri durdurur; toplam süreye ekler (birden fazla kez çağrılabilir). */
function debug_timer_stop(string $name): void
{
    if (!debug_enabled()) {
        return;
    }
    $store = &debug_store();
    if (!isset($store['timers'][$name]['started'])) {
        return;
    }
    $store['timers'][$name]['total'] += (microtime(true) - $store['timers'][$name]['started']) * 1000;
    unset($store['timers'][$name]['started']);
}

/** Basit sayaç: kaç kez buradan geçtik? */
function debug_count(string $name, int $by = 1): void
{
    if (!debug_enabled()) {
        return;
    }
    $store = &debug_store();
    $store['counters'][$name] = ($store['counters'][$name] ?? 0) + $by;
}

/**
 * Çalışan bir SQL sorgusunu kaydeder.
 *
 * includes/db_debug.php içindeki PDO alt sınıfları tarafından çağrılır;
 * uygulama kodunun bunu çağırması gerekmez.
 *
 * @param array<array-key, mixed> $params
 */
function debug_record_query(string $sql, array $params, float $ms, int $rows = -1, ?string $error = null): void
{
    if (!debug_enabled()) {
        return;
    }
    $store = &debug_store();
    $store['queries'][] = [
        'sql'    => $sql,
        'params' => debug_mask_array($params),
        'ms'     => $ms,
        'rows'   => $rows,
        'error'  => $error,
        'at_ms'  => debug_elapsed_ms(),
        'origin' => debug_caller(),
        // Yinelenen sorgu tespiti için boşlukları normalleştirilmiş hâli
        'norm'   => preg_replace('/\s+/', ' ', trim($sql)),
    ];
}

/**
 * Çağrıyı YAPAN uygulama satırını bulur.
 *
 * db.php / debug.php / db_debug.php çerçeveleri atlanır; aranan şey
 * "bu sorguyu hangi sayfa açtı" sorusunun cevabıdır.
 */
function debug_caller(): string
{
    /* VERİTABANI SARMALAYICILARI ATLANIR
       Aranan şey sorguyu AÇAN uygulama satırı; "query.php:79" yazan
       bir sorgu günlüğü hiçbir şey anlatmaz çünkü her sorgu oradan
       geçer. Sorgu katmanı (ADR-0011) araya girdiğinde bu liste
       güncellenmezse özellik sessizce işe yaramaz hâle gelir —
       tools/debug_test.php bunu sınıyor. */
    $skip = [
        'includes/debug.php',
        'includes/db_debug.php',
        'includes/db.php',
        'includes/query.php',
    ];

    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 18) as $frame) {
        $file = $frame['file'] ?? '';
        if ($file === '' || !str_starts_with($file, APP_ROOT)) {
            continue;
        }
        $rel = ltrim(substr($file, strlen(APP_ROOT)), '/\\');
        $rel = str_replace('\\', '/', $rel);
        if (in_array($rel, $skip, true)) {
            continue;
        }

        return $rel . ':' . (int)($frame['line'] ?? 0);
    }

    return '-';
}

/* =====================================================================
 * MASKELEME
 *
 * Araç çubuğu $_GET / $_POST / $_SESSION / $_SERVER içeriğini gösterir.
 * Bunların içinde sır olabilir; gösterilmeden önce buradan geçerler.
 * ===================================================================*/

/** Bu anahtar adı bir sır taşıyor olabilir mi? */
function debug_mask_key(string $key): bool
{
    return (bool)preg_match(
        '/(pass|parol|secret|token|csrf|_hash|hash$|api[_-]?key|authorizat|cookie|sess|salt|private|credential)/i',
        $key
    );
}

/**
 * Diziyi yinelemeli maskeler.
 *
 * İki katman:
 *   1) Anahtar adı şüpheliyse değer maskelenir.
 *   2) Değer, veritabanı parolasıyla veya oturum kimliğiyle birebir
 *      aynıysa anahtar adı ne olursa olsun maskelenir. Birinci katman
 *      anahtarın adına güvenir; bu katman değere bakar.
 *
 * @param array<array-key, mixed> $data
 * @return array<array-key, mixed>
 */
function debug_mask_array(array $data, int $depth = 0): array
{
    if ($depth > 6) {
        return ['...' => '(derinlik sınırı)'];
    }

    $out = [];
    foreach ($data as $key => $value) {
        $k = (string)$key;

        if (is_array($value)) {
            $out[$k] = debug_mask_array($value, $depth + 1);
            continue;
        }
        if (is_object($value)) {
            $out[$k] = '(' . get_class($value) . ')';
            continue;
        }
        /* Anahtar adi supheli olsa bile bool ve 0/1 bir sir TASIYAMAZ.
           Bunlari maskelemek yalnizca araci korlestiriyordu: ornegin
           oturumdaki must_change_password bayragi "***" gorunuyor ve
           "neden parola degistirme ekranina dusuyorum" sorusu araç
           çubuğundan cevaplanamiyordu. */
        if (is_bool($value) || (is_int($value) && ($value === 0 || $value === 1))) {
            $out[$k] = $value;
            continue;
        }

        if (debug_mask_key($k) || debug_is_secret_value($value)) {
            $out[$k] = '***';
            continue;
        }

        $out[$k] = $value;
    }

    return $out;
}

/**
 * Değerin kendisi bilinen bir sır mı?
 *
 * Anahtar adı masum olsa da (örn. 'p', 'v') değer veritabanı parolası
 * olabilir. Karşılaştırma hash_equals ile yapılır.
 */
function debug_is_secret_value(mixed $value): bool
{
    if (!is_string($value) || $value === '' || strlen($value) < 6) {
        return false;
    }

    static $secrets = null;
    if ($secrets === null) {
        $secrets = [];
        $file = CONFIG_PATH . '/database.php';
        if (is_file($file)) {
            /** @var array<string, mixed> $cfg */
            $cfg = require $file;
            foreach (['pass', 'user'] as $k) {
                if (is_string($cfg[$k] ?? null) && $cfg[$k] !== '') {
                    $secrets[] = $cfg[$k];
                }
            }
        }
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
            $secrets[] = session_id();
        }
        if (function_exists('csrf_token')) {
            $secrets[] = csrf_token();
        }
    }

    foreach ($secrets as $s) {
        if (hash_equals($s, $value)) {
            return true;
        }
    }

    return false;
}

/** Bir değeri araç çubuğunda gösterilebilir metne çevirir. */
function debug_export(mixed $value, int $depth = 0): string
{
    if ($value === null) {
    return 'null';
    }
    if (is_bool($value)) {
    return $value ? 'true' : 'false';
    }
    if (is_int($value) || is_float($value)) {
    return (string)$value;
    }
    if (is_string($value)) {
    return '"' . (mb_strlen($value) > 2000 ? mb_substr($value, 0, 2000) . '…' : $value) . '"';
    }
    if (is_object($value)) {
    return '(' . get_class($value) . ')';
    }

    if (is_array($value)) {
        if ($depth > 4) {
            return '[…]';
        }
        $parts = [];
        $n = 0;
        foreach (debug_mask_array($value) as $k => $v) {
            if (++$n > 50) {
            $parts[] = '…';
            break;
            }
            $parts[] = $k . ' => ' . debug_export($v, $depth + 1);
        }

        return '[' . implode(', ', $parts) . ']';
    }

    return '(' . gettype($value) . ')';
}

/* =====================================================================
 * ÖZET
 * ===================================================================*/

/**
 * Araç çubuğunun ve debug.log satırının okuduğu derlenmiş özet.
 *
 * @return array<string, mixed>
 */
function debug_summary(): array
{
    $store   = &debug_store();
    $queries = $store['queries'];

    $totalMs = 0.0;
    $slow    = 0;
    $failed  = 0;
    $byNorm  = [];

    foreach ($queries as $q) {
        $totalMs += $q['ms'];
        if ($q['ms'] >= DEBUG_SLOW_QUERY_MS) {
        $slow++;
        }
        if ($q['error'] !== null) {
        $failed++;
        }
        $byNorm[$q['norm']] = ($byNorm[$q['norm']] ?? 0) + 1;
    }

    /* Yinelenen sorgu = aynı SQL birden fazla kez.
       Genellikle N+1 probleminin işaretidir: döngü içinde sorgu. */
    $duplicates = array_filter($byNorm, static fn (int $n): bool => $n > 1);
    arsort($duplicates);

    return [
        'env'          => APP_ENV,
        'debug'        => true,
        'demo'         => APP_DEMO,
        'php'          => PHP_VERSION,
        'sapi'         => PHP_SAPI,
        'locale'       => function_exists('locale') ? locale() : '-',
        'user'         => auth_check() ? (auth_name() . ' (' . (string)auth_role() . ')') : 'anonim',
        'user_id'      => auth_id(),
        'method'       => $_SERVER['REQUEST_METHOD'] ?? '-',
        'path'         => debug_request_path(),
        'status'       => http_response_code() ?: 200,
        'time_ms'      => debug_elapsed_ms(),
        'memory'       => memory_get_usage(true),
        'memory_peak'  => memory_get_peak_usage(true),
        'memory_limit' => (string)ini_get('memory_limit'),
        'includes'     => count(get_included_files()),
        'query_count'  => count($queries),
        'query_ms'     => $totalMs,
        'query_slow'   => $slow,
        'query_failed' => $failed,
        'duplicates'   => $duplicates,
        'mark_count'   => count($store['marks']),
        'note_count'   => count($store['notes']),
        'dump_count'   => count($store['dumps']),
    ];
}

/** İsteğin yolu (sorgu dizesi dâhil, maskelenmiş). */
function debug_request_path(): string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if ($uri === '') {
        return PHP_SAPI === 'cli' ? '(cli)' : '-';
    }
    [$path, $qs] = array_pad(explode('?', $uri, 2), 2, '');
    if ($qs === '') {
        return $path;
    }

    parse_str($qs, $parsed);

    return $path . '?' . http_build_query(debug_mask_array($parsed));
}

/* =====================================================================
 * ÇIKTI KANALLARI
 * ===================================================================*/

/**
 * Ölçümleri yanıt başlığına da koyar.
 *
 * NEDEN GEREKLİ: CSV dışa aktarma, /api/* uçları ve yönlendirmeler HTML
 * basmaz; araç çubuğu oralarda görünemez. Başlık her yanıtta vardır,
 * tarayıcının ağ sekmesinden okunur.
 */
function debug_headers(): void
{
    if (!debug_enabled() || headers_sent() || PHP_SAPI === 'cli') {
        return;
    }
    $s = debug_summary();
    header(sprintf(
        'X-RiskOps-Debug: time=%.1fms; memory=%s; queries=%d; query_time=%.1fms; slow=%d',
        $s['time_ms'],
        debug_bytes($s['memory_peak']),
        $s['query_count'],
        $s['query_ms'],
        $s['query_slow']
    ));
}

/**
 * İstek biterken debug.log dosyasına tek satır özet yazar.
 *
 * app.log'a KARIŞMAZ: o dosya uyarı ve hataların kalıcı kaydıdır,
 * her isteğin ölçümüyle şişmemelidir.
 */
function debug_shutdown(): void
{
    if (!debug_enabled()) {
        return;
    }

    $s = debug_summary();

    /* Varsayılan: her istek yazılır (yukarıdaki yorum, config.php).
       RISKOPS_DEBUG_LOG_ONLY_SLOW=1 ise yalnızca dikkat çekenler. */
    if (DEBUG_LOG_ONLY_SLOW) {
        $interesting = $s['time_ms'] >= DEBUG_LOG_MIN_MS
                    || $s['query_count'] >= DEBUG_LOG_MIN_QUERIES
                    || $s['query_slow'] > 0
                    || $s['query_failed'] > 0
                    || $s['duplicates'] !== [];

        if (!$interesting) {
            return;
        }
    }

    $line = sprintf(
        "[%s] %s %s -> %d | %.1fms | %s | %d sorgu (%.1fms)%s%s%s\n",
        date('Y-m-d H:i:s'),
        $s['method'],
        $s['path'],
        $s['status'],
        $s['time_ms'],
        debug_bytes($s['memory_peak']),
        $s['query_count'],
        $s['query_ms'],
        $s['query_slow'] ? ' | YAVAS:' . $s['query_slow'] : '',
        $s['query_failed'] ? ' | HATALI:' . $s['query_failed'] : '',
        $s['duplicates'] ? ' | YINELENEN:' . count($s['duplicates']) : ''
    );

    if (!is_dir(LOG_PATH)) {
        @mkdir(LOG_PATH, 0775, true);
    }
    @file_put_contents(DEBUG_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

/* =====================================================================
 * BİÇİMLENDİRME YARDIMCILARI  (araç çubuğu ve CLI ortak kullanır)
 * ===================================================================*/

function debug_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
    return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
    return number_format($bytes / 1024, 0) . ' KB';
    }

    return $bytes . ' B';
}

function debug_ms(float $ms): string
{
    return $ms >= 1000
        ? number_format($ms / 1000, 2) . ' s'
        : number_format($ms, 1) . ' ms';
}

/**
 * SQL'i okunur hâle getirir: anahtar kelimeler satır başına gelir.
 *
 * Sözdizimi renklendirmesi YOK - o, SQL'i HTML olarak yorumlamayı
 * gerektirirdi. Burada yapılan tek şey satır kırmaktır; çıktı yine
 * e() ile kaçışlanarak basılır.
 */
function debug_format_sql(string $sql): string
{
    $sql = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;

    return preg_replace(
        '/\b(SELECT|FROM|WHERE|LEFT JOIN|RIGHT JOIN|INNER JOIN|JOIN|GROUP BY|ORDER BY|HAVING|LIMIT|OFFSET|UNION|INSERT INTO|VALUES|UPDATE|SET|DELETE FROM|ON DUPLICATE KEY UPDATE|FOR UPDATE)\b/i',
        "\n$1",
        $sql
    ) ?? $sql;
}
