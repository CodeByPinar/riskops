<?php
declare(strict_types=1);

/**
 * RiskOps - Aksiyon formu (create + edit ortak)
 * /var/www/riskops/actions/_form.php
 *
 * Beklenen değişkenler:
 *   $form        array   alan değerleri
 *   $formAction  string  POST hedefi
 *   $submitLabel string
 *   $cancelUrl   string
 *   $actionId    ?int
 *   $riskRow     ?array  bağlı risk (create'te ?risk_id ile gelmişse sabitlenir)
 *   $risks       array   risk seçimi için liste (yalnızca risk sabit değilse)
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

/* Bu parça bir GİRİŞ NOKTASI DEĞİL: çağıran sayfanın kapsamından
   besleniyor. Bildirimler o sözleşmeyi denetlenebilir kılar. */
/** @var array<string, mixed> $form */
/** @var string $formAction */
/** @var string $submitLabel */
/** @var string $cancelUrl */
/** @var array<int, array<string, mixed>> $risks */

$val = static function (string $key) use ($form): string {
    return has_old($key) ? old($key) : (string)($form[$key] ?? '');
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
?>
<form method="post" action="<?= e($formAction) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if (!empty($actionId)): ?>
        <input type="hidden" name="id" value="<?= (int)$actionId ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="rk-card">
                <div class="rk-card-head">
                    <h2 class="rk-card-title"><i class="bi bi-check2-square"></i> Aksiyon</h2>
                </div>
                <div class="rk-card-body">

                    <div class="rk-field">
                        <label class="rk-label" for="risk_id">Bağlı Risk <span class="req">*</span></label>
                        <?php if (!empty($riskRow)): ?>
                            <input type="hidden" name="risk_id" value="<?= (int)$riskRow['id'] ?>">
                            <div class="rk-locked-field">
                                <span class="rk-code"><?= e($riskRow['risk_code']) ?></span>
                                <span><?= e(str_limit($riskRow['title'], 70)) ?></span>
                                <a class="rk-btn rk-btn-sm"
                                   href="<?= e(url('/risks/view.php?id=' . (int)$riskRow['id'])) ?>">
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
                        <label class="rk-label" for="title">Aksiyon Başlığı <span class="req">*</span></label>
                        <input class="<?= e($cls('title', 'rk-input')) ?>" type="text" id="title" name="title"
                               maxlength="200" required value="<?= e($val('title')) ?>"
                               placeholder="Örn: RDP erişimini VPN arkasına alma">
                        <?= $err('title') ?>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="description">Açıklama</label>
                        <textarea class="rk-textarea" id="description" name="description" rows="5"
                                  placeholder="Yapılacak işin kapsamı, bağımlılıklar, kabul kriteri"><?= e($val('description')) ?></textarea>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="rk-card">
                <div class="rk-card-head">
                    <h2 class="rk-card-title"><i class="bi bi-sliders"></i> Atama &amp; Takip</h2>
                </div>
                <div class="rk-card-body">

                    <div class="rk-field">
                        <label class="rk-label" for="owner_id">Sorumlu <span class="req">*</span></label>
                        <select class="<?= e($cls('owner_id', 'rk-select')) ?>" id="owner_id" name="owner_id" required>
                            <option value="">Seçiniz</option>
                            <?= options_html(users_list(), $intOrNull($val('owner_id'))) ?>
                        </select>
                        <?= $err('owner_id') ?>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="priority">Öncelik <span class="req">*</span></label>
                        <select class="<?= e($cls('priority', 'rk-select')) ?>" id="priority" name="priority" required>
                            <?= options_from_values(action_priorities(), $val('priority') ?: 'Medium') ?>
                        </select>
                        <?= $err('priority') ?>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="status">Durum <span class="req">*</span></label>
                        <select class="<?= e($cls('status', 'rk-select')) ?>" id="status" name="status" required>
                            <?= options_from_values(action_statuses(), $val('status') ?: 'Open') ?>
                        </select>
                        <?= $err('status') ?>
                        <div class="rk-help">
                            &laquo;Completed&raquo; seçildiğinde tamamlanma zamanı otomatik damgalanır.
                        </div>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="due_date">Termin</label>
                        <input class="<?= e($cls('due_date', 'rk-input')) ?>" type="date"
                               id="due_date" name="due_date" value="<?= e($val('due_date')) ?>">
                        <?= $err('due_date') ?>
                        <div class="rk-help">Geçmiş tarihli açık aksiyonlar &laquo;geciken&raquo; sayılır.</div>
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
