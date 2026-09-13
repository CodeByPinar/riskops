# ADR-0006: Skor veritabanında üretilir, seviye üretilmez

- **Durum:** Kabul edildi
- **Tarih:** 2026-09-11
- **Etkilediği yerler:** `database/schema.sql`, `includes/risk.php`, `admin/settings/`

## Bağlam

Bir risk iki değerle ölçülüyor: olasılık (1-5) ve etki (1-5).

- **Skor** = olasılık × etki (1-25)
- **Seviye** = skorun düştüğü aralığın adı (Low / Medium / High / Critical)

İkisi de türetilmiş değer. Soru şuydu: hangisi veritabanında üretilsin?

## Karar

**Skor** `GENERATED ALWAYS AS (likelihood * impact) STORED` kolonudur.

**Seviye** değildir; `severity_from_score()` ile hesaplanıp sıradan bir
kolona yazılır.

Ayrım tek bir ölçüte dayanıyor: **tanım değişebilir mi?**

Skorun tanımı sabittir — olasılık çarpı etki, her zaman. Veritabanına
gömülebilir ve gömülmelidir: doğrudan SQL ile yazan bir betik bile
tutarsız skor üretemez.

Seviye eşikleri **Ayarlar ekranından değiştirilebilir**. Bir kurum
"10 ve üzeri High" derken bir diğeri "7 ve üzeri High" diyebilir. Eşik
değiştiğinde yalnızca yeni kayıtlar değil **mevcut tüm kayıtlar** yeniden
etiketlenmelidir; `risk_recalculate_severities()` bunu yapar.

## Sonuçlar

Skor için tek doğruluk kaynağı veritabanıdır. Uygulamadaki `risk_score()`
aynı hesabı yapar ama kimse ona mecbur değildir.

Seviye sorgulanabilir ve indekslenebilir bir kolondur: "Kritik riskler"
filtresi hesaplama yapmadan çalışır. Türetilmiş olsaydı her listeleme
sorgusu JSON eşik tablosuyla uğraşırdı.

Eşik değişimi bir **toplu güncelleme** işidir, ayar kaydedildiğinde
tetiklenir ve kaç kaydın etkilendiği kullanıcıya bildirilir.

## Kabul edilen maliyet

**Seviye ile skor geçici olarak tutarsız kalabilir** — eşik değişip
toplu güncelleme yarıda kesilirse. Bu yüzden güncelleme tek bir
transaction içinde yapılıyor.

`STORED` kolon tabloyu büyütür. 1-25 aralığındaki bir tamsayı için bu
ihmal edilebilir; kazancı (indekslenebilirlik, dışarıdan yazmaya karşı
koruma) ağır basıyor.

Eşikler elle düzenlenip aralıklar arasında **boşluk** bırakılabilir. O
boşluğa düşen skor için güvenli tarafa yuvarlanıyor: 16 üstü Critical,
altı Low. Test bunu kilitliyor.

## Değerlendirilen alternatifler

**İkisini de GENERATED yapmak:** Seviye eşiklerini şemaya gömerdi. Eşik
değiştirmek `ALTER TABLE` gerektirirdi; ayar ekranından yapılamazdı.

**İkisini de uygulamada hesaplamak:** Doğrudan SQL ile yazılan bir satır
skorsuz kalırdı. Şema, uygulamanın dışından gelen yazmalara karşı da
kendini korumalı.

**Seviyeyi VIEW ile üretmek:** Eşikler ayar tablosunda JSON olarak
durduğu için VIEW içinde JSON ayrıştırmak gerekirdi; sorgular okunamaz
hâle gelirdi ve indeks kullanılamazdı.

## İlgili

- Test: `tests/Unit/RiskScoringTest.php`, `tests/Integration/SchemaTest.php`
- Yeniden etiketleme: `risk_recalculate_severities()` — `includes/risk.php`
