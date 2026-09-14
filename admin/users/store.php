<?php

declare(strict_types=1);

/**
 * RiskOps - Yeni kullanıcı kaydı (POST handler)
 * /var/www/riskops/admin/users/store.php
 *
 * Parola yönetici tarafından BELİRLENMEZ, sistem üretir. Böylece:
 *   - zayıf ve tekrar eden parolalar önlenir,
 *   - yönetici başkasının kalıcı parolasını bilmez,
 *   - must_change_password ile ilk girişte değişim zorunlu olur.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_validate.php';

csrf_require();
require_login();
require_role(ROLE_ADMIN);

[$data, $errors] = user_collect_input(null);

if ($errors !== []) {
    user_fail_back($errors, '/admin/users/create.php');
}

$tempPassword = generate_temp_password();

try {
    db()->prepare(
        'INSERT INTO users
            (name, email, password, role, department_id, title, phone,
             status, must_change_password, password_changed_at, created_by)
         VALUES (:n, :e, :p, :r, :d, :t, :ph, :s, 1, NOW(), :cby)'
    )->execute([
        ':n'   => $data['name'],
        ':e'   => $data['email'],
        ':p'   => password_hash($tempPassword, PASSWORD_DEFAULT),
        ':r'   => $data['role'],
        ':d'   => $data['department_id'],
        ':t'   => $data['title'],
        ':ph'  => $data['phone'],
        ':s'   => $data['status'],
        ':cby' => auth_id(),
    ]);

    $newId = (int)db()->lastInsertId();
} catch (Throwable $ex) {
    app_log('error', 'User creation failed: ' . $ex->getMessage(), ['admin' => auth_id()]);
    old_set($_POST);
    flash('error', 'Kullanıcı kaydedilemedi.');
    redirect('/admin/users/create.php');
}

// Parola audit log'a ASLA yazılmaz (audit_json zaten maskeler, burada hiç göndermiyoruz)
audit('user_created', 'user', $newId, null, $data);

flash('success', $data['name'] . ' oluşturuldu.');
flash('warning', 'Geçici parola: ' . $tempPassword
    . ' — Bu parola bir daha gösterilmeyecek. Kullanıcıya güvenli bir kanaldan iletin.');

redirect('/admin/users/');
