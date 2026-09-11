<?php
declare(strict_types=1);

/**
 * RiskOps - Rapor CSV dışa aktarma
 * /var/www/riskops/reports/export_csv.php?r=<anahtar>&format=<excel|raw>
 *
 * Ekrandaki rapor ile AYNI tanımı kullanır (_reports.php): indirilen
 * dosya ile görünen tablo ayrışamaz.
 *
 * İKİ BİÇİM
 * ---------
 * excel (varsayılan) — insan için, Türkçe Excel'de çift tıkla açılır:
 *     UTF-8 BOM        Excel BOM'suz UTF-8'i Windows-1254 sanar
 *     sep=;            Excel'e ayracı açıkça bildirir; kullanıcının
 *                      yerel ayarı ne olursa olsun doğru ayrışır
 *     ; ayraç          Türkçe Excel virgülü ondalık ayracı sayar
 *     8,8              ondalık ayraç VİRGÜL; nokta kullanılırsa Excel
 *                      "8.8" değerini 88 olarak okur
 *     26.10.2026       tarihler yerel biçimde
 *     künye bloğu      rapor adı, belge no, kapsam, satır sayısı
 *     sıra numarası    ilk kolon
 *
 * raw — makine için, RFC 4180:
 *     virgül ayraç, BOM yok, künye yok, tek başlık satırı,
 *     tarihler ISO (2026-10-26), ondalık ayraç NOKTA.
 *     Başka bir sisteme beslenecekse bu biçim kullanılır.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_reports.php';

require_login();
require_can('report.view');

$defs = report_definitions();
$key  = input_enum('r', array_keys($defs));

if ($key === null) {
    app_abort(400, 'Unknown report key');
}

$def    = $defs[$key];
$format = input_enum('format', ['excel', 'raw'], 'excel');
$from   = input_date('from');
$to     = input_date('to');

if ($from !== null && $to !== null && $from > $to) {
    $from = $to = null;
}

$result = report_run($def, $from, $to);
$rows   = $result['rows'];

$docCode = 'RO-' . mb_strtoupper(mb_substr(str_replace('_', '', $key), 0, 6), 'UTF-8')
         . '-' . date('Ymd-Hi');

audit('report_exported', 'report', null, null, [
    'report' => $key,
    'format' => $format,
    'rows'   => count($rows),
    'from'   => $from,
    'to'     => $to,
    'doc'    => $docCode,
]);

/* ------------------------------------------------------------------ */
/* Biçim parametreleri                                                 */
/* ------------------------------------------------------------------ */

$isExcel = ($format === 'excel');
$sep     = $isExcel ? ';' : ',';

$company    = trim((string)setting('company_name', ''));
$slugSource = $company !== '' ? $company : app_name();
$slug       = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(
    strtr($slugSource, ['ı'=>'i','İ'=>'i','ş'=>'s','Ş'=>'s','ğ'=>'g','Ğ'=>'g',
                        'ü'=>'u','Ü'=>'u','ö'=>'o','Ö'=>'o','ç'=>'c','Ç'=>'c']),
    'UTF-8'
)) ?: 'riskops';

$filename = sprintf(
    '%s-%s-%s.%s.csv',
    trim($slug, '-'),
    str_replace('_', '-', $key),
    date('Ymd-Hi'),
    $format
);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'wb');

if ($isExcel) {
    fwrite($out, "\xEF\xBB\xBF");   // BOM
    fwrite($out, "sep=;\r\n");      // Excel'e ayracı bildir
}

/**
 * Bir hücreyi CSV'ye yazılabilir metne çevirir.
 *
 * FORMÜL ENJEKSİYONU: = + - @ ile başlayan hücreler Excel'de formül
 * olarak çalışır. Başına tek tırnak konarak metne zorlanır.
 */
