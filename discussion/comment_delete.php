<?php
declare(strict_types=1);

/**
 * RiskOps - Yorum silme (risk ve aksiyon icin ortak)
 * /var/www/riskops/discussion/comment_delete.php
 *
 * POST: type=risk|action, id
 *
 * YETKI: yorumun SAHIBI ya da admin. Ayni yetkiye sahip iki
 * kullanicidan biri digerinin yorumunu silebilseydi tartisma kaydi
 * tek tarafli temizlenebilirdi.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/discussion.php';

csrf_require();
require_login();

$type = discussion_type();
$cfg  = discussion_config($type);

require_can($cfg['ability']);

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing comment id');
}

$stmt = db()->prepare(
    'SELECT id, ' . $cfg['fk'] . ' AS parent_id, user_id'
    . ' FROM ' . $cfg['comments'] . ' WHERE id = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$comment = $stmt->fetch();

if ($comment === false) {
    flash('error', 'Yorum bulunamadi.');
    redirect($type === 'risk' ? '/risks/' : '/actions/');
}

$back = discussion_url($type, (int)$comment['parent_id'], '#yorumlar');

if (!discussion_may_delete($comment['user_id'] !== null ? (int)$comment['user_id'] : null)) {
    flash('error', 'Yalnizca kendi yorumunuzu silebilirsiniz.');
    redirect($back);
}

db()->prepare('DELETE FROM ' . $cfg['comments'] . ' WHERE id = :id')->execute([':id' => $id]);

audit($type . '_comment_deleted', $type, (int)$comment['parent_id'],
    ['comment_id' => $id, 'author_id' => $comment['user_id']], null);

flash('success', 'Yorum silindi.');
redirect($back);
