<?php

declare(strict_types=1);

/**
 * RiskOps - GitHub Pages icin statik demo sitesi uretici
 * /var/www/riskops/tools/build_demo_site.php
 *
 * Kullanim:
 *   php tools/build_demo_site.php                       (varsayilan)
 *   php tools/build_demo_site.php --base=/riskops/demo
 *   php tools/build_demo_site.php --host=http://127.0.0.1
 *
 * NE YAPAR
 *   Calisan uygulamaya GERCEKTEN giris yapar, ekranlari indirir ve
 *   GitHub Pages'te gezilebilecek statik HTML'e cevirir. Yani
 *   tanitim sitesindeki ekranlar taklit degil: uygulamanin kendi
 *   ciktisi, kendi CSS'i, kendi verisiyle.
 *
 * NEDEN EKRAN GORUNTUSU DEGIL
 *   Ekran goruntusu tiklanamaz. Burada kenar cubugu gercekten
 *   calisiyor, tablolar gercekten kayiyor, risk detayina gercekten
 *   giriliyor. "Nasil bir sey" sorusunun cevabi boyle veriliyor.
 *
 * NE YAPMAZ - VE BU BILINCLI
 *   Statik dosyada sunucu yok: kaydetme, silme, filtreleme ve
 *   sayfalama CALISMAZ. Bu baglantilar sessizce bozuk birakilmiyor,
 *   ACIKCA devre disi birakilip isaretleniyor (rk-demo-off). Tiklayan
 *   birinin 404 gormesi, "demo bozuk" demesi icin yeterli sebeptir.
 *
 * GUVENLIK
 *   Uretilen dosyalar herkese acik bir siteye konuyor. Bu yuzden:
 *     - CSRF belirtecleri bosaltilir (zaten olu, ama depoya girmesin)
 *     - form action'lari '#' yapilir
 *     - cikis/parola baglantilari devre disi
 *   Veri demo verisidir (tools/seed_demo.php); gercek kurum verisi
 *   olan bir kurulumda BU BETIK CALISTIRILMAMALIDIR.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);

/* ---------------------------------------------------------------------
 * Ayarlar
 * -------------------------------------------------------------------*/

$opt = getopt('', [
    'base::', 'host::', 'email::', 'pass::', 'out::', 'approot::',
    'lang::', 'altbase::', 'altlang::', 'assets::', 'skip-assets',
]);

/**
 * getopt() secenegini metne indirger.
 *
 * Ayni bayrak iki kez verilirse ("--base=a --base=b") getopt bir DIZI
 * dondurur; deger dogrudan rtrim()'e verilirse cokerdi. Son deger
 * kazanir - komut satirinda beklenen davranis budur.
 *
 * @param array<string, string|list<string>|false> $opt
 */
function opt_str(array $opt, string $key, string $default): string
{
    $v = $opt[$key] ?? null;

    if (is_array($v)) {
        $v = end($v);
    }

    return is_string($v) && $v !== '' ? $v : $default;
}

$BASE  = rtrim(opt_str($opt, 'base', '/riskops/demo'), '/');
$HOST  = rtrim(opt_str($opt, 'host', 'http://127.0.0.1'), '/');
$EMAIL = opt_str($opt, 'email', 'admin@riskops.local');
$PASS  = opt_str($opt, 'pass', 'Admin123456');
$OUT   = rtrim(opt_str($opt, 'out', $root . '/docs/demo'), '/');

/* Eklenti yonetim ekrani kurulum yolunu BILEREK gosteriyor
   ("cd <APP_ROOT>/plugins"). Bu bir sizinti degil, belgelenmis kurulum
   yolu. Ama demo baska bir makinede uretilirse oranin yolu
   ("/home/ali/projeler/riskops-dev") herkese acik bir sayfaya
   dusebilirdi. O yuzden gercek kok, KANONIK yola cevriliyor. */
$CANON = rtrim(opt_str($opt, 'approot', '/var/www/riskops'), '/');

/* ---------------------------------------------------------------------
 * DIL
 *
 * Uygulamada tam bir TR/EN sozlugu var (ADR-0002). Demo da iki dilde
 * uretiliyor: ayni sayfalar, ayni veri, farkli arayuz dili.
 *
 * ALTBASE, kenar cubugundaki dil degistiricinin isine yariyor. Statik
 * sitede "?setlocale=en" calismaz; o baglanti, DIGER DILDEKI AYNI
 * SAYFAYA cevriliyor. Boylece demo icinde dil degistirmek gercekten
 * calisiyor - devre disi birakilmis bir dugme gostermekten iyi.
 *
 * ASSETS paylasilabiliyor: iki dil ayni CSS/JS/font kumesini kullaniyor,
 * ikinci kopya 1.6 MB'lik gereksiz bir tekrar olurdu.
 * -------------------------------------------------------------------*/

