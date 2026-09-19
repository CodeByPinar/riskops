<?php

declare(strict_types=1);

/**
 * RiskOps - Departman silme (POST handler)
 * /var/www/riskops/admin/departments/delete.php
 *
 * Kullanımda olan departman SİLİNMEZ. Veritabanı FK RESTRICT ile zaten
 * engeller; burada önce kontrol edip kullanıcıya anlamlı bir mesaj
 * veriyoruz - ham bir SQL hatası göstermek yerine.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

csrf_require();
require_login();
require_role(ROLE_ADMIN);

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing department id');
}

$dept = db_row('SELECT id, name FROM departments WHERE id = :id LIMIT 1', [':id' => $id]);

if ($dept === null) {
    flash('error', 'Departman bulunamadı.');
    redirect('/admin/departments/');
}

$u = db_row_required(
    'SELECT
        (SELECT COUNT(*) FROM risks WHERE department_id = :id1 AND deleted_at IS NULL) AS risk_sayisi,
        (SELECT COUNT(*) FROM risks WHERE department_id = :id2) AS risk_tumu,
        (SELECT COUNT(*) FROM users WHERE department_id = :id3) AS kullanici_sayisi',
    [':id1' => $id, ':id2' => $id, ':id3' => $id]
);

if ((int)$u['risk_tumu'] > 0 || (int)$u['kullanici_sayisi'] > 0) {
    flash('error', sprintf(
        '%s silinemez: %d risk ve %d kullanıcı bu departmana bağlı. Bunun yerine pasifleştirin.',
        $dept['name'], (int)$u['risk_tumu'], (int)$u['kullanici_sayisi']
    ));
    redirect('/admin/departments/');
}

db_run('DELETE FROM departments WHERE id = :id', [':id' => $id]);

audit('department_deleted', 'department', $id, ['name' => $dept['name']], null);

flash('success', $dept['name'] . ' silindi.');
redirect('/admin/departments/');
