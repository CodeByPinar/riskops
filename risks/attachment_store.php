<?php
declare(strict_types=1);

/**
 * RiskOps - Riske dosya eki yükleme (POST handler)
 * /var/www/riskops/risks/attachment_store.php
 *
 * YETKİ: risk.update  (yorumla aynı gerekçe)
 *
 * Doğrulamanın TAMAMI includes/attachments.php içindeki attach_store()
 * fonksiyonundadır; oradaki yorumlar her kuralın neyi engellediğini
 * anlatıyor. Bu dosya yalnızca yetki, risk varlığı ve veritabanı
 * kaydıyla ilgilenir.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/attachments.php';
require_once __DIR__ . '/_risk_access.php';

csrf_require();
require_login();
require_can('risk.update');

$riskId = input_int('risk_id');
$risk   = risk_or_fail($riskId);
$back   = '/risks/view.php?id=' . (int)$risk['id'] . '#ekler';

/* Bir riske baglanabilecek ek sayisi sinirli: sinirsiz birakilirsa
   tek bir kayit uzerinden disk doldurulabilir. */
$stmt = db()->prepare('SELECT COUNT(*) FROM risk_attachments WHERE risk_id = :r');
$stmt->execute([':r' => $risk['id']]);
$count = (int)$stmt->fetchColumn();

if ($count >= ATTACH_MAX_PER_RISK) {
    flash('error', 'Bir riske en fazla ' . ATTACH_MAX_PER_RISK . ' ek eklenebilir.');
    redirect($back);
}

$file = $_FILES['attachment'] ?? null;

if (!is_array($file)) {
    flash('error', 'Dosya seçilmedi.');
    redirect($back);
}

$result = attach_store($file);

if (!$result['ok']) {
    flash('error', $result['error']);
    redirect($back);
}

try {
    db()->prepare(
        'INSERT INTO risk_attachments
            (risk_id, uploaded_by, original_name, stored_name, mime_type, size_bytes)
         VALUES (:r, :u, :o, :s, :m, :z)'
    )->execute([
        ':r' => $risk['id'],
        ':u' => auth_id(),
        ':o' => $result['original'],
        ':s' => $result['stored'],
        ':m' => $result['mime'],
        ':z' => $result['size'],
    ]);
} catch (Throwable $e) {
    /* Veritabani satiri yazilamadiysa diskteki dosya YETIM kalir.
       Temizlenmezse hicbir ekranda gorunmeyen ama yer kaplayan
       dosyalar birikir. */
    attach_delete_file($result['stored']);
    app_log('error', 'Attachment row insert failed: ' . $e->getMessage(), [
        'risk' => $risk['id'], 'stored' => $result['stored'],
    ]);
    flash('error', 'Ek kaydedilemedi.');
    redirect($back);
}

audit('risk_attachment_added', 'risk', (int)$risk['id'], null, [
    'attachment_id' => (int)db()->lastInsertId(),
    'name'          => $result['original'],
    'size'          => $result['size'],
    'mime'          => $result['mime'],
]);

flash('success', $result['original'] . ' eklendi.');
redirect($back);
