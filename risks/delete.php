<?php
declare(strict_types=1);

/**
 * RiskOps - Risk silme (POST handler)
 * /var/www/riskops/risks/delete.php
 *
 * SOFT DELETE: kayıt fiziksel olarak silinmez, deleted_at işaretlenir.
 * Risk kayıtları kurumsal kanit niteligindedir; denetimde "kim, ne zaman,
 * neyi sildi" sorusu cevaplanabilmelidir.
 *
 * GET ile silme YOKTUR. POST + CSRF + admin rolu zorunludur.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

csrf_require();
require_login();
require_role(ROLE_ADMIN);

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing risk id');
}

$stmt = db()->prepare('SELECT * FROM risks WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute([':id' => $id]);
$risk = $stmt->fetch();

if ($risk === false) {
    flash('error', 'Risk bulunamadı veya zaten silinmiş.');
    redirect('/risks/');
}

db()->prepare(
    'UPDATE risks SET deleted_at = NOW(), deleted_by = :by WHERE id = :id'
)->execute([':by' => auth_id(), ':id' => $id]);

audit('risk_deleted', 'risk', $id, [
    'risk_code' => $risk['risk_code'],
    'title'     => $risk['title'],
    'status'    => $risk['status'],
], null);

flash('success', $risk['risk_code'] . ' kodlu risk silindi.');
redirect('/risks/');