$LANG     = opt_str($opt, 'lang', 'tr') === 'en' ? 'en' : 'tr';
$ALTLANG  = $LANG === 'tr' ? 'en' : 'tr';
$ALTBASE  = rtrim(opt_str($opt, 'altbase', ''), '/');
$ASSETS   = rtrim(opt_str($opt, 'assets', $BASE . '/assets'), '/');
$SKIPASSETS = isset($opt['skip-assets']);

/* ---------------------------------------------------------------------
 * HTTP yardimcilari
 * -------------------------------------------------------------------*/

$jar = tempnam(sys_get_temp_dir(), 'rkjar');

/**
 * @param  array<string, string>|null $post
 * @return array{body: string, code: int}
 */
function http(string $url, ?array $post = null): array
{
    global $jar;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERAGENT      => 'RiskOps demo builder',
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $body = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['body' => $body, 'code' => $code];
}

function csrf_from(string $html): string
{
    preg_match('/name="_token" value="([^"]+)"/', $html, $m);

    return $m[1] ?? '';
}

function say(string $line): void
{
    echo $line, PHP_EOL;
}

/* ---------------------------------------------------------------------
 * Giris
 * -------------------------------------------------------------------*/

say("\n  RiskOps demo sitesi");
say('  ' . str_repeat('-', 62));
say("  kaynak : {$HOST}");
say("  taban  : {$BASE}");
say("  cikti  : {$OUT}\n");

$login = http($HOST . '/auth/login.php');
$token = csrf_from($login['body']);

if ($token === '') {
    say('  HATA: giris sayfasindan CSRF belirteci alinamadi.');
    exit(1);
}

$auth = http($HOST . '/auth/authenticate.php', [
    '_token'   => $token,
    'email'    => $EMAIL,
    'password' => $PASS,
]);

if ($auth['code'] !== 302) {
    say('  HATA: giris basarisiz (HTTP ' . $auth['code'] . ').');
    exit(1);
}
say('  giris yapildi');

/* Dili sec. Tercih oturumda VE kullanici kaydinda saklaniyor, yani
   sonraki butun istekler bu dilde geliyor. */
http($HOST . '/dashboard/?setlocale=' . $LANG);

/* GIRIS FLASH'INI TUKET
   Giris "Hos geldiniz, ..." mesajini oturuma yaziyor ve bu mesaj TEK
   SEFERLIK: ilk sayfa gosteriminde okunup silinir. Yukaridaki dil
   istegi 302 donuyor ve yonlendirmeyi izlemedigimiz icin mesaj
   tuketilmeden kaliyordu - yakalanan ilk sayfaya yapisiyordu.
   Ustelik giris dil secilmeden once yapildigi icin mesaj TURKCE
   kaliyor ve Ingilizce demoda yamali duruyordu. */
http($HOST . '/dashboard/');

say('  arayuz dili: ' . $LANG);

/* ---------------------------------------------------------------------
 * Yakalanacak sayfalar
 *
 * Once sabit ekranlar, sonra LISTELERDEN KESFEDILEN detay sayfalari.
 * Kesif onemli: elle id yazmak, demo verisi yeniden yuklendiginde
 * kirilan bir site demektir.
 * -------------------------------------------------------------------*/

$pages = [
    '/dashboard/',
    '/risks/',
    '/risks/create.php',
    '/risks/deleted.php',
    '/actions/',
    '/actions/create.php',
    '/assessments/',
    '/assessments/create.php',
    '/reports/',
    '/reports/executive_summary.php',
    '/admin/users/',
    '/admin/users/create.php',
    '/admin/departments/',
    '/admin/categories/',
    '/admin/settings/',
    '/admin/audit_logs/',
    '/admin/plugins/',
    '/profile/',
];

$fetched = [];
foreach ($pages as $path) {
    $r = http($HOST . $path);
    if ($r['code'] !== 200) {
        say("  ! {$path} (HTTP {$r['code']}) atlandi");
        continue;
    }
    $fetched[$path] = $r['body'];
}

/* Detay sayfalarini listelerden kesfet */
$details = [];
foreach ([['/risks/', '/risks/view.php'], ['/actions/', '/actions/view.php']] as [$list, $view]) {
    if (!isset($fetched[$list])) {
        continue;
    }
    preg_match_all('#' . preg_quote(basename($view), '#') . '\?id=(\d+)#', $fetched[$list], $m);
    foreach (array_unique($m[1]) as $id) {
        $details[dirname($view) . '/view/' . $id . '/'] = $view . '?id=' . $id;
    }
}

