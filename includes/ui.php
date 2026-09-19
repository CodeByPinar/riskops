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
    return '<span class="rk-badge ' . severity_class($severity) . '">' . $icon . e(severity_label($severity)) . '</span>';
}

function status_badge(?string $status): string
{
    if ($status === null || $status === '') {
        return '<span class="rk-badge st-none">-</span>';
    }
    return '<span class="rk-badge ' . status_class($status) . '">' . e(status_label($status)) . '</span>';
}

function priority_badge(?string $priority): string
{
    if ($priority === null || $priority === '') {
        return '<span class="rk-badge pr-none">-</span>';
    }
    return '<span class="rk-badge ' . priority_class($priority) . '">' . e(priority_label($priority)) . '</span>';
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
/**
 * Termin hücresi: gecikmişse kırmızı ve "N gün gecikti" ile.
 *
 * $openStatuses NEDEN VAR
 * -----------------------
 * "Gecikmiş" yalnızca AÇIK kayıtlar için anlamlıdır; kapatılmış bir
 * kaydın geçmiş termini gecikme değildir. Ama açık sayılan durumlar
 * risklerde ve aksiyonlarda FARKLIDIR:
 *
 *     risk_open_statuses()   = Open, Under Review, In Progress
 *     action_open_statuses() = Open, In Progress
 *
 * Bu parametre eklenmeden önce fonksiyon iki argüman alıyordu ama risk
 * ekranları üç argümanla çağırıyordu. PHP fazla argümanı SESSİZCE atar;
 * is_overdue() de aksiyon listesine düşüyordu. Sonuç: termini geçmiş
 * "Under Review" bir risk, risk listesinde, panelde, risk detayında ve
 * yönetici özetinde gecikmiş GÖRÜNMÜYORDU. Hata hiçbir uyarı üretmiyor,
 * yalnızca eksik vurgu olarak ortaya çıkıyordu.
 *
 * Boş bırakılırsa is_overdue() aksiyon durumlarına düşer - aksiyon
 * ekranlarının beklediği davranış budur.
 *
 * @param list<string> $openStatuses
 */
function due_date_cell(?string $dueDate, ?string $status = null, array $openStatuses = []): string
{
    if ($dueDate === null || $dueDate === '') {
        return '<span class="text-muted">-</span>';
    }
    $text = e(format_date($dueDate));
    if (is_overdue($dueDate, $status, $openStatuses)) {
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

/* =====================================================================
 * KATEGORİ RENKLERİ
 *
 * Kategori rozetinin rengi veritabanından gelir ve kullanıcı tarafından
 * seçilir; sabit bir sınıfa çevrilemez. Önceden style="background:#xxx"
 * olarak basılıyordu, bu da CSP'de style-src 'unsafe-inline' gerektiriyordu.
 *
 * Yeni yol: tüm kategori renkleri sayfa başında TEK bir <style> bloğunda
 * üretilir, blok nonce taşır (bkz. csp_nonce()). Markup yalnızca sınıf
 * kullanır.
 * ===================================================================== */

/**
 * Hex rengi güvenli bir sınıf adına çevirir.
 *
 * DOĞRULAMA ZORUNLU: bu değer doğrudan CSS'e yazılıyor. Doğrulanmadan
 * geçirilseydi veritabanına "#000;} body{display:none} .x{" gibi bir
 * değer yazan biri sayfanın stilini ele geçirebilirdi (CSS enjeksiyonu).
 * Yalnızca #rrggbb biçimi kabul edilir.
 */
function category_color_class(?string $hex): string
{
    if ($hex === null || preg_match('/^#[0-9a-fA-F]{6}$/', $hex) !== 1) {
        return 'rk-cat-default';
    }

    return 'rk-cat-' . strtolower(substr($hex, 1));
}

/**
 * Tüm kategori renkleri için CSS kuralları.
 *
 * PASİF KATEGORİLER DE DAHİL: pasif bir kategoriye bağlı eski riskler
 * listelerde görünmeye devam ediyor; yalnızca aktifler yazılsaydı o
 * rozetler renksiz kalırdı.
 */
function category_color_styles(): string
{
    $seen = [];
    $css  = '.rk-cat-default{background:var(--rk-muted-light)}';

    foreach (categories_list(false) as $c) {
        $hex = (string)($c['color'] ?? '');

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $hex) !== 1) {
            continue;
        }

        $cls = category_color_class($hex);
        if (isset($seen[$cls])) {
            continue;
        }
        $seen[$cls] = true;

        $css .= '.' . $cls . '{background:' . strtolower($hex) . '}';
    }

    return $css;
}

/** Kategori rengini taşıyan nokta. */
function category_dot(?string $hex): string
{
    return '<span class="rk-dot ' . e(category_color_class($hex)) . '"></span>';
}

/* =====================================================================
 * VERITABANI DEGERLERININ GORUNEN ADLARI
 *
 * status / severity / priority kolonlari ENUM'dur ve degerleri
 * INGILIZCEDIR ('Open', 'Critical'...). Bu degerler CSS sinifi ve
 * ayarlardaki esik JSON anahtari olarak da kullaniliyor, dolayisiyla
 * DEGISTIRILEMEZ.
 *
 * Degisen yalnizca GORUNEN AD: asagidaki haritalar Turkce karsiligi
 * doner, Ingilizcesi lang/en.php uzerinden gelir. Boylece depolanan
 * deger ile gosterilen metin birbirinden ayrilir.
 * ===================================================================== */

function severity_label(?string $v): string
{
    return match ($v) {
        'Low'      => t('Düşük'),
        'Medium'   => t('Orta'),
        'High'     => t('Yüksek'),
        'Critical' => t('Kritik'),
        default    => (string)$v,
    };
}

function status_label(?string $v): string
{
    return match ($v) {
        'Open'         => t('Açık'),
        'Under Review' => t('İncelemede'),
        'In Progress'  => t('Devam ediyor'),
        'Mitigated'    => t('Azaltıldı'),
        'Accepted'     => t('Kabul edildi'),
        'Transferred'  => t('Devredildi'),
        'Closed'       => t('Kapatıldı'),
        'Completed'    => t('Tamamlandı'),
        'Cancelled'    => t('İptal edildi'),
        default        => (string)$v,
    };
}

function priority_label(?string $v): string
{
    return severity_label($v);
}

/**
 * Denetim farkindaki bir degeri kisa metne indirger.
 *
 * Skaler ise kendisi, degilse JSON. json_encode() gecersiz UTF-8'de
 * false donebiliyor; o durumda bos metin basmak yerine yerini belli
 * ediyoruz - bos bir hucre "alan bostu" gibi okunurdu.
 */
function audit_value_short(mixed $value, int $limit = 26): string
{
    if (is_scalar($value)) {
        return str_limit((string)$value, $limit);
    }

    $json = json_encode($value, JSON_UNESCAPED_UNICODE);

    return str_limit($json === false ? '(gosterilemedi)' : $json, $limit);
}
