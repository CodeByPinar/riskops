<?php
declare(strict_types=1);

/**
 * RiskOps - Giriş noktası
 * /var/www/riskops/index.php
 */

require_once __DIR__ . '/includes/bootstrap.php';

redirect(auth_check() ? '/dashboard/' : '/auth/login.php');
