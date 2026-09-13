# ADR-0007: Hata ayıklama kipi — iki anahtar, süreli bayrak

- **Durum:** Kabul edildi
- **Tarih:** 2026-09-13
- **Etkilediği yerler:** `config/config.php`, `includes/debug.php`, `includes/db_debug.php`, `admin/debug/`

## Bağlam

"Bu sayfa neden yavaş", "bu sorgu neden boş döndü", "bu oturumda ne var"
sorularını cevaplamanın yolu `var_dump()` serpiştirmekti. Üç sorunu
vardı: çıktı sayfanın ortasına düşüyor, üretimde unutuluyor, ve asıl
sorunu göstermiyor — çalışan SQL görünmüyordu.

Bir hata ayıklama kipi gerekiyordu. Ama böyle bir kip açıkken **sorgu
metinlerini, dosya yollarını ve oturum içeriğini tarayıcıya basar**;
yanlış kurgulanırsa bir bilgi sızdırma yüzeyidir.

## Karar

### Kip iki ayrı yoldan açılır

**1. Ortam değişkeni** — kalıcı, sunucu yöneticisinin kararı:

```apache
SetEnv RISKOPS_DEBUG 1
```

`APP_ENV=production` iken bu **tek başına yetmez**; ayrıca
`RISKOPS_DEBUG_PRODUCTION=1` gerekir.

Neden çift bayrak: kopyalanmış bir VirtualHost'ta ya da kurulum
betiğinde **unutulmuş** tek bir satır, canlı sistemi sızdıran hâle
getirmemeli. İki bayrak istemek bunu "unutulabilir" olmaktan çıkarıp
"bilerek yapılmış" hâle getirir.

**2. Yönetim ekranı** — geçici, uygulama yöneticisinin kararı:

`/admin/debug/` → süre seç (1 / 4 / 24 saat). `storage/debug.flag`
dosyasına bir **bitiş zamanı** yazılır.

Bu yolda çift bayrak aranmaz ve bu kasıtlıdır: oradaki açma zaten kazara
değildir — oturum açmış bir admin, POST + CSRF ile, süreli olarak yapar
ve işlem denetim kaydına yazılır (`debug.enable` / `debug.disable`).
Çift bayrak kuralı **unutulmaya** karşıdır, bilinçli açmaya karşı değil.

### Bayrak sürelidir

Süre dolduğunda dosya silinmemiş olsa bile **yok sayılır**. "Geçen ay
açmıştım, hâlâ açıkmış" durumu oluşamaz.

### Kapalıyken maliyeti sıfırdır

`includes/db_debug.php` (sorgu kaydedici PDO alt sınıfları) yalnızca kip
açıkken yüklenir. Kapalıyken `db()` düz PDO döndürür; sorgu yolu bayrak
eklenmeden önceki hâliyle birebir aynıdır. `debug.php` içindeki her
fonksiyon ilk satırında döner.

### Sır basmaz

İki katmanlı maskeleme: anahtar adı şüpheliyse (`password`, `_csrf`,
`PHPSESSID`…) **ya da** değerin kendisi veritabanı parolası, oturum
kimliği veya CSRF jetonuyla aynıysa `***` basılır. Bool ve 0/1
maskelenmez — sır taşıyamazlar ve maskelemek aracı körleştiriyordu
(`must_change_password` bayrağı `***` görünüyordu).

Üretimde araç çubuğu **yalnızca admin rolüne** gösterilir.

## Sonuçlar

Sorgu kaydı üç noktadan yakalanıyor: `execute()`, `query()`/`exec()` ve
**başarısız `prepare()`**. Sonuncusu önemli: `EMULATE_PREPARES=false`
olduğu için bozuk SQL prepare aşamasında patlar ve `execute()` hiç
çağrılmaz — sarmalanmasaydı hatayı veren sorgu listede görünmezdi.

`tools/go_live_check.php` kipin açık olduğunu ve **hangi yoldan**
açıldığını raporlar, kapatma yolunu ona göre söyler: panelden açıldıysa
bir düğme yeter, ortamdan geldiyse VirtualHost düzenlenmeli.

## Kabul edilen maliyet

Bayrak dosyası `storage/` içinde durur; `storage/` yazılabilir değilse
kip yönetim ekranından açılamaz (ekran bunu açıkça söyler).

Kip açıkken her istek `storage/logs/debug.log` dosyasına bir satır yazar.
Uzun süreli ölçümde dosya büyür; `RISKOPS_DEBUG_LOG_ONLY_SLOW=1` ile
yalnızca yavaş/hatalı istekler yazılır.

`X-RiskOps-Debug` başlığı yalnızca yanıt henüz akıtılmamışsa gönderilebilir
(yönlendirmeler, JSON uçları). HTML sayfalarında aynı veriyi araç çubuğu
taşıyor.

## Değerlendirilen alternatifler

**Xdebug:** Daha güçlü ama sunucuya eklenti kurmak gerekiyor ve üretimde
istenmiyor. Ayrıca "bu sorguyu hangi satır açtı" sorusuna araç çubuğu
kadar doğrudan cevap vermiyor.

**Bayrağı oturumda tutmak:** Kip, sorgu kaydedici PDO sarmalayıcılarını
devreye sokuyor; bu karar **bağlantı kurulurken**, oturum açılmadan önce
verilmek zorunda. Ayrıca cron işleri ve CLI araçları da aynı kipi görmeli.

**Bayrağı ayar tablosunda tutmak:** Ayarları okumak için önce
veritabanına bağlanmak gerekir; bağlantının nasıl kurulacağı bayrağa
bağlı olduğu için döngü oluşuyor.

## İlgili

- Test: `tools/debug_test.php` (109 kapalı / 119 açık), panel HTTP testi (39)
- Kullanım: `README.md` → "Hata ayıklama kipi"
- Canlıya alma: `deploy/DEPLOY.md` → bölüm 12
