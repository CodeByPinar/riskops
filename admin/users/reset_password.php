<?php

declare(strict_types=1);

/**
 * RiskOps - Parola sıfırlama (POST handler)
 * /var/www/riskops/admin/users/reset_password.php
 *
 * Üretilen parola audit log'a YAZILMAZ; yalnızca bir kez ekranda gösterilir.
 * Ayrıca kullanıcının açık oturumları geçersiz kılınmaz - bu Phase 10'da
 * oturum tablosu eklenince ele alınacak (şimdilik bilinen bir sınır).
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

$stmt = db()->prepare('SELECT id, name, email FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if ($user === false) {
    flash('error', 'Kullanıcı bulunamadı.');
    redirect('/admin/users/');
}

$tempPassword = generate_temp_password();

db()->prepare(
    'UPDATE users SET password = :p, must_change_password = 1, password_changed_at = NOW()
     WHERE id = :id'
)->execute([':p' => password_hash($tempPassword, PASSWORD_DEFAULT), ':id' => $id]);

// Başarısız giriş sayaçlarını da temizle
db()->prepare('DELETE FROM login_attempts WHERE email = :e AND success = 0')
    ->execute([':e' => $user['email']]);

audit('user_password_reset', 'user', $id, null, ['by' => auth_id()]);

flash('success', $user['name'] . ' için parola sıfırlandı.');
flash('warning', 'Geçici parola: ' . $tempPassword
    . ' — Bu parola bir daha gösterilmeyecek. Kullanıcıya güvenli bir kanaldan iletin.');

redirect('/admin/users/edit.php?id=' . $id);
