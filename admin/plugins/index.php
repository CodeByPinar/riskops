<?php

declare(strict_types=1);

/**
 * RiskOps - Eklenti yönetimi
 * /var/www/riskops/admin/plugins/index.php
 *
 * Diskteki eklentileri listeler, açar, kapatır.
 *
 * Bu ekranda YÜKLEME YOKTUR ve bu bilinçlidir - gerekçesi sayfanın
 * kendi uyarı kutusunda kullanıcıya da yazılıyor.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
require_role(ROLE_ADMIN);

$pageTitle    = t('Eklentiler');
$pageSubtitle = t('Diskteki eklentileri açın veya kapatın');
$activeMenu   = 'admin.plugins';

$discovered = plugins_discover();
$enabled    = plugins_enabled();
$loaded     = plugins_loaded();
$storageOk  = is_dir(STORAGE_PATH) && is_writable(STORAGE_PATH);

/* HATALAR BURADA OKUNMAZ.
   plugins_errors() sayfa ÇİZİLİRKEN de dolabilir: sol menü
   nav.items filtresini header.php içinde çalıştırıyor, yani bu
   noktadan SONRA. Burada bir değişkene alsaydık, tam da bu ekranın
   göstermesi gereken hatayı - menüyü patlatan eklentiyi - kaçırırdık.
   Aşağıda, şablonun içinde çağrılıyor. */

/* Açık ama yüklenememiş olanlar: bildirimi geçerli, kancası patlamış
   ya da PHP sürümü yetmemiş. Sessizce "açık" göstermek yanıltıcı
   olurdu. */
$failed = array_values(array_diff($enabled, $loaded));

require LAYOUT_PATH . '/header.php';
?>

<?php if (!$storageOk): ?>
    <div class="rk-alert rk-alert-error">
        <i class="bi bi-exclamation-octagon"></i>
        <div>
            <?= te('storage/ dizini yazılabilir değil; eklentiler bu ekrandan açılıp kapatılamaz.') ?><br>
            <code>sudo chown -R www-data:www-data <?= e(STORAGE_PATH) ?></code>
        </div>
    </div>
<?php endif; ?>