foreach ($details as $target => $source) {
    $r = http($HOST . $source);
    if ($r['code'] === 200) {
        $fetched[$target] = $r['body'];
    }
}

say('  ' . count($fetched) . ' sayfa indirildi (' . count($details) . ' detay)');

/* Grafik verisi: sayfa bunu data-endpoint ile istiyor */
$charts = http($HOST . '/api/dashboard_charts.php');

/* ---------------------------------------------------------------------
 * Donusturme
 * -------------------------------------------------------------------*/

$captured = array_fill_keys(array_keys($fetched), true);

/**
 * Bir baglantiyi statik siteye uyarlar.
 *
 * Yakalanan bir sayfaya isaret ediyorsa taban on ekiyle yeniden
 * yazilir; etmiyorsa DEVRE DISI birakilir. Ucuncu bir secenek
 * ("oldugu gibi birak") 404 uretirdi.
 */
/**
 * @param array<string, true> $captured
 */
function map_link(
    string $href,
    array $captured,
    string $base,
    string $altBase,
    string $lang
): ?string {
    if ($href === '' || $href[0] !== '/' || str_starts_with($href, '//')) {
        return null;                       // dis baglanti ya da capa
    }
    if (str_starts_with($href, '/assets/')) {
        return $base . $href;              // varliklar kopyalaniyor
    }

    /* risks/view.php?id=3  ->  risks/view/3/ */
    if (preg_match('#^(/\w+)/view\.php\?id=(\d+)$#', $href, $m)) {
        $target = $m[1] . '/view/' . $m[2] . '/';

        return isset($captured[$target]) ? $base . $target : '';
    }

    /* Dil degistirici: "/risks/?setlocale=en" -> diger dildeki ayni
       sayfa. Statik sitede sorgu dizesi calismaz ama bu baglantinin
       KARSILIGI var, o yuzden devre disi birakilmiyor. */
    if (preg_match('/^(.*?)\?setlocale=(tr|en)$/', $href, $m)) {
        if ($altBase === '' || $m[2] === $lang) {
            return '';
        }
        $path = $m[1] === '' ? '/' : $m[1];

        return isset($captured[$path]) ? $altBase . $path : '';
    }

    $clean = strtok($href, '?');

    if ($clean !== $href) {
        return '';                         // sorgu dizeli: filtre/siralama
    }

    return isset($captured[$clean]) ? $base . $clean : '';
}

$words = $LANG === 'en'
    ? [
        'title' => 'Static preview',
        'body'  => 'real output from the running app, with demo data',
        'note'  => 'saving and filtering do not work',
        'data'  => 'UI is English; the demo records themselves are Turkish',
        'home'  => '&larr; about',
    ]
    : [
        'title' => 'Statik önizleme',
        'body'  => 'gerçek uygulamadan alınmış çıktı, demo verisiyle',
        'note'  => 'kaydetme ve filtreleme çalışmaz',
        'home'  => '&larr; tanıtım',
    ];

$banner = '<div class="rk-demo-bar">'
    . '<span class="rk-demo-dot"></span>'
    . '<strong>' . $words['title'] . '</strong>'
    . '<span class="rk-demo-sep">·</span>'
    . $words['body']
    . '<span class="rk-demo-sep">·</span>'
    . $words['note']
    /* Veri dili arayuz dilinden BAGIMSIZ: sozluk arayuzu cevirir,
       kurumun kendi kayitlarini degil. Ingilizce demoda bunu
       soylemek, "yarim cevrilmis" izlenimini onluyor. */
    . (isset($words['data'])
        ? '<span class="rk-demo-sep">·</span>' . $words['data']
        : '')
    . '<a class="rk-demo-home" href="' . htmlspecialchars(dirname($BASE) . '/', ENT_QUOTES) . '">'
    . $words['home'] . '</a>'
    . '<a class="rk-demo-repo" href="https://github.com/CodeByPinar/riskops">GitHub</a>'
    . '</div>';

/* demo.css de paylasiliyor: iki dilde ayni. */
$CSS = $SKIPASSETS ? dirname($ASSETS) . '/demo.css' : $BASE . '/demo.css';

