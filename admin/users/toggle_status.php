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

if ($newStatus === 0 && $id === auth_id()) {
    flash('error', 'Kendi hesabınızı devre dışı bırakamazsınız.');
    redirect('/admin/users/');
}

/* --- Son aktif admin koruması: ATOMİK -------------------------------
   Kontrol ve UPDATE aynı transaction içinde; tüm aktif admin satırları
   FOR UPDATE ile kilitlenir (bkz. last_admin_atomic_guard). Ayrı bir
   SELECT COUNT(...) + ayrı UPDATE klasik check-then-act yarışıydı: iki
   admin birbirini aynı anda pasifleştirdiğinde her iki istek de
   "başka admin var" görüyor ve sistem yönetimsiz kalıyordu. */
$pdo = db();
$pdo->beginTransaction();
try {
    $wasActiveAdmin = $user['role'] === ROLE_ADMIN && (int)$user['status'] === 1;

    if ($newStatus === 0 && !last_admin_atomic_guard($pdo, $id, $wasActiveAdmin)) {
        $pdo->rollBack();
        flash('error', 'Bu, sistemdeki tek aktif admin hesabı. Önce başka bir admin tanımlayın.');
        redirect('/admin/users/');
    }

    $pdo->prepare('UPDATE users SET status = :s WHERE id = :id')
        ->execute([':s' => $newStatus, ':id' => $id]);
    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('error', 'User status toggle failed: ' . $ex->getMessage(), ['user' => $id]);
    flash('error', 'Durum değiştirilemedi.');
    redirect('/admin/users/');
}

audit('user_status_changed', 'user', $id,
    ['status' => (int)$user['status']],
    ['status' => $newStatus]
);

flash('success', $user['name'] . ' ' . ($newStatus === 1 ? 'aktifleştirildi.' : 'devre dışı bırakıldı.'));
redirect('/admin/users/');
