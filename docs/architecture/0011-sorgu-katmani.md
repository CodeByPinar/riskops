# ADR-0011: İnce bir sorgu katmanı — ORM değil

- **Durum:** Kabul edildi
- **Tarih:** 2026-09-19
- **Etkilediği yerler:** `includes/query.php` (yeni), `includes/bootstrap.php`, `tests/bootstrap-unit.php`, uygulamadaki 145 çağrı yeri

## Bağlam

Uygulama boyunca aynı üç satır 151 kez tekrarlanıyordu:

```php
$stmt = db()->prepare('SELECT ...');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch() ?: null;
```

Üç somut sorun:

**1. Tip.** `PDO::prepare()` imzasında `PDOStatement|false` döner. Bu,
hata kipi `ERRMODE_SILENT` iken doğrudur; RiskOps bağlantıyı
`ERRMODE_EXCEPTION` ile açıyor, yani `false` hiç dönmüyor. Statik
çözümleyici bunu göremez — bir çalışma zamanı özniteliği. Sonuç:
PHPStan seviye 7'de 72 adet "Cannot call method fetchAll() on
PDOStatement|false" bulgusu.

**2. Değiştirme maliyeti.** Sorgu günlüğü, yavaş sorgu eşiği, okuma
replikası, yeniden deneme — her biri 151 yerde değişiklik demekti.
Hata ayıklama kipi tam da böyle bir ihtiyaçtı ve PDO alt sınıflaması
ile çözülmüştü (ADR-0007, `includes/db_debug.php`).

**3. Tekrar.** Üç satırın ikisi her seferinde birebir aynıydı.

## Karar

`includes/query.php`: prepare → execute → fetch üçlüsünün tek yeri.

```php
db_all($sql, $params)      // list<array<string, mixed>>
db_row($sql, $params)      // array<string, mixed>|null
db_value($sql, $params, $default)
db_int($sql, $params, $default)
db_column($sql, $params)   // ilk sütunun değerleri
db_pairs($sql, $params)    // ilk sütun anahtar, ikinci değer
db_exists($sql, $params)   // bool
db_run($sql, $params)      // etkilenen satır sayısı
db_insert($sql, $params)   // yeni satırın id'si
db_stmt($sql, $params, $pdo)   // tipli, çalıştırılmış statement
db_prepare($sql, $pdo)         // tipli, çalıştırılmamış statement
```

### Bu bir ORM DEĞİLDİR

Tablo eşleme, varlık nesnesi, tembel yükleme, sorgu kurucu yok. SQL
yine elle yazılıyor ve çağrı yerinde okunuyor: hangi indeksin
kullanılacağı, hangi `JOIN`'in kurulacağı, `COALESCE`'un nereye
gireceği geliştiricinin kararı kalıyor. ADR-0009'daki "çalışma zamanı
bağımlılığı yok" kararı da korunuyor — bu 250 satırlık bir dosya,
paket değil.

### `false` değil `null`

`PDOStatement::fetch()` satır bulamazsa `false` döner. `db_row()` bunu
`null`'a çeviriyor. Sebep: `false` ile `??` çalışmaz, `?->` çalışmaz,
`?array` tipine girmez ve "yok" ile "hata" aynı değerle anlatılmış
olur.

Bu bir SÖZLEŞME DEĞİŞİKLİĞİDİR: `=== false` kontrolleri `=== null`
olmak zorundaydı. Dönüşüm aracı bunları da çevirdi, PHPStan da
kalanları "her zaman yanlış" diye işaretledi.

### `0` ile "satır yok" ayrı

`fetchColumn()` hem "satır yok" hem "değer false" için `false`
döndürüyor. `db_value()` yalnızca gerçek `false` için varsayılana
düşüyor; `COUNT(*) = 0` gerçek bir cevaptır ve kaybolmamalı.

### Tek `prepare()` noktası

`db_prepare()` uygulamadaki tek `PDO::prepare()` çağrısı. `false`
kontrolü orada bir kez yazılı: sessizce güvenmek yerine, varsayım
bozulursa anlaşılır bir istisna veriyor.

## Sonuçlar

PHPStan seviye 7'de 125 bulgu vardı, 67'ye indi. Kaybolan 58'in 55'i
`PDOStatement|false` kaynaklıydı — düzeltilmedi, **var olmaz oldu**.

Sorgu günlüğü artık `includes/db_debug.php`'deki PDO alt sınıfı yerine
tek bir gövdede yapılabilir. O dosya bugün duruyor (geriye dönük
uyumluluk ve alt sınıfın yakaladığı `PDOStatement` yeniden kullanım
durumları için), ama katman varken yazılsaydı gerekmezdi.

## Kabul edilen maliyet

**SQL enjeksiyonuna karşı ek bir koruma değil.** Katman birleştirmeyi
kolaylaştırmıyor ama zorlaştırmıyor da; sorumluluk çağrı yerinde
kalıyor. `tools/check_conventions.php` bunu ayrıca denetliyor.

**Sütun ve tablo adları hâlâ parametre olamaz** (PDO buna izin
vermiyor). Sıralama sütunu gibi yerlerde beyaz liste şart — uygulamada
zaten öyle.

**Birim testiyle sınanamaz.** Katmanın tamamı PDO'nun gerçek davranışı
üzerine kurulu; sahte bir PDO ile sınamak, sınanan iddianın kendisini
varsaymak olurdu. Testler bu yüzden `tests/Integration/` altında ve
veritabanı istiyor.

**Bir kaç çağrı yeri dışarıda kaldı** — `bindValue` döngüleri, aynı
statement'ın döngüde yeniden çalıştırılması, `FETCH_KEY_PAIR` dışı özel
kipler. Bunlar `db_stmt()` / `db_prepare()` kullanıyor: tip güvencesi
var, sarmalayıcı yok. Statement seviyesinde iş yapan kod statement
seviyesinde kalmalı.

## Değerlendirilen alternatifler

**PHPStan stub dosyası** (`PDO::prepare(): PDOStatement` diye
yazmak): denendi, çalışmadı — PHPStan'in PDO için dahili işleyişi
kullanıcı stub'ını eziyor. Çalışsaydı bile yalnızca 1. sorunu
çözerdi; 2 ve 3 yerinde kalırdı.

**72 çağrı yerine `assert()` eklemek:** hiç çalışmayacak ölü dal
yazmak. Ayrıca gürültüyü koda taşıyıp çözümleyiciden gizlemek olurdu.

**Kuralı yok saymak (`ignoreErrors`):** gerçek bir `nonObject`
hatasını da örterdi.

**Doctrine DBAL:** çalışma zamanı bağımlılığı getirirdi (ADR-0009),
ve sorunun kendisi 250 satırla çözülüyor.

**Varlık başına repository dosyası** (`RiskRepository` vb.): sorgular
şu an ekranlarıyla birlikte duruyor ve çoğu tek bir ekrana özgü.
Hepsini bir dosyaya toplamak, "aynı yerde olsun" dışında bir fayda
üretmeden okumayı zorlaştırırdı. Bir sorgu iki ekranda tekrar
ederse o zaman ortak bir fonksiyona çıkarılır — tekrar görülünce,
tahminle değil.

## İlgili

- Testler: `tests/Integration/QueryLayerTest.php` (21)
- ADR-0001: Çerçeve kullanılmadı
- ADR-0007: Hata ayıklama kipi (PDO alt sınıflaması)
- ADR-0009: Geliştirme bağımlılığı evet, çalışma zamanı hayır
