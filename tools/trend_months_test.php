<?php
declare(strict_types=1);

/**
 * RiskOps - Ay bazli trend pencereleri regresyon testi
 * Calistirma:  php tools/trend_months_test.php
 *
 * Bagimlilik YOK: veritabani ve bootstrap gerekmez, yalnizca
 * includes/functions.php icindeki recent_months() test edilir.
 *
 * Kapsanan tuketiciler:
 *   api/dashboard_charts.php      (12 aylik trend penceresi)
 *   reports/executive_summary.php (6 aylik "Son 6 Ay" penceresi)
 *
 * REGRESYON ARKAPLANI: pencereler bir zamanlar
 *     date('Y-m', strtotime("-{$i} month"))
 * ile kuruluyordu. strtotime ay aritmetiginde gun tasmasini kirmaz;
 * ayin 29-31'inde hedef ayda o gun yoksa sonuc bir SONRAKI aya tasiyor,
 * ayni 'Y-m' anahtari iki kez uretiliyor ve pencere kuculuyordu
 * (dashboard 7-11 aya, yonetici ozeti 3-5 aya dusuyordu; kayip
 * aylarin riskleri hic sayilmiyordu).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../includes/functions.php';

$PASS = 0;
$FAIL = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $PASS, $FAIL;
    if ($ok) {
        $PASS++;
        printf("  [ OK ]  %-52s %s\n", $label, $detail);
    } else {
        $FAIL++;
        printf("  [FAIL]  %-52s %s\n", $label, $detail);
    }
}

function section(string $title): void
{
    echo "\n" . str_repeat('-', 72) . "\n  " . $title . "\n" . str_repeat('-', 72) . "\n";
}

/**
 * ORACLE — beklenen ay listesi mktime() ay normalizasyonuyla hesaplanir
 * (recent_months icindeki kodun bagimsizi).
 */
function expected_months(int $baseTs, int $count): array
{
    [$y, $m] = array_map('intval', explode('-', date('Y-m', $baseTs)));
    $out = [];
    for ($k = $count - 1; $k >= 0; $k--) {
        $out[] = date('Y-m', mktime(1, 1, 1, $m - $k, 1, $y));
    }
    return $out;
}

echo "\n=============== Trend Month Window Regression Test ===============";

/* ------------------------------------------------------------------ */
section('1. Ay sonu gunlerinde pencere (regresyon)');
/* ------------------------------------------------------------------ */

// Hatali davranisin kanitlandigi gunler: 29-31 (hedef ay kisa ise tasma).
$probes = [
    '2026-05-31', '2026-03-31', '2026-08-31', '2025-12-31',
    '2026-01-31', '2026-01-30', '2024-02-29', '2026-02-28',
    '2026-10-31', '2026-04-30', '2025-11-30', '2026-12-31',
];

foreach ($probes as $d) {
    $bts = strtotime($d . ' 12:00:00');
    $got = recent_months(12, $bts);
    check(
        "12 ay: {$d}",
        $got === expected_months($bts, 12) && count(array_unique($got)) === 12,
        count($got) . ' ay'
    );
}

/* ------------------------------------------------------------------ */
section('2. Sinir ve ikincil dallar');
/* ------------------------------------------------------------------ */

$now = time();
check('bugun (varsayilan taban): 12 ay',
    recent_months(12) === expected_months($now, 12));
check('taban null acik gecilirse ayni sonuc',
    recent_months(12, null) === recent_months(12));
check('count=1 yalnizca bulunulan ay',
    recent_months(1, $now) === [date('Y-m', $now)]);
check('count=0 bos liste', recent_months(0, $now) === []);
check('count negatif bos liste', recent_months(-3, $now) === []);
check('count=13 yil sinirini asar',
    recent_months(13, strtotime('2026-01-15 12:00:00'))
        === expected_months(strtotime('2026-01-15 12:00:00'), 13));
check('count=6 yonetici ozeti penceresi (2026-05-31 taban)',
    recent_months(6, strtotime('2026-05-31 12:00:00'))
        === expected_months(strtotime('2026-05-31 12:00:00'), 6));
check('anahtarlar eskiden yeniye sirali',
    recent_months(12, $now) === array_values(array_sort(recent_months(12, $now))));

/* SQL :since degeri: en eski ayin 1'i (api/dashboard_charts.php tuketimi) */
$keys = recent_months(12, strtotime('2026-05-31 12:00:00'));
check('since = en eski ayin 1\'i (2026-05-31 taban)',
    $keys[0] . '-01' === '2025-06-01', $keys[0] . '-01');

/* ------------------------------------------------------------------ */
section('3. Uc nokta baglantisi');
/* ------------------------------------------------------------------ */

$src = (string)file_get_contents(__DIR__ . '/../api/dashboard_charts.php');
check('api/dashboard_charts.php recent_months() kullaniyor',
    str_contains($src, 'recent_months(12)'));
check('dashboard bozuk strtotime("-N month") ifadesi kaldirildi',
    !str_contains($src, 'strtotime("-{'));

$srcExec = (string)file_get_contents(__DIR__ . '/../reports/executive_summary.php');
check('reports/executive_summary.php recent_months() kullaniyor',
    str_contains($srcExec, 'recent_months(6)'));
check('yonetici ozeti bozuk strtotime ay ifadeleri kaldirildi',
    !str_contains($srcExec, 'strtotime("-{') && !str_contains($srcExec, "strtotime('-5 month')"));

/* ------------------------------------------------------------------ */

echo "\n" . str_repeat('-', 72) . "\n";
printf("Sonuc: %d OK, %d FAIL\n", $PASS, $FAIL);
if ($FAIL > 0) {
    echo "TREND MONTH WINDOW TEST: FAIL\n";
    exit(1);
}
echo "TREND MONTH WINDOW TEST: PASS\n";
exit(0);

/** Kucukten buyuge siralar (strcmp) — 'Y-m' anahtarlari icin yeterli. */
function array_sort(array $a): array
{
    usort($a, static fn(string $x, string $y) => strcmp($x, $y));
    return $a;
}
