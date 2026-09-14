<?php

declare(strict_types=1);

/**
 * RiskOps - Risk duzenleme formu
 * /var/www/riskops/risks/edit.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_can('risk.update');

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing risk id');
}

$stmt = db()->prepare('SELECT * FROM risks WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute([':id' => $id]);
$risk = $stmt->fetch();

if ($risk === false) {
    app_abort(404, 'Risk not found: ' . $id);
}

$form = [
    'title'               => (string)$risk['title'],
    'description'         => (string)($risk['description'] ?? ''),
    'category_id'         => (string)$risk['category_id'],
    'department_id'       => (string)$risk['department_id'],
    'asset_name'          => (string)($risk['asset_name'] ?? ''),
    'threat'              => (string)($risk['threat'] ?? ''),
    'vulnerability'       => (string)($risk['vulnerability'] ?? ''),
    'owner_id'            => (string)$risk['owner_id'],
    'likelihood'          => (string)$risk['likelihood'],
    'impact'              => (string)$risk['impact'],
    'treatment_strategy'  => (string)($risk['treatment_strategy'] ?? ''),
    'residual_likelihood' => (string)($risk['residual_likelihood'] ?? ''),
    'residual_impact'     => (string)($risk['residual_impact'] ?? ''),
    'status'              => (string)$risk['status'],
    'target_date'         => (string)($risk['target_date'] ?? ''),
];

$formAction  = url('/risks/update.php');
$submitLabel = 'Değişiklikleri Kaydet';
$cancelUrl   = url('/risks/view.php?id=' . $id);
$riskId      = $id;

$pageTitle    = $risk['risk_code'] . ' düzenle';
$pageSubtitle = str_limit($risk['title'], 90);
$activeMenu   = 'risks';

require LAYOUT_PATH . '/header.php';
require __DIR__ . '/_form.php';
require LAYOUT_PATH . '/footer.php';
