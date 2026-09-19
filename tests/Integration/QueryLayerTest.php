<?php

declare(strict_types=1);

namespace RiskOps\Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Sorgu katmanı.
 *
 * Bu testler VERİTABANI İSTER ve bilerek öyle: katmanın tamamı PDO'nun
 * gerçek davranışı üzerine kurulu. PDO'yu taklit eden bir sahte nesne
 * ile sınamak, sınanan şeyin kendisini varsaymak olurdu — "fetch()
 * bulamazsa false döner" iddiasını doğrulayan tek şey gerçek sürücü.
 *
 * Sınanan sözleşmeler:
 *   1. YOK = null        (false değil; ?-> ve ?? ile çalışsın diye)
 *   2. 0 ile YOK ayrı    (db_value sıfırı kaybetmiyor)
 *   3. Parametreler bağlanıyor (SQL'e gömülmüyor)
 *   4. Hatalı SQL istisna fırlatıyor, false döndürmüyor
 */
#[CoversFunction('db_all')]
#[CoversFunction('db_row')]
#[CoversFunction('db_value')]
#[CoversFunction('db_int')]
#[CoversFunction('db_column')]
#[CoversFunction('db_pairs')]
#[CoversFunction('db_exists')]
#[CoversFunction('db_run')]
#[CoversFunction('db_insert')]
#[CoversFunction('db_stmt')]
#[CoversFunction('db_prepare')]
final class QueryLayerTest extends TestCase
{
    /* --------------------------------------------------- okuma */

    #[Test]
    public function db_all_satir_listesi_dondurur(): void
    {
        $rows = db_all('SELECT 1 AS a UNION ALL SELECT 2');

        self::assertCount(2, $rows);
        self::assertSame(1, $rows[0]['a']);
        self::assertSame(2, $rows[1]['a']);
    }

    #[Test]
    public function db_all_sonuc_yoksa_bos_dizi_dondurur(): void
    {
        /* Boş dizi, null değil: çağıran foreach'e sokabilsin. */
        self::assertSame([], db_all('SELECT 1 AS a FROM risks WHERE 1 = 0'));
    }

    #[Test]
    public function db_row_tek_satir_dondurur(): void
    {
        self::assertSame(['a' => 7], db_row('SELECT 7 AS a'));
    }

    #[Test]
    public function db_row_satir_yoksa_null_dondurur(): void
    {
        /* KATMANIN VAR OLMA SEBEBİ: PDO burada false döndürüyor.
           false ile ?? çalışmaz, ?-> çalışmaz, ?array tipine girmez. */
        self::assertNull(db_row('SELECT 1 AS a FROM risks WHERE 1 = 0'));
    }

    /* ---------------------------------------------- tekil değer */

    #[Test]
    public function db_value_ilk_sutunu_dondurur(): void
    {
        self::assertSame('x', db_value("SELECT 'x' AS a, 'y' AS b"));
    }

    #[Test]
    public function db_value_sifiri_yok_sanmaz(): void
    {
        /* fetchColumn() hem "satır yok" hem "değer false" için false
           döndürüyor. COUNT(*) = 0 gerçek bir cevaptır; varsayılana
           düşerse sayım sonuçları sessizce yanlış olurdu. */
        self::assertSame(0, db_int('SELECT COUNT(*) FROM risks WHERE 1 = 0'));
        self::assertSame(0, db_int('SELECT 0'));
    }

    #[Test]
    public function db_value_satir_yoksa_varsayilani_dondurur(): void
    {
        self::assertNull(db_value('SELECT id FROM risks WHERE 1 = 0'));
        self::assertSame('yok', db_value('SELECT id FROM risks WHERE 1 = 0', [], 'yok'));
    }

    #[Test]
    public function db_int_sayisal_olmayani_varsayilana_dusurur(): void
    {
        self::assertSame(0, db_int("SELECT 'abc'"));
        self::assertSame(-1, db_int("SELECT 'abc'", [], -1));
        self::assertSame(42, db_int("SELECT '42'"));
    }

    /* ------------------------------------------------ biçimler */

    #[Test]
    public function db_column_tek_sutunu_liste_yapar(): void
    {
        self::assertSame([1, 2, 3], db_column('SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3'));
    }

    #[Test]
    public function db_pairs_anahtar_deger_esler(): void
    {
        $pairs = db_pairs("SELECT 'a' AS k, 1 AS v UNION ALL SELECT 'b', 2");

        self::assertSame(['a' => 1, 'b' => 2], $pairs);
    }

    #[Test]
    public function db_exists_varligi_soyler(): void
    {
        self::assertTrue(db_exists('SELECT 1'));
        self::assertFalse(db_exists('SELECT 1 FROM risks WHERE 1 = 0'));
    }

    /* -------------------------------------------- parametreler */

    /* PARAMETRE DEGERI METIN OLARAK GERI GELIR
       STRINGIFY_FETCHES=false bir SUTUNUN tipine bakar; 'SELECT :n AS a'
       ifadesinde tip tasiyan bir sutun yok, surucu metin dondurur.
       Uygulama kodu zaten (int) ile kendi cevirimini yapiyor - burada
       sinanan sey, degerin BAGLANDIGI. */

    #[Test]
    public function adlandirilmis_parametreler_baglanir(): void
    {
        self::assertSame(['a' => '5'], db_row('SELECT :n AS a', [':n' => 5]));
    }

    #[Test]
    public function sirali_parametreler_baglanir(): void
    {
        self::assertSame(['a' => '5'], db_row('SELECT ? AS a', [5]));
    }

    #[Test]
    public function parametre_sql_olarak_yorumlanmaz(): void
    {
        /* Enjeksiyon denemesi bir DEĞER olarak geri gelmeli, SQL
           olarak çalışmamalı. Bu katmanın kendisi bir koruma değil
           ama korumayı bozmadığı sınanmalı. */
        $kotu = "1; DROP TABLE risks; --";
        self::assertSame(['a' => $kotu], db_row('SELECT :v AS a', [':v' => $kotu]));

        self::assertTrue(db_exists("SELECT 1 FROM information_schema.tables
                                     WHERE table_schema = DATABASE() AND table_name = 'risks'"));
    }

    /* ------------------------------------------------- yazma */

    #[Test]
    public function db_run_etkilenen_satiri_dondurur(): void
    {
        $code = 'QLT-' . bin2hex(random_bytes(4));
        $id   = $this->gecici($code);

        self::assertSame(1, db_run('UPDATE risks SET title = :t WHERE id = :id',
            [':t' => 'degisti ' . $code, ':id' => $id]));

        /* DEĞER AYNIYSA 0: bu "başarısız" demek değil. Sözleşmenin
           belgelenen ama şaşırtıcı yanı. */
        self::assertSame(0, db_run('UPDATE risks SET title = :t WHERE id = :id',
            [':t' => 'degisti ' . $code, ':id' => $id]));

        db_run('DELETE FROM risks WHERE id = :id', [':id' => $id]);
    }

    #[Test]
    public function db_insert_yeni_idyi_dondurur(): void
    {
        $code = 'QLT-' . bin2hex(random_bytes(4));
        $id   = $this->gecici($code);

        self::assertGreaterThan(0, $id);
        self::assertSame($code, db_value('SELECT risk_code FROM risks WHERE id = :id', [':id' => $id]));

        db_run('DELETE FROM risks WHERE id = :id', [':id' => $id]);
    }

    /* ------------------------------------------- statement'lar */

    #[Test]
    public function db_stmt_calistirilmis_statement_dondurur(): void
    {
        $stmt = db_stmt('SELECT 1 AS a UNION ALL SELECT 2');

        self::assertSame(['a' => 1], $stmt->fetch());
        self::assertSame(['a' => 2], $stmt->fetch());
    }

    #[Test]
    public function db_prepare_calistirmaz(): void
    {
        $stmt = db_prepare('SELECT :n AS a');

        /* Henüz çalışmadı: execute çağrılmadan satır olmamalı. */
        self::assertFalse($stmt->fetch());

        $stmt->execute([':n' => 9]);
        self::assertSame(['a' => '9'], $stmt->fetch());
    }

    #[Test]
    public function db_prepare_disaridan_baglanti_alabilir(): void
    {
        $pdo = db();

        self::assertInstanceOf(PDO::class, $pdo);
        self::assertSame(['a' => 3], db_stmt('SELECT 3 AS a', [], $pdo)->fetch());
    }

    /* ------------------------------------------------- hatalar */

    #[Test]
    public function hatali_sql_istisna_firlatir(): void
    {
        /* Katmanın tip güvencesi buna dayanıyor: prepare() false
           döndürmüyor, atıyor. Bu doğru olmasaydı db_prepare()
           içindeki RuntimeException devreye girerdi. */
        $this->expectException(PDOException::class);

        db_all('SELECT * FROM boyle_bir_tablo_yok');
    }

    #[Test]
    public function eksik_parametre_istisna_firlatir(): void
    {
        /* EMULATE_PREPARES=false: sürücü eksik bağlamayı yutmuyor. */
        $this->expectException(PDOException::class);

        db_row('SELECT :a AS x', [':b' => 1]);
    }

    /* -------------------------------------------------- yardım */

    /** Geçici bir risk satırı oluşturur; id'sini döndürür. */
    private function gecici(string $code): int
    {
        return db_insert(
            'INSERT INTO risks (risk_code, title, category_id, department_id, owner_id,
                                likelihood, impact, status, created_by)
             VALUES (:c, :t, 1, 1, 1, 3, 3, :s, 1)',
            [':c' => $code, ':t' => 'Sorgu katmani testi', ':s' => 'Open']
        );
    }
}
