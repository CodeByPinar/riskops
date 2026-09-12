<?php
declare(strict_types=1);

/**
 * RiskOps - Denetim kaydı CSV dışa aktarma
 * /var/www/riskops/admin/audit_logs/export_csv.php?format=excel|raw&<filtreler>
 *
 * Ekrandaki filtrelerin AYNISINI kullanır (_filters.php): indirilen
 * dosya ile görünen liste ayrışamaz. Denetim çıktısında bu şart.
 *
 * İKİ BİÇİM — reports/export_csv.php ile aynı kurallar:
 *   excel : UTF-8 BOM + sep=; + künye bloğu + yerel tarih
 *   raw   : RFC 4180, virgül, BOM yok, künye yok, ISO tarih
 *
 * BELLEK
 * ------
 * Denetim kaydı yüz binlerce satıra çıkabilir. Sorgu TAMPONSUZ
 * çalıştırılıyor (MYSQL_ATTR_USE_BUFFERED_QUERY = false): satırlar
 * sunucudan tek tek çekilip doğrudan çıktıya yazılıyor, tamamı belleğe
 * alınmıyor. Tamponlu çalıştırılsaydı büyük bir tabloda PHP bellek
 * sınırına çarpar ve dışa aktarma tam da en çok gerektiği anda
 * (büyük veri) başarısız olurdu.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_filters.php';

require_login();
require_role(ROLE_ADMIN);

$flt    = audit_filters();
$format = input_enum('format', ['excel', 'raw'], 'excel');
$isExcel = ($format === 'excel');
$sep     = $isExcel ? ';' : ',';

$docCode = 'RO-AUDIT-' . date('Ymd-Hi');

/* Toplam satiri once sayilir: kunye blogunda yazmak icin ve
   islemin buyuklugunu audit'e kaydetmek icin. */
$countStmt = db()->prepare("SELECT COUNT(*) FROM audit_logs l WHERE {$flt['where']}");
$countStmt->execute($flt['params']);
$total = (int)$countStmt->fetchColumn();

/* DIKKAT: bu islem de denetlenir. Denetim kaydini kimin disari
   aktardigi, denetim kaydinin kendisi kadar onemlidir. */
audit('audit_exported', 'audit_log', null, null, [
    'format'  => $format,
    'rows'    => $total,
    'filters' => array_filter($flt['f'], static fn($v) => $v !== null && $v !== ''),
    'doc'     => $docCode,
]);

$company = trim((string)setting('company_name', ''));
$filename = 'riskops-audit-' . date('Ymd-Hi') . '.' . $format . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

while (ob_get_level() > 0) {
    ob_end_clean();
}

$out = fopen('php://output', 'wb');

if ($isExcel) {
    fwrite($out, "\xEF\xBB\xBF");   // BOM: Excel BOM'suz UTF-8'i Windows-1254 sanar
    fwrite($out, "sep=;\r\n");      // ayraci acikca bildir
}

/**
 * Hücreyi CSV'ye yazılabilir metne çevirir.
 *
 * FORMÜL ENJEKSİYONU: = + - @ ile başlayan hücreler Excel'de formül
 * olarak çalışır. Denetim kaydı kullanıcı girdisi içerdiği için bu
 * gerçek bir yol: bir risk başlığına "=cmd|..." yazan biri, raporu
 * açan kişinin makinesinde komut çalıştırmayı deneyebilir.
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

/* ------------------------------------------------------------------ */
/* excel: künye bloğu                                                  */
/* ------------------------------------------------------------------ */

if ($isExcel) {
    $writeRow([app_name() . ($company !== '' ? ' — ' . $company : '')]);
    $writeRow(['Denetim Kaydı (Audit Log)']);
    $writeRow([]);
    $writeRow(['Belge no', $docCode]);
    $writeRow(['Oluşturulma', format_datetime(date('Y-m-d H:i:s'))]);
    $writeRow(['Oluşturan', auth_user()['name'] ?? '']);

    $f = $flt['f'];
    $writeRow(['İşlem filtresi',  $f['action'] ?? 'tümü']);
    $writeRow(['Varlık filtresi', $f['entity'] ?? 'tümü']);
    $writeRow([
        'Tarih aralığı',
        ($f['from'] !== null || $f['to'] !== null)
            ? ($f['from'] !== null ? format_date($f['from']) : 'başlangıçtan')
              . ' – ' . ($f['to'] !== null ? format_date($f['to']) : 'bugüne')
            : 'tümü',
    ]);
    $writeRow(['Arama', $f['q'] ?? '-']);

    $classification = trim((string)setting('report_classification', ''));
    if ($classification !== '') {
        $writeRow(['Gizlilik', $classification]);
    }

    $writeRow(['Satır sayısı', (string)$total]);
    $writeRow([]);
}

/* ------------------------------------------------------------------ */
/* Başlık satırı                                                       */
/* ------------------------------------------------------------------ */

$headers = ['Tarih', 'Kullanıcı', 'İşlem', 'Varlık', 'Varlık ID',
            'Eski Değerler', 'Yeni Değerler', 'IP', 'Tarayıcı'];

if ($isExcel) {
    array_unshift($headers, '#');
}
$writeRow($headers);

/* ------------------------------------------------------------------ */
/* Veri — tamponsuz                                                     */
/* ------------------------------------------------------------------ */

$pdo = db();
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

try {
    $stmt = $pdo->prepare(
        "SELECT l.created_at, l.user_name_snapshot,
                l.action, l.entity_type, l.entity_id,
                l.old_values, l.new_values, l.ip_address, l.user_agent
           FROM audit_logs l
          WHERE {$flt['where']}
          ORDER BY l.id DESC"
    );
    $stmt->execute($flt['params']);

    $i = 0;
    while (($r = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        $line = [];

        if ($isExcel) {
            $line[] = (string)(++$i);
        }

        $line[] = $isExcel
            ? format_datetime($r['created_at'])
            : (string)$r['created_at'];

        $line[] = (string)($r['user_name_snapshot'] ?? '');
        $line[] = (string)$r['action'];
        $line[] = (string)($r['entity_type'] ?? '');
        $line[] = (string)($r['entity_id'] ?? '');
        $line[] = (string)($r['old_values'] ?? '');
        $line[] = (string)($r['new_values'] ?? '');
        $line[] = (string)($r['ip_address'] ?? '');
        $line[] = (string)($r['user_agent'] ?? '');

        $writeRow($line);
    }

    $stmt->closeCursor();
} finally {
    /* Tamponsuz kip baglanti genelinde gecerlidir; birakilirsa sonraki
       her sorgu bu kipte calisir. Istisna atilsa bile geri alinmali. */
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
}

fclose($out);
exit;
