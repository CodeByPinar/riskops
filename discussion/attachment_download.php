<?php
declare(strict_types=1);

/**
 * RiskOps - Ek indirme (risk ve aksiyon icin ortak)
 * /var/www/riskops/discussion/attachment_download.php?type=risk&id=<ek id>
 *
 * YETKI: hedefin OKUMA yetkisi (risk.view / action.view). Ek indirmek
 * kaydi gormekle ayni seviyededir.
 *
 * NEDEN PHP UZERINDEN: dosyalar storage/ altinda ve web erisimine
 * kapali. Dogrudan baglanti verilseydi, baglantiyi ele geciren herkes
 * -giris yapmamis olsa bile- dosyaya ulasirdi.
 *
 * HER EK ZORLA INDIRILIR: Content-Type her zaman
 * application/octet-stream, Content-Disposition her zaman attachment.
 * Hicbir ek tarayicida RENDER EDILMEZ - bir SVG veya HTML eki inline
 * sunulsaydi icindeki script uygulamanin KENDI kokeninde calisir,
 * oturum cerezine erisebilen kalici bir XSS olurdu.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/attachments.php';
require_once __DIR__ . '/../includes/discussion.php';

require_login();

$type = discussion_type();
$cfg  = discussion_config($type);

require_can($cfg['view_ability']);

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing attachment id');
}

/* JOIN ile ust tablo: silinmis bir riskin eki indirilemez. Ek kaydi
   dursa bile risk soft-delete edilmisse erisim kapanmalidir. */
$stmt = db()->prepare(
    'SELECT a.stored_name, a.original_name
       FROM ' . $cfg['attachments'] . ' a
       JOIN ' . $cfg['parent'] . ' p ON p.id = a.' . $cfg['fk']
    . ' AND ' . str_replace('deleted_at', 'p.deleted_at', $cfg['alive']) . '
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
    app_log('error', 'Attachment file missing on disk', ['id' => $id, 'stored' => $stored]);
    app_abort(404, 'Attachment file missing');
}

audit($type . '_attachment_downloaded', 'attachment', $id, null, [
    'name' => $att['original_name'],
]);

/* Dosya adi basligina satir sonu veya tirnak kacarsa baslik enjeksiyonu
   olur. ASCII disi karakterler icin filename* (RFC 5987) kullaniliyor. */
$safe = (string)preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$att['original_name']);
$utf8 = rawurlencode((string)$att['original_name']);

if ($safe === '') {
    $safe = 'ek';
}

/* Onceki ciktiyi temizle: tek bir bosluk bile indirilen dosyanin basina
   yazilir ve dosyayi bozar. */
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
