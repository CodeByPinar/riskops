<?php
declare(strict_types=1);

/**
 * RiskOps - Veritabanı kimlik bilgileri (ÖRNEK)
 * /var/www/riskops/config/database.example.php
 *
 * KURULUM
 * -------
 *   cp config/database.example.php config/database.php
 *   # sonra database.php içindeki 'pass' değerini kendi parolanızla değiştirin
 *
 * database.php .gitignore içindedir ve depoya girmez.
 * Bu örnek dosya, hangi anahtarların beklendiğini gösterir.
 *
 * DİKKAT: Bu dosya YALNIZCA bir dizi döndürür.
 * PDO bağlantısı includes/db.php içindeki db() fonksiyonunda kurulur.
 * Böylece bağlantı hatası merkezî olarak loglanabilir ve bağlantı
 * "lazy" (ilk kullanımda) açılır.
 */

return [
    'host'    => 'localhost',
    'name'    => 'riskops',
    'user'    => 'riskops_user',
    'pass'    => 'BURAYA_KENDI_PAROLANIZI_YAZIN',
    'charset' => 'utf8mb4',
];
