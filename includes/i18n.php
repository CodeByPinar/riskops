<?php

declare(strict_types=1);

/**
 * RiskOps - Çok dilli arayüz
 * /var/www/riskops/includes/i18n.php
 *
 * ÇEVİRİ ANAHTARI, TÜRKÇE METNİN KENDİSİDİR:
 *     t('Yeni Risk')        değil        t('risks.new')
 *
 * Pratik sonucu: sözlükte karşılığı olmayan metin BOZULMAZ, doğru
 * Türkçesiyle görünür. Kısmi çeviri kullanılabilir bir durumdur.
 *
 * Gerekçe ve reddedilen alternatifler:
 *     docs/architecture/0002-ceviri-anahtari-kaynak-metin.md
 *
 * DEĞİŞKEN ARAYÜZ
 *   t('%d risk seçildi', [5])           -> sprintf ile
 *   t('Merhaba :name', [':name' => $n]) -> adlandırılmış yer tutucu
 */

/**
 * Desteklenen diller: kod => görünen ad.
 *
 * @return array<string, string>
 */
function i18n_locales(): array
{
    return [
        'tr' => 'Türkçe',
        'en' => 'English',
    ];
}

function i18n_default(): string
{
    $d = (string)setting('default_locale', 'tr');

    return isset(i18n_locales()[$d]) ? $d : 'tr';
}

/**
 * Bu isteğin dili.
 *
 * Öncelik sırası:
 *   1. Oturumda seçili dil (kullanıcı değiştirdiyse)
 *   2. Kullanıcının kayıtlı tercihi (users.locale)
 *   3. Sistem varsayılanı (settings.default_locale)
 *
 * Tarayıcının Accept-Language başlığı BİLEREK kullanılmıyor: kurumsal
 * bir uygulamada dil, kişinin hesabına bağlı bir tercihtir; kullandığı
 * tarayıcının diline göre değişmesi şaşırtıcı olur.
 */
function locale(): string
{
    $cache = &locale_cache();

    if ($cache !== null) {
        return $cache;
    }

    $locales = i18n_locales();

    $s = $_SESSION['locale'] ?? null;
    if (is_string($s) && isset($locales[$s])) {
        return $cache = $s;
    }

    $u = $_SESSION['user_locale'] ?? null;
    if (is_string($u) && isset($locales[$u])) {
        return $cache = $u;
    }

    return $cache = i18n_default();
}

/**
 * locale() önbelleğinin tutulduğu yer.
 *
 * NEDEN AYRI BİR FONKSİYON: dil istek başına bir kez hesaplanır ve
 * önbelleğe alınır. Önbellek locale() içinde `static` olsaydı
 * DIŞARIDAN SIFIRLANAMAZDI - PHP'de bir fonksiyonun static değişkenine
 * erişilemez. Aynı süreçte birden fazla dil senaryosu sınayan testler
 * bu yüzden hep ilk hesaplanan değeri görürdü; yani dil mantığı
 * pratikte test edilemez olurdu.
 *
 * Referansla döndüğü için çağıran yazabilir:
 *     $cache = &locale_cache();
 *     $cache = 'en';
 */
function &locale_cache(): ?string
{
    static $value = null;

    return $value;
}

/**
 * Önbelleği boşaltır; sonraki locale() çağrısı yeniden hesaplar.
 *
 * Uygulama akışında GEREKMEZ (dil bir istek içinde değişmez).
 * Testler ve dili değiştirip aynı süreçte çıktı üreten CLI araçları
 * için vardır.
 */
function locale_reset(): void
{
    $cache = &locale_cache();
    $cache = null;
}

/**
 * Aktif dilin sözlüğü. Dosya yoksa boş dizi döner ve her metin
 * anahtarının kendisiyle (Türkçesiyle) görünür.
 *
 * @return array<string, string>
 */
function i18n_dictionary(): array
{
    static $cache = [];

    $loc = locale();

    if (!isset($cache[$loc])) {
        $file = APP_ROOT . '/lang/' . $loc . '.php';

        /* basename kontrolu: locale() zaten beyaz listeden geliyor ama
           dosya yolu olusturan her yerde ikinci bir kontrol ucuzdur. */
        $cache[$loc] = (basename($loc) === $loc && is_file($file))
            ? (array)require $file
            : [];
    }

    return $cache[$loc];
}

/**
 * Çevir.
 *
 * @param string $text Türkçe kaynak metin (aynı zamanda anahtar)
 * @param array  $vars sprintf argümanları ya da [':ad' => 'değer']
 * @param array<array-key, string|int|float> $vars
 */
function t(string $text, array $vars = []): string
{
    $dict = i18n_dictionary();
    $out  = $dict[$text] ?? $text;

    if ($vars === []) {
        return $out;
    }

    /* Adlandirilmis yer tutucu mu, sprintf mi? Anahtarlarin tamami
       string ise adlandirilmis kabul edilir. */
    $named = true;
    foreach (array_keys($vars) as $k) {
        if (!is_string($k)) {
            $named = false;
            break;
        }
    }

    if ($named) {
        return strtr($out, $vars);
    }

    return vsprintf($out, $vars);
}

/**
 * Çevir ve HTML olarak kaçır. Şablonlarda en sık kullanılan biçim.
 *
 * @param array<array-key, string|int|float> $vars
 */
function te(string $text, array $vars = []): string
{
    return e(t($text, $vars));
}

/**
 * Dil değiştirme bağlantısı için adres.
 *
 * Mevcut sorgu dizesi korunur: kullanıcı dili değiştirdiğinde
 * filtrelediği listeden çıkıp başa dönmesin.
 */
function locale_switch_url(string $code): string
{
    $qs = $_GET;
    $qs['setlocale'] = $code;

    return current_path() . '?' . http_build_query($qs);
}

/**
 * ?setlocale=xx parametresini işler.
 *
 * bootstrap içinden, oturum açıldıktan SONRA çağrılır.
 *
 * GET ile durum değiştirmek normalde yapılmaz (CSRF kuralı), ama
 * burada değişen tek şey görüntüleme dilidir: veri değişmez, yetki
 * değişmez, geri alınabilir. Bir saldırganın kurbanın arayüz dilini
 * değiştirmesinin bir kazancı yok.
 */
function i18n_handle_switch(): void
{
    $code = input_enum('setlocale', array_keys(i18n_locales()));

    if ($code === null) {
        return;
    }

    $_SESSION['locale'] = $code;

    /* Giris yapmis kullanicinin tercihi kalici olsun. */
    if (auth_check()) {
        try {
            db()->prepare('UPDATE users SET locale = :l WHERE id = :id')
                ->execute([':l' => $code, ':id' => auth_id()]);
            $_SESSION['user_locale'] = $code;
        } catch (Throwable $e) {
            /* locale kolonu yoksa (eski kurulum) oturum icinde calisir. */
            app_log('warning', 'Locale preference could not be saved: ' . $e->getMessage());
        }
    }

    /* setlocale parametresini adresten temizle: kullanici baglantiyi
       paylastiginda karsi tarafin dili degismesin. */
    $qs = $_GET;
    unset($qs['setlocale']);

    redirect(current_path() . ($qs !== [] ? '?' . http_build_query($qs) : ''));
}