$escape = static function ($v) use ($sep): string {
    $v = (string)$v;

    if ($v !== '' && str_contains('=+-@', $v[0])) {
        $v = "'" . $v;
    }
    if (str_contains($v, '"') || str_contains($v, $sep)
        || str_contains($v, "\n") || str_contains($v, "\r")) {
        return '"' . str_replace('"', '""', $v) . '"';
    }
    return $v;
};

$writeRow = static function (array $fields) use ($out, $escape, $sep): void {
    fwrite($out, implode($sep, array_map($escape, $fields)) . "\r\n");
};

/**
 * Kolon adına göre değeri biçimlendirir.
 *
 * excel: tarih yerel biçimde, ondalık ayraç virgül
 * raw  : tarih ISO, ondalık ayraç nokta (değer olduğu gibi)
 */
$formatValue = static function (string $column, $value) use ($isExcel): string {
    if ($value === null || $value === '') {
        return '';
    }

    if (str_ends_with($column, '_date')) {
        return $isExcel ? format_date((string)$value) : (string)$value;
    }
    if (str_ends_with($column, '_at')) {
        return $isExcel ? format_datetime((string)$value) : (string)$value;
    }

    // Ondalıklı sayı: Excel biçiminde ayraç virgül olmalı
    if ($isExcel && is_numeric($value) && str_contains((string)$value, '.')) {
        return str_replace('.', ',', (string)$value);
    }

    return (string)$value;
};

/* ------------------------------------------------------------------ */
/* excel: künye bloğu                                                  */
/* ------------------------------------------------------------------ */

if ($isExcel) {
    $writeRow([app_name() . ($company !== '' ? ' — ' . $company : '')]);
    $writeRow([$def['title']]);
    $writeRow([$def['description']]);
    $writeRow([]);
    $writeRow(['Belge no', $docCode]);
    $writeRow(['Oluşturulma', format_datetime(date('Y-m-d H:i:s'))]);
    $writeRow(['Oluşturan', auth_user()['name'] ?? '']);
    $writeRow([
        'Tarih aralığı',
        ($from !== null || $to !== null)
            ? ($from !== null ? format_date($from) : 'başlangıçtan')
              . ' – ' . ($to !== null ? format_date($to) : 'bugüne')
            : 'tümü',
    ]);

    $classification = trim((string)setting('report_classification', ''));
    if ($classification !== '') {
        $writeRow(['Gizlilik', $classification]);
    }

    $writeRow(['Satır sayısı', (string)count($rows)]);
    $writeRow([]);
}

/* ------------------------------------------------------------------ */
/* Başlık satırı                                                       */
/* ------------------------------------------------------------------ */

$headers = array_values($def['columns']);
if ($isExcel) {
    array_unshift($headers, '#');
}
$writeRow($headers);

/* ------------------------------------------------------------------ */
/* Veri                                                                */
/* ------------------------------------------------------------------ */

$i = 0;
foreach ($rows as $row) {
    $line = [];
    if ($isExcel) {
        $line[] = (string)(++$i);
    }
    foreach (array_keys($def['columns']) as $col) {
        $line[] = $formatValue($col, $row[$col] ?? null);
    }
    $writeRow($line);
}

/* ------------------------------------------------------------------ */
/* Toplam satırı                                                       */
/* ------------------------------------------------------------------ */

/* Toplam satırı YALNIZCA excel biçiminde. Ham biçim bir veri setidir;
   içine özet satırı karıştırmak onu tüketen sistemi bozar. */
if ($isExcel && !empty($def['total_row']) && $rows !== []) {
    $totals = [''];   // sıra numarası kolonu boş
    $first  = true;
    foreach (array_keys($def['columns']) as $col) {
        if ($first) {
            $totals[] = 'TOPLAM';
            $first = false;
            continue;
        }
        $totals[] = in_array($col, $def['sum_columns'] ?? [], true)
            ? (string)array_sum(array_column($rows, $col))
            : '';
    }
    $writeRow($totals);
}

fclose($out);
exit;
