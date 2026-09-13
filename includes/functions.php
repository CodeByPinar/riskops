<?php
declare(strict_types=1);

/**
 * RiskOps - Genel yardimci fonksiyonlar
 * /var/www/riskops/includes/functions.php
 */

/* =====================================================================
 * LOGLAMA VE HATA SONLANDIRMA
 * ===================================================================*/

/** Uygulama log dosyasina satir yazar. Asla exception firlatmaz. */
function app_log(string $level, string $message, array $context = []): void
{
    if (!is_dir(LOG_PATH)) {
        @mkdir(LOG_PATH, 0775, true);
    }
    $line = sprintf(
        "[%s] %s: %s%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
    );
    @file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

/** Istegi HTTP durum koduyla sonlandirir. Kullaniciya SQL/stack trace gostermez. */
function app_abort(int $status = 500, string $internalMessage = ''): never
{
    if ($internalMessage !== '') {
        app_log('error', "abort({$status}): {$internalMessage}");
    }
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "ABORT {$status} {$internalMessage}\n");
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
    }
    $page = APP_ROOT . '/errors/' . $status . '.php';
    if (is_file($page)) {
        require $page;
        exit;
    }
    $titles = [
        400 => 'Bad Request',
        403 => 'Bu sayfaya erişim yetkiniz yok.',
        404 => 'Aradığınız sayfa bulunamadı.',
        405 => 'Method Not Allowed',
        500 => 'An unexpected error occurred.',
    ];
    $msg = $titles[$status] ?? 'An unexpected error occurred.';
    /* Bu sayfa uygulama stil dosyasini YUKLEMEZ: hata, CSS'e ulasilamayan
       bir durumda da olusabilir. Stiller bu yuzden sayfanin icinde, ama
       satir ici OZNITELIK olarak degil - nonce tasiyan tek bir blokta.
       Satir ici oznitelik style-src 'unsafe-inline' gerektirirdi. */
    $css = '.rk-err{font:15px/1.6 system-ui,sans-serif;color:#1e293b;'
         . 'max-width:540px;margin:14vh auto;padding:0 24px}'
         . '.rk-err-code{font-size:46px;font-weight:700;color:#94a3b8}'
         . '.rk-err p{margin:8px 0 20px}'
         . '.rk-err a{color:#1d4ed8;text-decoration:none}';

    echo '<!doctype html><meta charset="utf-8"><title>' . $status . '</title>'
       . style_block($css)
       . '<div class="rk-err">'
       . '<div class="rk-err-code">' . $status . '</div>'
       . '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>'
       . '<a href="' . htmlspecialchars(BASE_PATH . '/', ENT_QUOTES, 'UTF-8') . '">'
       . '&larr; Ana sayfaya dön</a></div>';
    exit;
}

/* =====================================================================
 * CSP NONCE
 * ===================================================================*/

/**
 * Bu istek için tek kullanımlık CSP nonce değeri.
 *
 * DİKKAT: nonce YALNIZCA <style> ve <script> BLOKLARINDA işe yarar.
 * style="" ÖZNİTELİĞİ için geçerli DEĞİLDİR (onun için 'unsafe-hashes'
 * gerekirdi). Yeni kod yazarken style="" EKLEMEYİN - sessizce
 * uygulanmaz, hata da görünmez.
 *
 * Gerekçe: docs/architecture/0003-csp-unsafe-inline-kaldirildi.md
 */
function csp_nonce(): string
{
    static $nonce = null;

    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }

    return $nonce;
}

/** Nonce taşıyan bir <style> bloğu üretir. */
function style_block(string $css): string
{
    if (trim($css) === '') {
        return '';
    }

    return '<style nonce="' . e(csp_nonce()) . '">' . $css . '</style>';
}

/* =====================================================================
 * ÇIKTI GÜVENLİĞİ (XSS)
 * ===================================================================*/

/** HTML'e basilan HER kullanıcı verisi bu fonksiyondan gecmelidir. */
function e(mixed $value): string
{
    if ($value === null || is_bool($value) || is_array($value)) {
        $value = is_bool($value) ? ($value ? '1' : '0') : (is_array($value) ? '' : '');
    }
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* =====================================================================
 * URL VE YONLENDIRME
 * ===================================================================*/

function url(string $path = '/'): string
{
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }
    return BASE_PATH . $path;
}

