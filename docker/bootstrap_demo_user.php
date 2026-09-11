<?php
declare(strict_types=1);

/**
 * RiskOps - konteyner ilk açılışında demo hesabını kurar
 * docker/bootstrap_demo_user.php
 *
 * entrypoint.sh tarafından, YALNIZCA RISKOPS_DEMO=1 iken ve yalnızca
 * veritabanı boşken çağrılır.
 *
 * Ayrı bir dosya olmasının sebebi: aynı iş entrypoint içinde `php -r`
 * ile yapılsaydı, sh -> su -> php üç katmanlı tırnak kaçışı gerekirdi.
 * O kaçışların bir yerde bozulması sessiz bir hataya yol açar.
 *
 * Yalnızca komut satırından çalışır.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

if (!APP_DEMO) {
    fwrite(STDERR, "bootstrap_demo_user: RISKOPS_DEMO=1 degil, atlandi.\n");
    exit(0);
}

$hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);

db()->prepare(
    'INSERT INTO users (name, email, password, role, status, must_change_password,
                        password_changed_at, created_at)
     VALUES (:n, :e, :p, :r, 1, 0, NOW(), NOW())
     ON DUPLICATE KEY UPDATE
        name = :n2, password = :p2, role = :r2, status = 1,
        must_change_password = 0, password_changed_at = NOW()'
)->execute([
    ':n'  => 'Demo Kullanıcı', ':n2' => 'Demo Kullanıcı',
    ':e'  => DEMO_EMAIL,
    ':p'  => $hash,            ':p2' => $hash,
    ':r'  => ROLE_VIEWER,      ':r2' => ROLE_VIEWER,
]);

/* Yazma yetkisi olan seed hesaplarini kapat.
   admin@riskops.local parolasi depoda herkese aciktir; bir demo
   konteyneri internete acilirsa o hesapla girilebilirdi. */
db()->prepare(
    "UPDATE users SET status = 0
      WHERE email IN ('admin@riskops.local','manager@riskops.local',
                      'analyst@riskops.local','analyst2@riskops.local')"
)->execute();

echo 'Demo hesabi hazir: ' . DEMO_EMAIL . ' / ' . DEMO_PASSWORD . ' (viewer)' . PHP_EOL;
echo 'Yazma yetkili seed hesaplari kapatildi.' . PHP_EOL;
exit(0);
