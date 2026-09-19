<?php

declare(strict_types=1);

/**
 * RiskOps - Yorum ekleme (risk ve aksiyon icin ortak)
 * /var/www/riskops/discussion/comment_store.php
 *
 * POST: type=risk|action, parent_id, body
 *
 * YETKI: hedefin kendi guncelleme yetkisi (risk.update / action.update).
 * Okuma yetkisi (risk.view) YETMEZ: viewer rolu bilincli olarak salt
 * okunurdur; yorum yazabilmek, herkese acik demo kurulumunda
 * ziyaretcilerin kalici icerik birakabilmesi demekti.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/discussion.php';

csrf_require();
require_login();

$type = discussion_type();
$cfg  = discussion_config($type);

require_can($cfg['ability']);

$parent = discussion_parent($type, input_int('parent_id'));
$back   = discussion_url($type, (int)$parent['id'], '#yorumlar');

$body = input('body');

if ($body === null || trim($body) === '') {
    flash('error', 'Yorum bos olamaz.');
    redirect($back);
}

$body = trim($body);

if (mb_strlen($body) > 4000) {
    flash('error', 'Yorum en fazla 4000 karakter olabilir.');
    redirect($back);
}

/* Tablo ve kolon adlari SABIT kayit defterinden (includes/discussion.php);
   istemciden gelen degerler yalnizca placeholder olarak giriyor. */
db_run(
    'INSERT INTO ' . $cfg['comments'] . ' (' . $cfg['fk'] . ', user_id, body)'
    . ' VALUES (:p, :u, :b)',
    [':p' => $parent['id'], ':u' => auth_id(), ':b' => $body]
);

$commentId = (int)db()->lastInsertId();

/* Yorumun TAMAMI audit'e yazilmaz: 4000 karaktere kadar cikabiliyor ve
   denetim kaydi okunabilir kalmali. Icerik zaten kendi tablosunda. */
audit($type . '_comment_added', $type, (int)$parent['id'], null, [
    'comment_id' => $commentId,
    'length'     => mb_strlen($body),
]);

flash('success', 'Yorum eklendi.');
redirect($back);
