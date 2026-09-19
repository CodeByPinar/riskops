<?php

declare(strict_types=1);

namespace RiskOps\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Şema güvenceleri.
 *
 * Uygulamanın güvendiği veritabanı davranışlarının GERÇEKTEN
 * kurulduğunu doğrular. Bunlar yalnızca "tablo var mı" kontrolleri
 * değil: her biri, kodun bir yerde varsaydığı bir garantiye karşılık
 * geliyor.
 */
final class SchemaTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        /* Baglantiyi burada acmak, semanin yoklugunda testin
           "baglanamadim" diye acikca patlamasini sagliyor. Sorgular
           artik sorgu katmanindan geciyor, saklanan bir PDO alanina
           gerek kalmadi. */
        db();
    }

    #[Test]
    public function beklenen_tablolarin_tamami_var(): void
    {
        $expected = [
            'action_attachments', 'action_comments', 'audit_logs', 'departments',
            'login_attempts', 'risk_actions', 'risk_assessments', 'risk_attachments',
            'risk_categories', 'risk_comments', 'risk_sequences', 'risks',
            'settings', 'users',
        ];

        $actual = db_column('SHOW TABLES');
        sort($actual);

        self::assertSame($expected, $actual, 'şema beklenen tablo kümesiyle eşleşmiyor');
    }

    #[Test]
    public function inherent_skor_veritabaninda_uretilir(): void
    {
        /* risk_score() PHP'de de var ama TEK DOĞRULUK KAYNAĞI
           veritabanıdır: doğrudan SQL ile yazan bir betik bile
           tutarsız skor üretemesin. */
        $col = db_row(
            "SELECT EXTRA, GENERATION_EXPRESSION
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'risks'
                AND COLUMN_NAME = 'inherent_score'"
        );

        self::assertNotFalse($col, 'risks.inherent_score kolonu yok');
        self::assertStringContainsString('STORED GENERATED', strtoupper((string)$col['EXTRA']));
    }

    #[Test]
    public function risk_kodu_benzersizdir(): void
    {
        $idx = db_all(
            "SHOW INDEX FROM risks WHERE Column_name = 'risk_code'"
        );

        self::assertNotEmpty($idx, 'risk_code üzerinde indeks yok');
        self::assertSame(0, (int)$idx[0]['Non_unique'], 'risk_code benzersiz değil');
    }

    #[Test]
    public function silinen_kullanici_yorumlari_goturmez(): void
    {
        /* Yorumun yazarı silinse bile yorum kalmalı (SET NULL).
           CASCADE olsaydı bir kullanıcıyı silmek tartışma geçmişini
           sessizce yok ederdi. */
        $fk = db_column(
            "SELECT DELETE_RULE
               FROM information_schema.REFERENTIAL_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = 'risk_comments'"
        );

        self::assertNotEmpty($fk, 'risk_comments üzerinde yabancı anahtar yok');
        self::assertContains('SET NULL', $fk, 'yazar silindiğinde yorum da siliniyor');
    }

    #[Test]
    public function sql_mode_sessiz_veri_kaybina_izin_vermiyor(): void
    {
        /* STRICT olmadan taşan bir değer sessizce kırpılır; risk
           kaydında bu, veri kaybının fark edilmemesi demektir. */
        $mode = (string)db_value('SELECT @@SESSION.sql_mode');

        self::assertStringContainsString('STRICT', strtoupper($mode));
    }

    #[Test]
    public function baglanti_utf8mb4_kullaniyor(): void
    {
        /* utf8 (3 bayt) Türkçe için yeter ama emoji ve bazı
           karakterler için yetmez; kullanıcı metni sessizce bozulur. */
        $charset = (string)db_value('SELECT @@SESSION.character_set_connection');

        self::assertSame('utf8mb4', $charset);
    }

    #[Test]
    public function php_ve_mysql_ayni_ani_gosteriyor(): void
    {
        /* Kayma, audit zaman damgalarını ve hesap kilidi penceresini
           bozar - ölçülmüş bir hata: 3 saatlik fark yüzünden kaba
           kuvvet kilidi hiç devreye girmiyordu. */
        $mysqlNow = (string)db_value('SELECT NOW()');
        $drift    = abs(strtotime($mysqlNow) - time());

        self::assertLessThanOrEqual(2, $drift, "PHP ile MySQL saatleri {$drift} sn kaymış");
    }
}
