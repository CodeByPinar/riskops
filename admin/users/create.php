<?php
declare(strict_types=1);

/**
 * RiskOps - Yeni kullanıcı formu
 * /var/www/riskops/admin/users/create.php
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_validate.php';

require_login();
require_role(ROLE_ADMIN);

$form = ['role' => ROLE_VIEWER, 'status' => '1'];

$formAction  = url('/admin/users/store.php');
$submitLabel = 'Kullanıcıyı Oluştur';
$cancelUrl   = url('/admin/users/');
$userId      = null;
$isSelf      = false;

$pageTitle    = 'Yeni Kullanıcı';
$pageSubtitle = 'Geçici parola kayıttan sonra bir kez gösterilecek';
$activeMenu   = 'admin.users';

require LAYOUT_PATH . '/header.php';
require __DIR__ . '/_form.php';
require LAYOUT_PATH . '/footer.php';
