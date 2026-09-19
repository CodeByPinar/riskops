<?php

declare(strict_types=1);

/**
 * RiskOps - Aksiyonu tamamla (hızlı işlem, POST handler)
 * /var/www/riskops/actions/complete.php
 *
 * Liste ve risk detayındaki tek tıklık "Tamamla" düğmesi buraya gönderir.
 * Durum değiştiren bir işlemdir: GET ile ÇALIŞMAZ, POST + CSRF zorunludur.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

csrf_require();
require_login();
require_can('action.complete');

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing action id');
}

$action = db_row(
    'SELECT a.* FROM risk_actions a
     JOIN risks r ON r.id = a.risk_id AND r.deleted_at IS NULL
     WHERE a.id = :id LIMIT 1',
    [':id' => $id]
);

if ($action === null) {
    flash('error', 'Aksiyon bulunamadı.');
    redirect('/actions/');
}

$returnTo = input('return') === 'risk'
    ? '/risks/view.php?id=' . (int)$action['risk_id']
    : '/actions/';

if ($action['status'] === 'Completed') {
    flash('info', 'Bu aksiyon zaten tamamlanmış.');
    redirect($returnTo);
}

if ($action['status'] === 'Cancelled') {
    flash('warning', 'İptal edilmiş bir aksiyon tamamlanamaz. Önce durumunu değiştirin.');
    redirect($returnTo);
}

$now = date('Y-m-d H:i:s');

db_run(
    "UPDATE risk_actions SET status = 'Completed', completed_at = :now WHERE id = :id",
    [':now' => $now, ':id' => $id]
);

audit('action_completed', 'risk_action', $id,
    ['status' => $action['status'], 'completed_at' => $action['completed_at']],
    ['status' => 'Completed', 'completed_at' => $now]
);

flash('success', '«' . str_limit($action['title'], 50) . '» tamamlandı olarak işaretlendi.');
redirect($returnTo);
