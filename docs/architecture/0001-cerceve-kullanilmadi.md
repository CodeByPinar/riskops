# ADR-0001: Çerçeve (framework) kullanılmadı

- **Durum:** Kabul edildi
- **Tarih:** 2026-09-11
- **Etkilediği yerler:** tüm proje

## Bağlam

RiskOps, kurumsal BT ve siber risklerin kayıt altına alınması için
yazıldı: risk kaydı, 5×5 değerlendirme, aksiyon takibi, rapor ve denetim
kaydı. Laravel ya da Symfony ile yazmak olağan tercih olurdu.

Belirleyici kısıtlar:

- Hedef ortam kurumsal bir sunucu: Ubuntu + Apache + PHP + MariaDB.
  Composer, Node ve derleme adımı olmayan kurulumlar da hedefte
  (paylaşımlı hosting dâhil).
- Uygulamayı devralacak kişinin bir çerçeveyi bilmesi beklenemez.
- Güvenlik davranışının **görünür** olması isteniyordu: yetkilendirmenin,
  CSRF korumasının ve sorgu katmanının nerede olduğu okunarak
  anlaşılabilmeli.

## Karar

Çerçeve kullanılmadı. Çalışma zamanı harici bağımlılığı **sıfır**.

Yapı, çerçevenin verdiği şeyleri açıkça yazarak sağlıyor:

| İhtiyaç | Karşılığı |
|---|---|
| Giriş noktası | Her sayfa `includes/bootstrap.php` ile başlar |
| Veritabanı | `db()` — tembel PDO tekili, native prepared statement |
| Yetkilendirme | `require_can()` / `require_role()` — rol→yetki matrisi |
| CSRF | `csrf_require()` — POST işleyicilerinin ilk satırı |
| Şablon | PHP'nin kendisi + `e()` ile kaçışlama |
| Yönlendirme | Dosya sistemi (Apache) |

Geliştirme bağımlılığı olarak yalnızca PHPUnit var (`require-dev`);
uygulamayı çalıştırmak için gerekmez.

## Sonuçlar

**Kurulum dosya kopyalamaktır.** `git clone`, `config/database.php` yaz,
şemayı yükle. Composer ya da derleme adımı yok.

**Yükseltme yükü yok.** Çerçevenin ana sürüm geçişleri, güvenlik
yamalarının takibi ve bağımlılık ağacının denetimi gündeme gelmiyor.

**Güvenlik davranışı okunabilir.** "Bu isteği kim yapabilir" sorusunun
cevabı dosyanın ilk beş satırında.

## Kabul edilen maliyet

**Çerçevenin bedava verdiği şeyler elle yazıldı** ve bakımı bize ait:
oturum sıkılaştırma, CSRF, sayfalama, doğrulama, i18n, denetim kaydı.
Toplamı ~18.500 satır.

**Kolay hata yapılan yerler kalıyor.** Bir sorguyu `prepare` yerine
birleştirmek ya da `e()` çağırmayı unutmak bir çerçevede zor, burada
mümkün. Bu yüzden kurallar makineye denetlettiriliyor:
`tools/check_conventions.php` her itmede çalışıyor.

**ORM yok.** Karmaşık raporlar elle yazılmış SQL. Bu uygulama için
avantaj sayıldı — raporlar zaten elle ayarlanmış sorgular — ama yeni
tablo eklemek daha çok yazı demek.

## Değerlendirilen alternatifler

**Laravel:** Hazır yetkilendirme, migration ve test altyapısı. Elenme
sebebi: Composer ve derleme adımı zorunluluğu, devralacak kişi için
öğrenme yükü.

**Slim / Fat-Free gibi mikro çerçeveler:** Çerçeve yükünün çoğunu
getiriyor, kazancın azını veriyor.

**Statik çözümleyici (PHPStan / Psalm):** Elenmedi, ertelendi —
yol haritasında. Çerçevesiz bir kod tabanında değeri yüksek.

## İlgili

- Yapı: `README.md` → "Mimari"
- Kural denetimi: `tools/check_conventions.php`
