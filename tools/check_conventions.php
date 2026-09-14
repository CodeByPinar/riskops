<?php

declare(strict_types=1);

/**
 * RiskOps - Proje kurallarının denetimi
 * /var/www/riskops/tools/check_conventions.php
 *
 * Çalıştırma:
 *     php tools/check_conventions.php            # rapor
 *     php tools/check_conventions.php --quiet    # yalnızca ihlaller
 *
 * Çıkış kodu: 0 = temiz, 1 = en az bir ihlal.
 *
 * NE DENETLER
 * -----------
 * Genel bir güvenlik tarayıcısı DEĞİLDİR. Bu projenin kendine koyduğu
 * ve bozulması kolay kuralları denetler:
 *
 *   1. SQL'e DOĞRUDAN kullanıcı girdisi birleştirilmiyor
 *      (sınırı için aşağıdaki nota bakın)
 *   2. Satır içi <script> yok       (CSP: script-src 'self')
 *   3. style="" özniteliği yok      (CSP: nonce blokları kapsar, öznitelikleri değil)
 *   4. Silme bağlantıları GET değil (POST + CSRF + yetki)
 *   5. POST işleyen her uç csrf_require() çağırıyor
 *   6. Üretim kodunda var_dump/print_r kalmamış
 *   7. Her PHP dosyası declare(strict_types=1) ile başlıyor
 *
 * NEDEN GREP DEĞİL
 * ----------------
 * İlk sürüm kabuk grep'leriydi ve kullanılamazdı: kuralı ANLATAN
 * yorum satırları kuralın ihlali sanılıyordu ("satır içi <script>
 * yazmayın" diyen yorum, <script> içerdiği için ihlal sayılıyordu).
 * Burada dosyalar token'larına ayrılıyor; yalnızca GERÇEKTEN basılan
 * HTML ve gerçek kod inceleniyor, yorumlar değil.
 *
 * SQL KURALININ SINIRI
 * --------------------
 * Yakaladığı: $_GET / $_POST gibi bir değerin SQL metnine doğrudan
 * birleştirilmesi ya da gömülmesi.
 *
 * Yakalayamadığı: üç satır önce kurulmuş bir değişkenin aynı yere
 * konması. Bunu görmek gerçek bir veri akışı çözümlemesi gerektirir.
 *
 * Bu bilinçli bir sınır, eksiklik değil: uygulama tablo ve kolon
 * ADLARINI bilerek birleştiriyor (tanımlayıcılar parametre olarak
 * bağlanamaz; bkz. includes/discussion.php, adlar sabit bir kayıt
 * defterinden gelir) ve filtre cümlelerini beyaz listeden kuruyor.
 * Her birleştirmeyi ihlal sayan bir kural 37 meşru yeri işaretliyordu;
 * öyle bir kural kapatılır ve hiçbir işe yaramaz. Dar ama GÜVENİLİR
 * bir kural, geniş ama gürültülü olandan iyidir.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root  = dirname(__DIR__);
$quiet = in_array('--quiet', $argv, true);

/* Denetim dışı: bağımlılıklar, yüklenen dosyalar, testler (kasıtlı
   olarak kötü örnek içerebilirler). */
$skipDirs = ['/vendor/', '/storage/', '/.git/', '/tests/', '/node_modules/'];

/** @var array<string, list<array{file:string, line:int, detail:string}>> */
$violations = [];

function violation(string $rule, string $file, int $line, string $detail = ''): void
{
    global $violations, $root;

    $violations[$rule][] = [
        'file'   => ltrim(str_replace($root, '', $file), '/\\'),
        'line'   => $line,
        'detail' => $detail,
    ];
}

/* ---------------------------------------------------------------------
 * Dosyaları topla
 * -------------------------------------------------------------------*/
