<?php
declare(strict_types=1);

/**
 * RiskOps - Risk formu (create + edit ortak)
 * /var/www/riskops/risks/_form.php
 *
 * Beklenen degiskenler:
 *   $form        array   alan degerleri
 *   $formAction  string  POST hedefi
 *   $submitLabel string  buton metni
 *   $cancelUrl   string
 *   $riskId      ?int    edit ise risk id (hidden alan için)
 *
 * Alan degerleri once "old input"tan (doğrulama hatasindan sonra),
 * yoksa $form'dan okunur.
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

/* Bu parça bir GİRİŞ NOKTASI DEĞİL: create.php ve edit.php içinden
   include ediliyor ve değişkenleri onların kapsamından alıyor.
   Aşağıdaki bildirimler o sözleşmeyi makine okunur hâle getirir -
   yukarıdaki düz metin listeyle aynı şeyi söylüyorlar, ama bunu
   statik çözümleme de doğrulayabiliyor. */
/** @var array<string, mixed> $form */
/** @var string $formAction */
/** @var string $submitLabel */
/** @var string $cancelUrl */

/** Alan değeri: once old input, yoksa $form. */
$val = static function (string $key) use ($form): string {
    return has_old($key) ? old($key) : (string)($form[$key] ?? '');
};

/** Alan için hata sinifi. */
$cls = static function (string $key, string $base): string {
    return $base . (field_error($key) !== '' ? ' is-invalid' : '');
};

/** Hata mesaji satırı. */
$err = static function (string $key): string {
    $m = field_error($key);
    return $m !== '' ? '<div class="rk-error"><i class="bi bi-exclamation-circle"></i> ' . e($m) . '</div>' : '';
};

$thresholdsJson = json_encode(
    setting('severity_thresholds', ['Low' => [1, 4], 'Medium' => [5, 9], 'High' => [10, 16], 'Critical' => [17, 25]]),
    JSON_UNESCAPED_UNICODE
);

