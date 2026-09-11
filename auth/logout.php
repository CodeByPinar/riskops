<?php
declare(strict_types=1);

/**
 * RiskOps - Çıkış
 * /var/www/riskops/auth/logout.php
 *
 * Çıkış bir DURUM DEGISTIREN islemdir; bu yuzden GET ile degil,
 * POST + CSRF ile yapılır (login CSRF saldirilarina karsi).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

csrf_require();

if (auth_check()) {
    audit('logout', 'user', auth_id(), null, ['email' => $_SESSION['user_email'] ?? null]);
}

auth_destroy();

// Flash mesaji gosterebilmek için yeni ve boş bir oturum başlat
session_start();
session_regenerate_id(true);
flash('info', 'Oturumunuz güvenli şekilde sonlandırıldı.');

redirect('/auth/login.php');
