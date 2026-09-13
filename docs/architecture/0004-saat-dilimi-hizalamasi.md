# ADR-0004: MySQL oturum saati PHP ile hizalanır

- **Durum:** Kabul edildi
- **Tarih:** 2026-09-11
- **Etkilediği yerler:** `includes/db.php` (`db_sync_timezone`), `includes/bootstrap.php`

## Bağlam

Sunucunun MariaDB'si UTC, uygulama `Europe/Istanbul` çalışıyordu.
Zaman damgalarının bir kısmını **MySQL** yazıyor (`NOW()`,
`CURRENT_TIMESTAMP`: `created_at`, `updated_at`, `last_login_at`, denetim
kaydı), bir kısmını **PHP** hesaplıyor (`time()`, `date()`).

Ölçülen fark: **3 saat**.

Sonuçları yalnızca kozmetik değildi:

1. **Tüm ekranlarda tarihler 3 saat geri görünüyordu.** Fark edilmesi
   kolay olan tek belirti buydu.

2. **Hesap kilidi hiç devreye girmiyordu.** Kaba kuvvet penceresi PHP
   tarafında hesaplanıyor (`date(..., time() - $lockout)`) ve MySQL'in
   yazdığı `attempted_at` ile karşılaştırılıyordu. Pencerenin başlangıcı
   kayıtlardan **ileride** kaldığı için koşul hiçbir satırı yakalamıyordu.
   Ölçümle doğrulandı: 8 başarısız denemede kilit oluşmadı.

3. **`auth_revalidate()` içindeki "parola benden sonra mı değişti"
   karşılaştırması** aynı nedenle çalışmıyordu — yani parolası sıfırlanan
   bir kullanıcının açık oturumu kapanmıyordu.

İkinci ve üçüncü madde **güvenlik önlemlerinin sessizce devre dışı
kalması** demekti ve hiçbir hata üretmiyordu.

## Karar

Bağlantı kurulduktan sonra MySQL oturumunun saat dilimi PHP'ninkine
hizalanır:

```php
db()->prepare('SET time_zone = ?')->execute([$offset]);   // örn. '+03:00'
```

Hizalama `bootstrap.php` içinde, `date_default_timezone_set()`
çağrısından **sonra** yapılır — çünkü PHP'nin saat dilimi ayar
tablosundan okunuyor ve bağlantı kurulurken henüz belli değil.

Ofset bir kez uygulanır ve tekrar çağrılarda atlanır (statik önbellek).

## Sonuçlar

`NOW()` ile `date()` aynı anı gösterir. Zamana dayalı her kontrol —
hesap kilidi, oturum zaman aşımı, parola değişim karşılaştırması, geciken
aksiyon hesabı — aynı saate bakar.

Hizalama başarısız olursa uygulama **çalışmaya devam eder** ama sessiz
kalmaz: `app_log('error', ...)` ile kaydedilir. Tarihler kayık olur ancak
uygulama açılır; bu, "tarih yanlış" ile "sistem kapalı" arasındaki bilinçli
bir tercihtir.

## Kabul edilen maliyet

Her bağlantıda fazladan bir `SET time_zone` sorgusu çalışır. Ölçülen
maliyeti 0.1 ms'nin altında.

Ofset **sabit** gönderilir (`+03:00`), IANA adı değil. MariaDB'de adlarla
çalışmak `mysql.time_zone*` tablolarının doldurulmuş olmasını gerektirir ve
çoğu kurulumda dolu değildir. Bunun bedeli: yaz saati geçişi olan bir
bölgede ofset, geçiş anında yeniden hesaplanana kadar eski değerde kalır.
Türkiye 2016'dan beri kalıcı +03'te olduğu için bu kurulum etkilenmiyor.

## Değerlendirilen alternatifler

**Her şeyi UTC'de saklayıp görüntülerken çevirmek:** Daha doğru bir
yaklaşım ve yeni bir projede tercih edilirdi. Burada `NOW()` yazan onlarca
sorgunun ve okuyan onlarca ekranın tamamını değiştirmek gerekirdi; risk,
kazancı aşıyordu.

**Sunucunun MySQL'ini Europe/Istanbul yapmak:** Uygulamayı sunucu
yapılandırmasına bağımlı kılardı. Aynı veritabanını kullanan başka bir
uygulama ya da yeni bir sunucu, sorunu sessizce geri getirirdi.

**Zaman damgalarını yalnızca PHP'ye yazdırmak:** `DEFAULT CURRENT_TIMESTAMP`
ve `ON UPDATE CURRENT_TIMESTAMP` kolonlarından vazgeçmek gerekirdi; doğrudan
SQL ile yazan bir betik zaman damgasız satır üretebilirdi.

## İlgili

- Test: `tests/Integration/SchemaTest.php` → `php_ve_mysql_ayni_ani_gosteriyor`
- Teşhis: `php tools/debug_report.php` → "Fark" satırı
