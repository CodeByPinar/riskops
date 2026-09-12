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

        <?php $rkNavItem('dashboard', 'Dashboard', 'bi-speedometer2', '/dashboard/'); ?>

        <div class="rk-nav-section">Risk Management</div>
        <?php
        $rkNavItem('risks', 'Risk Register', 'bi-list-columns-reverse', '/risks/');
        if (can('risk.create')) {
            $rkNavItem('risks.create', 'Yeni Risk', 'bi-plus-square', '/risks/create.php');
        }
        $rkNavItem('assessments', 'Assessments', 'bi-clipboard-data', '/assessments/');
        $rkNavItem('actions', 'Action Plans', 'bi-check2-square', '/actions/');
        ?>

        <div class="rk-nav-section">Analiz</div>
        <?php $rkNavItem('reports', 'Reports', 'bi-bar-chart-line', '/reports/'); ?>

        <?php if (auth_role() === ROLE_ADMIN): ?>
            <div class="rk-nav-section">Administration</div>
            <?php
            $rkNavItem('admin.users',       'Users',       'bi-people',        '/admin/users/');
            $rkNavItem('admin.departments', 'Departments', 'bi-diagram-3',     '/admin/departments/');
            $rkNavItem('admin.categories',  'Categories',  'bi-tags',          '/admin/categories/');
            $rkNavItem('risks.deleted',     'Silinen Riskler', 'bi-trash3',    '/risks/deleted.php');
            $rkNavItem('admin.audit',       'Audit Logs',  'bi-journal-text',  '/admin/audit_logs/');
            $rkNavItem('admin.settings',    'Settings',    'bi-gear',          '/admin/settings/');
            ?>
        <?php endif; ?>

    </nav>

    <?php if ($user !== null): ?>
    <div class="rk-side-user">
        <?php /* Ad ve avatar profile baglanir: kullanicinin kendi
                 hesabini aradigi ilk yer burasi. */ ?>
        <a class="rk-side-user-link" href="<?= e(url('/profile/')) ?>" title="Profilim">
            <div class="rk-avatar"><?= e(initials($user['name'])) ?></div>
            <div class="rk-side-user-info">
                <div class="rk-side-user-name"><?= e($user['name']) ?></div>
                <div class="rk-side-user-role"><?= e(role_label($user['role'])) ?></div>
            </div>
        </a>
        <?php /* Demo hesabı parolasını değiştiremez; bağlantıyı da gösterme.
                 Asıl engel change_password.php içindedir - bu yalnızca
                 kullanıcıyı çalışmayan bir düğmeye tıklatmamak için. */ ?>
        <?php if (!(APP_DEMO && strcasecmp((string)($user['email'] ?? ''), DEMO_EMAIL) === 0)): ?>
        <a class="rk-logout-btn" href="<?= e(url('/auth/change_password.php')) ?>"
           title="Parola değiştir" aria-label="Parola değiştir">
            <i class="bi bi-key"></i>
        </a>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/auth/logout.php')) ?>" class="m-0">
            <?= csrf_field() ?>
            <button type="submit" class="rk-logout-btn" title="Çıkış yap" aria-label="Çıkış yap">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </form>
    </div>
    <?php endif; ?>

</aside>
