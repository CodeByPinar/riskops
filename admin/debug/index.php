<?php
declare(strict_types=1);

/**
 * RiskOps - Hata ayıklama kipi yönetimi
 * /var/www/riskops/admin/debug/index.php
 *
 * Kipi açıp kapatmanın ve durumunu görmenin tek ekranı.
 *
 * Burada sunulan bilgi bilerek AYRINTILI: kipin hangi yoldan açık
 * olduğu (ortam değişkeni mi, bu ekrandan mı), ne zaman kapanacağı ve
 * kapatma yetkisinin bu ekranda olup olmadığı. "Kapattım ama hâlâ
 * açık" şaşkınlığının sebebi hemen her zaman ikinci bir kaynaktır.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
require_role(ROLE_ADMIN);

$pageTitle    = t('Hata Ayıklama');
$pageSubtitle = t('Geliştirme araçlarını geçici olarak açın');
$activeMenu   = 'admin.debug';

$flag      = debug_flag_read();
$source    = debug_source();
$remaining = debug_flag_remaining();

/* Ortamdan gelen kip bu ekrandan kapatılamaz; düğmenin ne yapıp ne
   yapamayacağı kullanıcıya önceden söylenmeli. */
$fromEnv   = defined('APP_DEBUG_FROM_ENV') && APP_DEBUG_FROM_ENV;
$storageOk = is_dir(STORAGE_PATH) && is_writable(STORAGE_PATH);

require LAYOUT_PATH . '/header.php';
?>

<div class="rk-card">
    <div class="rk-card-head">
        <h2 class="rk-card-title"><?= te('Durum') ?></h2>
    </div>
    <div class="rk-card-body">

        <div class="rk-dbgstate<?= APP_DEBUG ? ' is-on' : '' ?>">
            <div class="rk-dbgstate-ic">
                <i class="bi <?= APP_DEBUG ? 'bi-bug-fill' : 'bi-bug' ?>"></i>
            </div>
            <div class="rk-dbgstate-txt">
                <strong>
                    <?= APP_DEBUG ? te('Hata ayıklama kipi AÇIK') : te('Hata ayıklama kipi kapalı') ?>
                </strong>
                <span>
                    <?php if ($source === 'panel'): ?>
                        <?= te('Bu ekrandan açıldı') ?>
                        &middot; <?= e(debug_human_duration($remaining)) ?> <?= te('sonra kapanacak') ?>
                    <?php elseif ($source === 'ortam'): ?>
                        <?= te('Sunucu yapılandırmasından açık (RISKOPS_DEBUG) — bu ekrandan kapatılamaz') ?>
                    <?php elseif ($source === 'ortam+panel'): ?>
                        <?= te('Hem sunucu yapılandırmasından hem bu ekrandan açık') ?>
                    <?php else: ?>
                        <?= te('Araç çubuğu görünmüyor, sorgular kaydedilmiyor, istisnalar sade 500 sayfası olarak görünüyor') ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <?php if (!$storageOk): ?>
            <div class="rk-alert rk-alert-error">
                <i class="bi bi-exclamation-octagon"></i>
                <div>
                    <?= te('storage/ dizini yazılabilir değil; kip bu ekrandan açılamaz.') ?><br>
                    <code>sudo chown -R www-data:www-data <?= e(STORAGE_PATH) ?></code>
                </div>
            </div>
        <?php endif; ?>

        <div class="rk-dbgactions">
            <?php if (APP_DEBUG_FROM_PANEL): ?>

                <form method="post" action="<?= e(url('/admin/debug/toggle.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="disable">
                    <button type="submit" class="rk-btn rk-btn-danger">
                        <i class="bi bi-power"></i> <?= te('Şimdi kapat') ?>
                    </button>
                </form>

                <?php foreach (DEBUG_FLAG_DURATIONS as $min => $label): ?>
                    <form method="post" action="<?= e(url('/admin/debug/toggle.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="enable">
                        <input type="hidden" name="minutes" value="<?= e((string)$min) ?>">
                        <button type="submit" class="rk-btn">
                            <i class="bi bi-arrow-clockwise"></i>
                            <?= te('Süreyi uzat') ?> &middot; <?= e(t($label)) ?>
                        </button>
                    </form>
                <?php endforeach; ?>

            <?php else: ?>

                <?php foreach (DEBUG_FLAG_DURATIONS as $min => $label): ?>
                    <form method="post" action="<?= e(url('/admin/debug/toggle.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="enable">
                        <input type="hidden" name="minutes" value="<?= e((string)$min) ?>">
                        <button type="submit"
                                class="rk-btn <?= $min === (int)array_key_first(DEBUG_FLAG_DURATIONS) ? 'rk-btn-primary' : '' ?>"
                                <?= $storageOk ? '' : 'disabled' ?>>
                            <i class="bi bi-play-fill"></i>
                            <?= te('Aç') ?> &middot; <?= e(t($label)) ?>
                        </button>
                    </form>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>

        <p class="rk-help">
            <?= te('Süre dolduğunda kip kendiliğinden kapanır; bayrak dosyası silinmemiş olsa bile yok sayılır. Açık unutma riski bu yüzden yoktur.') ?>
        </p>

        <?php if ($flag !== null): ?>
            <dl class="rk-dl">
                <div class="rk-dl-row">
                    <dt><?= te('Açan') ?></dt>
                    <dd><?= e($flag['by_name'] !== '' ? $flag['by_name'] : '-') ?></dd>
                </div>
                <div class="rk-dl-row">
                    <dt><?= te('Açılma') ?></dt>
                    <dd><?= e(format_datetime(date('Y-m-d H:i:s', $flag['at']))) ?></dd>
                </div>
                <div class="rk-dl-row">
                    <dt><?= te('Bitiş') ?></dt>
                    <dd>
                        <?= e(format_datetime(date('Y-m-d H:i:s', $flag['until']))) ?>
                        <span class="rk-u-dim">(<?= e(debug_human_duration($remaining)) ?>)</span>
                    </dd>
                </div>
            </dl>
        <?php endif; ?>

    </div>
