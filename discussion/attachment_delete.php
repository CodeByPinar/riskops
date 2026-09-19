<?php

declare(strict_types=1);

/**
 * RiskOps - Ek silme (risk ve aksiyon icin ortak)
 * /var/www/riskops/discussion/attachment_delete.php
 *
 * POST: type=risk|action, id
 *
 * YETKI: ekleyen kisi ya da admin.
 *
 * Buradaki silme GERCEK silmedir; dosya diskten de kalkar. Islem audit
 * log'a yazilir, yani "kim neyi kaldirdi" sorusu cevaplanabilir.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/attachments.php';
require_once __DIR__ . '/../includes/discussion.php';

csrf_require();
require_login();

$type = discussion_type();
$cfg  = discussion_config($type);

require_can($cfg['ability']);

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing attachment id');
}

$att = db_row(
    'SELECT id, ' . $cfg['fk'] . ' AS parent_id, uploaded_by, original_name, stored_name'
    . ' FROM ' . $cfg['attachments'] . ' WHERE id = :id LIMIT 1',
    [':id' => $id]
);

if ($att === null) {
    flash('error', 'Ek bulunamadi.');
    redirect($type === 'risk' ? '/risks/' : '/actions/');
}

$back = discussion_url($type, (int)$att['parent_id'], '#ekler');

if (!discussion_may_delete($att['uploaded_by'] !== null ? (int)$att['uploaded_by'] : null)) {
    flash('error', 'Yalnizca kendi ekledigininiz dosyayi silebilirsiniz.');
    redirect($back);
}

/* Once veritabani satiri, sonra disk. Ters sirada olsaydi ve satir
   silme basarisiz olsaydi, arayuzde gorunen ama diskte olmayan bir ek
   kalir, tiklayan herkes 404 alirdi. Bu sirada en kotu ihtimal yetim
   bir dosya: gorunmez ve zararsiz. */
db_run('DELETE FROM ' . $cfg['attachments'] . ' WHERE id = :id', [':id' => $id]);

if (!attach_delete_file((string)$att['stored_name'])) {
    app_log('warning', 'Attachment file could not be deleted', [
        'type' => $type, 'id' => $id, 'stored' => $att['stored_name'],
    ]);
}

audit($type . '_attachment_deleted', $type, (int)$att['parent_id'],
    ['attachment_id' => $id, 'name' => $att['original_name']], null);

flash('success', $att['original_name'] . ' silindi.');
redirect($back);