$written = 0;
$ready   = [];
foreach ($fetched as $path => $html) {
    /* 1) Baglantilar ve varlik yollari */
    $html = preg_replace_callback(
        '/\b(href|action)="([^"]*)"/',
        static function (array $m) use ($captured, $BASE, $ALTBASE, $LANG, $ASSETS): string {
            $mapped = map_link($m[2], $captured, $BASE, $ALTBASE, $LANG);
            if ($mapped === null) {
                return $m[0];
            }
            if (str_starts_with($m[2], '/assets/')) {
                return $m[1] . '="' . $ASSETS . substr($m[2], 7) . '"';
            }
            if ($mapped === '') {
                return $m[1] . '="#" class="rk-demo-off" '
                     . 'title="Statik önizlemede çalışmaz"';
            }

            return $m[1] . '="' . $mapped . '"';
        },
        $html
    ) ?? $html;

    $html = preg_replace('#\b(src)="/assets/([^"]*)"#', '$1="' . $ASSETS . '/$2"', $html) ?? $html;

    /* 2) Grafik ucu: canli API yerine yakalanan JSON */
    $html = str_replace(
        'data-endpoint="/api/dashboard_charts.php"',
        'data-endpoint="' . $BASE . '/api/dashboard_charts.json"',
        $html
    );

    /* 3) CSP nonce'unu sabitle
       Nonce her istekte rastgele uretiliyor. Statik kopyada bir islevi
       yok (CSP baslgini Pages kendisi veriyor), ama sabitlenmezse her
       yeniden uretimde 56 dosyanin TAMAMI degisir ve git gecmisi
       anlamsiz gurultuyle dolar. Zaman damgalari korunuyor: onlar
       gercek icerik. */
    $html = preg_replace('/ nonce="[^"]*"/', ' nonce="statik-onizleme"', $html) ?? $html;

    /* 4) Gercek dosya sistemi kokunu kanonik yola cevir */
    if ($root !== $CANON) {
        $html = str_replace($root, $CANON, $html);
    }

    /* 5) CSRF belirteclerini bosalt - olu ama depoya girmesinler */
    $html = preg_replace('/name="_token" value="[^"]*"/', 'name="_token" value=""', $html) ?? $html;

    /* 6) Demo seridi ve ek bicem */
    $html = str_replace(
        '</head>',
        '  <link rel="stylesheet" href="' . $CSS . '">' . "\n</head>",
        $html
    );
    $html = preg_replace('/(<body[^>]*>)/', '$1' . "\n" . $banner, $html, 1) ?? $html;

    /* 7) Biriktir */
    $file = rtrim($OUT . rtrim($path, '/'), '/');
    if (str_ends_with($path, '/')) {
        $file = $OUT . $path . 'index.html';
    } else {
        $file = $OUT . preg_replace('/\.php$/', '', $path) . '/index.html';
    }

    $ready[$file] = $html;
}

/* Denetim, YAZILACAK son halin uzerinde calisir: ham indirmede CSRF
   belirteci hala dolu oldugu icin orada bakmak yanlis alarm verirdi. */
/* ---------------------------------------------------------------------
 * YAYIN ONCESI SIZINTI DENETIMI
 *
 * Bu betigin ciktisi HERKESE ACIK bir siteye konuyor. Kaynak sunucuda
 * hata ayiklama kipi acikken yakalama yapilirsa, her sayfaya calisan
 * SQL'ler, sorgu sayilari, oturum anahtarlari, ortam degiskenleri ve
 * app.log'un son 60 satiri gomulu gelir.
 *
 * BU GERCEKTEN OLDU: gelistirme vhost'unda "SetEnv RISKOPS_DEBUG 1"
 * duruyordu ve ilk yakalama araci curubugunu de beraberinde getirdi.
 * Ekran goruntusunde fark edildi - yani gozden kacabilirdi.
 *
 * O yuzden burada UYARI degil, DURDURMA var: temizlemeye calismak
 * yanlis olurdu, cunku bir varyantini kacirirsam sonuc yine yayinlanir.
 * Dogru davranis, kaynagi duzeltip yeniden calistirmak.
 * -------------------------------------------------------------------*/

/**
 * @param array<string, string> $pages
 * @return list<string>
 */
