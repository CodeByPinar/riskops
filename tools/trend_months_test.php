<?php

declare(strict_types=1);

/**
 * RiskOps - ay penceresi regresyon testi
 * tools/trend_months_test.php
 *
 * Korudugu hata: strtotime("-N month") gun tasmasini kirmaz. Ayin
 * 29-31'inde hedef ayda o gun yoksa sonuc bir sonraki aya tasar, ayni
 * Y-m anahtari iki kez uretilir ve pencere kisalir. Olculen etki:
 * 2026-05-31 tabaninda dashboard 12 ay yerine 7 ay, yonetici ozeti
 * 6 ay yerine 4 ay gosteriyordu; kayip aylarin riskleri hicbir
 * sayacta gorunmuyordu.
 *
 * VERITABANI GEREKTIRMEZ: yalnizca includes/functions.php yuklenir.
 *
 *   php tools/trend_months_test.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $note = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("  [%s] %s%s\n", $ok ? 'OK  ' : 'FAIL', $label, $note !== '' ? "  ({$note})" : '');
}

/**
 * Beklenen pencereyi, tasmaya dusmeyen bagimsiz bir yolla uretir.
 *
 * @return list<string>
 */
function expected_months(int $baseTs, int $count): array
{
    $y = (int)date('Y', $baseTs);
    $m = (int)date('n', $baseTs);
    $out = [];
    for ($i = $count - 1; $i >= 0; $i--) {
        $mm = $m - $i;
        $yy = $y;
        while ($mm <= 0) {
        $mm += 12;
        $yy--;
        }
        $out[] = sprintf('%04d-%02d', $yy, $mm);
    }
    return $out;
}

/* ------------------------------------------------------------------ */
echo "\n1) Ay sonu gunleri - hatanin tetiklendigi gunler\n";
/* ------------------------------------------------------------------ */

$probes = [
    '2026-01-31', '2026-03-31', '2026-05-31', '2026-07-31',
    '2026-08-31', '2026-10-31', '2026-12-31', '2026-01-30', '2026-01-29',
];

foreach ($probes as $day) {
    $ts = strtotime($day . ' 12:00:00');
    $m12 = recent_months(12, $ts);
    $m6  = recent_months(6, $ts);

    check("{$day}: 12 ay tam",
        $m12 === expected_months($ts, 12), count($m12) . ' ay');
    check("{$day}: 6 ay tam",
        $m6 === expected_months($ts, 6), count($m6) . ' ay');
}

/* ------------------------------------------------------------------ */
echo "\n2) Artik yil ve yil siniri\n";
/* ------------------------------------------------------------------ */

$leap = strtotime('2024-02-29 12:00:00');
check('2024-02-29: 12 ay tam', recent_months(12, $leap) === expected_months($leap, 12));
check('2024-02-29: en eski ay 2023-03', recent_months(12, $leap)[0] === '2023-03');

$ny = strtotime('2026-01-01 00:30:00');
check('2026-01-01: yil sinirini gecer', recent_months(3, $ny) === ['2025-11', '2025-12', '2026-01']);

/* ------------------------------------------------------------------ */
echo "\n3) Siralama ve sinirlar\n";
/* ------------------------------------------------------------------ */

$base = strtotime('2026-05-31 12:00:00');
$keys = recent_months(12, $base);

check('eskiden yeniye sirali', $keys === array_values($keys) && $keys[0] < $keys[11]);
check('son eleman taban ayi', end($keys) === '2026-05');
check('tekrar eden ay yok', count($keys) === count(array_unique($keys)));

check('count=1 -> tek ay', recent_months(1, $base) === ['2026-05']);
check('count=0 -> en az 1 ay', count(recent_months(0, $base)) === 1);
check('count=-5 -> en az 1 ay', count(recent_months(-5, $base)) === 1);
check('count=500 -> 120 ile sinirli', count(recent_months(500, $base)) === 120);

/* Tek bir sabit taban kullanilir: recent_months(12) ile recent_months(12, null)
   ayri time() cagrilari yapar ve test ay donumunde calisirsa iki cagri farkli
   aya dusup sahte FAIL uretir. */
$fixed = time();
check('null taban = acik taban (ayni damga ile)',
    recent_months(12, $fixed) === recent_months(12, null === null ? $fixed : null));

/* ------------------------------------------------------------------ */
echo "\n4) Tuketici baglantisi\n";
/* ------------------------------------------------------------------ */

/* Yalnizca "recent_months() cagriliyor mu" demek yetmez: pencere dogru
   kurulup SQL alt siniri hala ayri bir strtotime'dan gelirse hata geri
   doner. Bu yuzden $since'in $monthKeys[0]'dan turetildigi de aranir. */

$dash = (string)file_get_contents(__DIR__ . '/../api/dashboard_charts.php');
check('dashboard: recent_months(12) kullaniyor', str_contains($dash, 'recent_months(12)'));
check('dashboard: since pencereden turetiliyor', str_contains($dash, "\$monthKeys[0] . '-01'"));
check('dashboard: eski tasmali kalip kalmadi',
    !str_contains($dash, "strtotime(\"-{\$i} month\")")
    && !str_contains($dash, "strtotime('-11 month')"));

$exec = (string)file_get_contents(__DIR__ . '/../reports/executive_summary.php');
check('yonetici ozeti: recent_months(6) kullaniyor', str_contains($exec, 'recent_months(6)'));
check('yonetici ozeti: since pencereden turetiliyor', str_contains($exec, "\$monthKeys[0] . '-01'"));
check('yonetici ozeti: eski tasmali kalip kalmadi',
    !str_contains($exec, "strtotime(\"-{\$i} month\")")
    && !str_contains($exec, "strtotime('-5 month')"));

/* ------------------------------------------------------------------ */

printf("\n  SONUC: %d OK / %d FAIL\n\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
