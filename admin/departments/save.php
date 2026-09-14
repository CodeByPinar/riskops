<?php

declare(strict_types=1);

/**
 * RiskOps - Departman kaydet (create + update, POST handler)
 * /var/www/riskops/admin/departments/save.php
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

csrf_require();
require_login();
require_role(ROLE_ADMIN);

$id      = input_int('id');
$isEdit  = $id !== null && $id > 0;
$backUrl = '/admin/departments/' . ($isEdit ? '?edit=' . $id : '');

$before = null;
if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM departments WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $before = $stmt->fetch();
    if ($before === false) {
        flash('error', 'Departman bulunamadı.');
        redirect('/admin/departments/');
    }
}

/* ----------------------------------------------------- Doğrulama ---- */

$errors = [];

$name = input('name');
if ($name === null) {
    $errors['name'] = 'Ad zorunludur.';
} elseif (mb_strlen($name) < 2) {
    $errors['name'] = 'Ad en az 2 karakter olmalıdır.';
} elseif (mb_strlen($name) > 100) {
    $errors['name'] = 'Ad en fazla 100 karakter olabilir.';
}

$code = input('code');
if ($code === null) {
    $errors['code'] = 'Kod zorunludur.';
} else {
    $code = mb_strtoupper($code, 'UTF-8');
    if (preg_match('/^[A-Z0-9_-]{2,20}$/u', $code) !== 1) {
        $errors['code'] = 'Kod 2-20 karakter olmalı; yalnızca harf, rakam, - ve _ içerebilir.';
    }
}

// Ad ve kod benzersizliği (kendisi hariç)
foreach ([['name', $name], ['code', $code]] as [$field, $value]) {
    if ($value === null || isset($errors[$field])) {
        continue;
    }
    $stmt = db()->prepare(
        "SELECT id FROM departments WHERE {$field} = :v AND (:self IS NULL OR id <> :self2) LIMIT 1"
    );
    $stmt->execute([':v' => $value, ':self' => $isEdit ? $id : null, ':self2' => $id ?? 0]);
    if ($stmt->fetchColumn() !== false) {
        $errors[$field] = 'Bu ' . ($field === 'name' ? 'ad' : 'kod') . ' zaten kullanılıyor.';
    }
}

$managerId = input_int('manager_id');
if ($managerId !== null && !lookup_has(users_list(false), $managerId)) {
    $errors['manager_id'] = 'Geçerli bir kullanıcı seçiniz.';
}

$sortOrder = input_int_range('sort_order', 0, 9999, 0) ?? 0;
$isActive  = input('is_active') === '1' ? 1 : 0;

if ($errors !== []) {
    old_set($_POST);
    errors_set($errors);
    flash('error', 'Formda ' . count($errors) . ' hata var.');
    redirect($backUrl);
}

$data = [
    'name'        => $name,
    'code'        => $code,
    'description' => input('description'),
    'manager_id'  => $managerId,
    'sort_order'  => $sortOrder,
    'is_active'   => $isActive,
];

/* --------------------------------------------------------- Yazma ---- */

try {
    if ($isEdit) {
        db()->prepare(
            'UPDATE departments SET name=:n, code=:c, description=:d,
                    manager_id=:m, sort_order=:s, is_active=:a
             WHERE id=:id'
        )->execute([
            ':n' => $data['name'], ':c' => $data['code'], ':d' => $data['description'],
            ':m' => $data['manager_id'], ':s' => $data['sort_order'],
            ':a' => $data['is_active'], ':id' => $id,
        ]);

        [$oldValues, $newValues] = audit_diff($before, $data);
        if ($oldValues !== []) {
            audit('department_updated', 'department', $id, $oldValues, $newValues);
        }
        flash('success', $data['name'] . ' güncellendi.');
    } else {
        db()->prepare(
            'INSERT INTO departments (name, code, description, manager_id, sort_order, is_active)
             VALUES (:n, :c, :d, :m, :s, :a)'
        )->execute([
            ':n' => $data['name'], ':c' => $data['code'], ':d' => $data['description'],
            ':m' => $data['manager_id'], ':s' => $data['sort_order'], ':a' => $data['is_active'],
        ]);

        audit('department_created', 'department', (int)db()->lastInsertId(), null, $data);
        flash('success', $data['name'] . ' eklendi.');
    }
} catch (Throwable $ex) {
    app_log('error', 'Department save failed: ' . $ex->getMessage(), ['id' => $id]);
    old_set($_POST);
    flash('error', 'Departman kaydedilemedi.');
    redirect($backUrl);
}

redirect('/admin/departments/');
