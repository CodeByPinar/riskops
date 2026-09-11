<?php
declare(strict_types=1);

/**
 * RiskOps - Veritabanı kimlik bilgileri (konteyner)
 *
 * Bu dosya imaj kurulurken config/database.php olarak kopyalanır.
 * Değerler ortam değişkeninden gelir; imajın içinde parola gömülü
 * DEĞİLDİR. docker-compose.yml bunları tek yerden verir.
 *
 * Varsayılanlar yalnızca docker-compose ile birlikte anlamlıdır:
 * "db" servis adıdır, Docker'ın iç DNS'i onu çözer.
 */

$env = static function (string $key, string $default): string {
    $v = $_SERVER[$key] ?? getenv($key);
    return (is_string($v) && $v !== '') ? $v : $default;
};

return [
    'host'    => $env('RISKOPS_DB_HOST', 'db'),
    'name'    => $env('RISKOPS_DB_NAME', 'riskops'),
    'user'    => $env('RISKOPS_DB_USER', 'riskops_user'),
    'pass'    => $env('RISKOPS_DB_PASS', ''),
    'charset' => 'utf8mb4',
];
