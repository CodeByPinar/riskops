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

/**
 * Menü, ÖNCE VERİ OLARAK kurulur, sonra basılır.
 *
 * Neden: eklentiler menüye giriş ekleyebilsin. Doğrudan echo eden bir
 * menüde araya girilecek bir nokta yok; dizi hâlindeyken
 * hook_filter('nav.items', ...) ile eklenebiliyor, çıkarılabiliyor,
 * yeniden sıralanabiliyor.
 *
 * Girdi biçimi:
 *   ['section' => 'Başlık']                     bölüm ayracı
 *   ['key','label','icon','href']               menü girişi
 *
 * key, aktif girişi belirlemek için kullanılır ($activeMenu ile
 * karşılaştırılır; 'risks' anahtarı 'risks.create' sayfasında da aktif
 * sayılır).
 */
$navItems = [
    ['key' => 'dashboard', 'label' => t('Panel'), 'icon' => 'bi-speedometer2', 'href' => '/dashboard/'],

    ['section' => t('Risk Yönetimi')],
    ['key' => 'risks', 'label' => t('Risk Kaydı'), 'icon' => 'bi-list-columns-reverse', 'href' => '/risks/'],
];

if (can('risk.create')) {
    $navItems[] = ['key' => 'risks.create', 'label' => t('Yeni Risk'), 'icon' => 'bi-plus-square', 'href' => '/risks/create.php'];
}

$navItems[] = ['key' => 'assessments', 'label' => t('Değerlendirmeler'), 'icon' => 'bi-clipboard-data', 'href' => '/assessments/'];
$navItems[] = ['key' => 'actions', 'label' => t('Aksiyon Planları'), 'icon' => 'bi-check2-square', 'href' => '/actions/'];

$navItems[] = ['section' => t('Analiz')];
$navItems[] = ['key' => 'reports', 'label' => t('Raporlar'), 'icon' => 'bi-bar-chart-line', 'href' => '/reports/'];

if (auth_role() === ROLE_ADMIN) {
    $navItems[] = ['section' => t('Yönetim')];
    $navItems[] = ['key' => 'admin.users', 'label' => t('Kullanıcılar'), 'icon' => 'bi-people', 'href' => '/admin/users/'];
    $navItems[] = ['key' => 'admin.departments', 'label' => t('Departmanlar'), 'icon' => 'bi-diagram-3', 'href' => '/admin/departments/'];
    $navItems[] = ['key' => 'admin.categories', 'label' => t('Kategoriler'), 'icon' => 'bi-tags', 'href' => '/admin/categories/'];
    $navItems[] = ['key' => 'risks.deleted', 'label' => t('Silinen Riskler'), 'icon' => 'bi-trash3', 'href' => '/risks/deleted.php'];
    $navItems[] = ['key' => 'admin.audit', 'label' => t('Denetim Kaydı'), 'icon' => 'bi-journal-text', 'href' => '/admin/audit_logs/'];
    $navItems[] = ['key' => 'admin.settings', 'label' => t('Ayarlar'), 'icon' => 'bi-gear', 'href' => '/admin/settings/'];
    $navItems[] = ['key' => 'admin.plugins', 'label' => t('Eklentiler'), 'icon' => 'bi-puzzle', 'href' => '/admin/plugins/'];
    $navItems[] = ['key' => 'admin.debug', 'label' => t('Hata Ayıklama'), 'icon' => 'bi-bug', 'href' => '/admin/debug/'];
}

/* Eklentiler burada araya girer. Dönen dizi GÜVENİLMEZ kabul edilir;
   aşağıdaki basma döngüsü her alanı doğrular ve kaçışlar. */
$navItems = hook_filter('nav.items', $navItems);

$user = auth_user();
?>
<aside class="rk-sidebar" id="rkSidebar">

    <div class="rk-brand">
        <a class="rk-brand-link" href="<?= e(url('/dashboard/')) ?>"
           aria-label="<?= e(app_name()) ?> - <?= te('Panel') ?>">
            <?= brand_wordmark('dark') ?>
        </a>
    </div>

    <nav class="rk-nav">
        <?php
        foreach ($navItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            /* --- Bölüm ayracı --- */
            if (isset($item['section'])) {
                echo '<div class="rk-nav-section">' . e((string)$item['section']) . '</div>';
                continue;
            }

            if (!isset($item['label'], $item['href'])) {
                continue;
            }

            $href = (string)$item['href'];

            /* SİTE İÇİ YOL ZORUNLU.
               Menü girişi bir eklentiden gelmiş olabilir ve href
               doğrudan bağlantıya yazılıyor. "javascript:..." bir XSS
               vektörüdür; "//baska.site" ise kullanıcıyı dışarı taşır.
               Tek eğik çizgiyle başlamayan her şey reddedilir. */
            if (!str_starts_with($href, '/') || str_starts_with($href, '//')) {
                continue;
            }

            $key      = isset($item['key']) ? (string)$item['key'] : '';
            $icon     = isset($item['icon']) ? (string)$item['icon'] : 'bi-dot';
            $isActive = $key !== '' && (($activeMenu === $key) || str_starts_with($activeMenu, $key . '.'));

            /* İkon sınıfı da dışarıdan gelebilir: yalnızca Bootstrap
               Icons adlandırması kabul edilir. */
            if (preg_match('/^bi-[a-z0-9-]+$/', $icon) !== 1) {
                $icon = 'bi-dot';
            }

            echo '<a class="rk-nav-link' . ($isActive ? ' active' : '') . '" href="' . e(url($href)) . '">'
               . '<i class="bi ' . e($icon) . '"></i><span>' . e((string)$item['label']) . '</span></a>';
        }
        ?>
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
