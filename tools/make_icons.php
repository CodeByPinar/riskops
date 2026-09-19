<?php

declare(strict_types=1);

/**
 * RiskOps - Marka varlık ureteci
 * /var/www/riskops/tools/make_icons.php
 *
 * KAYNAK (web'e kapali, storage/brand/.htaccess ile engelli):
 *   storage/brand/logo-icon.png      -> kare uygulama ikonu
 *   storage/brand/logo-wordmark.png  -> tam logo
 *
 * URETILEN (assets/img/, web'e açık):
 *   favicon-16.png  favicon-32.png       tarayici sekmesi
 *   apple-touch-icon.png (180)           iOS ana ekran
 *   icon-192.png  icon-512.png           PWA / Android
 *   logo-icon-64.png                     sidebar + login ikonu (30px @2x)
 *   logo-wordmark.png (yukseklik 120)    açık zeminler, rapor başlıkları
 *
 * Calistirma: php /var/www/riskops/tools/make_icons.php
 * Logo değiştiğinde tekrar çalıştırılır.
 *
 * NEDEN boyle: kaynak dosyalar 1 MB'in uzerinde. Her sayfa yuklemesinde
 * 30px'lik bir ikon için 1 MB indirmek kabul edilemez. Kaynak web disinda
 * tutulur, yalnızca kucultulmus turevler servis edilir.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../includes/bootstrap.php';

if (!extension_loaded('gd')) {
    fwrite(STDERR, "HATA: PHP gd eklentisi yuklu degil.\n");
    exit(1);
}

$brandDir = STORAGE_PATH . '/brand';
$outDir   = APP_ROOT . '/assets/img';

if (!is_dir($outDir) && !@mkdir($outDir, 0755, true)) {
    fwrite(STDERR, "HATA: {$outDir} olusturulamadi.\n");
    exit(1);
}

/** PNG yukler, doğrular. */
function load_png(string $path): GdImage
{
    if (!is_file($path)) {
        fwrite(STDERR, "HATA: Kaynak bulunamadı -> {$path}\n");
        exit(1);
    }
    $info = getimagesize($path);
    if ($info === false || $info[2] !== IMAGETYPE_PNG) {
        fwrite(STDERR, "HATA: Geçerli bir PNG degil -> {$path}\n");
        exit(1);
    }
    $img = imagecreatefrompng($path);
    if ($img === false) {
        fwrite(STDERR, "HATA: PNG okunamadi -> {$path}\n");
        exit(1);
    }
    return $img;
}

/**
 * Renk ayirir; GD basarisiz olursa betik durur.
 *
 * imagecolorallocatealpha() palet dolarsa false doner ve o deger
 * dogrudan imagesetpixel()'e giriyor - false sessizce "renk 0" diye
 * yorumlanip ciktiyi bozardi. Bilesenler ayrica araliga kirpiliyor:
 * yuvarlamadan 256 cikmasi gecerli bir renk degil.
 */
function color(GdImage $img, int $r, int $g, int $b, int $a): int
{
    $c = imagecolorallocatealpha(
        $img,
        max(0, min(255, $r)),
        max(0, min(255, $g)),
        max(0, min(255, $b)),
        max(0, min(127, $a))
    );

    if ($c === false) {
        fwrite(STDERR, "HATA: renk ayrilamadi\n");
        exit(1);
    }

    return $c;
}

/** Saydamligi koruyarak yeniden boyutlandirir ve yazar. */
function write_resized(GdImage $src, string $path, int $width, int $height): void
{
    /* 0 ya da negatif boyutlu bir ikon istenmis olamaz; istenmisse
       cagiran taraf bozuk ve sessizce bos PNG uretmek yanlis olur. */
    if ($width < 1 || $height < 1) {
        fwrite(STDERR, "HATA: gecersiz ikon boyutu {$width}x{$height}\n");
        exit(1);
    }

    $dst = imagecreatetruecolor($width, $height);

    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = color($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $width, $height, $transparent);
    imagealphablending($dst, true);

    imagecopyresampled(
        $dst, $src,
        0, 0, 0, 0,
        $width, $height,
        imagesx($src), imagesy($src)
    );

    if (!imagepng($dst, $path, 9)) {
        fwrite(STDERR, "  HATA: " . basename($path) . " yazilamadi\n");
        imagedestroy($dst);
        return;
    }
    imagedestroy($dst);
    @chmod($path, 0644);

    printf("  [OK] %-24s %4dx%-4d  %7s\n",
        basename($path), $width, $height, number_format((float)filesize($path)) . ' B');
}

