<?php

declare(strict_types=1);

/**
 * RiskOps - Dosya eki yukleme (risk ve aksiyon icin ortak)
 * /var/www/riskops/discussion/attachment_store.php
 *
 * POST (multipart): type=risk|action, parent_id, attachment
 *
 * Dogrulamanin TAMAMI includes/attachments.php icindeki attach_store()
 * fonksiyonundadir; her kuralin neyi engelledigi orada yaziyor.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/attachments.php';
require_once __DIR__ . '/../includes/discussion.php';

csrf_require();
require_login();

$type = discussion_type();
$cfg  = discussion_config($type);

require_can($cfg['ability']);

$parent = discussion_parent($type, input_int('parent_id'));
$back   = discussion_url($type, (int)$parent['id'], '#ekler');

/* Kayit basina ek sayisi sinirli: sinirsiz birakilirsa tek bir kayit
   uzerinden disk doldurulabilir. */
$stmt = db_stmt(
    'SELECT COUNT(*) FROM ' . $cfg['attachments'] . ' WHERE ' . $cfg['fk'] . ' = :p',
    [':p' => $parent['id']]
);

if ((int)$stmt->fetchColumn() >= ATTACH_MAX_PER_RISK) {
    flash('error', 'Bir kayda en fazla ' . ATTACH_MAX_PER_RISK . ' ek eklenebilir.');
    redirect($back);
}

$file = $_FILES['attachment'] ?? null;

if (!is_array($file)) {
    flash('error', 'Dosya secilmedi.');
    redirect($back);
}

$result = attach_store($file);

if (!$result['ok']) {
    flash('error', $result['error']);
    redirect($back);
}

try {
    db_run(
        'INSERT INTO ' . $cfg['attachments']
        . ' (' . $cfg['fk'] . ', uploaded_by, original_name, stored_name, mime_type, size_bytes)'
        . ' VALUES (:p, :u, :o, :s, :m, :z)',
        [
        ':p' => $parent['id'],
        ':u' => auth_id(),
        ':o' => $result['original'],
        ':s' => $result['stored'],
        ':m' => $result['mime'],
        ':z' => $result['size'],
    ]
    );
} catch (Throwable $e) {
    /* Veritabani satiri yazilamadiysa diskteki dosya YETIM kalir:
       hicbir ekranda gorunmez ama yer kaplar. */
    attach_delete_file($result['stored']);
    app_log('error', 'Attachment row insert failed: ' . $e->getMessage(), [
        'type' => $type, 'parent' => $parent['id'], 'stored' => $result['stored'],
    ]);
    flash('error', 'Ek kaydedilemedi.');
    redirect($back);
}

audit($type . '_attachment_added', $type, (int)$parent['id'], null, [
    'attachment_id' => (int)db()->lastInsertId(),
    'name'          => $result['original'],
    'size'          => $result['size'],
    'mime'          => $result['mime'],
]);

flash('success', $result['original'] . ' eklendi.');
redirect($back);
