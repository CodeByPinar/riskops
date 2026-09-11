<?php
declare(strict_types=1);

/**
 * RiskOps - Departman aktif/pasif (POST handler)
 * /var/www/riskops/admin/departments/toggle.php
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

csrf_require();
require_login();
require_role(ROLE_ADMIN);

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing department id');
}

$stmt = db()->prepare('SELECT id, name, is_active FROM departments WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$dept = $stmt->fetch();

if ($dept === false) {
    flash('error', 'Departman bulunamadı.');
    redirect('/admin/departments/');
}

$newState = (int)$dept['is_active'] === 1 ? 0 : 1;

db()->prepare('UPDATE departments SET is_active = :a WHERE id = :id')
    ->execute([':a' => $newState, ':id' => $id]);

audit('department_updated', 'department', $id,
    ['is_active' => (int)$dept['is_active']],
    ['is_active' => $newState]
);

flash('success', $dept['name'] . ' ' . ($newState === 1 ? 'aktifleştirildi.' : 'pasifleştirildi.'));
redirect('/admin/departments/');
