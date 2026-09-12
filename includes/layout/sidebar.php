<?php
declare(strict_types=1);

/**
 * RiskOps - Sol menu
 * /var/www/riskops/includes/layout/sidebar.php
 *
 * NOT: Buradaki can() kontrolleri YALNIZCA gorsel amaclidir.
 *      Gerçek yetki kontrolü her sayfanin kendi require_can() cagrisindadir.
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

$activeMenu = $activeMenu ?? '';

/** Menu ogesi ciktilar. */
$rkNavItem = static function (string $key, string $label, string $icon, string $href) use ($activeMenu): void {
    $isActive = ($activeMenu === $key) || str_starts_with($activeMenu, $key . '.');
    echo '<a class="rk-nav-link' . ($isActive ? ' active' : '') . '" href="' . e(url($href)) . '">'
       . '<i class="bi ' . e($icon) . '"></i><span>' . e($label) . '</span></a>';
};

$user = auth_user();
?>
<aside class="rk-sidebar" id="rkSidebar">

    <div class="rk-brand">
        <a class="rk-brand-link" href="<?= e(url('/dashboard/')) ?>"
           aria-label="<?= e(app_name()) ?> - Dashboard">
            <?= brand_wordmark('dark') ?>
        </a>
    </div>

    <nav class="rk-nav">

        <?php $rkNavItem('dashboard', t('Dashboard'), 'bi-speedometer2', '/dashboard/'); ?>

        <div class="rk-nav-section"><?= te('Risk Management') ?></div>
        <?php
        $rkNavItem('risks', t('Risk Register'), 'bi-list-columns-reverse', '/risks/');
        if (can('risk.create')) {
            $rkNavItem('risks.create', t('Yeni Risk'), 'bi-plus-square', '/risks/create.php');
        }
        $rkNavItem('assessments', t('Assessments'), 'bi-clipboard-data', '/assessments/');
        $rkNavItem('actions', t('Action Plans'), 'bi-check2-square', '/actions/');
        ?>

        <div class="rk-nav-section"><?= te('Analiz') ?></div>
        <?php $rkNavItem('reports', t('Reports'), 'bi-bar-chart-line', '/reports/'); ?>

        <?php if (auth_role() === ROLE_ADMIN): ?>
            <div class="rk-nav-section"><?= te('Administration') ?></div>
            <?php
            $rkNavItem('admin.users',       t('Users'),       'bi-people',        '/admin/users/');
            $rkNavItem('admin.departments', t('Departments'), 'bi-diagram-3',     '/admin/departments/');
            $rkNavItem('admin.categories',  t('Categories'),  'bi-tags',          '/admin/categories/');
            $rkNavItem('risks.deleted',     t('Silinen Riskler'), 'bi-trash3',    '/risks/deleted.php');
            $rkNavItem('admin.audit',       t('Audit Logs'),  'bi-journal-text',  '/admin/audit_logs/');
            $rkNavItem('admin.settings',    t('Settings'),    'bi-gear',          '/admin/settings/');
            ?>
        <?php endif; ?>

    </nav>

    <?php if ($user !== null): ?>
    <?php
    /* Dil degistirici. GET ile calisir - degisen tek sey goruntuleme
       dilidir: veri degismez, yetki degismez, geri alinabilir. */
    $rkLocales = i18n_locales();
    ?>
    <?php if (count($rkLocales) > 1): ?>
    <div class="rk-side-locale">
        <?php foreach ($rkLocales as $code => $name): ?>
            <a class="rk-locale-btn<?= locale() === $code ? ' is-active' : '' ?>"
               href="<?= e(locale_switch_url($code)) ?>"
               title="<?= e($name) ?>"><?= e(strtoupper($code)) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="rk-side-user">
        <?php /* Ad ve avatar profile baglanir: kullanicinin kendi
                 hesabini aradigi ilk yer burasi. */ ?>
        <a class="rk-side-user-link" href="<?= e(url('/profile/')) ?>" title="<?= te('Profilim') ?>">
            <div class="rk-avatar"><?= e(initials($user['name'])) ?></div>
            <div class="rk-side-user-info">
                <div class="rk-side-user-name"><?= e($user['name']) ?></div>
                <div class="rk-side-user-role"><?= te(role_label($user['role'])) ?></div>
            </div>
        </a>
        <?php /* Demo hesabı parolasını değiştiremez; bağlantıyı da gösterme.
                 Asıl engel change_password.php içindedir - bu yalnızca
                 kullanıcıyı çalışmayan bir düğmeye tıklatmamak için. */ ?>
        <?php if (!(APP_DEMO && strcasecmp((string)($user['email'] ?? ''), DEMO_EMAIL) === 0)): ?>
        <a class="rk-logout-btn" href="<?= e(url('/auth/change_password.php')) ?>"
           title="<?= te('Parola değiştir') ?>" aria-label="<?= te('Parola değiştir') ?>">
            <i class="bi bi-key"></i>
        </a>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/auth/logout.php')) ?>" class="m-0">
            <?= csrf_field() ?>
            <button type="submit" class="rk-logout-btn" title="<?= te('Çıkış yap') ?>" aria-label="<?= te('Çıkış yap') ?>">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </form>
    </div>
    <?php endif; ?>

</aside>
