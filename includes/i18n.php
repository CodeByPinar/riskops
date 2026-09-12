<?php
declare(strict_types=1);

/**
 * RiskOps - Çok dilli arayüz
 * /var/www/riskops/includes/i18n.php
 *
 * =====================================================================
 *  ANAHTAR, TÜRKÇE METNİN KENDİSİDİR
 * =====================================================================
 *
 * t('Yeni Risk') şeklinde çağrılır; t('risks.new') gibi soyut bir
 * anahtar kullanılmaz. Sebep bir tasarım tercihinden fazlası:
 *
 * 1. GEÇİŞ GÜVENLİ. Uygulamada 900'den fazla satır metin içeriyor.
 *    Soyut anahtar kullanılsaydı, wrap edilmemiş her metin ya boş
 *    görünür ya da "risks.new" gibi bir anahtar basardı. Kaynak metin
 *    anahtar olunca, çevrilmemiş bir metin OLDUĞU GİBİ - yani doğru
 *    Türkçesiyle - görünür. Eksik çeviri sayfayı bozmaz.
 *
 * 2. KOD OKUNUR KALIR. t('Silinen Riskler') okunduğunda ne yazdığı
 *    bellidir; t('risks.deleted.title') için sözlüğe bakmak gerekir.
 *
 * 3. Türkçe sözlük dosyası BOŞ olabilir: anahtar bulunamazsa anahtarın
 *    kendisi döner, o da zaten Türkçedir.
 *
 * MALİYETİ: Türkçe metin değişirse çeviri anahtarı da değişir ve
 * İngilizcesi düşer (Türkçeye geri döner). Bu kabul edilebilir -
 * sessizce YANLIŞ çeviri göstermektense doğru Türkçe göstermek yeğdir.
 *
 * DEĞİŞKEN ARAYÜZ
 *   t('%d risk seçildi', [5])           -> sprintf ile
 *   t('Merhaba :name', [':name' => $n]) -> adlandırılmış yer tutucu
 */

/** Desteklenen diller: kod => görünen ad. */
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
    static $locale = null;

    if ($locale !== null) {
        return $locale;
    }

    $locales = i18n_locales();

    $s = $_SESSION['locale'] ?? null;
    if (is_string($s) && isset($locales[$s])) {
        return $locale = $s;
    }

    $u = $_SESSION['user_locale'] ?? null;
    if (is_string($u) && isset($locales[$u])) {
        return $locale = $u;
    }

    return $locale = i18n_default();
}

/**
 * Aktif dilin sözlüğü. Dosya yoksa boş dizi döner ve her metin
 * anahtarının kendisiyle (Türkçesiyle) görünür.
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

/** Çevir ve HTML olarak kaçır. Şablonlarda en sık kullanılan biçim. */
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