function redirect(string $path, int $status = 302): never
{
    if (!headers_sent()) {
        header('Location: ' . url($path), true, $status);
    }
    exit;
}

function current_path(): string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $p = strtok($uri, '?');
    return $p === false ? '/' : $p;
}

/**
 * Mevcut query string'i koruyarak yeni bir URL üretir.
 * Filtre + siralama + sayfalama linkleri için kullanılır.
 */
function query_url(array $overrides = [], array $remove = []): string
{
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        $params[$k] = $v;
    }
    foreach ($remove as $k) {
        unset($params[$k]);
    }
    $params = array_filter(
        $params,
        static fn($v) => $v !== '' && $v !== null && !is_array($v)
    );
    $qs = http_build_query($params);
    return current_path() . ($qs !== '' ? '?' . $qs : '');
}

/* =====================================================================
 * GIRDI OKUMA VE DOGRULAMA  (server-side validation)
 * ===================================================================*/

function input(string $key, ?string $default = null): ?string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    if ($v === null || is_array($v)) {
        return $default;
    }
    $v = trim((string)$v);
    return $v === '' ? $default : $v;
}

function input_int(string $key, ?int $default = null): ?int
{
    $v = input($key);
    if ($v === null) {
        return $default;
    }
    $parsed = filter_var($v, FILTER_VALIDATE_INT);
    return $parsed === false ? $default : (int)$parsed;
}

function input_int_range(string $key, int $min, int $max, ?int $default = null): ?int
{
    $v = input_int($key);
    return ($v === null || $v < $min || $v > $max) ? $default : $v;
}

/** Deger yalnızca izin verilen listedeyse kabul edilir (ENUM guvenligi). */
function input_enum(string $key, array $allowed, ?string $default = null): ?string
{
    $v = input($key);
    return ($v !== null && in_array($v, $allowed, true)) ? $v : $default;
}

/** Yalnızca gerçek bir YYYY-MM-DD tarihi kabul eder. */
function input_date(string $key, ?string $default = null): ?string
{
    $v = input($key);
    if ($v === null) {
        return $default;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return ($d !== false && $d->format('Y-m-d') === $v) ? $v : $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/* =====================================================================
 * TARIH / METIN FORMATLAMA
 * ===================================================================*/

/**
 * Son N takvim ayini eskiden yeniye "Y-m" anahtari olarak dondurur.
 *
 * NEDEN VAR: strtotime("-N month") GUN TASMASINI KIRMAZ.
 * Ayin 29-31'inde, hedef ayda o gun yoksa sonuc bir sonraki aya tasar:
 *
 *     2026-05-31 -1 month  ->  "31 Nisan" yok  ->  2026-05-01
 *
 * Ayni Y-m anahtari iki kez uretilir, dizi anahtari cakisir ve pencere
 * kisalir. Olculen etki: 2026-05-31 tabaninda 12 aylik pencere 7 aya,
 * 6 aylik pencere 4 aya duser; kaybolan aylarin riskleri hicbir
 * sayacta gorunmez. Ayin yalnizca 3 gununde, sessizce yanlis rakam.
 *
 * COZUM: ay aritmetigi her zaman ayin 1'ine sabitlenir. 1. gunden
 * cikarilan ay asla tasmaz.
 *
 * @param int      $count  kac ay (1-120 arasina kirpilir)
 * @param int|null $baseTs taban zaman damgasi; null ise simdi
 * @return string[] ornek: ['2025-06', '2025-07', ... , '2026-05']
 */
function recent_months(int $count, ?int $baseTs = null): array
{
    $count = max(1, min(120, $count));
    $base  = $baseTs ?? time();

    // Ayin 1'ine, gece yarisina sabitle: tasma buradan sonra imkansiz.
    $anchor = new DateTimeImmutable(date('Y-m-01 00:00:00', $base));

    $months = [];
    for ($i = $count - 1; $i >= 0; $i--) {
        $months[] = $anchor->sub(new DateInterval('P' . $i . 'M'))->format('Y-m');
    }

    return $months;
}

function format_date(?string $value, string $empty = '-'): string
{
    if ($value === null || $value === '' || str_starts_with($value, '0000')) {
        return $empty;
    }
    $ts = strtotime($value);
    return $ts === false
        ? $empty
        : date((string)setting('date_format', FALLBACK_DATE_FORMAT), $ts);
}

function format_datetime(?string $value, string $empty = '-'): string
{
    if ($value === null || $value === '' || str_starts_with($value, '0000')) {
        return $empty;
    }
    $ts = strtotime($value);
    return $ts === false
        ? $empty
        : date((string)setting('datetime_format', FALLBACK_DATETIME_FORMAT), $ts);
}

/** Bugunden itibaren kac gün kaldi? Gecmisse negatif döner. */
function days_until(?string $date): ?int
{
    if ($date === null || $date === '') {
        return null;
    }
    $t = strtotime($date);
    if ($t === false) {
        return null;
    }
    return (int)floor(($t - strtotime(date('Y-m-d'))) / 86400);
}

function str_limit(?string $text, int $limit = 80): string
{
    $text = trim((string)$text);
    return mb_strlen($text, 'UTF-8') <= $limit
        ? $text
        : mb_substr($text, 0, $limit - 1, 'UTF-8') . '...';
}

/* =====================================================================
 * ISTEMCI BILGISI
 * ===================================================================*/

/**
 * DIKKAT: X-Forwarded-For bilerek DIKKATE ALINMAZ.
 * Proxy arkasina alinirsa burasi bilincli olarak guncellenmelidir;
 * aksi halde saldirgan audit log'daki IP'yi sahteleyebilir.
 */
function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45);
}

