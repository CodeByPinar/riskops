<?php
declare(strict_types=1);

/**
 * RiskOps - Yeni değerlendirme
 * /var/www/riskops/assessments/create.php
 *
 * Bu ekran riski yeniden değerlendirmek içindir: skoru günceller VE
 * geçmişe bir satır yazar. Riskin diğer alanlarını değiştirmek için
 * risk düzenleme formu kullanılır.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_validate.php';

require_login();
require_can('risk.assess');

$risk = assessment_find_risk(input_int('risk_id'));

$risks = [];
if ($risk === null) {
    $risks = db()->query(
        'SELECT id, risk_code, title FROM risks
         WHERE deleted_at IS NULL ORDER BY risk_code DESC'
    )->fetchAll();

    if ($risks === []) {
        flash('warning', 'Önce en az bir risk kaydı oluşturmalısınız.');
        redirect('/risks/');
    }
}

$val = static function (string $key, string $default = '') {
    return has_old($key) ? old($key) : $default;
};
$cls = static function (string $key, string $base): string {
    return $base . (field_error($key) !== '' ? ' is-invalid' : '');
};
$err = static function (string $key): string {
    $m = field_error($key);
    return $m !== '' ? '<div class="rk-error"><i class="bi bi-exclamation-circle"></i> ' . e($m) . '</div>' : '';
};
$intOrNull = static function (string $v): ?int {
    return $v === '' ? null : (int)$v;
};

$thresholdsJson = json_encode(
    setting('severity_thresholds', ['Low' => [1, 4], 'Medium' => [5, 9], 'High' => [10, 16], 'Critical' => [17, 25]]),
    JSON_UNESCAPED_UNICODE
);

$pageTitle    = 'Yeni Değerlendirme';
$pageSubtitle = $risk !== null
    ? $risk['risk_code'] . ' — ' . str_limit($risk['title'], 70)
    : 'Bir riski yeniden değerlendirin';
$activeMenu   = 'assessments';

require LAYOUT_PATH . '/header.php';
?>

<form method="post" action="<?= e(url('/assessments/store.php')) ?>" novalidate>
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="rk-card">
                <div class="rk-card-head">
                    <h2 class="rk-card-title"><i class="bi bi-clipboard-data"></i> Değerlendirme</h2>
                </div>
                <div class="rk-card-body">

                    <div class="rk-field">
                        <label class="rk-label" for="risk_id">Risk <span class="req">*</span></label>
                        <?php if ($risk !== null): ?>
                            <input type="hidden" name="risk_id" value="<?= (int)$risk['id'] ?>">
                            <div class="rk-locked-field">
                                <span class="rk-code"><?= e($risk['risk_code']) ?></span>
                                <span><?= e(str_limit($risk['title'], 62)) ?></span>
                                <a class="rk-btn rk-btn-sm" href="<?= e(url('/risks/view.php?id=' . (int)$risk['id'])) ?>">
                                    <i class="bi bi-box-arrow-up-right"></i> Riske git
                                </a>
                            </div>
                        <?php else: ?>
                            <select class="<?= e($cls('risk_id', 'rk-select')) ?>" id="risk_id" name="risk_id" required>
                                <option value="">Seçiniz</option>
                                <?php $sel = $intOrNull($val('risk_id'));
                                foreach ($risks as $r): ?>
                                    <option value="<?= (int)$r['id'] ?>" <?= $sel === (int)$r['id'] ? 'selected' : '' ?>>
                                        <?= e($r['risk_code'] . ' — ' . str_limit($r['title'], 60)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <?= $err('risk_id') ?>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="assessment_type">Tür <span class="req">*</span></label>
                        <select class="<?= e($cls('assessment_type', 'rk-select')) ?>"
                                id="assessment_type" name="assessment_type" required>
                            <?php $selType = $val('assessment_type', 'review');
                            foreach (assessment_types() as $t): ?>
                                <option value="<?= e($t) ?>" <?= $selType === $t ? 'selected' : '' ?>>
                                    <?= e(assessment_type_label($t)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('assessment_type') ?>
                        <div class="rk-help">
                            <strong>Gözden geçirme</strong> riskin ham (inherent) skorunu günceller.
                            <strong>Residual</strong> kontroller sonrası kalan riski günceller ve
                            inherent skoru aşamaz.
                        </div>
                    </div>

                    <div class="rk-score-row" data-rk-score-scope>
                        <div class="rk-field">
                            <label class="rk-label" for="likelihood">Olasılık <span class="req">*</span></label>
                            <select class="<?= e($cls('likelihood', 'rk-select')) ?>" id="likelihood"
                                    name="likelihood" data-rk-score="likelihood" required>
                                <option value="">Seçiniz</option>
                                <?= options_from_scale(likelihood_labels(), $intOrNull($val('likelihood'))) ?>
                            </select>
                            <?= $err('likelihood') ?>
                        </div>

                        <div class="rk-field">
                            <label class="rk-label" for="impact">Etki <span class="req">*</span></label>
                            <select class="<?= e($cls('impact', 'rk-select')) ?>" id="impact"
                                    name="impact" data-rk-score="impact" required>
                                <option value="">Seçiniz</option>
                                <?= options_from_scale(impact_labels(), $intOrNull($val('impact'))) ?>
                            </select>
                            <?= $err('impact') ?>
                        </div>

                        <div class="rk-score-out">
                            <span class="rk-label">Skor</span>
                            <span class="rk-score sev-none" data-rk-score-out
                                  data-rk-thresholds='<?= e($thresholdsJson) ?>'>-</span>
                        </div>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="notes">Gerekçe / Not</label>
                        <textarea class="<?= e($cls('notes', 'rk-textarea')) ?>" id="notes" name="notes"
                                  rows="4" maxlength="2000"
                                  placeholder="Skorun neden değiştiği, hangi kontrolün devreye girdiği"><?= e($val('notes')) ?></textarea>
                        <?= $err('notes') ?>
                        <div class="rk-help">
                            Denetimde en çok sorulan soru budur: skor neden değişti?
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <?php if ($risk !== null): ?>
            <div class="rk-card">
                <div class="rk-card-head">
                    <h2 class="rk-card-title"><i class="bi bi-clock-history"></i> Mevcut Durum</h2>
                </div>
                <div class="rk-card-body">
                    <dl class="rk-dl">
                        <div class="rk-dl-row">
                            <dt>Inherent</dt>
                            <dd>
                                <?= score_chip((int)$risk['inherent_score']) ?>
                                <span class="rk-cell-sub">
                                    O<?= (int)$risk['likelihood'] ?> × E<?= (int)$risk['impact'] ?>
                                </span>
                            </dd>
                        </div>
                        <div class="rk-dl-row">
                            <dt>Residual</dt>
                            <dd>
                                <?php if ($risk['residual_likelihood'] !== null && $risk['residual_impact'] !== null): ?>
                                    <?= score_chip((int)$risk['residual_likelihood'] * (int)$risk['residual_impact']) ?>
                                    <span class="rk-cell-sub">
                                        O<?= (int)$risk['residual_likelihood'] ?> × E<?= (int)$risk['residual_impact'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Henüz değerlendirilmedi</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="rk-dl-row">
                            <dt>Durum</dt>
                            <dd><?= status_badge($risk['status']) ?></dd>
                        </div>
                    </dl>
                </div>
            </div>
            <?php endif; ?>

            <div class="rk-card">
                <div class="rk-card-head">
                    <h2 class="rk-card-title"><i class="bi bi-calendar-event"></i> Tarih</h2>
                </div>
                <div class="rk-card-body">
                    <div class="rk-field rk-u-mb0">
                        <label class="rk-label" for="assessed_at">Değerlendirme Tarihi</label>
                        <input class="<?= e($cls('assessed_at', 'rk-input')) ?>" type="date"
                               id="assessed_at" name="assessed_at"
                               value="<?= e($val('assessed_at', date('Y-m-d'))) ?>"
                               max="<?= e(date('Y-m-d')) ?>">
                        <?= $err('assessed_at') ?>
                        <div class="rk-help">Geriye dönük kayıt girilebilir, ileri tarih girilemez.</div>
                    </div>
                </div>
            </div>

            <div class="rk-alert rk-alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    Bu kayıt <strong>riskin skorunu günceller</strong> ve değerlendirme
                    geçmişine eklenir. Geçmiş kayıtları silinemez veya düzenlenemez.
                </div>
            </div>

            <div class="rk-form-actions">
                <button type="submit" class="rk-btn rk-btn-primary">
                    <i class="bi bi-check-lg"></i> Değerlendirmeyi Kaydet
                </button>
                <a class="rk-btn" href="<?= e($risk !== null
                    ? url('/risks/view.php?id=' . (int)$risk['id'])
                    : url('/assessments/')) ?>">Vazgeç</a>
            </div>
        </div>
    </div>
</form>

<?php require LAYOUT_PATH . '/footer.php'; ?>
