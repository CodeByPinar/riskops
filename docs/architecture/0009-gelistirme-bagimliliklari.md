# ADR-0009: Geliştirme bağımlılığı evet, çalışma zamanı bağımlılığı hayır

- **Durum:** Kabul edildi
- **Tarih:** 2026-09-14
- **Etkilediği yerler:** `composer.json`, `.php-cs-fixer.dist.php`, `phpstan.neon`, `.github/workflows/ci.yml`

## Bağlam

Projeye dışarıdan bir değerlendirme geldi. Özetle dört öneri:

1. Symfony Components kullan — Laravel'in aksine proje yapısını
   dayatmadan, tek tek bağımlılık olarak girer.
2. Doctrine gibi bir ORM, geliştirme ve refactor maliyetini büyük ölçüde
   ortadan kaldırır.
3. php-cs-fixer gibi araçları geliştirme bağımlılığı olarak kullan.
4. Commit geçmişinde tasarım deseni eksikliği göze çarpıyor.

Öneriler ADR-0001'deki "çerçeve yok, çalışma zamanı bağımlılığı sıfır"
kararıyla doğrudan ilişkili. Karar yeniden değerlendirildi.

## Karar

**İki bağımlılık türü ayrılır ve farklı kurala tabidir.**

**Geliştirme bağımlılıkları: SERBEST.** Uygulamayı çalıştırmak için
gerekmezler, `require-dev` altındadırlar, üretim sunucusunda
bulunmazlar. Eklenenler:

| Araç | Ne için |
|---|---|
| `phpunit/phpunit` | 128 test (116 birim + 12 entegrasyon) |
| `friendsofphp/php-cs-fixer` | Kod biçimi PSR-12 |
| `phpstan/phpstan` | Statik çözümleme, seviye 8 (bkz. ADR-0012) |

**Çalışma zamanı bağımlılıkları: HAYIR.** ADR-0001 geçerliliğini
koruyor. Doctrine ve Symfony Components şimdilik alınmadı.

## Sonuçlar

### Kod biçimi artık tartışma konusu değil

`composer cs:fix` bir kez çalıştırıldı; 132 dosyanın 106'sı biçimlendi.
Değişikliklerin tamamı mekanikti — `<?php` sonrası boş satır, tek
satırlık gövdelerin açılması, `fn (` boşluğu. Hiçbir test kırılmadı.

İki PSR-12 kuralı bilerek geri alındı, gerekçesi
`.php-cs-fixer.dist.php` içinde yazılı: çok satırlı çağrıları her
argümanı ayrı satıra zorlayan kural (`audit()` çağrıları altı satıra
bölünüyordu) ve şablonlardaki karışık HTML/PHP girintisine dokunan kural.

### Statik çözümleme ilk koşusunda gerçek bir hata buldu

`due_date_cell()` iki parametre alıyordu; dört risk ekranı ise üç
argümanla çağırıyordu. PHP, kullanıcı tanımlı bir fonksiyona fazla
argüman verilmesini hata saymaz — sessizce atar.

Atılan argüman `risk_open_statuses()` idi; `is_overdue()` de varsayılan
olarak `action_open_statuses()` listesine düşüyordu. İki listenin farkı
tek bir durum: **Under Review**.

Sonuç: **termini geçmiş, incelemedeki bir risk; risk listesinde,
panelde, risk detayında ve yönetici özetinde gecikmiş görünmüyordu.**
Hiçbir uyarı üretmiyor, yalnızca eksik vurgu olarak ortaya çıkıyordu ve
dört ekranda da gözden kaçmıştı.

Bu tek bulgu, aracın maliyetini fazlasıyla karşıladı.
Regresyon testi: `tests/Unit/DueDateCellTest.php`.

### Temel çizgi (baseline) borcu yok

Alışılmış yol, mevcut bulguları dondurup üzerine devam etmektir.
Gerek kalmadı:

| | Bulgu | Ne yapıldı |
|---|---|---|
| İlk koşu | 110 | — |
| `dynamicConstantNames` | 37 | Bulguların çoğu sahteydi: ön yükleyici `config.php`'yi çalıştırdığı için PHPStan `APP_ENV`'i sabit değer sanıyor, ortam karşılaştırmalarını "her zaman false" sayıyordu |
| Parçalara `@var` sözleşmesi + 3 gerçek düzeltme | 12 | `_form.php` gibi parçalar çağıranın kapsamından besleniyor; beklenen değişkenler düz metin yerine `@var` ile yazıldı — aynı bilgi, artık denetlenebilir |
| `tools/` istisnası | 0 | Doğrulama betiklerinde "her zaman doğru" uyarıları; bir testin iddiası zaten böyle görünür |

