# ADR-0005: "En az bir aktif admin" invariantı kilitli okumayla korunur

- **Durum:** Kabul edildi
- **Tarih:** 2026-09-12
- **Etkilediği yerler:** `admin/users/_validate.php`, `admin/users/toggle_status.php`, `admin/users/update.php`

## Bağlam

Sistemde her zaman en az bir aktif `admin` kalmalıdır. Sıfıra düşerse
kullanıcı yönetimi ekranına kimse giremez ve kurtarma **doğrudan
veritabanı müdahalesi** gerektirir.

İlk koruma bir `check-then-act` yarışıydı:

```php
if (another_active_admin_exists($id)) {   // kilitsiz SELECT COUNT(*)
    $pdo->prepare('UPDATE users SET status = 0 WHERE id = ?')->execute([$id]);
}
```

Tam **iki** aktif admin varken birbirini aynı anda pasifleştiren iki
istek düşünün:

| an | istek A | istek B |
|---|---|---|
| 1 | `COUNT(*) WHERE id <> A` → 1 (B var) | |
| 2 | | `COUNT(*) WHERE id <> B` → 1 (A var) |
| 3 | A pasif | |
| 4 | | B pasif |

Her ikisi de korumadan geçti; sistemde sıfır aktif admin kaldı.

Bu senaryo yalnızca teorik değildi — aynı anda iki sekmeden işlem yapan
tek bir yönetici de tetikleyebilir.

## Karar

Kontrol, çağıranın transaction'ı içinde **kilitli okuma** ile yapılır:

```sql
SELECT id, role, status FROM users
 WHERE id = :target OR (role = 'admin' AND status = 1)
 ORDER BY id
 FOR UPDATE
```

Üç ayrıntı bilerek böyle:

**`FOR UPDATE`** — satırlar kilitlenir. İkinci işlem, birincisi commit
edene kadar bloklanır; sonra commit edilmiş güncel durumu okur ve
reddedilir. İki işlem serileşir.

**`ORDER BY id`** — kilit sırası sabitlenir. Sabit olmasaydı iki işlem
satırları ters sırada kilitleyip birbirini bekleyebilirdi (deadlock).

**Hedefin durumu da burada okunur** — çağıranın transaction öncesi
okuduğu "hedef aktif admin miydi" bilgisine güvenilmez. Hedef bu arada
admin'e yükseltilmiş olabilir; o bilgiyle erken dönmek korumayı tamamen
atlatırdı. Kilitli okuma, REPEATABLE READ altında bile **güncel** veriyi
(current read) görür.

Kontrol ve `UPDATE` **aynı transaction** içindedir.

## Sonuçlar

Eşzamanlı iki istekten yalnızca biri geçer, ikincisi anlaşılır bir hata
alır. Ölçülerek doğrulandı: `tools/last_admin_race_test.php` iki ayrı
bağlantıdan aynı anda deneyip sonucu raporluyor (7/7).

Aynı koruma üç yerde de kullanılıyor: hesabı pasifleştirme, rol düşürme
ve kullanıcı güncelleme.

## Kabul edilen maliyet

`FOR UPDATE` tüm aktif admin satırlarını kilitler. Admin sayısı tipik
olarak tek haneli olduğu için bu, ölçülebilir bir darboğaz değil; ama
kural olarak transaction kısa tutulmalı — kilit altındayken e-posta
gönderimi gibi yavaş işler yapılmamalı.

## Değerlendirilen alternatifler

**Veritabanı seviyesinde CHECK/TRIGGER:** "En az bir satır şu koşulu
sağlamalı" bir satır kısıtı değildir; MariaDB'de `CHECK` bunu ifade
edemez. `TRIGGER` ile yazılabilirdi ama hata mesajı kullanıcıya
anlaşılır biçimde taşınamıyor ve mantık iki yere bölünüyordu.

**Uygulama seviyesinde kilit (`GET_LOCK`):** İşe yarardı ama adlandırılmış
kilit, kilidi bırakmayı unutan bir kod yolunda tüm yönetimi bloklar.
Satır kilidi transaction bitince kendiliğinden serbest kalır.

**"Son admin silinemez" yerine "kendini pasifleştiremez":** Farklı bir
invariant; iki farklı adminin birbirini pasifleştirmesini engellemiyor.

## İlgili

- Test: `tools/last_admin_race_test.php`
- Koruma: `last_admin_atomic_guard()` — `admin/users/_validate.php`
