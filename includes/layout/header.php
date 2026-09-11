<?php
declare(strict_types=1);

/**
 * RiskOps - Sayfa ust sablonu
 * /var/www/riskops/includes/layout/header.php
 *
 * Sayfalarda kullanım:
 *   $pageTitle    = 'Risk Register';
 *   $pageSubtitle = 'Tüm kurumsal IT ve siber risk kayıtları';
 *   $activeMenu   = 'risks';
 *   $pageActions  = '<a class="rk-btn rk-btn-primary" href="...">Yeni Risk</a>';
 *   require LAYOUT_PATH . '/header.php';
 *   ... içerik ...
 *   require LAYOUT_PATH . '/footer.php';
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

$pageTitle    = $pageTitle    ?? '';
$pageSubtitle = $pageSubtitle ?? '';
$activeMenu   = $activeMenu   ?? '';
$pageActions  = $pageActions  ?? '';   // HAM HTML - sayfa kendisi kacislamakla yukumlu
$assetVersion = '20260911q';
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle !== '' ? $pageTitle . ' - ' . app_name() : app_name()) ?></title>
<?= favicon_tags() ?>

<link rel="stylesheet" href="<?= e(url('/assets/vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(url('/assets/vendor/bootstrap-icons/bootstrap-icons.css')) ?>">
<link rel="stylesheet" href="<?= e(url('/assets/css/app.css?v=' . $assetVersion)) ?>">
<link rel="stylesheet" media="print" href="<?= e(url('/assets/css/print.css?v=' . $assetVersion)) ?>">
</head>
<body class="rk-body">

<?php require LAYOUT_PATH . '/sidebar.php'; ?>

<div class="rk-backdrop" data-rk-sidebar-close></div>

<div class="rk-main">

    <?php require LAYOUT_PATH . '/navbar.php'; ?>

    <main class="rk-content">

        <?php if ($pageTitle !== ''): ?>
        <div class="rk-page-head">
            <div>
                <h1 class="rk-page-title"><?= e($pageTitle) ?></h1>
                <?php if ($pageSubtitle !== ''): ?>
                    <p class="rk-page-sub"><?= e($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($pageActions !== ''): ?>
                <div class="rk-page-actions"><?= $pageActions ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php require PARTIALS_PATH . '/print_letterhead.php'; ?>
        <?php require PARTIALS_PATH . '/alerts.php'; ?>