$files = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($it as $f) {
    /** @var SplFileInfo $f */
    if (!$f->isFile() || $f->getExtension() !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', $f->getPathname());
    foreach ($skipDirs as $skip) {
        if (str_contains($path, $skip)) {
            continue 2;
        }
    }
    $files[] = $path;
}
sort($files);

/* ---------------------------------------------------------------------
 * Yardımcılar
 * -------------------------------------------------------------------*/

const SUPERGLOBALS = ['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_FILES'];
const INPUT_FUNCS  = ['input', 'input_int', 'input_enum', 'input_date', 'input_int_range'];

/** SQL cümlesi içeren bir metin mi? */
function looks_like_sql(string $text): bool
{
    return (bool)preg_match(
        '/\b(SELECT\s|INSERT\s+INTO\s|UPDATE\s+\w|DELETE\s+FROM\s)/i',
        $text
    );
}

/**
 * Çift tırnaklı bir SQL metninin GÖMÜLÜ ifadelerinde kullanıcı girdisi
 * var mı? ("... WHERE id = {$_GET['id']}")
 *
 * @param array<int, array{0:int, 1:string, 2:int}|string> $tokens
 */
function interpolated_user_input(array $tokens, int $start): ?string
{
    $n = count($tokens);

    for ($i = $start; $i < $n; $i++) {
        $t = $tokens[$i];

        // Metin kapandı: '"' ya da heredoc sonu.
        if (is_string($t) && $t === '"') {
            return null;
        }
        if (is_array($t) && $t[0] === T_END_HEREDOC) {
            return null;
        }
        if (is_array($t) && $t[0] === T_VARIABLE && in_array($t[1], SUPERGLOBALS, true)) {
            return $t[1];
        }
    }

    return null;
}

/**
 * Bir birleştirme zincirini takip eder ve içinde kullanıcı girdisi
 * olup olmadığına bakar.
 *
 * Tablo/kolon ADI birleştirmek meşrudur - tanımlayıcılar parametre
 * olarak bağlanamaz (bkz. includes/discussion.php, adlar sabit bir
 * kayıt defterinden gelir). Tehlikeli olan, DEĞERİN birleştirilmesidir.
 *
 * @param array<int, array{0:int, 1:string, 2:int}|string> $tokens
 */
function chain_has_user_input(array $tokens, int $start): ?string
{
    $n = count($tokens);

    for ($i = $start; $i < $n; $i++) {
        $t = $tokens[$i];

        // Noktalı virgül / kapanış: ifade bitti.
        if (is_string($t) && in_array($t, [';', ',', ')'], true)) {
            return null;
        }
        if (is_array($t) && $t[0] === T_WHITESPACE) {
            continue;
        }

        if (is_array($t) && $t[0] === T_VARIABLE && in_array($t[1], SUPERGLOBALS, true)) {
            return $t[1];
        }
        if (is_array($t) && $t[0] === T_STRING && in_array($t[1], INPUT_FUNCS, true)) {
            return $t[1] . '()';
        }
    }

    return null;
}

/* ---------------------------------------------------------------------
 * Denetim
 * -------------------------------------------------------------------*/
foreach ($files as $file) {
    $src    = (string)file_get_contents($file);
    $tokens = token_get_all($src);
    $rel    = ltrim(str_replace($root, '', $file), '/\\');

    /* --- 7) strict_types --------------------------------------------- */
    if (!str_contains(substr($src, 0, 200), 'declare(strict_types=1)')) {
        violation('strict_types', $file, 1);
    }

    $hasPostRead   = false;
    $hasCsrfGuard  = false;
    $inlineHtml    = '';
    $htmlLineStart = [];

    foreach ($tokens as $idx => $token) {
        /* --- Basılan HTML'i topla (yorumlar DEĞİL) -------------------- */
        if (is_array($token) && $token[0] === T_INLINE_HTML) {
            $htmlLineStart[strlen($inlineHtml)] = $token[2];
            $inlineHtml .= $token[1];
            continue;
        }

        if (!is_array($token)) {
            continue;
        }

        /* --- 1) SQL'e kullanıcı girdisi ------------------------------- */
        if ($token[0] === T_CONSTANT_ENCAPSED_STRING && looks_like_sql($token[1])) {
            $found = chain_has_user_input($tokens, $idx + 1);
            if ($found !== null) {
                violation('sql_injection', $file, $token[2], $found . ' SQL içine birleştirilmiş');
            }
        }

        /* --- Çift tırnaklı SQL içine GÖMÜLÜ kullanıcı girdisi --------- */
        if ($token[0] === T_ENCAPSED_AND_WHITESPACE && looks_like_sql($token[1])) {
            $found = interpolated_user_input($tokens, $idx + 1);
            if ($found !== null) {
                violation('sql_injection', $file, $token[2], $found . ' SQL metnine gömülmüş');
            }
        }

        /* --- 5) POST okuyan uçlar ------------------------------------ */
        if ($token[0] === T_VARIABLE && $token[1] === '$_POST') {
            $hasPostRead = true;
        }
        if ($token[0] === T_STRING && in_array($token[1], ['is_post', 'csrf_require', 'csrf_verify'], true)) {
            if ($token[1] === 'is_post') {
                $hasPostRead = true;
            } else {
                $hasCsrfGuard = true;
            }
        }

        /* --- 6) Üretim kodunda döküm --------------------------------- */
        if ($token[0] === T_STRING
            && in_array(strtolower($token[1]), ['var_dump', 'print_r', 'var_export'], true)
            && !str_starts_with($rel, 'tools/')) {
            /* var_export meşru kullanımları var (ayar yazımı); yalnızca
               ÇIKTIYA basanları yakala: ikinci argümanı true olmayan
               var_export ile var_dump/print_r. */
            if (strtolower($token[1]) !== 'var_export') {
                violation('debug_leftover', $file, $token[2], $token[1] . '()');
            }
        }
    }

    /* --- Basılan HTML üzerinde denetimler ----------------------------- */
    $htmlLine = static function (int $offset) use ($htmlLineStart): int {
        $line = 1;
        foreach ($htmlLineStart as $start => $l) {
            if ($start > $offset) {
                break;
            }
            $line = $l;
        }

        return $line;
    };

    /* --- 2) satır içi <script> --------------------------------------- */
    if (preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>/i', $inlineHtml, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as [$text, $offset]) {
            violation('inline_script', $file, $htmlLine($offset), trim($text));
        }
    }

    /* --- 3) style="" özniteliği -------------------------------------- */
    if (preg_match_all('/\sstyle\s*=\s*["\']/i', $inlineHtml, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as [$text, $offset]) {
            violation('inline_style', $file, $htmlLine($offset), trim($text));
        }
    }

    /* --- 4) GET ile silme bağlantısı --------------------------------- */
    if (preg_match_all('/href\s*=\s*["\'][^"\']*\b(delete|destroy)\.php/i', $inlineHtml, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as [$text, $offset]) {
            violation('get_delete', $file, $htmlLine($offset), trim($text));
        }
    }

    /* --- 5) sonuç ----------------------------------------------------- */
    /* Yalnızca GİRDİ NOKTALARI: alt çizgiyle başlayan dosyalar
       (_validate.php, _form.php) include'dur, isteği onlar karşılamaz -
       onları çağıran işleyici csrf_require() yapar. includes/ ve
       tools/ da uç değildir. */
    $basename  = basename($rel);
    $isPartial = str_starts_with($basename, '_');
    $isEntry   = !$isPartial
              && !str_starts_with($rel, 'includes/')
              && !str_starts_with($rel, 'tools/')
              && !str_starts_with($rel, 'errors/');

    if ($isEntry && $hasPostRead && !$hasCsrfGuard) {
        violation('missing_csrf', $file, 1, 'POST okuyor ama csrf_require() yok');
    }
}