/* ------------------------------------------------------------------ */
/* 1) Kare ikon turevleri                                              */
/* ------------------------------------------------------------------ */

$iconSrc = load_png($brandDir . '/logo-icon.png');
printf("Kaynak ikon    : %dx%d\n", imagesx($iconSrc), imagesy($iconSrc));

if (imagesx($iconSrc) !== imagesy($iconSrc)) {
    fwrite(STDERR, "UYARI: Ikon kare degil, orani bozulabilir.\n");
}

echo "\nKare ikon turevleri:\n";
foreach ([
    'favicon-16.png'        => 16,
    'favicon-32.png'        => 32,
    'logo-icon-64.png'      => 64,
    'apple-touch-icon.png'  => 180,
    'icon-192.png'          => 192,
    'icon-512.png'          => 512,
] as $name => $size) {
    write_resized($iconSrc, $outDir . '/' . $name, $size, $size);
}
imagedestroy($iconSrc);

/* ------------------------------------------------------------------ */
/* 2) Wordmark                                                         */
/* ------------------------------------------------------------------ */

$wordSrc = load_png($brandDir . '/logo-wordmark.png');
$wW = imagesx($wordSrc);
$wH = imagesy($wordSrc);
printf("\nKaynak wordmark: %dx%d\n", $wW, $wH);

echo "\nWordmark - ACIK zemin (orijinal renkler):\n";
// CSS'te yukseklik 26-34px; 120px kaynak retina ekranlarda bile net kalir.
foreach ([120, 240] as $targetH) {
    $targetW = (int)round($wW * $targetH / $wH);
    $name = $targetH === 120 ? 'logo-wordmark.png' : 'logo-wordmark@2x.png';
    write_resized($wordSrc, $outDir . '/' . $name, $targetW, $targetH);
}

/* ------------------------------------------------------------------ */
/* 3) KOYU zemin varyanti                                              */
/*                                                                      */
/* Wordmark'taki "Risk" kismi #041F3C laciverttir ve koyu sidebar       */
/* uzerinde okunmaz. Lacivert pikseller BEYAZA cevrilir, mavi           */
/* pikseller korunur (okunurluk için hafifce aydinlatilir).             */
/*                                                                      */
/* Ayrim luminans üzerinden yapılır:                                    */
/*   L < 38  -> lacivert govde -> beyaz                                 */
/*   L > 52  -> mavi aile      -> korunur + %18 beyaz karisim           */
/*   arasi   -> smoothstep geçiş (antialias kenarlari bozulmaz)         */
/* ------------------------------------------------------------------ */

$light = imagecreatetruecolor($wW, $wH);
imagealphablending($light, false);
imagesavealpha($light, true);

for ($y = 0; $y < $wH; $y++) {
    for ($x = 0; $x < $wW; $x++) {
        $rgba = imagecolorat($wordSrc, $x, $y);
        $a = ($rgba >> 24) & 0x7F;

        if ($a === 127) {
            imagesetpixel($light, $x, $y, color($light, 0, 0, 0, 127));
            continue;
        }

        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;

        $lum = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        // smoothstep(38, 52, lum)
        $t = max(0.0, min(1.0, ($lum - 38.0) / 14.0));
        $t = $t * $t * (3.0 - 2.0 * $t);

        // t=0 -> tam beyaz ; t=1 -> orijinal renk + %18 beyaz
        $mix = 1.0 - (0.82 * $t);

        imagesetpixel($light, $x, $y, color(
            $light,
            (int)round($r + (255 - $r) * $mix),
            (int)round($g + (255 - $g) * $mix),
            (int)round($b + (255 - $b) * $mix),
            $a
        ));
    }
}

echo "\nWordmark - KOYU zemin (açık varyant):\n";
foreach ([120, 240] as $targetH) {
    $targetW = (int)round($wW * $targetH / $wH);
    $name = $targetH === 120 ? 'logo-wordmark-light.png' : 'logo-wordmark-light@2x.png';
    write_resized($light, $outDir . '/' . $name, $targetW, $targetH);
}

imagedestroy($light);
imagedestroy($wordSrc);

/* ------------------------------------------------------------------ */

echo "\nTamamlandi. Tarayicida Ctrl+F5 ile onbellegi temizleyin.\n";
