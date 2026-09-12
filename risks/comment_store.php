<?php
declare(strict_types=1);

/**
 * RiskOps - Riske yorum ekleme (POST handler)
 * /var/www/riskops/risks/comment_store.php
 *
 * YETKI: risk.update
 *
 * Neden risk.view degil: viewer rolu bilincli olarak SALT OKUNURDUR.
 * Yorum yazabilmek, herkese acik demo kurulumunda ziyaretcilerin
 * kalici icerik birakabilmesi demekti. Yorum yazmak riskin
 * yonetimine katilmaktir; okumakla ayni sey degil.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_risk_access.php';

csrf_require();
require_login();
require_can('risk.update');

$riskId = input_int('risk_id');
$risk   = risk_or_fail($riskId);

$body = input('body');
$back = '/risks/view.php?id=' . (int)$risk['id'];

if ($body === null || trim($body) === '') {
    flash('error', 'Yorum boş olamaz.');
    redirect($back);
}

$body = trim($body);

if (mb_strlen($body) > 4000) {
    flash('error', 'Yorum en fazla 4000 karakter olabilir.');
    redirect($back);
}

db()->prepare(
    'INSERT INTO risk_comments (risk_id, user_id, body) VALUES (:r, :u, :b)'
)->execute([':r' => $risk['id'], ':u' => auth_id(), ':b' => $body]);

$commentId = (int)db()->lastInsertId();

/* Yorumun TAMAMI audit'e yazilmaz: 4000 karaktere kadar cikabiliyor
   ve denetim kaydi okunabilir kalmali. Kimlik ve uzunluk yeter;
   icerik zaten risk_comments tablosunda duruyor. */
audit('risk_comment_added', 'risk', (int)$risk['id'], null, [
    'comment_id' => $commentId,
    'length'     => mb_strlen($body),
]);

flash('success', 'Yorum eklendi.');
redirect($back . '#yorumlar');
