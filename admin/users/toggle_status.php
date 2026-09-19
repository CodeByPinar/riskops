<?php

declare(strict_types=1);

/**
 * RiskOps - Kullanıcı aktif/pasif (POST handler)
 * /var/www/riskops/admin/users/toggle_status.php
 *
 * Kullanıcı SİLİNMEZ: risks.owner_id, created_by ve audit kayıtları
 * kullanıcıya bağlıdır (FK RESTRICT). Ayrılan personel pasife alınır,
 * geçmiş kayıtlar bozulmaz.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_validate.php';

csrf_require();
require_login();
require_role(ROLE_ADMIN);

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing user id');
}

$user = db_row('SELECT id, name, role, status FROM users WHERE id = :id LIMIT 1', [':id' => $id]);

if ($user === null) {
    flash('error', 'Kullanıcı bulunamadı.');
    redirect('/admin/users/');
}

$newStatus = (int)$user['status'] === 1 ? 0 : 1;

if ($newStatus === 0) {
    if ($id === auth_id()) {
        flash('error', 'Kendi hesabınızı devre dışı bırakamazsınız.');
        redirect('/admin/users/');
    }
    /* Erken geri bildirim: kilitsizdir, yarisi kapatmaz. Otoriter
       kontrol asagida, UPDATE ile ayni transaction icindedir. */
    if ($user['role'] === ROLE_ADMIN && !other_active_admin_exists($id)) {
        flash('error', 'Bu, sistemdeki tek aktif admin hesabı. Önce başka bir admin tanımlayın.');
        redirect('/admin/users/');
    }
}

/* Koruma ve yazma TEK transaction icinde olmak zorunda: aralarinda
   commit edilmis baska bir istek araya girerse sistemde sifir aktif
   admin kalabilir. Pasiflestirme disindaki yonde (aktiflestirme)
   invariant zaten tehdit altinda degil ama ayni yolu kullanmak
   kod yolunu tekillestiriyor. */
$pdo = db();
$pdo->beginTransaction();

try {
    if ($newStatus === 0 && !last_admin_atomic_guard($pdo, $id)) {
        $pdo->rollBack();
        flash('error', 'Bu, sistemdeki tek aktif admin hesabı. Önce başka bir admin tanımlayın.');
        redirect('/admin/users/');
    }

    $pdo->prepare('UPDATE users SET status = :s WHERE id = :id')
        ->execute([':s' => $newStatus, ':id' => $id]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

audit('user_status_changed', 'user', $id,
    ['status' => (int)$user['status']],
    ['status' => $newStatus]
);

flash('success', $user['name'] . ' ' . ($newStatus === 1 ? 'aktifleştirildi.' : 'devre dışı bırakıldı.'));
redirect('/admin/users/');