$intOrNull = static function (string $v): ?int {
    return $v === '' ? null : (int)$v;
};
?>
<form method="post" action="<?= e($formAction) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if (!empty($riskId)): ?>
        <input type="hidden" name="id" value="<?= (int)$riskId ?>">
    <?php endif; ?>

    <div class="row g-3">

        <!-- ============================ Tanım ============================ -->
        <div class="col-12 col-xl-8">
            <div class="rk-card">
                <div class="rk-card-head">
                    <h2 class="rk-card-title"><i class="bi bi-file-text"></i> <?= te('Risk Tanımı') ?></h2>
                </div>
                <div class="rk-card-body">

                    <div class="rk-field">
                        <label class="rk-label" for="title"><?= te('Başlık') ?> <span class="req">*</span></label>
                        <input class="<?= e($cls('title', 'rk-input')) ?>" type="text" id="title" name="title"
                               maxlength="200" required
                               value="<?= e($val('title')) ?>"
                               placeholder="<?= te('Örn: Internete açık RDP servisleri üzerinden yetkisiz erişim') ?>">
                        <?= $err('title') ?>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="description"><?= te('Açıklama') ?></label>
                        <textarea class="rk-textarea" id="description" name="description" rows="4"
                                  placeholder="<?= te('Riskin ne olduğu, hangi koşullarda gerçekleşebileceği') ?>"><?= e($val('description')) ?></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="rk-field">
                                <label class="rk-label" for="threat"><?= te('Tehdit') ?></label>
                                <textarea class="rk-textarea" id="threat" name="threat" rows="3"
                                          placeholder="<?= te('Tehdit kaynağı / aktörü') ?>"><?= e($val('threat')) ?></textarea>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="rk-field">
                                <label class="rk-label" for="vulnerability"><?= te('Zafiyet') ?></label>
                                <textarea class="rk-textarea" id="vulnerability" name="vulnerability" rows="3"
                                          placeholder="<?= te('İstismar edilebilecek zayıflık') ?>"><?= e($val('vulnerability')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="asset_name"><?= te('Etkilenen Varlık / Sistem') ?></label>
                        <input class="rk-input" type="text" id="asset_name" name="asset_name" maxlength="200"
                               value="<?= e($val('asset_name')) ?>"
                               placeholder="<?= te('Örn: SRV-DC01, Müşteri Portalı, Yedekleme altyapısı') ?>">
                    </div>

                </div>
            </div>

            <!-- ======================= Değerlendirme ======================= -->
            <div class="rk-card">
                <div class="rk-card-head">
                    <h2 class="rk-card-title"><i class="bi bi-calculator"></i> <?= te('Risk Değerlendirmesi') ?></h2>
                </div>
                <div class="rk-card-body">

                    <p class="rk-help mb-3">
                        <strong>Inherent risk</strong>, hiçbir kontrol uygulanmadığı varsayımıyla hesaplanan ham risktir.
                        Skor = Olasılık &times; Etki (5&times;5 matris).
                    </p>

                    <div class="rk-score-row" data-rk-score-scope>
                        <div class="rk-field">
                            <label class="rk-label" for="likelihood"><?= te('Olasılık') ?> <span class="req">*</span></label>
                            <select class="<?= e($cls('likelihood', 'rk-select')) ?>" id="likelihood"
                                    name="likelihood" data-rk-score="likelihood" required>
                                <option value=""><?= te('Seçiniz') ?></option>
                                <?= options_from_scale(likelihood_labels(), $intOrNull($val('likelihood'))) ?>
                            </select>
                            <?= $err('likelihood') ?>
                        </div>

                        <div class="rk-field">
                            <label class="rk-label" for="impact"><?= te('Etki') ?> <span class="req">*</span></label>
                            <select class="<?= e($cls('impact', 'rk-select')) ?>" id="impact"
                                    name="impact" data-rk-score="impact" required>
                                <option value=""><?= te('Seçiniz') ?></option>
                                <?= options_from_scale(impact_labels(), $intOrNull($val('impact'))) ?>
                            </select>
                            <?= $err('impact') ?>
                        </div>

                        <div class="rk-score-out">
                            <span class="rk-label"><?= te('Inherent Skor') ?></span>
                            <span class="rk-score sev-none" data-rk-score-out
                                  data-rk-thresholds='<?= e($thresholdsJson) ?>'>-</span>
                        </div>
                    </div>

                    <hr class="rk-sep">

                    <p class="rk-help mb-3">
                        <strong>Residual risk</strong>, kontroller ve aksiyonlar uygulandıktan sonra kalan risktir.
                        Henüz aksiyon planlanmadıysa boş bırakın &mdash; sonradan doldurabilirsiniz.
                    </p>

                    <div class="rk-field rk-u-maxw320">
                        <label class="rk-label" for="treatment_strategy"><?= te('Treatment Strategy') ?></label>
                        <select class="rk-select" id="treatment_strategy" name="treatment_strategy">
                            <option value=""><?= te('Belirlenmedi') ?></option>
                            <?= options_from_values(treatment_strategies(), $val('treatment_strategy') ?: null) ?>
                        </select>
                    </div>

                    <div class="rk-score-row" data-rk-score-scope>
                        <div class="rk-field">
                            <label class="rk-label" for="residual_likelihood"><?= te('Residual Olasılık') ?></label>
                            <select class="<?= e($cls('residual_likelihood', 'rk-select')) ?>"
                                    id="residual_likelihood" name="residual_likelihood" data-rk-score="likelihood">
                                <option value="">-</option>
                                <?= options_from_scale(likelihood_labels(), $intOrNull($val('residual_likelihood'))) ?>
                            </select>
                            <?= $err('residual_likelihood') ?>
                        </div>

                        <div class="rk-field">
                            <label class="rk-label" for="residual_impact"><?= te('Residual Etki') ?></label>
                            <select class="<?= e($cls('residual_impact', 'rk-select')) ?>"
                                    id="residual_impact" name="residual_impact" data-rk-score="impact">
                                <option value="">-</option>
                                <?= options_from_scale(impact_labels(), $intOrNull($val('residual_impact'))) ?>
                            </select>
                            <?= $err('residual_impact') ?>
                        </div>

                        <div class="rk-score-out">
                            <span class="rk-label"><?= te('Residual Skor') ?></span>
                            <span class="rk-score sev-none" data-rk-score-out
                                  data-rk-thresholds='<?= e($thresholdsJson) ?>'>-</span>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ======================== Sınıflandırma ======================== -->
        <div class="col-12 col-xl-4">
            <div class="rk-card">
                <div class="rk-card-head">
                    <h2 class="rk-card-title"><i class="bi bi-tags"></i> <?= te('Sınıflandırma') ?></h2>
                </div>
                <div class="rk-card-body">

                    <div class="rk-field">
                        <label class="rk-label" for="category_id"><?= te('Kategori') ?> <span class="req">*</span></label>
                        <select class="<?= e($cls('category_id', 'rk-select')) ?>" id="category_id" name="category_id" required>
                            <option value=""><?= te('Seçiniz') ?></option>
                            <?= options_html(categories_list(), $intOrNull($val('category_id'))) ?>
                        </select>
                        <?= $err('category_id') ?>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="department_id"><?= te('Departman') ?> <span class="req">*</span></label>
                        <select class="<?= e($cls('department_id', 'rk-select')) ?>" id="department_id" name="department_id" required>
                            <option value=""><?= te('Seçiniz') ?></option>
                            <?= options_html(departments_list(), $intOrNull($val('department_id'))) ?>
                        </select>
                        <?= $err('department_id') ?>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="owner_id"><?= te('Risk Sahibi') ?> <span class="req">*</span></label>
                        <select class="<?= e($cls('owner_id', 'rk-select')) ?>" id="owner_id" name="owner_id" required>
                            <option value=""><?= te('Seçiniz') ?></option>
                            <?= options_html(users_list(), $intOrNull($val('owner_id'))) ?>
                        </select>
                        <?= $err('owner_id') ?>
                        <div class="rk-help"><?= te('Riskin takibinden sorumlu kişi.') ?></div>
                    </div>

                </div>
            </div>

            <div class="rk-card">
                <div class="rk-card-head">
                    <h2 class="rk-card-title"><i class="bi bi-flag"></i> Durum &amp; Termin</h2>
                </div>
                <div class="rk-card-body">

                    <div class="rk-field">
                        <label class="rk-label" for="status"><?= te('Durum') ?> <span class="req">*</span></label>
                        <select class="<?= e($cls('status', 'rk-select')) ?>" id="status" name="status" required>
                            <?= options_from_values(risk_statuses(), $val('status') ?: 'Open') ?>
                        </select>
                        <?= $err('status') ?>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="target_date"><?= te('Hedef Tarih') ?></label>
                        <input class="<?= e($cls('target_date', 'rk-input')) ?>" type="date"
                               id="target_date" name="target_date" value="<?= e($val('target_date')) ?>">
                        <?= $err('target_date') ?>
                        <div class="rk-help"><?= te('Riskin kapatılması hedeflenen tarih.') ?></div>
                    </div>

                </div>
            </div>

            <div class="rk-form-actions">
                <button type="submit" class="rk-btn rk-btn-primary">
                    <i class="bi bi-check-lg"></i> <?= e($submitLabel) ?>
                </button>
                <a class="rk-btn" href="<?= e($cancelUrl) ?>">Vazgeç</a>
            </div>
        </div>

    </div>
</form>
