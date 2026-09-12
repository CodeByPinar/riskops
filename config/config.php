<?php
declare(strict_types=1);

/**
 * RiskOps - Uygulama yapilandirmasi
 * /var/www/riskops/config/config.php
 *
 * Bu dosya hiçbir sey yazdirmaz, sadece sabit tanimlar.
 */

// --- Ortam ------------------------------------------------------------
// 'development' -> hatalar ekranda + log dosyasinda
// 'production'  -> hatalar SADECE log dosyasinda, kullaniciya sade mesaj
//
// Ortam KOD DEĞİŞTİRMEDEN ayarlanır: Apache VirtualHost içindeki
//     SetEnv RISKOPS_ENV production
// satırı yeterlidir. Böylece üretime alma işlemi bir yapılandırma
// değişikliğidir, bir kod değişikliği değil - yanlışlıkla geliştirme
// ayarıyla canlıya çıkma riski ortadan kalkar.
//
// Tanımlı değilse güvenli varsayılan: development (hataları gizlemek
// yerine gösterir; sessizce bozuk çalışmaktan iyidir).
$riskopsEnv = $_SERVER['RISKOPS_ENV'] ?? getenv('RISKOPS_ENV') ?: '';
define('APP_ENV', in_array($riskopsEnv, ['development', 'production'], true)
    ? $riskopsEnv
    : 'development');
unset($riskopsEnv);

// --- Demo kipi --------------------------------------------------------
// Herkese açık tanıtım kurulumunda 1 yapılır:
//     SetEnv RISKOPS_DEMO 1
//
// Etkisi YALNIZCA giriş ekranındadır: ziyaretçiye deneme hesabının
// bilgilerini gösterir ve verinin her gece sıfırlandığını bildirir.
// Yetkilendirmeye HİÇBİR etkisi yoktur - demo hesabının ne yapabileceğini
// rolü belirler (bkz. includes/auth.php), bu bayrak değil.
//
// Varsayılan 0: bir kurum kurulumu yanlışlıkla demo bilgisi gösteremez.
define('APP_DEMO', ($_SERVER['RISKOPS_DEMO'] ?? getenv('RISKOPS_DEMO') ?: '') === '1');

// Giriş ekranında gösterilecek deneme hesabı. Parola BURADA durur ve
// zaten herkese açıktır; bu hesabın rolü 'viewer' olmalıdır.
define('DEMO_EMAIL',    'demo@riskops.local');
define('DEMO_PASSWORD', 'RiskOpsDemo2026');

// --- Hata ayıklama kipi -----------------------------------------------
// Apache VirtualHost içinde:
//     SetEnv RISKOPS_DEBUG 1
//
// Açıkken: sayfanın altında hata ayıklama araç çubuğu (çalışan SQL
// sorguları ve süreleri, zaman çizelgesi, istek/oturum içeriği,
// log kuyruğu), istisnalar için yığın izli ayrıntılı hata sayfası ve
// storage/logs/debug.log dosyasına istek özetleri.
//
// ÜRETİMDE KAZAYLA AÇILAMAZ
// -------------------------
// APP_ENV 'production' iken RISKOPS_DEBUG tek başına YETMEZ; ayrıca
// RISKOPS_DEBUG_PRODUCTION=1 gerekir. Neden: bu bayrak sorgu metinlerini,
// dosya yollarını ve oturum içeriğini tarayıcıya basar. Bir kurulum
// betiğinde ya da kopyalanmış bir VirtualHost'ta unutulmuş tek bir
// SetEnv satırının canlı sistemi bilgi sızdıran bir hâle getirmesi
// kabul edilemez. İki ayrı bayrak istemek bunu "unutulabilir" olmaktan
// çıkarıp "bilerek yapılmış" hâle getirir.
//
// Açık olsa bile araç çubuğu üretimde YALNIZCA admin rolüne gösterilir
// (bkz. debug_visible(), includes/debug.php).
$riskopsDebug = (($_SERVER['RISKOPS_DEBUG'] ?? getenv('RISKOPS_DEBUG') ?: '') === '1');
if ($riskopsDebug && APP_ENV === 'production') {
    $riskopsDebug = (($_SERVER['RISKOPS_DEBUG_PRODUCTION']
                      ?? getenv('RISKOPS_DEBUG_PRODUCTION') ?: '') === '1');
}
define('APP_DEBUG', $riskopsDebug);
unset($riskopsDebug);

