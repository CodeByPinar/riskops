<?php
declare(strict_types=1);

/**
 * RiskOps - Kullanıcı düzenleme formu
 * /var/www/riskops/admin/users/edit.php
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_validate.php';

require_login();
require_role(ROLE_ADMIN);

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing user id');
}

$stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if ($user === false) {
    app_abort(404, 'User not found: ' . $id);
}

$form = [
    'name'          => (string)$user['name'],
    'email'         => (string)$user['email'],
    'role'          => (string)$user['role'],
    'department_id' => (string)($user['department_id'] ?? ''),
    'title'         => (string)($user['title'] ?? ''),
    'phone'         => (string)($user['phone'] ?? ''),
    'status'        => (string)(int)$user['status'],
];

$formAction  = url('/admin/users/update.php');
$submitLabel = 'Değişiklikleri Kaydet';
$cancelUrl   = url('/admin/users/');
$userId      = $id;
$isSelf      = ($id === auth_id());

$pageTitle    = $user['name'];
$pageSubtitle = $user['email'] . ' — ' . role_label($user['role']);
$activeMenu   = 'admin.users';

require LAYOUT_PATH . '/header.php';
require __DIR__ . '/_form.php';
?>

<div class="rk-card">
    <div class="rk-card-head">
        <h2 class="rk-card-title"><i class="bi bi-key"></i> Parola</h2>
    </div>
    <div class="rk-card-body">
        <div class="rk-danger-zone" style="margin:0;background:var(--rk-surface-alt);border-color:var(--rk-border)">
            <div>
                <strong>Parolayı sıfırla</strong>
                <div class="rk-help">
                    Sistem yeni bir geçici parola üretir ve bir kez gösterir.
                    Kullanıcı ilk girişte değiştirmek zorunda kalır.
                    <?php if ($user['password_changed_at'] !== null): ?>
                        Son değişiklik: <?= e(format_datetime($user['password_changed_at'])) ?>.
                    <?php endif; ?>
                </div>
            </div>
            <form method="post" action="<?= e(url('/admin/users/reset_password.php')) ?>"
                  data-rk-confirm="<?= e($user['name'] . ' için yeni geçici parola üretilecek. Onaylıyor musunuz?') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="rk-btn"><i class="bi bi-arrow-repeat"></i> Sıfırla</button>
            </form>
        </div>
    </div>
</div>

<?php require LAYOUT_PATH . '/footer.php'; ?>
