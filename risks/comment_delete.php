<?php
declare(strict_types=1);

/**
 * RiskOps - Yorum silme (POST handler)
 * /var/www/riskops/risks/comment_delete.php
 *
 * YETKI: yorumun SAHIBI ya da admin.
 *
 * Neden risk.update yetmiyor: ayni yetkiye sahip iki kullanicidan
 * biri digerinin yorumunu silebilseydi, tartisma kaydi tek tarafli
 * temizlenebilirdi. Baskasinin yorumunu yalnizca admin kaldirir.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

csrf_require();
require_login();
require_can('risk.update');

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing comment id');
}

$stmt = db()->prepare(
    'SELECT id, risk_id, user_id FROM risk_comments WHERE id = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$comment = $stmt->fetch();

if ($comment === false) {
    flash('error', 'Yorum bulunamadı.');
    redirect('/risks/');
}

$back = '/risks/view.php?id=' . (int)$comment['risk_id'];

$isOwner = $comment['user_id'] !== null && (int)$comment['user_id'] === auth_id();

if (!$isOwner && auth_role() !== ROLE_ADMIN) {
    flash('error', 'Yalnızca kendi yorumunuzu silebilirsiniz.');
    redirect($back);
}

db()->prepare('DELETE FROM risk_comments WHERE id = :id')->execute([':id' => $id]);

audit('risk_comment_deleted', 'risk', (int)$comment['risk_id'],
    ['comment_id' => $id, 'author_id' => $comment['user_id']], null);

flash('success', 'Yorum silindi.');
redirect($back . '#yorumlar');
