<?php

declare(strict_types=1);

/**
 * RiskOps - Yeni aksiyon kaydı (POST handler)
 * /var/www/riskops/actions/store.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_validate.php';

csrf_require();
require_login();
require_can('action.create');

[$data, $errors] = action_collect_input();

$backUrl = '/actions/create.php'
    . ($data['risk_id'] !== null ? '?risk_id=' . (int)$data['risk_id'] : '');

if ($errors !== []) {
    action_fail_back($errors, $backUrl);
}

$completedAt = action_completed_at(null, 'Open', (string)$data['status']);

try {
    db()->prepare(
        'INSERT INTO risk_actions
            (risk_id, title, description, owner_id, priority, status, due_date, completed_at, created_by)
         VALUES (:rid, :title, :desc, :owner, :pri, :status, :due, :done, :cby)'
    )->execute([
        ':rid'    => $data['risk_id'],
        ':title'  => $data['title'],
        ':desc'   => $data['description'],
        ':owner'  => $data['owner_id'],
        ':pri'    => $data['priority'],
        ':status' => $data['status'],
        ':due'    => $data['due_date'],
        ':done'   => $completedAt,
        ':cby'    => auth_id(),
    ]);

    $actionId = (int)db()->lastInsertId();
} catch (Throwable $ex) {
    app_log('error', 'Action creation failed: ' . $ex->getMessage(), ['user' => auth_id()]);
    old_set($_POST);
    flash('error', 'Aksiyon kaydedilemedi. Sorun devam ederse sistem yöneticinize başvurun.');
    redirect($backUrl);
}

audit('action_created', 'risk_action', $actionId, null, $data);

flash('success', 'Aksiyon oluşturuldu.');
redirect('/risks/view.php?id=' . (int)$data['risk_id']);