Her bulgu ya düzeltildi ya da neden yok sayıldığı `phpstan.neon` içinde
yazılı. Seviye buradan kademeli yükseltilecek.

## Kabul edilen maliyet

**Katkı vermek için artık Composer gerekiyor** — ama yalnızca
*geliştirmek* için. Uygulamayı *çalıştırmak* için hâlâ gerekmiyor;
`vendor/` dizini olmadan uygulama açılır.

**Biçimlendirme commit'i `git blame` gürültüsü yarattı.** 106 dosyaya
dokunuldu. Azaltmak için kendi commit'inde ve açıkça "yalnızca biçim"
etiketiyle yapıldı; `git blame --ignore-rev` ile atlanabilir.

## Değerlendirilen alternatifler

### Doctrine ORM — alınmadı

Refactor maliyeti argümanı **doğru**: bugün bir kolon eklemek sorguya,
forma, `_validate.php`'ye ve görünüme dokunmak demek.

Karşı ağırlıklar:

- **Değer, elle ayarlanmış SQL'de.** `inherent_score` bir
  `GENERATED ... STORED` kolon; arama FULLTEXT; son admin koruması sabit
  kilit sıralı `FOR UPDATE`; risk kodu sayacı
  `ON DUPLICATE KEY UPDATE ... LAST_INSERT_ID(n+1)`. Doctrine bunların
  hepsinde native query'ye düşer — ORM'in maliyeti alınır, tam da önemli
  yerlerde faydası alınamaz.
- **Hedef ortam.** ADR-0001'de yazılı: Composer'sız kurulum bilinçli bir
  kısıt. Doctrine bunu bitirir.
- Raporlar zaten elle ayarlanmış sorgular; ORM'in en çok kazandırdığı
  yer olan CRUD, bu uygulamanın en küçük kısmı.

**Ara yol yol haritasına alındı:** ORM değil, ince bir **repository
katmanı**. Sorgular tek yerde toplanır, refactor maliyeti düşer, SQL
kontrolü bizde kalır.

### Symfony Components — şimdilik alınmadı

"Parça parça alınır, yapı dayatmaz" iddiası **doğru**. Doğal adaylar
var: `symfony/console` (`tools/` altındaki betikler),
`symfony/mailer` (`includes/mailer.php`), `symfony/validator`.

Alınmama sebebi teknik değil, sıralama: bunların her biri çalışma zamanı
bağımlılığı getirir ve Composer'sız kurulumu bitirir. O kısıt
kalkmadıkça girmeleri anlamsız. Kısıt gözden geçirilirse ilk aday
`symfony/console`'dur — en az bulaşıcı olan odur.

### "Tasarım deseni eksikliği" — kısmen katılınıyor

Gözlem, kod tabanının prosedürel olduğu yönüyle **doğru**: uygulama
kodunda toplam iki sınıf var. Ama bu bir gözden kaçma değil, ADR-0001'de
gerekçesiyle yazılı bir karar.

Desenler var; sınıf hiyerarşisi yerine fonksiyon ve yapılandırma
diliyle ifade edilmişler:

| Desen | Nerede |
|---|---|
| Decorator / Proxy | `RiskOpsDebugPdo`, `RiskOpsDebugStatement` — PDO'yu sarmalayıp her sorguyu kaydediyor |
| Registry | `discussion_registry()`, `reports/_reports.php` |
| Strategy | Yorum/ek uçları tek koddan; `discussion_config()` risk ve aksiyona aynı davranışı farklı tablolarla uyguluyor |
| Singleton | `db()` |
| Template Method | `_validate.php` parçaları, `store`/`update` tarafından paylaşılıyor |

Değerlendirmenin yöntemine bir not: desen kullanımı **commit
geçmişinden** okunamaz; `includes/` okunmalı.

## İlgili

- ADR-0001: Çerçeve kullanılmadı
- Regresyon testi: `tests/Unit/DueDateCellTest.php`
- Yapılandırma: `.php-cs-fixer.dist.php`, `phpstan.neon`
