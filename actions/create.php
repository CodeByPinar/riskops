<?php
declare(strict_types=1);

/**
 * RiskOps - Yeni aksiyon formu
 * /var/www/riskops/actions/create.php
 *
 * ?risk_id=N ile gelinirse risk sabitlenir (risk detayından "Aksiyon ekle").
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_validate.php';

require_login();
require_can('action.create');

$riskRow = action_find_risk(input_int('risk_id'));

// Risk sabit değilse seçim listesi hazırla (kapanmış riskler de dahil:
// kapanmış bir riske denetim gereği aksiyon eklenebilir)
$risks = [];
if ($riskRow === null) {
    $risks = db()->query(
        'SELECT id, risk_code, title FROM risks
         WHERE deleted_at IS NULL
         ORDER BY risk_code DESC'
    )->fetchAll();

    if ($risks === []) {
        flash('warning', 'Önce en az bir risk kaydı oluşturmalısınız.');
        redirect('/risks/');
    }
}

$form = [
    'owner_id' => (string)auth_id(),
    'priority' => 'Medium',
    'status'   => 'Open',
];

$formAction  = url('/actions/store.php');
$submitLabel = 'Aksiyonu Oluştur';
$cancelUrl   = $riskRow !== null
    ? url('/risks/view.php?id=' . (int)$riskRow['id'])
    : url('/actions/');
$actionId    = null;

$pageTitle    = 'Yeni Aksiyon';
$pageSubtitle = $riskRow !== null
    ? $riskRow['risk_code'] . ' — ' . str_limit($riskRow['title'], 70)
    : 'Bir riske bağlı aksiyon planı oluşturun';
$activeMenu   = 'actions';

require LAYOUT_PATH . '/header.php';
require __DIR__ . '/_form.php';
require LAYOUT_PATH . '/footer.php';
