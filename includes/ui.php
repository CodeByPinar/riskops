<?php
declare(strict_types=1);

/**
 * RiskOps - Arayuz yardimcilari (rozet, skor, boş durum)
 * /var/www/riskops/includes/ui.php
 *
 * Buradaki fonksiyonlarin TAMAMI e() ile kacislanmis HTML döndürür.
 */

function severity_badge(?string $severity, bool $withIcon = false): string
{
    if ($severity === null || $severity === '') {
        return '<span class="rk-badge sev-none">-</span>';
    }
    $icon = '';
    if ($withIcon) {
        $icon = match ($severity) {
            'Critical' => '<i class="bi bi-exclamation-octagon-fill"></i>',
            'High'     => '<i class="bi bi-exclamation-triangle-fill"></i>',
            'Medium'   => '<i class="bi bi-dash-circle-fill"></i>',
            'Low'      => '<i class="bi bi-check-circle-fill"></i>',
            default    => '',
        };
    }
    return '<span class="rk-badge ' . severity_class($severity) . '">' . $icon . e($severity) . '</span>';
}

function status_badge(?string $status): string
{
    if ($status === null || $status === '') {
        return '<span class="rk-badge st-none">-</span>';
    }
    return '<span class="rk-badge ' . status_class($status) . '">' . e($status) . '</span>';
}

function priority_badge(?string $priority): string
{
    if ($priority === null || $priority === '') {
        return '<span class="rk-badge pr-none">-</span>';
    }
    return '<span class="rk-badge ' . priority_class($priority) . '">' . e($priority) . '</span>';
}

/** Skoru severity rengiyle birlikte kutu icinde gösterir. */
function score_chip(?int $score, ?string $severity = null): string
{
    if ($score === null) {
        return '<span class="rk-score sev-none">-</span>';
    }
    $severity = $severity ?? severity_from_score($score);
    return '<span class="rk-score ' . severity_class($severity) . '">' . (int)$score . '</span>';
}

/** Gecikmis tarihleri kirmizi ve ikonlu gösterir. */
function due_date_cell(?string $dueDate, ?string $status = null): string
{
    if ($dueDate === null || $dueDate === '') {
        return '<span class="text-muted">-</span>';
    }
    $text = e(format_date($dueDate));
    if (is_overdue($dueDate, $status)) {
        $days = abs((int)days_until($dueDate));
        return '<span class="rk-overdue"><i class="bi bi-clock-history"></i> ' . $text
             . ' <small>(' . $days . ' gün gecikti)</small></span>';
    }
    return $text;
}

/** Kullanıcı adindan bas harfleri üretir: "Ali Veli" -> "AV" */
function initials(?string $name): string
{
    $name = trim((string)$name);
    if ($name === '') {
        return '?';
    }
    $parts = preg_split('/\s+/u', $name) ?: [];
    $first = mb_substr($parts[0] ?? '', 0, 1, 'UTF-8');
    $last  = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8') : '';
    return mb_strtoupper($first . $last, 'UTF-8');
}

/** Liste ekranlarinda "kayıt yok" bileseni. */
function empty_state(
    string $title = 'Kayıt bulunamadı',
    string $text = '',
    string $icon = 'bi-inbox',
    string $actionHtml = ''
): string {
    return '<div class="rk-empty">'
         . '<i class="bi ' . e($icon) . '"></i>'
         . '<div class="rk-empty-title">' . e($title) . '</div>'
         . ($text !== '' ? '<div class="rk-empty-text">' . e($text) . '</div>' : '')
         . $actionHtml
         . '</div>';
}

/* =====================================================================
 * MARKA VARLIKLARI
 *
 * Logo dosyaları /assets/img/ altinda beklenir:
 *   logo-icon.png        -> kare uygulama ikonu (sidebar, login, favicon kaynağı)
 *   logo-wordmark.png    -> tam logo, ACIK zeminler için (rapor, yazdırma)
 *   favicon-16/32.png    -> logo-icon.png'den üretilir
 *   apple-touch-icon.png -> logo-icon.png'den üretilir
 *
 * Dosya yoksa gecici bir ikon/metin döndürülür; sayfa asla kirilmaz.
 * ===================================================================*/

/** Logo dosyasinin varligini istek başına bir kez kontrol eder. */
function brand_asset_exists(string $relativePath): bool
{
    static $cache = [];
    if (!array_key_exists($relativePath, $cache)) {
        $cache[$relativePath] = is_file(APP_ROOT . $relativePath);
    }
    return $cache[$relativePath];
}

/** Kare marka ikonu. Koyu zeminlerde de çalışır (kendi arka plani var). */
function brand_mark(): string
{
    // 64px kaynak: 30px kutuda retina ekranda da net kalir, ~4 KB.
    if (brand_asset_exists('/assets/img/logo-icon-64.png')) {
        return '<img class="rk-brand-logo" src="' . e(url('/assets/img/logo-icon-64.png'))
             . '" alt="' . e(app_name()) . '" width="30" height="30" decoding="async">';
    }
    return '<span class="rk-brand-mark"><i class="bi bi-shield-check"></i></span>';
}

/**
 * Tam logo (wordmark). Logo koyu lacivert metin icerdigi için
 * YALNIZCA açık zeminlerde kullanilmalidir.
 */
function brand_wordmark(string $background = 'light'): string
{
    // $background = üzerine konulacak ZEMIN rengi.
    //   'light' -> beyaz/açık zemin -> orijinal lacivert logo
    //   'dark'  -> lacivert zemin   -> açık varyant ("Risk" beyaz)
    $isDark = ($background === 'dark');

    $base  = $isDark ? '/assets/img/logo-wordmark-light' : '/assets/img/logo-wordmark';
    $class = $isDark ? 'rk-wordmark-light' : 'rk-wordmark';

    if (brand_asset_exists($base . '.png')) {
        $srcset = brand_asset_exists($base . '@2x.png')
            ? ' srcset="' . e(url($base . '.png')) . ' 1x, '
              . e(url($base . '@2x.png')) . ' 2x"'
            : '';
        return '<img class="' . $class . '" src="' . e(url($base . '.png'))
             . '"' . $srcset . ' alt="' . e(app_name()) . '" decoding="async">';
    }

    return '<strong>' . e(app_name()) . '</strong>';
}

/** <head> icine yerlestirilecek favicon linkleri. */
function favicon_tags(): string
{
    $icons = [
        ['/assets/img/favicon-32.png',     'icon',             '32x32'],
        ['/assets/img/favicon-16.png',     'icon',             '16x16'],
        ['/assets/img/apple-touch-icon.png','apple-touch-icon', '180x180'],
    ];

    $out = '';
    foreach ($icons as [$path, $rel, $sizes]) {
        if (brand_asset_exists($path)) {
            $out .= '<link rel="' . $rel . '" type="image/png" sizes="' . $sizes
                  . '" href="' . e(url($path)) . '">' . "\n";
        }
    }
    return $out;
}