// Bu süreyi aşan sorgu araç çubuğunda "yavaş" işaretlenir (ms).
define('DEBUG_SLOW_QUERY_MS', 100);

// debug.log'a VARSAYILAN OLARAK HER PHP İSTEĞİ yazılır.
//
// Statik dosyalar (CSS, JS, görsel) PHP'den geçmediği için dosyaya
// girmez; bir sayfa görüntülemesi genellikle 1-2 satır üretir. Yani
// "her isteği yaz" pratikte gürültü değil, kronolojik bir kayıttır -
// ve hata ayıklama kipi zaten geçici olarak açılır.
//
// Uzun süreli bir ölçüm için yalnızca sorunlu istekler isteniyorsa:
//     SetEnv RISKOPS_DEBUG_LOG_ONLY_SLOW 1
// Bu durumda yalnızca aşağıdaki eşikleri aşan ya da yavaş/hatalı/
// yinelenen sorgu içeren istekler yazılır.
define('DEBUG_LOG_ONLY_SLOW',
    (($_SERVER['RISKOPS_DEBUG_LOG_ONLY_SLOW']
      ?? getenv('RISKOPS_DEBUG_LOG_ONLY_SLOW') ?: '') === '1'));

define('DEBUG_LOG_MIN_MS',      250);
define('DEBUG_LOG_MIN_QUERIES', 20);

// Araç çubuğunun "Log" panelinde gösterilen app.log satır sayısı.
define('DEBUG_LOG_TAIL_LINES', 60);

// --- Dizin sabitleri --------------------------------------------------
define('APP_ROOT',      dirname(__DIR__));
define('CONFIG_PATH',   APP_ROOT . '/config');
define('INCLUDES_PATH', APP_ROOT . '/includes');
define('LAYOUT_PATH',   INCLUDES_PATH . '/layout');
define('PARTIALS_PATH', INCLUDES_PATH . '/partials');
define('STORAGE_PATH',  APP_ROOT . '/storage');
define('LOG_PATH',      STORAGE_PATH . '/logs');
define('UPLOAD_PATH',   STORAGE_PATH . '/uploads');
define('LOG_FILE',      LOG_PATH . '/app.log');

// Hata ayıklama istek özetleri. app.log'dan AYRI tutulur: o dosya
// uyarı ve hataların kalıcı kaydıdır, her isteğin ölçümüyle
// şişmemelidir.
define('DEBUG_LOG_FILE', LOG_PATH . '/debug.log');

// --- URL --------------------------------------------------------------
// DocumentRoot = /var/www/riskops olduğu için uygulama web kokunde duruyor.
// Alt dizine tasinirsa buraya '/riskops' gibi bir on ek yazılır.
define('BASE_PATH', '');

// --- Varlık sürümü ----------------------------------------------------
// CSS ve JS bağlantılarına ?v=... olarak eklenir; tarayıcının eski
// dosyayı önbellekten vermesini engeller.
//
// TEK YERDE DURUR VE BÖYLE KALMALI. Önceden bu değer üç ayrı dosyada
// (header.php, footer.php, auth/login.php) elle yazılıydı. app.css
// baştan sona yenilendiğinde ikisi güncellendi, giriş ekranındaki
// unutuldu: dosya sunucuda yeniydi ama tarayıcı eskisini gösteriyordu.
// Hata "çalışmıyor" gibi görünür, oysa yalnızca önbellektir.
//
// ASSETS DEĞİŞTİYSE BURAYI ARTIRIN (tarih + harf yeterli).
define('ASSET_VERSION', '20260912a');

// --- Oturum -----------------------------------------------------------
define('SESSION_NAME', 'RISKOPS_SESSION');

// --- Fallback değerler ------------------------------------------------
// settings tablosu okunamazsa bunlar kullanılır.
define('FALLBACK_APP_NAME',        'RiskOps');
define('FALLBACK_TIMEZONE',        'Europe/Istanbul');
define('FALLBACK_DATE_FORMAT',     'd.m.Y');
define('FALLBACK_DATETIME_FORMAT', 'd.m.Y H:i');
define('FALLBACK_PER_PAGE',        25);
