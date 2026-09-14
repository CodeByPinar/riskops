<?php

declare(strict_types=1);

/**
 * RiskOps - Entegrasyon testleri için ön yükleyici
 * /var/www/riskops/tests/bootstrap-app.php
 *
 * Uygulamanın GERÇEK bootstrap'ını yükler: veritabanı bağlantısı,
 * ayar tablosu, saat dilimi hizalaması - hepsi üretimdeki gibi.
 *
 * ÖN KOŞUL: config/database.php mevcut ve çalışan bir veritabanına
 * işaret ediyor olmalı. Yoksa testler baştan durur ve sebebini söyler
 * (sessizce atlanmaz - "testler geçti" yanılsaması, testin hiç
 * çalışmamasından daha kötüdür).
 *
 * UYARI: Bu testler gerçek veritabanına yazar. Geliştirme ya da CI
 * veritabanında çalıştırın; ürettikleri kayıtları kendileri temizler.
 */

if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}

$configFile = dirname(__DIR__) . '/config/database.php';

if (!is_file($configFile)) {
    fwrite(STDERR, <<<TXT

    Entegrasyon testleri için veritabanı yapılandırması gerekiyor.

        cp config/database.example.php config/database.php
        # düzenleyin, sonra:
        mysql -u root -p riskops < database/schema.sql
        mysql -u root -p riskops < database/seed.sql

    Yalnızca veritabanısız birim testleri için:
        composer test:unit


    TXT);
    exit(1);
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';

/* Bağlantı gerçekten çalışıyor mu? db() bağlanamazsa app_abort(500)
   çağırır ve CLI'da exit(1) yapar; test çıktısı bu durumda anlaşılmaz
   olur. Burada erken ve açık bir mesajla duruyoruz. */
try {
    db()->query('SELECT 1');
} catch (Throwable $e) {
    fwrite(STDERR, "\n  Veritabanına bağlanılamadı: " . $e->getMessage() . "\n\n");
    exit(1);
}