</div>

<div class="rk-card">
    <div class="rk-card-head">
        <h2 class="rk-card-title"><?= te('Açıkken ne değişir') ?></h2>
    </div>
    <div class="rk-card-body">
        <ul class="rk-dbglist">
            <li>
                <i class="bi bi-window-dock"></i>
                <div>
                    <strong><?= te('Araç çubuğu') ?></strong>
                    <span><?= te('Sayfanın altında: çalışan her SQL ve süresi, bağlanan parametreler, sorguyu açan dosya ve satır, zaman çizelgesi, istek ve oturum içeriği, log kuyruğu.') ?></span>
                </div>
            </li>
            <li>
                <i class="bi bi-bug"></i>
                <div>
                    <strong><?= te('Ayrıntılı hata sayfası') ?></strong>
                    <span><?= te('İstisnalar yığın izi, hatalı satırın kaynak parçası ve o isteğe kadar çalışmış sorgularla görünür. Kapalıyken aynı hata sade bir 500 sayfasıdır.') ?></span>
                </div>
            </li>
            <li>
                <i class="bi bi-file-earmark-text"></i>
                <div>
                    <strong><?= te('Ölçüm kaydı') ?></strong>
                    <span><code>storage/logs/debug.log</code> — <?= te('istek başına süre, bellek ve sorgu sayısı.') ?></span>
                </div>
            </li>
            <li>
                <i class="bi bi-shield-lock"></i>
                <div>
                    <strong><?= te('Neler gösterilmez') ?></strong>
                    <span><?= te('Parolalar, CSRF jetonu, oturum kimliği ve veritabanı parolası maskelenir. Üretim ortamında araç çubuğu yalnızca admin rolüne gösterilir.') ?></span>
                </div>
            </li>
        </ul>

        <?php if (APP_ENV === 'production'): ?>
            <div class="rk-alert rk-alert-warning rk-u-mb0">
                <i class="bi bi-exclamation-triangle"></i>
                <div><?= te('Bu kurulum üretim ortamında çalışıyor. Kipi yalnızca incelemeniz gereken süre boyunca açık tutun.') ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="rk-card">
    <div class="rk-card-head">
        <h2 class="rk-card-title"><?= te('Ortam') ?></h2>
    </div>
    <div class="rk-card-body is-flush">
        <table class="rk-table">
            <tbody>
            <?php
            $rows = [
                t('Ortam (APP_ENV)')      => APP_ENV,
                t('Sunucu değişkeni')     => $fromEnv ? 'RISKOPS_DEBUG=1' : t('ayarlı değil'),
                t('Panel bayrağı')        => APP_DEBUG_FROM_PANEL ? t('açık') : t('kapalı'),
                t('Bayrak dosyası')       => str_replace(APP_ROOT . '/', '', DEBUG_FLAG_FILE),
                t('Yavaş sorgu eşiği')    => DEBUG_SLOW_QUERY_MS . ' ms',
                t('Ölçüm kaydı kapsamı')  => DEBUG_LOG_ONLY_SLOW
                                                ? t('yalnızca yavaş/hatalı istekler')
                                                : t('her istek'),
                'PHP'                     => PHP_VERSION . ' / ' . PHP_SAPI,
            ];
            foreach ($rows as $k => $v):
                ?>
                <tr>
                    <th class="rk-w-30"><?= e((string)$k) ?></th>
                    <td><?= e((string)$v) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require LAYOUT_PATH . '/footer.php'; ?>