function client_agent(): string
{
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'cli'), 0, 255);
}

/* =====================================================================
 * FLASH MESAJLAR
 * ===================================================================*/

/** $type: success | error | warning | info */
function flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** Mesajlari döndürür VE siler. Yalnızca layout icinde cagrilmali. */
function flash_take(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($messages) ? $messages : [];
}

/* =====================================================================
 * ESKI FORM GIRDILERI  (validation hatasindan sonra formu doldurmak için)
 * ===================================================================*/

function old_set(array $data, array $except = ['password', 'password_confirm', '_token']): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    foreach ($except as $k) {
        unset($data[$k]);
    }
    $_SESSION['_old'] = array_filter($data, static fn($v) => !is_array($v));
}

function old(string $key, string $default = ''): string
{
    $store = $GLOBALS['_old_input'] ?? [];
    return isset($store[$key]) ? (string)$store[$key] : $default;
}

/** Bir önceki istekte bu alan için değer tasindi mi? */
function has_old(string $key): bool
{
    $store = $GLOBALS['_old_input'] ?? [];
    return is_array($store) && array_key_exists($key, $store);
}

/* =====================================================================
 * ALAN BAZLI DOGRULAMA HATALARI
 *
 * POST handler dogrulamada takilirsa errors_set() ile hatalari oturuma
 * yazar ve forma geri yönlendirir. Form sayfasi field_error() ile
 * her alanin altinda ilgili mesaji gösterir.
 * ===================================================================*/

/** @param array<string,string> $errors alan adı => mesaj */
function errors_set(array $errors): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['_errors'] = $errors;
    }
}

function field_error(string $key): string
{
    $store = $GLOBALS['_errors'] ?? [];
    return is_array($store) ? (string)($store[$key] ?? '') : '';
}

function has_errors(): bool
{
    return !empty($GLOBALS['_errors']);
}

/* =====================================================================
 * SAYFALAMA
 * ===================================================================*/

function paginate(int $total, int $perPage, int $currentPage): array
{
    $perPage = max(1, $perPage);
    $pages   = max(1, (int)ceil($total / $perPage));
    $current = min(max(1, $currentPage), $pages);
    $offset  = ($current - 1) * $perPage;

    return [
        'total'    => $total,
        'per_page' => $perPage,
        'pages'    => $pages,
        'current'  => $current,
        'offset'   => $offset,
        'from'     => $total > 0 ? $offset + 1 : 0,
        'to'       => min($offset + $perPage, $total),
        'has_prev' => $current > 1,
        'has_next' => $current < $pages,
    ];
}
