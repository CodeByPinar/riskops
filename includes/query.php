<?php

declare(strict_types=1);

/**
 * RiskOps - Sorgu katmanı
 * /var/www/riskops/includes/query.php
 *
 * NE DEĞİL
 * --------
 * Bu bir ORM değil. Tablo eşleme, varlık nesnesi, tembel yükleme,
 * sorgu kurucu (query builder) yok. SQL'i yine siz yazıyorsunuz ve
 * çağrı yerinde okunuyor - hangi indeksin kullanılacağı, hangi JOIN'in
 * kurulacağı sizin kararınız kalıyor (ADR-0009).
 *
 * NE
 * --
 * prepare -> execute -> fetch üçlüsünün TEK yeri. Uygulamada 151 çağrı
 * yerinde aynı üç satır tekrarlanıyordu:
 *
 *     $stmt = db()->prepare('SELECT ...');   // 1
 *     $stmt->execute([':id' => $id]);        // 2
 *     $row = $stmt->fetch() ?: null;         // 3
 *
 * Artık:
 *
 *     $row = db_row('SELECT ...', [':id' => $id]);
 *
 * NEDEN - ÜÇ SEBEP, ÜÇÜ DE SOMUT
 * ------------------------------
 * 1. TİP. PDO::prepare() imzasında PDOStatement|false döner; bu, hata
 *    kipi ERRMODE_SILENT iken doğrudur. Bizde ERRMODE_EXCEPTION açık,
 *    yani false hiç dönmüyor - ama statik çözümleyici bunu göremez ve
 *    her çağrı yerinde "false üzerinde fetch çağıramazsın" der. Bu
 *    dosya o bilgiyi BİR KEZ söylüyor; çağıranlar kesin tip alıyor.
 *
 * 2. DEĞİŞTİRME MALİYETİ. Sorgu günlüğü, yavaş sorgu eşiği, okuma
 *    replikası, yeniden deneme - her biri 151 yerde değil burada bir
 *    kez yapılır. Hata ayıklama kipi zaten böyle bir ihtiyaçtı ve
 *    PDO alt sınıfı yazılarak çözülmüştü (includes/db_debug.php);
 *    o çözüm bu katman varken gerekmezdi.
 *
 * 3. TEKRAR. Üç satırın ikisi her seferinde aynıydı. Aynı olan şeyin
 *    151 kopyası, 151 kez yanlış yazılma fırsatıdır.
 *
 * KURAL: PARAMETRE VERMEDEN DEĞİŞKEN GÖMMEYİN
 * -------------------------------------------
 * Bu katman SQL birleştirmeyi kolaylaştırmaz, zorlaştırmaz da -
 * sorumluluk çağıranda. Kullanıcıdan gelen hiçbir şey SQL metnine
 * girmemeli; her değer $params ile geçmeli. tools/check_conventions.php
 * bunu ayrıca denetliyor.
 *
 * Sütun ve tablo ADLARI parametre olamaz (PDO buna izin vermez).
 * Sıralama sütunu gibi yerlerde beyaz liste kullanın - uygulamadaki
 * her sıralama zaten sabit bir haritadan geçiyor.
 */

/**
 * Hazırlar, çalıştırır ve statement'ı döndürür.
 *
 * Bu dosyadaki TEK PDOStatement üreten yer. Diğer yardımcılar hep
 * bunun üzerinden geçiyor, dolayısıyla bir davranış eklemek isteyen
 * tek bir gövde değiştiriyor.
 *
 * Neden ayrıca dışa açık: imleçle satır satır dolaşmak (fetch döngüsü)
 * ya da FETCH_KEY_PAIR gibi özel bir kip gerektiğinde statement'ın
 * kendisi lazım. Böyle bir durumda db()->prepare() yerine bunu
 * kullanın; kazanılan tip güvencesi kaybolmaz.
 *
 * $pdo yalnızca uygulamanın kendi bağlantısı DIŞINDA bir bağlantı
 * gerektiğinde verilir: kilitlenme testleri ayrı bir oturum açıyor,
 * risk_recalculate_severities() dışarıdan bağlantı alabiliyor.
 *
 * @param  array<array-key, mixed> $params
 */
function db_stmt(string $sql, array $params = [], ?PDO $pdo = null): PDOStatement
{
    $stmt = db_prepare($sql, $pdo);
    $stmt->execute($params);

    return $stmt;
}

/**
 * Yalnızca hazırlar; çalıştırmaz.
 *
 * Aynı statement'ı bir döngüde çok kez çalıştırmak için. PDO bir kez
 * hazırlar, sunucu sorgu planını yeniden kullanır - toplu güncellemede
 * her satır için yeniden hazırlamaktan hızlıdır.
 */
function db_prepare(string $sql, ?PDO $pdo = null): PDOStatement
{
    $stmt = ($pdo ?? db())->prepare($sql);

    /* PDO::prepare() false dönebilir DEDİĞİ hâlde dönmüyor: bağlantı
       ERRMODE_EXCEPTION ile açılıyor, hatalı SQL istisna fırlatıyor.
       Buradaki kontrol o sözü YERİNDE tutar; sessizce güvenmek yerine
       varsayım bozulursa anlaşılır bir hata veriyoruz.

       Uygulamanın TAMAMINDA prepare() burada, tek bir yerde çağrılıyor;
       bu kontrol de bu yüzden tek bir yerde yazılıyor. */
    if ($stmt === false) {
        throw new RuntimeException('PDO::prepare() basarisiz: ' . $sql);
    }

    return $stmt;
}

