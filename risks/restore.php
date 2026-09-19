<?php

declare(strict_types=1);

/**
 * RiskOps - Silinen riski geri alma (POST handler)
 * /var/www/riskops/risks/restore.php
 *
 * risks/delete.php'nin tersi: deleted_at ve deleted_by temizlenir.
 *
 * GET ile geri alma YOKTUR. POST + CSRF + admin rolü zorunludur.
 * Silmeyle aynı yetki: aksi hâlde daha düşük yetkili bir kullanıcı,
 * admin'in bilinçli olarak kaldırdığı bir kaydı geri getirebilirdi.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

csrf_require();
require_login();
require_role(ROLE_ADMIN);

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing risk id');
}

/* deleted_at IS NOT NULL sarti onemli: zaten aktif bir kaydi "geri
   almak" anlamsizdir ve audit log'a yaniltici bir satir yazardi. */
$risk = db_row(
    'SELECT id, risk_code, title, status, deleted_at
       FROM risks
      WHERE id = :id AND deleted_at IS NOT NULL
      LIMIT 1',
    [':id' => $id]
);

if ($risk === null) {
    flash('error', 'Kayıt bulunamadı veya zaten aktif.');
    redirect('/risks/deleted.php');
}

/* Risk kodu benzersizdir. Kayit silinmisken ayni kodla yeni bir risk
   olusturulmus olabilir; o durumda geri alma UNIQUE kisitini ihlal
   eder. Once kontrol et, kullaniciya anlasilir bir mesaj ver -
   veritabani hatasiyla 500'e dusurme. */
$clash = db_stmt(
    'SELECT id FROM risks
      WHERE risk_code = :code AND id <> :id AND deleted_at IS NULL
      LIMIT 1',
    [':code' => $risk['risk_code'], ':id' => $id]
);

if ($clash->fetch() !== false) {
    flash('error', $risk['risk_code'] . ' kodu şu anda başka bir aktif risk '
        . 'tarafından kullanılıyor. Geri alınamaz.');
    redirect('/risks/deleted.php');
}

db_run(
    'UPDATE risks SET deleted_at = NULL, deleted_by = NULL WHERE id = :id',
    [':id' => $id]
);

audit('risk_restored', 'risk', $id,
    ['deleted_at' => $risk['deleted_at']],
    ['risk_code' => $risk['risk_code'], 'title' => $risk['title'], 'deleted_at' => null]
);

flash('success', $risk['risk_code'] . ' kodlu risk geri alındı.');
redirect('/risks/view.php?id=' . $id);
