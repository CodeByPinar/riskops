<?php

declare(strict_types=1);

/**
 * RiskOps - Kategori silme (POST handler)
 * /var/www/riskops/admin/categories/delete.php
 *
 * Kullanımda olan kategori SİLİNMEZ (FK RESTRICT). Soft-delete edilmiş
 * riskler de sayılır: o riskler geri alınabilir ve kategorisiz kalmamalıdır.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

csrf_require();
require_login();
require_role(ROLE_ADMIN);

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing category id');
}

$cat = db_row('SELECT id, name FROM risk_categories WHERE id = :id LIMIT 1', [':id' => $id]);

if ($cat === null) {
    flash('error', 'Kategori bulunamadı.');
    redirect('/admin/categories/');
}

$count = (int)db_value('SELECT COUNT(*) FROM risks WHERE category_id = :id', [':id' => $id]);

if ($count > 0) {
    flash('error', sprintf(
        '%s silinemez: %d risk bu kategoriye bağlı. Bunun yerine pasifleştirin.',
        $cat['name'], $count
    ));
    redirect('/admin/categories/');
}

db_run('DELETE FROM risk_categories WHERE id = :id', [':id' => $id]);

audit('category_deleted', 'risk_category', $id, ['name' => $cat['name']], null);

flash('success', $cat['name'] . ' silindi.');
redirect('/admin/categories/');
