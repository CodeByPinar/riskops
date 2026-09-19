<?php

declare(strict_types=1);

/**
 * RiskOps - Çeviri sözlüğü denetimi
 * /var/www/riskops/tools/i18n_check.php
 *
 * Kullanım:  php tools/i18n_check.php
 *
 * ÜÇ SORU
 *
 *   1. TEKRAR EDEN ANAHTAR VAR MI?            (hata — çıkış kodu 1)
 *      PHP bir dizide aynı anahtar iki kez yazılırsa uyarı vermez,
 *      SESSİZCE sonuncuyu tutar. Çeviri anahtarı kaynak metnin
 *      kendisi olduğu için (ADR-0002) bu kolayca olur: aynı Türkçe
 *      kelime iki ekranda geçer, iki farklı İngilizce karşılık
 *      yazılır, biri kaybolur. Kimse fark etmez çünkü ekran yine
 *      İngilizce görünür — sadece yanlış İngilizce.
 *
 *   2. KARŞILIĞI OLMAYAN ANAHTAR VAR MI?      (uyarı)
 *      Bunlar bozuk değil: karşılığı olmayan metin Türkçe görünür
 *      (ADR-0002'nin asıl amacı). Ama listesi bilinmeli.
 *
 *   3. KULLANILMAYAN KARŞILIK VAR MI?         (uyarı)
 *      Ekran silinmiş ya da metin değişmiş olabilir. Zararsız ama
 *      sözlüğü şişiriyor ve "bu çevrilmiş" yanılsaması veriyor.
 *
 *      DİKKAT — BU LİSTE KESİN DEĞİLDİR: tarayıcı yalnızca metin
 *      sabitini görür. `te(role_label($u['role']))` gibi DEĞİŞKEN
 *      argümanlı çağrılarda anahtar çalışma zamanında belli olur,
 *      yani "kullanılmıyor" görünen bir karşılık aslında
 *      kullanılıyor olabilir. Bu yüzden rapor edilir, silinmez;
 *      kaç dinamik çağrı olduğu da yazdırılıyor.
 *
 * Token tabanlı: yorumdaki örnek çağrılar sayılmaz.
 */

require_once __DIR__ . '/../config/config.php';

$root = dirname(__DIR__);
$fail = false;

/* ---------------------------------------------------------------------
 * 1) Sözlük dosyalarında tekrar eden anahtar
 * -------------------------------------------------------------------*/

/**
 * Sözlük dosyasındaki en dış dizi anahtarlarını sırayla döndürür.
 *
 * @return list<string>
 */
