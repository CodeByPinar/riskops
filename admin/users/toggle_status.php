<?php
declare(strict_types=1);

/**
 * RiskOps - Kullanıcı aktif/pasif (POST handler)
 * /var/www/riskops/admin/users/toggle_status.php
 *
 * Kullanıcı SİLİNMEZ: risks.owner_id, created_by ve audit kayıtları
 * kullanıcıya bağlıdır (FK RESTRICT). Ayrılan personel pasife alınır,
 * geçmiş kayıtlar bozulmaz.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_validate.php';

csrf_require();
require_login();
require_role(ROLE_ADMIN);

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing user id');
}

$stmt = db()->prepare('SELECT id, name, role, status FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if ($user === false) {
    flash('error', 'Kullanıcı bulunamadı.');
    redirect('/admin/users/');
}

$newStatus = (int)$user['status'] === 1 ? 0 : 1;

if ($newStatus === 0) {
    if ($id === auth_id()) {
        flash('error', 'Kendi hesabınızı devre dışı bırakamazsınız.');
        redirect('/admin/users/');
    }
    if ($user['role'] === ROLE_ADMIN && !other_active_admin_exists($id)) {
        flash('error', 'Bu, sistemdeki tek aktif admin hesabı. Önce başka bir admin tanımlayın.');
        redirect('/admin/users/');
    }
}

db()->prepare('UPDATE users SET status = :s WHERE id = :id')
    ->execute([':s' => $newStatus, ':id' => $id]);

audit('user_status_changed', 'user', $id,
    ['status' => (int)$user['status']],
    ['status' => $newStatus]
);

flash('success', $user['name'] . ' ' . ($newStatus === 1 ? 'aktifleştirildi.' : 'devre dışı bırakıldı.'));
redirect('/admin/users/');
