<?php
declare(strict_types=1);

/**
 * RiskOps - Yeni risk formu
 * /var/www/riskops/risks/create.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_can('risk.create');

$form = [
    'status'      => 'Open',
    'owner_id'    => (string)auth_id(),
    'department_id' => (string)(auth_user()['department_id'] ?? ''),
];

$formAction  = url('/risks/store.php');
$submitLabel = 'Riski Oluştur';
$cancelUrl   = url('/risks/');
$riskId      = null;

$pageTitle    = 'Yeni Risk';
$pageSubtitle = 'Risk kodu kayıt sırasında otomatik üretilecek';
$activeMenu   = 'risks.create';

require LAYOUT_PATH . '/header.php';
require __DIR__ . '/_form.php';
require LAYOUT_PATH . '/footer.php';