function i18n_keys_in_file(string $path): array
{
    $tokens = token_get_all((string)file_get_contents($path));
    $n      = count($tokens);
    $keys   = [];
    $depth  = 0;

    for ($i = 0; $i < $n; $i++) {
        $tk = $tokens[$i];

        if ($tk === '[') {
            $depth++;
            continue;
        }
        if ($tk === ']') {
            $depth--;
            continue;
        }
        if ($depth !== 1 || !is_array($tk) || $tk[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }

        $j = $i + 1;
        while (isset($tokens[$j]) && is_array($tokens[$j])
               && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $j++;
        }
        if (!isset($tokens[$j]) || !is_array($tokens[$j]) || $tokens[$j][0] !== T_DOUBLE_ARROW) {
            continue;
        }

        $keys[] = stripcslashes(substr($tk[1], 1, -1));
    }

    return $keys;
}

echo "\n";
echo "======================================================================\n";
echo "  ÇEVİRİ SÖZLÜĞÜ DENETİMİ\n";
echo "======================================================================\n\n";

foreach (glob($root . '/lang/*.php') ?: [] as $file) {
    $keys  = i18n_keys_in_file($file);
    $dupes = array_filter(array_count_values($keys), static fn (int $c): bool => $c > 1);
    $name  = basename($file);

    if ($dupes === []) {
        printf("  [ OK ]  %-10s %d anahtar, tekrar yok\n", $name, count($keys));
        continue;
    }

    $fail = true;
    printf("  [FAIL]  %-10s %d tekrar eden anahtar:\n", $name, count($dupes));
    foreach ($dupes as $k => $c) {
        printf("            x%d  %s\n", $c, $k);
    }
}

/* ---------------------------------------------------------------------
 * 2-3) Kullanım ile sözlüğü karşılaştır
 * -------------------------------------------------------------------*/

/**
 * Kodda geçen t()/te() anahtarlarını toplar.
 *
 * @return array{0: array<string, list<string>>, 1: int}
 *         [anahtar => dosyalar, degisken argumanli cagri sayisi]
 */
function i18n_used_keys(string $root): array
{
    $skip = ['.git', 'vendor', 'node_modules', 'storage', 'lang', 'docker'];
    $used = [];
    $dynamic = 0;

    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $f): bool => !in_array($f->getFilename(), $skip, true)
        )
    );

    foreach ($it as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $tokens = token_get_all((string)file_get_contents($file->getPathname()));
        $n      = count($tokens);

        for ($i = 0; $i < $n; $i++) {
            $tk = $tokens[$i];
            if (!is_array($tk) || $tk[0] !== T_STRING || ($tk[1] !== 't' && $tk[1] !== 'te')) {
                continue;
            }
            if (($tokens[$i + 1] ?? null) !== '(') {
                continue;
            }
            $arg = $tokens[$i + 2] ?? null;
            if (!is_array($arg) || $arg[0] !== T_CONSTANT_ENCAPSED_STRING) {
                /* Degisken ya da fonksiyon argumanli cagri: anahtar
                   calisma zamaninda belli oluyor, burada gorulemez. */
                $dynamic++;
                continue;
            }
            $prev = $tokens[$i - 1] ?? null;
            if (is_array($prev)
                && in_array($prev[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
                continue;
            }

            $key = stripcslashes(substr($arg[1], 1, -1));
            $rel = str_replace($root . '/', '', $file->getPathname());
            $used[$key][] = $rel;
        }
    }

    ksort($used);

    return [$used, $dynamic];
}

[$used, $dynamic] = i18n_used_keys($root);
$dict = require $root . '/lang/en.php';

/* Testler bilerek sözlükte OLMAYAN anahtarlar kullanıyor: geri düşme
   davranışını sınıyorlar. Eksik sayılmamalılar. */
$missing = [];
foreach (array_diff_key($used, $dict) as $key => $files) {
    $onlyTests = array_filter($files, static fn (string $f): bool => !str_starts_with($f, 'tests/'));
    if ($onlyTests !== []) {
        $missing[$key] = $onlyTests;
    }
}

$unused = array_diff_key($dict, $used);

printf("\n  kullanılan %d  ·  sözlükte %d  ·  karşılıksız %d  ·  kullanılmayan %d\n\n",
    count($used), count($dict), count($missing), count($unused));

if ($missing !== []) {
    echo "  KARŞILIĞI YOK (Türkçe görünecek):\n";
    foreach ($missing as $k => $files) {
        printf("    %-52s %s\n", mb_strimwidth($k, 0, 50, '…'), $files[0]);
    }
    echo "\n";
}

if ($unused !== []) {
    printf("  SÖZLÜKTE VAR, SABİT OLARAK KULLANILMIYOR"
        . " (%d değişken argümanlı çağrı var; bazıları oradan geliyor olabilir):\n",
        $dynamic);
    foreach (array_keys($unused) as $k) {
        printf("    %s\n", mb_strimwidth((string)$k, 0, 60, '…'));
    }
    echo "\n";
}

echo $fail
    ? "  SONUÇ: tekrar eden anahtar var — düzeltilmeli.\n\n"
    : "  SONUÇ: sözlük tutarlı.\n\n";

exit($fail ? 1 : 0);
