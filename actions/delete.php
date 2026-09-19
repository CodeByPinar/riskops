<?php

declare(strict_types=1);

/**
 * RiskOps - Aksiyon silme (POST handler)
 * /var/www/riskops/actions/delete.php
 *
 * Aksiyonlar risklerin aksine SERT silinir: bunlar iş kayıtları değil,
 * planlama kalemleridir. Yine de silinen kaydın tamamı audit log'a
 * yazılır, böylece "ne silindi" sorusu cevaplanabilir.
 *
 * GET ile silme YOKTUR. POST + CSRF + admin rolü zorunludur.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

csrf_require();
require_login();
require_role(ROLE_ADMIN);

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing action id');
}

$action = db_row('SELECT * FROM risk_actions WHERE id = :id LIMIT 1', [':id' => $id]);

if ($action === null) {
    flash('error', 'Aksiyon bulunamadı veya zaten silinmiş.');
    redirect('/actions/');
}

$returnTo = input('return') === 'risk'
    ? '/risks/view.php?id=' . (int)$action['risk_id']
    : '/actions/';

db_run('DELETE FROM risk_actions WHERE id = :id', [':id' => $id]);

audit('action_deleted', 'risk_action', $id, [
    'risk_id'  => (int)$action['risk_id'],
    'title'    => $action['title'],
    'owner_id' => (int)$action['owner_id'],
    'priority' => $action['priority'],
    'status'   => $action['status'],
    'due_date' => $action['due_date'],
], null);

flash('success', 'Aksiyon silindi.');
redirect($returnTo);
