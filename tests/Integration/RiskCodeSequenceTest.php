<?php

declare(strict_types=1);

namespace RiskOps\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Risk kodu üreteci.
 *
 * next_risk_code() `INSERT ... ON DUPLICATE KEY UPDATE
 * last_number = LAST_INSERT_ID(last_number + 1)` kullanır. Amaç, iki
 * kullanıcı aynı anda risk oluşturduğunda aynı kodu almamaları.
 * "SELECT MAX + 1" yaklaşımı bu yarışı kaybederdi.
 *
 * Bu testler kodu ÜRETİR ama risk KAYDETMEZ; ürettikleri sıra
 * numaralarını sonunda geri alır.
 */
final class RiskCodeSequenceTest extends TestCase
{
    private static PDO $pdo;
    private static int $before = 0;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = db();

        $row = self::$pdo->query(
            'SELECT last_number FROM risk_sequences WHERE seq_year = YEAR(CURDATE())'
        )->fetch();

        self::$before = $row === false ? 0 : (int)$row['last_number'];
    }

    public static function tearDownAfterClass(): void
    {
        /* Test verisi bırakma: sırayı başladığı yere döndür. */
        self::$pdo->prepare(
            'UPDATE risk_sequences SET last_number = :n WHERE seq_year = YEAR(CURDATE())'
        )->execute([':n' => self::$before]);
    }

    #[Test]
    public function kod_beklenen_bicimdedir(): void
    {
        self::assertMatchesRegularExpression(
            '/^RISK-\d{4}-\d{4}$/',
            next_risk_code(),
            'kod biçimi RISK-YYYY-NNNN olmalı'
        );
    }

    #[Test]
    public function kod_icinde_gecerli_yil_vardir(): void
    {
        self::assertStringStartsWith('RISK-' . date('Y') . '-', next_risk_code());
    }

    #[Test]
    public function ardisik_cagrilar_asla_ayni_kodu_vermez(): void
    {
        $codes = [];
        for ($i = 0; $i < 25; $i++) {
            $codes[] = next_risk_code();
        }

        self::assertSame(
            $codes,
            array_values(array_unique($codes)),
            'tekrarlanan risk kodu üretildi'
        );
    }

    #[Test]
    public function numaralar_birer_birer_artar(): void
    {
        $first  = (int)substr(next_risk_code(), -4);
        $second = (int)substr(next_risk_code(), -4);

        self::assertSame($first + 1, $second);
    }

    #[Test]
    public function ayri_baglantilardan_gelen_istekler_de_cakismaz(): void
    {
        /* Gerçek yarış koşulunu tek süreçte kurmak mümkün değil; ama
           AYRI BAĞLANTILAR kullanmak, sayacın oturuma değil tabloya
           bağlı olduğunu doğrular. Aynı kodu iki bağlantı alıyorsa
           üreteç zaten bozuktur. */
        $cfg = require CONFIG_PATH . '/database.php';
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['name'], $cfg['charset']);

        $second = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $a = next_risk_code();
        $b = next_risk_code($second);
        $c = next_risk_code();

        self::assertCount(3, array_unique([$a, $b, $c]), "çakışan kod: {$a} {$b} {$c}");
    }
}