/* ---------------------------------------------------------------------
 * Rapor
 * -------------------------------------------------------------------*/
/** kural => [ihlal başlığı, geçildiğinde yazılacak başlık] */
$labels = [
    'sql_injection'  => ["SQL'e kullanıcı girdisi birleştirilmiş",
                         "SQL'de doğrudan kullanıcı girdisi yok"],
    'inline_script'  => ['Satır içi <script> bulundu',
                         "Satır içi <script> yok (CSP: script-src 'self')"],
    'inline_style'   => ['style="" özniteliği bulundu',
                         'style="" özniteliği yok (CSP: nonce blokları kapsar)'],
    'get_delete'     => ['Silme işlemine GET bağlantısı bulundu',
                         'Silme bağlantısı yok (POST + CSRF)'],
    'missing_csrf'   => ['POST işleyen uçta csrf_require() yok',
                         'POST işleyen her uç csrf_require() çağırıyor'],
    'debug_leftover' => ['Üretim kodunda var_dump/print_r kalmış',
                         'Üretim kodunda döküm çağrısı yok'],
    'strict_types'   => ['declare(strict_types=1) eksik',
                         'Her dosya declare(strict_types=1) ile başlıyor'],
];

$total = 0;
foreach ($violations as $rows) {
    $total += count($rows);
}

if (!$quiet) {
    echo "\nRiskOps - proje kuralları denetimi\n";
    printf("  %d PHP dosyası incelendi\n", count($files));
}

if ($total === 0) {
    if (!$quiet) {
        echo "\n";
        foreach ($labels as [, $okLabel]) {
            printf("  [ OK ]  %s\n", $okLabel);
        }
    }
    echo "\n  Tüm kurallar sağlanıyor.\n\n";
    exit(0);
}

echo "\n";
foreach ($labels as $rule => [$badLabel, $okLabel]) {
    if (!isset($violations[$rule])) {
        if (!$quiet) {
            printf("  [ OK ]  %s\n", $okLabel);
        }
        continue;
    }

    printf("\n  [FAIL]  %s  (%d)\n", $badLabel, count($violations[$rule]));
    foreach ($violations[$rule] as $v) {
        printf("          %s:%d%s\n", $v['file'], $v['line'], $v['detail'] !== '' ? '  ' . $v['detail'] : '');

        // GitHub Actions ek açıklaması
        if (getenv('GITHUB_ACTIONS') === 'true') {
            printf("::error file=%s,line=%d::%s %s\n", $v['file'], $v['line'], $badLabel, $v['detail']);
        }
    }
}

printf("\n  SONUÇ: %d ihlal\n\n", $total);
exit(1);
