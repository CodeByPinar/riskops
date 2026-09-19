<?php

declare(strict_types=1);

/**
 * RiskOps - Kategori kaydet (create + update, POST handler)
 * /var/www/riskops/admin/categories/save.php
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

csrf_require();
require_login();
require_role(ROLE_ADMIN);

$id      = input_int('id');
$isEdit  = $id !== null && $id > 0;
$backUrl = '/admin/categories/' . ($isEdit ? '?edit=' . $id : '');

$before = null;
if ($isEdit) {
    $before = db_row('SELECT * FROM risk_categories WHERE id = :id LIMIT 1', [':id' => $id]);
    if ($before === null) {
        flash('error', 'Kategori bulunamadı.');
        redirect('/admin/categories/');
    }
}

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

foreach ([['name', $name], ['code', $code]] as [$field, $value]) {
    if ($value === null || isset($errors[$field])) {
        continue;
    }
    $stmt = db_stmt(
        "SELECT id FROM risk_categories WHERE {$field} = :v AND (:self IS NULL OR id <> :self2) LIMIT 1",
        [':v' => $value, ':self' => $isEdit ? $id : null, ':self2' => $id ?? 0]
    );
    if ($stmt->fetchColumn() !== false) {
        $errors[$field] = 'Bu ' . ($field === 'name' ? 'ad' : 'kod') . ' zaten kullanılıyor.';
    }
}

/* Renk: JS kapalıyken metin alanı, açıkken ikisi de aynı değeri taşır.
   Metin alanı geçerliyse o kazanır. */
$color = input('color_text') ?? input('color') ?? '#64748b';
if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
    $errors['color'] = 'Renk #RRGGBB biçiminde olmalıdır.';
} else {
    $color = mb_strtolower($color);
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
    'color'       => $color,
    'sort_order'  => $sortOrder,
    'is_active'   => $isActive,
];

try {
    if ($isEdit) {
        db_run(
            'UPDATE risk_categories SET name=:n, code=:c, description=:d,
                    color=:col, sort_order=:s, is_active=:a
             WHERE id=:id',
            [
            ':n'   => $data['name'],
            ':c'   => $data['code'],
            ':d'   => $data['description'],
            ':col' => $data['color'],
            ':s'   => $data['sort_order'],
            ':a'   => $data['is_active'],
            ':id'  => $id,
        ]
        );

        [$oldValues, $newValues] = audit_diff($before, $data);
        if ($oldValues !== []) {
            audit('category_updated', 'risk_category', $id, $oldValues, $newValues);
        }
        flash('success', $data['name'] . ' güncellendi.');
    } else {
        db_run(
            'INSERT INTO risk_categories (name, code, description, color, sort_order, is_active)
             VALUES (:n, :c, :d, :col, :s, :a)',
            [
            ':n'   => $data['name'],
            ':c'   => $data['code'],
            ':d'   => $data['description'],
            ':col' => $data['color'],
            ':s'   => $data['sort_order'],
            ':a'   => $data['is_active'],
        ]
        );

        audit('category_created', 'risk_category', (int)db()->lastInsertId(), null, $data);
        flash('success', $data['name'] . ' eklendi.');
    }
} catch (Throwable $ex) {
    app_log('error', 'Category save failed: ' . $ex->getMessage(), ['id' => $id]);
    old_set($_POST);
    flash('error', 'Kategori kaydedilemedi.');
    redirect($backUrl);
}

redirect('/admin/categories/');