<?php $errors = plugins_errors(); ?>
<?php if ($errors !== []): ?>
    <div class="rk-alert rk-alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        <div>
            <strong><?= te('Bu istekte eklenti hatası oluştu') ?></strong>
            <ul class="rk-u-mb0">
                <?php foreach ($errors as $err): ?>
                    <li>
                        <code><?= e($err['hook']) ?></code> —
                        <?= e($err['message']) ?>
                        <span class="rk-u-dim"><?= e($err['file']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="rk-card">
    <div class="rk-card-head">
        <h2 class="rk-card-title"><?= te('Kurulu eklentiler') ?></h2>
        <span class="rk-card-tools rk-u-dim">
            <?= e((string)count($discovered)) ?> <?= te('bulundu') ?> ·
            <?= e((string)count($loaded)) ?> <?= te('yüklü') ?>
        </span>
    </div>

    <?php if ($discovered === []): ?>
        <div class="rk-card-body">
            <?= empty_state(
                t('Henüz eklenti yok'),
                t('Eklentiler plugins/ dizinine konur. Her eklenti kendi klasöründe bir plugin.php dosyası taşır.'),
                'bi-puzzle'
            ) ?>
        </div>
    <?php else: ?>
        <div class="rk-table-wrap">
            <table class="rk-table">
                <thead>
                    <tr>
                        <th><?= te('Eklenti') ?></th>
                        <th><?= te('Sürüm') ?></th>
                        <th><?= te('Durum') ?></th>
                        <th class="rk-u-shrink"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($discovered as $slug => $plugin): ?>
                    <?php
                    $isEnabled = in_array($slug, $enabled, true);
                    $isLoaded  = in_array($slug, $loaded, true);
                    $isFailed  = in_array($slug, $failed, true);
                    ?>
                    <tr>
                        <td>
                            <span class="rk-link-strong"><?= e($plugin['name']) ?></span>
                            <?php if ($plugin['description'] !== ''): ?>
                                <div class="rk-cell-sub"><?= e($plugin['description']) ?></div>
                            <?php endif; ?>
                            <div class="rk-cell-sub">
                                <code>plugins/<?= e($slug) ?>/</code>
                                <?php if ($plugin['author'] !== ''): ?>
                                    · <?= e($plugin['author']) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="rk-u-nowrap"><?= e($plugin['version']) ?></td>
                        <td>
                            <?php if ($isFailed): ?>
                                <span class="rk-badge sev-critical"><?= te('Yüklenemedi') ?></span>
                            <?php elseif ($isLoaded): ?>
                                <span class="rk-badge sev-low"><?= te('Eklenti açık') ?></span>
                            <?php else: ?>
                                <span class="rk-badge sev-none"><?= te('Eklenti kapalı') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="rk-u-shrink">
                            <form method="post" action="<?= e(url('/admin/plugins/toggle.php')) ?>"
                                  <?= $isEnabled ? 'data-rk-confirm="' . e(t('Eklenti kapatılacak. Emin misiniz?')) . '"' : '' ?>>
                                <?= csrf_field() ?>
                                <input type="hidden" name="slug" value="<?= e($slug) ?>">
                                <input type="hidden" name="action" value="<?= $isEnabled ? 'disable' : 'enable' ?>">
                                <button type="submit"
                                        class="rk-btn rk-btn-sm <?= $isEnabled ? 'rk-btn-danger' : 'rk-btn-primary' ?>"
                                        <?= $storageOk ? '' : 'disabled' ?>>
                                    <?= $isEnabled ? te('Eklentiyi kapat') : te('Eklentiyi aç') ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="rk-card">
    <div class="rk-card-head">
        <h2 class="rk-card-title"><?= te('Eklenti kurmak') ?></h2>
    </div>
    <div class="rk-card-body">

        <div class="rk-alert rk-alert-warning">
            <i class="bi bi-shield-exclamation"></i>
            <div>
                <strong><?= te('Eklenti kurmak, yazarına çekirdek kadar güvenmektir.') ?></strong><br>
                <?= te('Eklenti, uygulamanın kendi yetkileriyle çalışan PHP kodudur: veritabanına erişebilir, dosya yazabilir, ağa çıkabilir. PHP\'de gerçek bir kum havuzu yoktur; bu yüzden kısıtlandığı iddia edilmiyor.') ?>
            </div>
        </div>

        <p>
            <?= te('Bu ekrandan dosya YÜKLENEMEZ ve bu bilinçli bir karardır: yükleme ucu olsaydı, ele geçirilmiş tek bir yönetici hesabı doğrudan uzaktan kod çalıştırmaya dönüşürdü. Kurulum sunucuda yapılır:') ?>
        </p>

        <pre class="rk-code-block">cd <?= e(APP_ROOT) ?>/plugins
git clone &lt;eklenti-adresi&gt; eklenti-adi
# ya da: scp -r eklenti-adi sunucu:<?= e(APP_ROOT) ?>/plugins/</pre>

        <p class="rk-help">
            <?= te('Dosyalar yerine konduktan sonra eklenti bu listede görünür ve buradan açılabilir. Yazma yetkisi gerektirmez; yalnızca açık eklenti listesi storage/plugins.json içinde tutulur.') ?>
        </p>

        <h3 class="rk-subhead"><?= te('Mevcut kancalar') ?></h3>
        <table class="rk-table">
            <tbody>
                <?php
                $hooks = [
                    'risk.created'        => t('Risk oluşturuldu (eylem) — $riskId, $veri'),
                    'risk.updated'        => t('Risk güncellendi (eylem) — $riskId, $eski, $yeni'),
                    'risk.deleted'        => t('Risk silindi (eylem) — $riskId, $veri'),
                    'nav.items'           => t('Sol menü (filtre) — menü dizisi'),
                    'reports.definitions' => t('Rapor kayıt defteri (filtre) — rapor tanımları'),
                ];
                foreach ($hooks as $hook => $aciklama):
                    ?>
                    <tr>
                        <th class="rk-w-30"><code><?= e($hook) ?></code></th>
                        <td><?= e($aciklama) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="rk-help">
            <?= te('Örnek eklenti: plugins/ornek-kayit-defteri/ — hem eylem hem filtre kancası kullanıyor ve yorumlarla açıklıyor.') ?>
        </p>
    </div>
</div>

<?php if (hook_map() !== []): ?>
<div class="rk-card">
    <div class="rk-card-head">
        <h2 class="rk-card-title"><?= te('Bu istekte kayıtlı dinleyiciler') ?></h2>
    </div>
    <div class="rk-card-body is-flush">
        <table class="rk-table">
            <tbody>
            <?php foreach (hook_map() as $hook => $count): ?>
                <tr>
                    <th class="rk-w-30"><code><?= e($hook) ?></code></th>
                    <td><?= e((string)$count) ?> <?= te('dinleyici') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require LAYOUT_PATH . '/footer.php'; ?>
