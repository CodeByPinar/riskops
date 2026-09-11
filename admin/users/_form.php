<?php
declare(strict_types=1);

/**
 * RiskOps - Kullanıcı formu (create + edit ortak)
 * /var/www/riskops/admin/users/_form.php
 *
 * Beklenen: $form, $formAction, $submitLabel, $cancelUrl, $userId, $isSelf
 *
 * NOT: Parola bu formda ASLA girilmez. Yeni kullanıcıya sistem geçici
 * parola üretir; mevcut kullanıcı için ayrı "parola sıfırla" işlemi vardır.
 * Böylece yönetici başkasının parolasını bilerek belirleyemez.
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

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

$roleDescriptions = [
    ROLE_ADMIN   => 'Tam yetki: kullanıcı, ayar, kategori yönetimi ve risk silme.',
    ROLE_MANAGER => 'Risk ve aksiyon yönetimi, raporlar, kullanıcı görüntüleme.',
    ROLE_ANALYST => 'Risk oluşturma/değerlendirme ve aksiyon yönetimi.',
    ROLE_VIEWER  => 'Yalnızca görüntüleme.',
];
?>
<form method="post" action="<?= e($formAction) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if (!empty($userId)): ?>
        <input type="hidden" name="id" value="<?= (int)$userId ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="rk-card">
                <div class="rk-card-head">
                    <h2 class="rk-card-title"><i class="bi bi-person"></i> Kimlik</h2>
                </div>
                <div class="rk-card-body">

                    <div class="rk-field">
                        <label class="rk-label" for="name">Ad Soyad <span class="req">*</span></label>
                        <input class="<?= e($cls('name', 'rk-input')) ?>" type="text" id="name" name="name"
                               maxlength="100" required value="<?= e($val('name')) ?>"
                               placeholder="Örn: Elif Kaya">
                        <?= $err('name') ?>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="email">E-posta <span class="req">*</span></label>
                        <input class="<?= e($cls('email', 'rk-input')) ?>" type="email" id="email" name="email"
                               maxlength="150" required value="<?= e($val('email')) ?>"
                               autocomplete="off" placeholder="ornek@kurum.local">
                        <?= $err('email') ?>
                        <div class="rk-help">Giriş için kullanılır, benzersiz olmalıdır.</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="rk-field">
                                <label class="rk-label" for="title">Ünvan</label>
                                <input class="<?= e($cls('title', 'rk-input')) ?>" type="text" id="title"
                                       name="title" maxlength="100" value="<?= e($val('title')) ?>"
                                       placeholder="Örn: BT Risk Analisti">
                                <?= $err('title') ?>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="rk-field">
                                <label class="rk-label" for="phone">Telefon</label>
                                <input class="<?= e($cls('phone', 'rk-input')) ?>" type="text" id="phone"
                                       name="phone" maxlength="30" value="<?= e($val('phone')) ?>">
                                <?= $err('phone') ?>
                            </div>
                        </div>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label" for="department_id">Departman</label>
                        <select class="<?= e($cls('department_id', 'rk-select')) ?>"
                                id="department_id" name="department_id">
                            <option value="">Belirtilmedi</option>
                            <?= options_html(departments_list(false),
                                             $val('department_id') === '' ? null : (int)$val('department_id')) ?>
                        </select>
                        <?= $err('department_id') ?>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="rk-card">
                <div class="rk-card-head">
                    <h2 class="rk-card-title"><i class="bi bi-shield-lock"></i> Yetki</h2>
                </div>
                <div class="rk-card-body">

                    <div class="rk-field">
                        <label class="rk-label" for="role">Rol <span class="req">*</span></label>
                        <select class="<?= e($cls('role', 'rk-select')) ?>" id="role" name="role" required
                                <?= !empty($isSelf) ? 'disabled' : '' ?>>
                            <?php $selRole = $val('role') ?: ROLE_VIEWER;
                            foreach (all_roles() as $r): ?>
                                <option value="<?= e($r) ?>" <?= $selRole === $r ? 'selected' : '' ?>>
                                    <?= e(role_label($r)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($isSelf)): ?>
                            <input type="hidden" name="role" value="<?= e($val('role')) ?>">
                            <div class="rk-help">Kendi rolünüzü değiştiremezsiniz.</div>
                        <?php endif; ?>
                        <?= $err('role') ?>

                        <div class="rk-role-help">
                            <?php foreach ($roleDescriptions as $r => $desc): ?>
                                <div><strong><?= e(role_label($r)) ?>:</strong> <?= e($desc) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="rk-field">
                        <label class="rk-label">Hesap Durumu</label>
                        <label class="rk-check">
                            <input type="checkbox" name="status" value="1"
                                   <?= $val('status') === '0' ? '' : 'checked' ?>
                                   <?= !empty($isSelf) ? 'disabled' : '' ?>>
                            <span>Aktif (giriş yapabilir)</span>
                        </label>
                        <?php if (!empty($isSelf)): ?>
                            <input type="hidden" name="status" value="1">
                        <?php endif; ?>
                        <?= $err('status') ?>
                    </div>

                    <?php if (empty($userId)): ?>
                        <div class="rk-alert rk-alert-info" style="margin:0">
                            <i class="bi bi-key"></i>
                            <div>
                                Kayıttan sonra sistem <strong>geçici bir parola</strong> üretecek ve
                                bir kez gösterecek. Kullanıcı ilk girişte parolasını değiştirmek
                                zorunda kalacak.
                            </div>
                        </div>
                    <?php endif; ?>

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
