<?php
declare(strict_types=1);

/**
 * RiskOps - Ayarlar
 * /var/www/riskops/admin/settings/index.php
 *
 * Ayarlar veritabanından gelir; bu ekran hiçbir anahtarı sabit kodlamaz.
 * Yeni bir ayar satırı eklendiğinde tipine uygun alan kendiliğinden çıkar.
 *
 * İstisna: severity_thresholds için özel bir editör vardır - dört bandın
 * 1-25 aralığını boşluksuz ve çakışmasız kapatması gerekir, bu kural
 * ham bir JSON metin alanıyla korunamaz.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login();
require_role(ROLE_ADMIN);

$rows = db_all(
    "SELECT * FROM settings ORDER BY FIELD(setting_group,'general','risk','security'), sort_order, id"
);

$groups = [];
foreach ($rows as $row) {
    $groups[$row['setting_group']][] = $row;
}

$groupMeta = [
    'general'  => ['Genel', 'bi-gear', 'Uygulama kimliği, tarih biçimleri ve liste davranışı'],
    'risk'     => ['Risk', 'bi-shield-exclamation', 'Risk skorlama ve termin uyarıları'],
    'security' => ['Güvenlik', 'bi-lock', 'Oturum, giriş denemeleri ve parola politikası'],
];

$val = static function (array $row) {
    return has_old($row['setting_key'])
        ? old($row['setting_key'])
        : (string)($row['setting_value'] ?? '');
};
$err = static function (string $key): string {
    $m = field_error($key);
    return $m !== '' ? '<div class="rk-error"><i class="bi bi-exclamation-circle"></i> ' . e($m) . '</div>' : '';
};
$cls = static function (string $key, string $base): string {
    return $base . (field_error($key) !== '' ? ' is-invalid' : '');
};

$thresholds = setting('severity_thresholds',
    ['Low' => [1, 4], 'Medium' => [5, 9], 'High' => [10, 16], 'Critical' => [17, 25]]);

$dateFormats     = ['d.m.Y', 'd/m/Y', 'Y-m-d', 'd F Y', 'j M Y'];
$dateTimeFormats = ['d.m.Y H:i', 'd/m/Y H:i', 'Y-m-d H:i', 'd F Y H:i'];
$commonZones     = ['Europe/Istanbul', 'Europe/London', 'Europe/Berlin', 'UTC',
                    'America/New_York', 'Asia/Dubai'];

$riskCount = (int)db_value('SELECT COUNT(*) FROM risks');

$pageTitle    = 'Ayarlar';
$pageSubtitle = count($rows) . ' ayar · değişiklikler audit log\'a yazılır';
$activeMenu   = 'admin.settings';

require LAYOUT_PATH . '/header.php';
?>

<form method="post" action="<?= e(url('/admin/settings/save.php')) ?>" novalidate>
    <?= csrf_field() ?>

    <?php foreach ($groups as $groupKey => $items):
        [$gLabel, $gIcon, $gDesc] = $groupMeta[$groupKey] ?? [ucfirst($groupKey), 'bi-sliders', ''];
    ?>
    <div class="rk-card">
        <div class="rk-card-head">
            <h2 class="rk-card-title"><i class="bi <?= e($gIcon) ?>"></i> <?= e($gLabel) ?></h2>
            <div class="rk-card-tools"><span class="rk-help"><?= e($gDesc) ?></span></div>
        </div>
        <div class="rk-card-body">

            <?php foreach ($items as $row):
                $key      = $row['setting_key'];
                $editable = (int)$row['is_editable'] === 1;
                $current  = $val($row);
            ?>
            <div class="rk-setting-row<?= $editable ? '' : ' is-locked' ?>">
                <div class="rk-setting-label">
                    <label for="s_<?= e($key) ?>"><?= e($row['label']) ?></label>
                    <code><?= e($key) ?></code>
                    <?php if (($row['description'] ?? '') !== ''): ?>
                        <div class="rk-help"><?= e($row['description']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="rk-setting-control">
                    <?php if (!$editable): ?>
                        <input class="rk-input" type="text" value="<?= e($current) ?>" disabled>
                        <div class="rk-help"><i class="bi bi-lock"></i> <?= te('Kod tarafından yönetilir.') ?></div>

                    <?php elseif ($key === 'severity_thresholds'): ?>
                        <div class="rk-threshold-editor" id="rkThresholds" data-rk-threshold-editor="rkThresholdPreview">
                            <?php foreach (['Low', 'Medium', 'High', 'Critical'] as $sev):
                                $band = $thresholds[$sev] ?? [0, 0];
                            ?>
                                <div class="rk-threshold-row">
                                    <span class="rk-badge <?= e(severity_class($sev)) ?>"><?= e($sev) ?></span>
                                    <input class="rk-input" type="number" min="1" max="25"
                                           name="threshold_<?= e($sev) ?>_min"
                                           value="<?= (int)($band[0] ?? 0) ?>"
                                           data-rk-threshold aria-label="<?= e($sev) ?> alt sınır">
                                    <span class="rk-threshold-dash">—</span>
                                    <input class="rk-input" type="number" min="1" max="25"
                                           name="threshold_<?= e($sev) ?>_max"
                                           value="<?= (int)($band[1] ?? 0) ?>"
                                           data-rk-threshold aria-label="<?= e($sev) ?> üst sınır">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?= $err('severity_thresholds') ?>
                        <div class="rk-help">
                            Dört bant 1–25 aralığını <strong>boşluksuz ve çakışmasız</strong> kapatmalıdır.
                            Skor = olasılık × etki.
                        </div>

                        <div class="rk-threshold-preview" id="rkThresholdPreview">
                            <div class="rk-help rk-u-mb6"><?= te('Önizleme (5×5 matris)') ?></div>
                            <table class="rk-matrix rk-matrix-mini">
                                <tbody>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <tr>
                                        <th><?= $i ?></th>
                                        <?php for ($l = 1; $l <= 5; $l++): ?>
                                            <td><div class="rk-mx-cell <?= e(severity_class(severity_from_score($l * $i))) ?>"
                                                     data-score="<?= $l * $i ?>"><?= $l * $i ?></div></td>
                                        <?php endfor; ?>
                                    </tr>
                                <?php endfor; ?>
                                <tr class="rk-matrix-foot">
                                    <th></th>
                                    <?php for ($l = 1; $l <= 5; $l++): ?><th><?= $l ?></th><?php endfor; ?>
                                </tr>
                                </tbody>
                            </table>
                        </div>

                    <?php elseif ($key === 'timezone'): ?>
                        <input class="<?= e($cls($key, 'rk-input')) ?>" type="text"
                               id="s_<?= e($key) ?>" name="<?= e($key) ?>"
                               value="<?= e($current) ?>" list="rkZones">
                        <datalist id="rkZones">
                            <?php foreach ($commonZones as $z): ?>
                                <option value="<?= e($z) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <?= $err($key) ?>
                        <div class="rk-help">Şu an: <?= e(date('d.m.Y H:i T')) ?></div>

                    <?php elseif ($key === 'date_format' || $key === 'datetime_format'): ?>
                        <?php $presets = $key === 'date_format' ? $dateFormats : $dateTimeFormats; ?>
                        <select class="<?= e($cls($key, 'rk-select')) ?>" id="s_<?= e($key) ?>" name="<?= e($key) ?>">
                            <?php foreach ($presets as $fmt): ?>
                                <option value="<?= e($fmt) ?>" <?= $current === $fmt ? 'selected' : '' ?>>
                                    <?= e($fmt) ?> &nbsp;—&nbsp; <?= e(date($fmt)) ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if (!in_array($current, $presets, true) && $current !== ''): ?>
                                <option value="<?= e($current) ?>" selected>
                                    <?= e($current) ?> — <?= e(@date($current)) ?>
                                </option>
                            <?php endif; ?>
                        </select>
                        <?= $err($key) ?>

                    <?php elseif ($row['setting_type'] === 'bool'): ?>
                        <label class="rk-check">
                            <input type="checkbox" id="s_<?= e($key) ?>" name="<?= e($key) ?>" value="1"
                                   <?= in_array(strtolower($current), ['1','true','yes','on'], true) ? 'checked' : '' ?>>
                            <span>Etkin</span>
                        </label>
                        <?= $err($key) ?>

                    <?php elseif ($row['setting_type'] === 'int'): ?>
                        <input class="<?= e($cls($key, 'rk-input')) ?>" type="number"
                               id="s_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($current) ?>">
                        <?= $err($key) ?>

                    <?php else: ?>
                        <input class="<?= e($cls($key, 'rk-input')) ?>" type="text" maxlength="255"
                               id="s_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($current) ?>">
                        <?= $err($key) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </div>
    <?php endforeach; ?>

    <div class="rk-card">
        <div class="rk-card-body">
            <?php if ($riskCount > 0): ?>
            <label class="rk-check rk-u-mb12">
                <input type="checkbox" name="recalculate" value="1" checked>
                <span>
                    Eşikler değişirse <strong><?= $riskCount ?> riskin</strong> ve
                    değerlendirme geçmişinin seviye etiketlerini yeniden hesapla
                </span>
            </label>
            <div class="rk-alert rk-alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    Bu kutuyu <strong>işaretsiz bırakırsanız</strong> mevcut riskler eski
                    eşiklere göre etiketlenmiş kalır ve liste ile matris birbiriyle çelişir.
                    Skorlar hiçbir durumda değişmez — yalnızca etiketler tazelenir.
                </div>
            </div>
            <?php endif; ?>

            <button type="submit" class="rk-btn rk-btn-primary">
                <i class="bi bi-check-lg"></i> <?= te('Ayarları Kaydet') ?>
            </button>
            <a class="rk-btn" href="<?= e(url('/admin/settings/')) ?>">Sıfırla</a>
        </div>
    </div>
</form>
<?php require LAYOUT_PATH . '/footer.php'; ?>
