<?php
declare(strict_types=1);

/**
 * RiskOps - konteyner açılışında veritabanı yoklaması
 * docker/db_ping.php
 *
 * entrypoint.sh kullanır. İki kip:
 *
 *   php docker/db_ping.php           ŞEMA HAZIRSA 0, değilse 1 döner
 *   php docker/db_ping.php --count   risks tablosundaki satır sayısını basar
 *
 * "Hazır" ölçütü bağlantı DEĞİL, risks tablosunun varlığıdır. MariaDB
 * bağlantı kabul etmeye, /docker-entrypoint-initdb.d betikleri bitmeden
 * başlıyor. Ölçüt yalnızca bağlantı olsaydı, bekleme döngüsü erken
 * çıkar, ardından gelen seed adımı "tablo yok" diye atlanır ve uygulama
 * sessizce boş açılırdı.
 *
 * bootstrap.php YÜKLENMEZ: bu betik veritabanı daha hazır değilken de
 * çalışacak. bootstrap ilk sorguda app_abort(500) ile çıkar ve bekleme
 * döngüsü anlamsız hâle gelirdi.
 *
 * Yalnızca komut satırından çalışır.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$cfg = require __DIR__ . '/../config/database.php';

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $cfg['host'],
    $cfg['name'],
    $cfg['charset']
);

try {
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, 'db_ping: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/* Sema hazir mi? Her iki kip de once bunu gecmek zorunda. */
try {
    $count = (int)$pdo->query('SELECT COUNT(*) FROM risks')->fetchColumn();
} catch (Throwable $e) {
    fwrite(STDERR, 'db_ping: sema henuz hazir degil - ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

if (in_array('--count', array_slice($argv, 1), true)) {
    echo $count;
}

exit(0);
