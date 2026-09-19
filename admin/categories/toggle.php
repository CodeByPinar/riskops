<?php

declare(strict_types=1);

/**
 * RiskOps - Kategori aktif/pasif (POST handler)
 * /var/www/riskops/admin/categories/toggle.php
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

csrf_require();
require_login();
require_role(ROLE_ADMIN);

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing category id');
}

$cat = db_row('SELECT id, name, is_active FROM risk_categories WHERE id = :id LIMIT 1', [':id' => $id]);

if ($cat === null) {
    flash('error', 'Kategori bulunamadı.');
    redirect('/admin/categories/');
}

$newState = (int)$cat['is_active'] === 1 ? 0 : 1;

db_run('UPDATE risk_categories SET is_active = :a WHERE id = :id', [':a' => $newState, ':id' => $id]);

audit('category_updated', 'risk_category', $id,
    ['is_active' => (int)$cat['is_active']],
    ['is_active' => $newState]
);

flash('success', $cat['name'] . ' ' . ($newState === 1 ? 'aktifleştirildi.' : 'pasifleştirildi.'));
redirect('/admin/categories/');
