<?php
declare(strict_types=1);

/**
 * RiskOps - Profil güncelleme (POST handler)
 * /var/www/riskops/profile/update.php
 *
 * YALNIZCA ad, unvan ve telefon güncellenir.
 *
 * Bu dosyanın en kritik satırı UPDATE sorgusudur: SET listesinde
 * role, department_id, status ve email KASITLI OLARAK YOKTUR ve
 * WHERE id = auth_id() ile kullanıcı yalnızca kendi kaydına yazar.
 * İstemciden gelen bir "role" alanı olsa bile hiçbir etkisi olmaz -
 * yetki yükseltme yüzeyi baştan kapalıdır.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

csrf_require();
require_login();

$stmt = db()->prepare('SELECT id, name, email, title, phone FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => auth_id()]);
$before = $stmt->fetch();

if ($before === false) {
    app_abort(404, 'User not found');
}

/* Paylasilan demo hesabi: bir ziyaretcinin degisikligi sonraki
   herkesi etkiler. Formda alanlar zaten disabled; bu sunucu
   tarafindaki asil engel. */
if (APP_DEMO && strcasecmp((string)$before['email'], DEMO_EMAIL) === 0) {
    flash('info', 'Deneme hesabının bilgileri değiştirilemez.');
    redirect('/profile/');
}

$name  = input('name');
$title = input('title');
$phone = input('phone');

$errors = [];

if ($name === null || trim($name) === '') {
    $errors['name'] = 'Ad Soyad zorunludur.';
} elseif (mb_strlen(trim($name)) < 3) {
    $errors['name'] = 'Ad Soyad en az 3 karakter olmalıdır.';
} elseif (mb_strlen($name) > 100) {
    $errors['name'] = 'Ad Soyad en fazla 100 karakter olabilir.';
}

if ($title !== null && mb_strlen($title) > 100) {
    $errors['title'] = 'Unvan en fazla 100 karakter olabilir.';
}

if ($phone !== null && mb_strlen($phone) > 30) {
    $errors['phone'] = 'Telefon en fazla 30 karakter olabilir.';
}

if ($errors !== []) {
    errors_set($errors);
    old_set($_POST);
    flash('error', 'Profil güncellenemedi. İşaretli alanları düzeltin.');
    redirect('/profile/');
}

$data = [
    'name'  => trim((string)$name),
    'title' => ($title !== null && trim($title) !== '') ? trim($title) : null,
    'phone' => ($phone !== null && trim($phone) !== '') ? trim($phone) : null,
];

/* SET listesinde role / department_id / status / email YOK.
   WHERE id = kendi id'si. Ikisi birlikte, istemciden ne gelirse
   gelsin yetki yukseltmeyi imkansiz kilar. */
db()->prepare(
    'UPDATE users SET name = :n, title = :t, phone = :p WHERE id = :id'
)->execute([
    ':n'  => $data['name'],
    ':t'  => $data['title'],
    ':p'  => $data['phone'],
    ':id' => auth_id(),
]);

/* Kenar cubugundaki ad oturumdan okunuyor; tazelenmezse kullanici
   degisikligi gormez ve kaydin calismadigini sanir. */
$_SESSION['user_name'] = $data['name'];

[$old, $new] = audit_diff($before, $data, ['id', 'email']);

if ($old !== []) {
    audit('profile_updated', 'user', (int)auth_id(), $old, $new);
}

flash('success', 'Profiliniz güncellendi.');
redirect('/profile/');
