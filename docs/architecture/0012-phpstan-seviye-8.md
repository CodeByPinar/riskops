# ADR-0012: PHPStan seviye 8'de durulması

- **Durum:** Kabul edildi
- **Tarih:** 2026-09-19
- **Etkilediği yerler:** `phpstan.neon`, CI

## Bağlam

Seviye 5'ten 8'e kademeli çıkıldı; her seviye kendi commit'inde, kendi
düzeltmeleriyle, **baseline kullanılmadan**. Ölçülen maliyetler:

| Seviye | Bulgu (başlangıçta) | Ne soruyor |
|--------|--------------------|------------|
| 6 | 80 | Bu dizi ne taşıyor? |
| 7 | 200 → 125 | Bu ifade `false` dönebilir mi? |
| 8 | 204 → 56 | Bu şey `null` olabilir mi? |
| 9 | 895 → 830 | Bu `mixed` gerçekten ne? |

7 ve 8'in düşmesinin sebebi 6'nın ve sorgu katmanının (ADR-0011)
yaptığı iş: her seviye bir öncekinin kazancını devralıyor.

## Karar

**Seviye 8'de durulmuştur.** Seviye 9 açılmayacak.

## Gerekçe

Seviye 9, `mixed` üzerinde herhangi bir işlem yapmayı yasaklıyor.
Uygulama prosedürel ve veritabanı satırları `array<string, mixed>`
olarak dolaşıyor (ADR-0001: ORM yok, varlık sınıfı yok). Sonuç:

```php
// bugün
$title = (string)$row['title'];

// seviye 9 ile
$raw = $row['title'];
$title = is_scalar($raw) ? (string)$raw : '';
```

830 yerde bu dönüşüm demek. Kazanılan güvence: sütunun beklenmedik bir
tip taşıması durumunda erken yakalama. Oysa **tip zaten şemada
sabit** — `title` bir `VARCHAR NOT NULL`, ve şema `SchemaTest` ile
doğrulanıyor. Yani seviye 9'un koruduğu senaryo, veritabanı şemasının
sessizce değişmesi; bunun doğru savunması sütun kontrolü, her satır
erişiminde `is_scalar` değil.

Kod okunurluğu bedeli somut, kazanç varsayımsal. Seviye 9 bu kod
tabanında **kendi hedefini de kaçırırdı**: 830 sarmalayıcı arasında
gerçek bir tip hatası görünmez olurdu.

## Sonuçlar

- CI'da PHPStan seviye 8, **baseline yok, ignoreErrors yalnızca
  gerekçeli üç kural için**.
- Yeni kod seviye 8'i geçmek zorunda; bu, yeni bir `?array` dönüşünün
  çağrı yerinde ele alınmasını mecbur kılıyor.
- Seviye 9 kapalı; açılmak istenirse önce veritabanı satırlarının
  tipli bir temsili gerekir (ADR-0001'i yeniden tartışmak demektir).

## Kabul edilen maliyet

`mixed` hâlâ kod tabanında dolaşıyor. Bir sütun `int` sanılıp `string`
gelirse PHPStan susar; yakalayan şey test ya da çalışma zamanıdır.
Bunun bilinçli bir sınır olduğunu burada yazıyoruz ki "seviye 8 = her
şey tipli" sanılmasın.

## İlgili

- ADR-0001: Çerçeve kullanılmadı (prosedürel, ORM yok)
- ADR-0009: Geliştirme bağımlılığı evet, çalışma zamanı hayır
- ADR-0011: İnce bir sorgu katmanı — seviye 7'nin 58 bulgusunu
  düzelterek değil, ortadan kaldırarak çözdü
