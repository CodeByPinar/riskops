<?php

declare(strict_types=1);

/**
 * RiskOps - Aksiyon güncelleme (POST handler)
 * /var/www/riskops/actions/update.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_validate.php';

csrf_require();
require_login();
require_can('action.update');

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing action id');
}

$before = db_row(
    'SELECT a.* FROM risk_actions a
     JOIN risks r ON r.id = a.risk_id AND r.deleted_at IS NULL
     WHERE a.id = :id LIMIT 1',
    [':id' => $id]
);

if ($before === null) {
    flash('error', 'Aksiyon bulunamadı.');
    redirect('/actions/');
}

[$data, $errors] = action_collect_input();

if ($errors !== []) {
    action_fail_back($errors, '/actions/edit.php?id=' . $id);
}

// Aksiyon başka bir riske taşınamaz: bağlı olduğu risk sabittir.
$data['risk_id'] = (int)$before['risk_id'];

$statusChanged = (string)$before['status'] !== (string)$data['status'];
$completedAt   = action_completed_at(
    $before['completed_at'],
    (string)$before['status'],
    (string)$data['status']
);

try {
    db_run(
        'UPDATE risk_actions SET
            title = :title,
            description = :desc,
            owner_id = :owner,
            priority = :pri,
            status = :status,
            due_date = :due,
            completed_at = :done
         WHERE id = :id',
        [
        ':title'  => $data['title'],
        ':desc'   => $data['description'],
        ':owner'  => $data['owner_id'],
        ':pri'    => $data['priority'],
        ':status' => $data['status'],
        ':due'    => $data['due_date'],
        ':done'   => $completedAt,
        ':id'     => $id,
    ]
    );
} catch (Throwable $ex) {
    app_log('error', 'Action update failed: ' . $ex->getMessage(), ['action' => $id, 'user' => auth_id()]);
    old_set($_POST);
    flash('error', 'Değişiklikler kaydedilemedi.');
    redirect('/actions/edit.php?id=' . $id);
}

[$oldValues, $newValues] = audit_diff($before, $data);

if ($oldValues !== []) {
    audit('action_updated', 'risk_action', $id, $oldValues, $newValues);
}
if ($statusChanged && $data['status'] === 'Completed') {
    audit('action_completed', 'risk_action', $id,
        ['status' => $before['status']],
        ['status' => 'Completed', 'completed_at' => $completedAt]
    );
}

flash('success', 'Aksiyon güncellendi.');
redirect('/risks/view.php?id=' . (int)$before['risk_id']);
