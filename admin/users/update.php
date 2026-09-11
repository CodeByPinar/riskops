<?php
declare(strict_types=1);

/**
 * RiskOps - Kullanıcı güncelleme (POST handler)
 * /var/www/riskops/admin/users/update.php
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

$stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$before = $stmt->fetch();

if ($before === false) {
    flash('error', 'Kullanıcı bulunamadı.');
    redirect('/admin/users/');
}

[$data, $errors] = user_collect_input($id);

if ($errors !== []) {
    user_fail_back($errors, '/admin/users/edit.php?id=' . $id);
}

try {
    db()->prepare(
        'UPDATE users SET
            name = :n, email = :e, role = :r, department_id = :d,
            title = :t, phone = :ph, status = :s
         WHERE id = :id'
    )->execute([
        ':n'  => $data['name'],
        ':e'  => $data['email'],
        ':r'  => $data['role'],
        ':d'  => $data['department_id'],
        ':t'  => $data['title'],
        ':ph' => $data['phone'],
        ':s'  => $data['status'],
        ':id' => $id,
    ]);
} catch (Throwable $ex) {
    app_log('error', 'User update failed: ' . $ex->getMessage(), ['user' => $id]);
    old_set($_POST);
    flash('error', 'Değişiklikler kaydedilemedi.');
    redirect('/admin/users/edit.php?id=' . $id);
}

// Kendi bilgilerini güncellediyse oturumdaki kopyayı da tazele
if ($id === auth_id()) {
    $_SESSION['user_name']          = $data['name'];
    $_SESSION['user_email']         = $data['email'];
    $_SESSION['user_department_id'] = $data['department_id'];
}

[$oldValues, $newValues] = audit_diff($before, $data, ['updated_at', 'created_at', 'password']);

if ($oldValues !== []) {
    audit('user_updated', 'user', $id, $oldValues, $newValues);
}

flash('success', $data['name'] . ' güncellendi.');
redirect('/admin/users/');
