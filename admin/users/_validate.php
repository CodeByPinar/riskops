<?php
declare(strict_types=1);

/**
 * RiskOps - Kullanıcı formu doğrulaması ve yardımcıları
 * /var/www/riskops/admin/users/_validate.php
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

/**
 * Geçici parola üretir.
 *
 * Karıştırılması kolay karakterler (0/O, 1/l/I) alfabeden çıkarılmıştır:
 * bu parola telefonda okunup elle yazılacaktır.
 */
function generate_temp_password(int $length = 14): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

/** Sistemde başka aktif admin var mı? (kendini kilitleme koruması) */
function other_active_admin_exists(int $excludeUserId): bool
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM users
         WHERE role = 'admin' AND status = 1 AND id <> :id"
    );
    $stmt->execute([':id' => $excludeUserId]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * POST verisini okur ve doğrular.
 *
 * @param int|null $selfId düzenlenen kullanıcının id'si (e-posta benzersizliği için)
 * @return array{0: array<string,mixed>, 1: array<string,string>}
 */
function user_collect_input(?int $selfId = null): array
{
    $errors = [];

    $name = input('name');
    if ($name === null) {
        $errors['name'] = 'Ad soyad zorunludur.';
    } elseif (mb_strlen($name) < 3) {
        $errors['name'] = 'Ad soyad en az 3 karakter olmalıdır.';
    } elseif (mb_strlen($name) > 100) {
        $errors['name'] = 'Ad soyad en fazla 100 karakter olabilir.';
    }

    $email = input('email');
    if ($email === null) {
        $errors['email'] = 'E-posta zorunludur.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Geçerli bir e-posta adresi giriniz.';
    } elseif (mb_strlen($email) > 150) {
        $errors['email'] = 'E-posta en fazla 150 karakter olabilir.';
    } else {
        $stmt = db()->prepare(
            'SELECT id FROM users WHERE email = :e AND (:self IS NULL OR id <> :self2) LIMIT 1'
        );
        $stmt->execute([':e' => $email, ':self' => $selfId, ':self2' => $selfId ?? 0]);
        if ($stmt->fetchColumn() !== false) {
            $errors['email'] = 'Bu e-posta adresi zaten kayıtlı.';
        }
    }

    $role = input_enum('role', all_roles());
    if ($role === null) {
        $errors['role'] = 'Geçerli bir rol seçiniz.';
    }

    $departmentId = input_int('department_id');
    if ($departmentId !== null && !lookup_has(departments_list(false), $departmentId)) {
        $errors['department_id'] = 'Geçerli bir departman seçiniz.';
    }

    $title = input('title');
    if ($title !== null && mb_strlen($title) > 100) {
        $errors['title'] = 'En fazla 100 karakter olabilir.';
    }

    $phone = input('phone');
    if ($phone !== null && mb_strlen($phone) > 30) {
        $errors['phone'] = 'En fazla 30 karakter olabilir.';
    }

    $status = input('status') === '1' ? 1 : 0;

    /* --- Kendini kilitleme koruması ---------------------------------
       Admin kendi rolünü düşüremez veya kendi hesabını kapatamaz;
       aksi halde sistemde admin kalmayabilir.                        */
    if ($selfId !== null && $selfId === auth_id()) {
        if ($role !== ROLE_ADMIN) {
            $errors['role'] = 'Kendi rolünüzü admin dışına çıkaramazsınız.';
        }
        if ($status !== 1) {
            $errors['status'] = 'Kendi hesabınızı devre dışı bırakamazsınız.';
        }
    }

    /* --- Son aktif admin koruması ----------------------------------- */
    if ($selfId !== null && !isset($errors['role']) && !isset($errors['status'])) {
        $stmt = db()->prepare('SELECT role, status FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $selfId]);
        $current = $stmt->fetch();

        $wasActiveAdmin = $current !== false
            && $current['role'] === ROLE_ADMIN && (int)$current['status'] === 1;
        $staysActiveAdmin = $role === ROLE_ADMIN && $status === 1;

        if ($wasActiveAdmin && !$staysActiveAdmin && !other_active_admin_exists($selfId)) {
            $errors['role'] = 'Bu, sistemdeki tek aktif admin hesabı. Önce başka bir admin tanımlayın.';
        }
    }

    return [[
        'name'          => $name,
        'email'         => $email !== null ? mb_strtolower($email) : null,
        'role'          => $role,
        'department_id' => $departmentId,
        'title'         => $title,
        'phone'         => $phone,
        'status'        => $status,
    ], $errors];
}

/** Doğrulama hatasında formu tekrar gösterir. */
function user_fail_back(array $errors, string $backUrl): never
{
    old_set($_POST);
    errors_set($errors);
    flash('error', 'Formda ' . count($errors) . ' hata var. Lütfen işaretli alanları düzeltin.');
    redirect($backUrl);
}
