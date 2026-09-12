<?php
declare(strict_types=1);

/**
 * RiskOps - Ek indirme
 * /var/www/riskops/risks/attachment_download.php?id=<ek id>
 *
 * YETKİ: risk.view  (riski görebilen ekini de indirebilir)
 *
 * NEDEN PHP ÜZERİNDEN: dosyalar storage/ altında ve web erişimine
 * kapalı. Doğrudan bağlantı verilseydi, bağlantıyı ele geçiren herkes
 * — giriş yapmamış olsa bile — dosyaya ulaşırdı.
 *
 * HER EK ZORLA İNDİRİLİR
 * ----------------------
 * Content-Type her zaman application/octet-stream, Content-Disposition
 * her zaman attachment. Hiçbir ek tarayıcıda RENDER EDİLMEZ.
 *
 * Sebep: bir SVG veya HTML eki inline sunulsaydı, içindeki script
 * uygulamanın KENDİ kökeninde çalışırdı — oturum çerezine erişebilen
 * kalıcı bir XSS olurdu. Bu uzantılar beyaz listede yok, ama savunma
 * tek katmana bırakılmaz.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/attachments.php';

require_login();
require_can('risk.view');

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing attachment id');
}

/* JOIN ile risks: silinmis bir riskin eki indirilemez. Ek kaydi
   dursa bile risk soft-delete edilmisse erisim kapanmalidir. */
$stmt = db()->prepare(
    'SELECT a.stored_name, a.original_name, a.size_bytes
       FROM risk_attachments a
       JOIN risks r ON r.id = a.risk_id AND r.deleted_at IS NULL
      WHERE a.id = :id
      LIMIT 1'
);
$stmt->execute([':id' => $id]);
$att = $stmt->fetch();

if ($att === false) {
    app_abort(404, 'Attachment not found');
}

$stored = (string)$att['stored_name'];
$path   = attach_dir() . '/' . $stored;

if (basename($stored) !== $stored || !is_file($path)) {
    app_log('error', 'Attachment file missing on disk', [
        'id' => $id, 'stored' => $stored,
    ]);
    app_abort(404, 'Attachment file missing');
}

audit('risk_attachment_downloaded', 'attachment', $id, null, [
    'name' => $att['original_name'],
]);

/* Dosya adi basligina satir sonu veya tirnak kacarsa baslik
   enjeksiyonu olur. ASCII disi karakterler icin filename* (RFC 5987)
   kullaniliyor; duz filename yedek olarak sadelestirilmis hali. */
$safe = (string)preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$att['original_name']);
$utf8 = rawurlencode((string)$att['original_name']);

if ($safe === '') {
    $safe = 'ek';
}

/* Onceki ciktiyi temizle: tek bir bosluk bile indirilen dosyanin
   basina yazilir ve dosyayi bozar. */
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/octet-stream');
header("Content-Disposition: attachment; filename=\"{$safe}\"; filename*=UTF-8''{$utf8}");
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'");
header('Cache-Control: private, no-store');

readfile($path);
exit;