function leak_scan(array $pages, string $canon): array
{
    $patterns = [
        'hata ayıklama araç çubuğu' => '/id="rkdbg"|class="rk-dbg/',
        'oturum çerezi'             => '/RISKOPS_SESSION=[A-Za-z0-9]/',
        'dolu CSRF belirteci'       => '/name="_token" value="[A-Za-z0-9]{8}/',
        /* Kanonik kurulum yolu disindaki dosya sistemi yollari.
           "/var/www/riskops" belgelenmis ve ekranda bilerek gosteriliyor;
           ama derleme baska bir makinede yapilirsa oranin yolu sizardi. */
        'beklenmeyen sunucu yolu'   => '#(?<!\w)/(?:home|Users|srv|opt|root)/[\w./-]+#',
    ];

    $found = [];
    foreach ($pages as $path => $html) {
        foreach ($patterns as $label => $re) {
            if (preg_match($re, $html) === 1) {
                $found[] = $label . '  ->  ' . $path;
            }
        }
    }

    return array_values(array_unique($found));
}

$leaks = leak_scan($ready, $CANON);

if ($leaks !== []) {
    say('');
    say('  DURDURULDU - yayinlanmamasi gereken icerik bulundu:');
    foreach (array_slice($leaks, 0, 10) as $l) {
        say('    ' . $l);
    }
    if (count($leaks) > 10) {
        say('    ... ve ' . (count($leaks) - 10) . ' tane daha');
    }
    say('');
    say('  Hata ayiklama araci cubugu geliyorsa kaynak sunucuda kip aciktir.');
    say('  Vhost icindeki "SetEnv RISKOPS_DEBUG 1" satirini kapatin, ya da');
    say('  kipsiz bir sunucudan yakalayin:');
    say('');
    say('    php -S 127.0.0.1:8081 -t ' . $GLOBALS['root']);
    say('    php tools/build_demo_site.php --host=http://127.0.0.1:8081');
    say('');
    exit(1);
}

say('  sizinti denetimi temiz');

foreach ($ready as $file => $html) {
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0755, true);
    }
    file_put_contents($file, $html);
    $written++;
}

say("  {$written} sayfa yazildi");

/* ---------------------------------------------------------------------
 * Varliklar ve veri
 * -------------------------------------------------------------------*/

function copy_tree(string $from, string $to): int
{
    $n = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $item) {
        if (!$item instanceof SplFileInfo) {
            continue;
        }
        $dest = $to . DIRECTORY_SEPARATOR . $it->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($dest)) {
                mkdir($dest, 0755, true);
            }
            continue;
        }
        copy($item->getPathname(), $dest);
        $n++;
    }

    return $n;
}

if ($SKIPASSETS) {
    say('  varliklar atlandi (paylasilan kopya: ' . $ASSETS . ')');
} else {
    $assetCount = copy_tree($root . '/assets', $OUT . '/assets');
    say("  {$assetCount} varlik kopyalandi");
}

if ($charts['code'] === 200) {
    if (!is_dir($OUT . '/api')) {
        mkdir($OUT . '/api', 0755, true);
    }
    file_put_contents($OUT . '/api/dashboard_charts.json', $charts['body']);
    say('  grafik verisi kaydedildi');
}

/* Demo bicemi: uygulamanin CSS'ine dokunmadan, ayri dosya */
if (!$SKIPASSETS) {
    file_put_contents($OUT . '/demo.css', <<<'CSS'
/* RiskOps statik önizleme — yalnızca demo sitesine özgü.
   Uygulamanın kendi CSS'i değiştirilmez: demo, ürünü kirletmemeli. */

.rk-demo-bar {
    position: sticky; top: 0; z-index: 1080;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    padding: 8px 16px;
    background: #041f3c; color: #b6c9de;
    font: 500 12.5px/1.4 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    border-bottom: 1px solid #0b356d;
}
.rk-demo-bar strong { color: #eaf3ff; font-weight: 700; }
.rk-demo-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #23b0f8; box-shadow: 0 0 0 3px rgba(35,176,248,.18);
}
.rk-demo-sep { color: #40597a; }
.rk-demo-home, .rk-demo-repo {
    margin-left: auto; color: #23b0f8; text-decoration: none; font-weight: 600;
}
.rk-demo-repo { margin-left: 14px; }
.rk-demo-home:hover, .rk-demo-repo:hover { text-decoration: underline; }

/* Statik sitede çalışmayan bağlantılar: gizlenmiyor, İŞARETLENİYOR.
   Gizlemek arayüzü yalan söyletirdi; burada "var ama bu önizlemede
   çalışmıyor" demek daha dürüst. */
a.rk-demo-off,
button.rk-demo-off { opacity: .45; cursor: not-allowed; }
a.rk-demo-off:hover { text-decoration: none; }
CSS);
}

say("  demo.css yazildi\n");

/* Dil tercihi kullanici kaydinda kaliyor; kurulumu birakip gittigimiz
   gibi birakiyoruz. */
http($HOST . '/dashboard/?setlocale=tr');

say('  bitti.');