/**
 * Tüm satırlar.
 *
 * @param  array<array-key, mixed> $params
 * @return list<array<string, mixed>>
 */
function db_all(string $sql, array $params = []): array
{
    /** @var list<array<string, mixed>> $rows */
    $rows = db_stmt($sql, $params)->fetchAll();

    return $rows;
}

/**
 * Tek satır; yoksa null.
 *
 * fetch() bulamazsa false döner. Bunu null'a çeviriyoruz: "yok" ile
 * "hata" aynı değerle anlatılmamalı, ve ?-> ile ?? yalnızca null ile
 * çalışır.
 *
 * @param  array<array-key, mixed> $params
 * @return array<string, mixed>|null
 */
function db_row(string $sql, array $params = []): ?array
{
    $row = db_stmt($sql, $params)->fetch();

    return is_array($row) ? $row : null;
}

/**
 * İlk satırın ilk sütunu; satır yoksa $default.
 *
 * @param  array<array-key, mixed> $params
 */
function db_value(string $sql, array $params = [], mixed $default = null): mixed
{
    $value = db_stmt($sql, $params)->fetchColumn();

    /* fetchColumn() satır yoksa false döner - ama sütunun KENDİSİ de
       false olabilir (TINYINT(1), boş sonuç kümesi sayımı değil). PDO
       ikisini ayırt etmiyor; bu yüzden $default yalnızca gerçek false
       için devreye giriyor ve "0" ile "yok" karışmıyor. */
    return $value === false ? $default : $value;
}

/**
 * Tek sütunluk sayım/toplam sorguları için.
 *
 * @param  array<array-key, mixed> $params
 */
function db_int(string $sql, array $params = [], int $default = 0): int
{
    $value = db_value($sql, $params);

    return is_numeric($value) ? (int)$value : $default;
}

/**
 * İlk sütunun tüm değerleri (id listesi, kod listesi).
 *
 * @param  array<array-key, mixed> $params
 * @return list<mixed>
 */
function db_column(string $sql, array $params = []): array
{
    /** @var list<mixed> $values */
    $values = db_stmt($sql, $params)->fetchAll(PDO::FETCH_COLUMN);

    return $values;
}

/**
 * İlk sütun anahtar, ikinci sütun değer.
 *
 * Grafik ve özet ekranlarında "durum -> adet" gibi eşlemeler için.
 *
 * @param  array<array-key, mixed> $params
 * @return array<array-key, mixed>
 */
function db_pairs(string $sql, array $params = []): array
{
    /** @var array<array-key, mixed> $pairs */
    $pairs = db_stmt($sql, $params)->fetchAll(PDO::FETCH_KEY_PAIR);

    return $pairs;
}

/**
 * Sorgu en az bir satır döndürüyor mu?
 *
 * SELECT 1 ... LIMIT 1 ile kullanın; COUNT(*) gereksiz iş yapar.
 *
 * @param  array<array-key, mixed> $params
 */
function db_exists(string $sql, array $params = []): bool
{
    return db_stmt($sql, $params)->fetchColumn() !== false;
}

/**
 * INSERT / UPDATE / DELETE - etkilenen satır sayısını döndürür.
 *
 * DİKKAT: 0 dönmesi "başarısız" demek DEĞİLDİR. Bir UPDATE, satırı
 * bulduğu hâlde değerler zaten aynıysa 0 döndürür. "Kayıt var mıydı?"
 * sorusunun cevabı bu değer değildir; ayrıca sorun.
 *
 * @param  array<array-key, mixed> $params
 */
function db_run(string $sql, array $params = []): int
{
    return db_stmt($sql, $params)->rowCount();
}

/**
 * INSERT çalıştırır ve yeni satırın id'sini döndürür.
 *
 * @param  array<array-key, mixed> $params
 */
function db_insert(string $sql, array $params = []): int
{
    db_stmt($sql, $params);

    return (int)db()->lastInsertId();
}

/**
 * Tek satır; satır YOKSA istisna.
 *
 * NE ZAMAN: sorgunun sıfır satır döndürmesi MÜMKÜN DEĞİLSE, ya da
 * mümkünse bile bu bir hata sayılıyorsa.
 *
 *   GROUP BY'sız bir toplama sorgusu (COUNT, SUM, COALESCE) tablo boş
 *   olsa bile TEK BİR SATIR döndürür - sayılar sıfır olur. Yani
 *   `$stats['toplam']` yazan ekran haklıdır; db_row() bunu bilemediği
 *   için ?array döndürüyor ve çağıran her alan erişiminde null
 *   ihtimaliyle uğraşmak zorunda kalıyordu.
 *
 * NE ZAMAN DEĞİL: "kayıt var mı?" sorusunda. Orada yokluk normaldir
 * ve cevabı 404'tür, istisna değil - db_row() kullanın.
 *
 * @param  array<array-key, mixed> $params
 * @return array<string, mixed>
 */
function db_row_required(string $sql, array $params = []): array
{
    $row = db_row($sql, $params);

    if ($row === null) {
        throw new RuntimeException('Satir donmesi beklenen sorgu bos dondu: ' . $sql);
    }

    return $row;
}
