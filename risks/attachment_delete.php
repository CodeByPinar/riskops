<?php
declare(strict_types=1);

/**
 * RiskOps - Ek silme (POST handler)
 * /var/www/riskops/risks/attachment_delete.php
 *
 * YETKİ: ekleyen kişi ya da admin. Yorum silmeyle aynı gerekçe —
 * aynı yetkiye sahip iki kullanıcıdan biri diğerinin kanıtını
 * kaldırabilseydi, kayıt tek taraflı temizlenebilirdi.
 *
 * Buradaki silme GERÇEK silmedir (soft delete değil): dosya diskten
 * de kalkar. Risk kaydının kendisi kanıt niteliğindedir; ona bağlı
 * bir ekin silinmesi audit log'a yazılır, yani "kim neyi kaldırdı"
 * sorusu yine cevaplanabilir.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/attachments.php';

csrf_require();
require_login();
require_can('risk.update');

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing attachment id');
}

$stmt = db()->prepare(
    'SELECT id, risk_id, uploaded_by, original_name, stored_name
       FROM risk_attachments WHERE id = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$att = $stmt->fetch();

if ($att === false) {
    flash('error', 'Ek bulunamadı.');
    redirect('/risks/');
}

$back = '/risks/view.php?id=' . (int)$att['risk_id'] . '#ekler';

$isOwner = $att['uploaded_by'] !== null && (int)$att['uploaded_by'] === auth_id();

if (!$isOwner && auth_role() !== ROLE_ADMIN) {
    flash('error', 'Yalnızca kendi eklediğiniz dosyayı silebilirsiniz.');
    redirect($back);
}

/* Once veritabani satiri, sonra disk. Ters sirada yapilsaydi ve satir
   silme basarisiz olsaydi, arayuzde var gorunen ama diskte olmayan bir
   ek kalirdi; tiklayan herkes 404 alirdi. Bu sirada en kotu ihtimal
   yetim bir dosya: gorunmez, zararsiz, sonradan temizlenebilir. */
db()->prepare('DELETE FROM risk_attachments WHERE id = :id')->execute([':id' => $id]);

if (!attach_delete_file((string)$att['stored_name'])) {
    app_log('warning', 'Attachment file could not be deleted', [
        'id' => $id, 'stored' => $att['stored_name'],
    ]);
}

audit('risk_attachment_deleted', 'risk', (int)$att['risk_id'],
    ['attachment_id' => $id, 'name' => $att['original_name']], null);

flash('success', $att['original_name'] . ' silindi.');
redirect($back);
