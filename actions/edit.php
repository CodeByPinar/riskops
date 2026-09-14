<?php

declare(strict_types=1);

/**
 * RiskOps - Aksiyon düzenleme formu
 * /var/www/riskops/actions/edit.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_validate.php';

require_login();
require_can('action.update');

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing action id');
}

$stmt = db()->prepare(
    'SELECT a.*, r.risk_code, r.title AS risk_title
     FROM risk_actions a
     JOIN risks r ON r.id = a.risk_id AND r.deleted_at IS NULL
     WHERE a.id = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$action = $stmt->fetch();

if ($action === false) {
    app_abort(404, 'Action not found: ' . $id);
}

$riskRow = [
    'id'        => (int)$action['risk_id'],
    'risk_code' => $action['risk_code'],
    'title'     => $action['risk_title'],
];

$form = [
    'risk_id'     => (string)$action['risk_id'],
    'title'       => (string)$action['title'],
    'description' => (string)($action['description'] ?? ''),
    'owner_id'    => (string)$action['owner_id'],
    'priority'    => (string)$action['priority'],
    'status'      => (string)$action['status'],
    'due_date'    => (string)($action['due_date'] ?? ''),
];

$risks       = [];
$formAction  = url('/actions/update.php');
$submitLabel = 'Değişiklikleri Kaydet';
$cancelUrl   = url('/risks/view.php?id=' . (int)$action['risk_id']);
$actionId    = $id;

$pageTitle    = 'Aksiyonu düzenle';
$pageSubtitle = str_limit($action['title'], 90);
$activeMenu   = 'actions';

require LAYOUT_PATH . '/header.php';
require __DIR__ . '/_form.php';
require LAYOUT_PATH . '/footer.php';
