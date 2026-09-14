# Mimari kararlar (ADR)

Bu dizin, projede **neden öyle yapıldığını** tutar. Kodun kendisi ne
yaptığını zaten söylüyor; buradaki kayıtlar seçilmeyen yolu ve seçimin
bedelini anlatır.

## Neden ayrı dosyalar?

Bu gerekçeler önce kaynak dosyaların başındaki uzun yorum bloklarında
duruyordu. İki sorun çıktı:

1. **Karar bir dosyaya ait değildi.** CSP kararı `bootstrap.php`,
   `functions.php`, `ui.php` ve `debug_toolbar.php` içinde parça parça
   anlatılıyordu; dördü de eksik, hiçbiri bütün değildi.
2. **Kod okunmuyordu.** 40 satırlık bir gerekçe bloğunun ardından gelen
   12 satırlık fonksiyonu görmek için önce gerekçeyi geçmek gerekiyordu.

Artık kod, **onu bozmadan değiştirmek için gerekeni** söylüyor; karar
kaydı ise tartışmayı taşıyor. Kod içindeki kısa not ilgili ADR'ye işaret
eder.

## Biçim

Her kayıt aynı beş başlığı taşır: Bağlam, Karar, Sonuçlar, Kabul edilen
maliyet, Değerlendirilen alternatifler. Kararlar **değiştirilmez**;
bir karar geçersizleşirse yenisi yazılır ve eskisi "Yerini aldı: ADR-XXXX"
notuyla işaretlenir.

## Kayıtlar

| # | Karar | Etkilediği yerler |
|---|---|---|
| [0001](0001-cerceve-kullanilmadi.md) | Çerçeve (framework) kullanılmadı | tüm proje |
| [0002](0002-ceviri-anahtari-kaynak-metin.md) | Çeviri anahtarı, Türkçe metnin kendisidir | `includes/i18n.php`, `lang/` |
| [0003](0003-csp-unsafe-inline-kaldirildi.md) | CSP'den `unsafe-inline` kaldırıldı | `includes/bootstrap.php`, `includes/functions.php` |
| [0004](0004-saat-dilimi-hizalamasi.md) | MySQL oturum saati PHP ile hizalanır | `includes/db.php` |
| [0005](0005-son-admin-invaryanti.md) | "En az bir aktif admin" kilitli okumayla korunur | `admin/users/` |
| [0006](0006-seviye-esikleri-ayarda.md) | Skor veritabanında üretilir, seviye üretilmez | `database/schema.sql`, `includes/risk.php` |
| [0007](0007-hata-ayiklama-kipi.md) | Hata ayıklama kipi: iki anahtar, süreli bayrak | `config/config.php`, `includes/debug.php` |
| [0008](0008-dosya-eki-guvenligi.md) | Dosya ekleri üç katmanlı doğrulanır | `includes/attachments.php` |
| [0009](0009-gelistirme-bagimliliklari.md) | Geliştirme bağımlılığı evet, çalışma zamanı hayır | `composer.json`, `phpstan.neon` |
| [0010](0010-eklenti-sistemi.md) | Eklenti sistemi: kancalar var, kum havuzu yok | `includes/plugins.php`, `plugins/`, `admin/plugins/` |
