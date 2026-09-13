# ADR-0008: Dosya ekleri üç katmanlı doğrulanır

- **Durum:** Kabul edildi
- **Tarih:** 2026-09-12
- **Etkilediği yerler:** `includes/attachments.php`, `discussion/attachment_*.php`, `storage/.htaccess`

## Bağlam

Dosya yükleme, uygulamanın en tehlikeli yüzeyidir. Saldırganın hedefi
genellikle iki şeyden biridir:

1. Sunucuya **çalıştırılabilir** bir dosya yazdırmak (`shell.php`)
2. Tarayıcıda **çalışan** bir dosya sunmak (`.svg` ya da `.html` içinde script)

Risk kayıtlarına ve aksiyonlara kanıt dosyası eklenebilmesi isteniyordu.

## Karar

Beş kural birlikte uygulanır; her biri yukarıdaki iki hedeften birini
kapatır.

**1. İstemciden gelen ad asla diske yazılmaz.** Diskteki ad uygulama
tarafından üretilir ve rastgeledir. `../../config/database.php` ya da
`shell.php` gibi adlar bu yüzden hiçbir şey yapamaz. Özgün ad yalnızca
veritabanında, gösterim amacıyla saklanır.

**2. Uzantı beyaz listeden.** Kara liste kullanılmaz: `.phtml`, `.php5`,
`.phar` gibi varyantları saymakla bitmez, biri unutulur.

**3. Gerçek içerik tipi doğrulanır (`finfo`).** İstemcinin bildirdiği
`Content-Type` tamamen sahtedir; bir PHP dosyasının başına GIF başlığı
koyup "image/gif" demek saniyeler sürer. `finfo` dosyanın **kendisine**
bakar ve uzantıyla tutarlı olması aranır.

**4. Dosyalar web erişimine kapalı dizinde durur.** `storage/uploads`
altında; hem `storage/.htaccess` hem VirtualHost `DirectoryMatch` ile
kapalı. İki kat koruma, çünkü `.htaccess` yalnızca `AllowOverride All`
açıkken çalışır — performans için kapatılırsa dizin sessizce açığa
çıkardı.

**5. İndirme her zaman `application/octet-stream` + `attachment` +
`nosniff`.** Tarayıcıda hiçbir ek render edilmez. Bir SVG ya da HTML eki
inline sunulsaydı içindeki script **uygulamanın kendi kökeninde**
çalışırdı; kalıcı XSS olurdu.

Ek olarak boyut ve adet sınırı var: dosya başına 10 MB, kayıt başına 20 ek.

## Sonuçlar

Yüklenen bir dosya ne sunucuda çalıştırılabilir ne tarayıcıda
yorumlanabilir. En kötü durumda kullanıcı zararlı bir dosyayı **indirir**
— bu artık uygulamanın değil işletim sisteminin sınırı.

İndirme ucu yetki denetimi yapar: eki görebilmek için ilgili kaydı
görebilme yetkisi gerekir. Rastgele dosya adı bir güvenlik önlemi
**sayılmaz**, yalnızca çakışmayı önler.

## Kabul edilen maliyet

**Önizleme yok.** Bir görsel ek bile tarayıcıda gösterilmez, indirilir.
Kullanım kolaylığından ödün verildi; inline sunmanın bedeli kalıcı XSS
riski olurdu.

**`php.ini` sınırı uygulamanınkinden küçükse** büyük ekler PHP tarafından
sessizce reddedilir. `tools/debug_report.php` bu uyumsuzluğu raporluyor.

## Değerlendirilen alternatifler

**Dosyaları veritabanında (BLOB) saklamak:** Yedekleme ve taşıma
kolaylaşırdı; yükleme/indirme sırasındaki bellek maliyeti ve veritabanı
boyutu ağır bastı.

**Ayrı bir dosya sunucusu / farklı köken:** En güvenli yol — script farklı
kökende çalışır, uygulamaya zarar veremez — ama tek sunuculuk kurulum
hedefiyle çelişiyor. Uygulama büyürse bu karar yeniden değerlendirilmeli.

**Antivirüs taraması (ClamAV):** Elenmedi, ertelendi. Yukarıdaki kurallar
"çalıştırma"yı kapatıyor; virüs taraması indirilen dosyanın içeriğiyle
ilgili ayrı bir sorun.

## İlgili

- Sınırlar: `ATTACH_MAX_BYTES`, `ATTACH_MAX_PER_RISK` — `includes/attachments.php`
- Yükleme güvenliği kontrolleri: 24/24
