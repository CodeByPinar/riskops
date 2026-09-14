# ADR-0010: Eklenti sistemi — kancalar var, kum havuzu yok

- **Durum:** Kabul edildi
- **Tarih:** 2026-09-14
- **Etkilediği yerler:** `includes/plugins.php`, `plugins/`, `admin/plugins/`, `includes/layout/sidebar.php`, `reports/_reports.php`, `risks/`

## Bağlam

Uygulamaya üçüncü taraf kod eklenebilmesi istendi: menüye giriş,
kuruma özel rapor, risk olaylarına tepki (dış sisteme bildirim,
ayrı bir günlük, entegrasyon).

Alternatif, her kurum için çekirdeği çatallamaktı (fork). Çatal,
yükseltmeyi imkânsız hâle getirir: bir güvenlik yaması geldiğinde her
kurumun kendi değişikliklerini yeniden birleştirmesi gerekir.

## Karar

### Kanca tabanlı eklenti sistemi

İki tür kanca:

```php
hook_do('risk.created', $riskId, $veri);          // EYLEM  - dönüş yok
$items = hook_filter('nav.items', $items);        // FİLTRE - değer zincirden geçer
```

Eklenti bir dizindir: `plugins/<slug>/plugin.php` bildirimi döndürür,
`hooks.php` kancaları kaydeder.

Mevcut kancalar: `risk.created`, `risk.updated`, `risk.deleted`,
`nav.items`, `reports.definitions`.

### Bu bir kum havuzu DEĞİLDİR ve öyle olduğu iddia edilmiyor

Eklenti, uygulamanın kendi ayrıcalıklarıyla çalışan PHP kodudur.
Veritabanına erişebilir, dosya yazabilir, ağa çıkabilir. PHP'de
gerçek bir kum havuzu yoktur — `disable_functions` ve benzeri önlemler
kararlı bir saldırganı durdurmaz, yalnızca durdurduğu yanılsamasını
verir.

Bunun doğrudan sonucu: **bir eklentiyi kurmak, yazarına çekirdek kadar
güvenmektir.** Bu cümle yönetim ekranında da yazıyor.

### Bu yüzden: eklenti web arayüzünden YÜKLENEMEZ

Yönetim ekranı yalnızca **diskte zaten var olan** eklentileri açıp
kapatır. Dosyayı sunucuya koymak operatörün işidir (`scp`, `git`,
paket yöneticisi).

Yükleme ucu olsaydı, ele geçirilmiş tek bir admin hesabı **doğrudan
uzaktan kod çalıştırmaya** dönüşürdü. Kimlik doğrulama zafiyeti ile
sunucunun tamamen kaybı arasındaki fark ortadan kalkardı. Bir dosya
yükleme kutusunun kolaylığı bu bedele değmez.

### Bozuk bir eklenti uygulamayı düşürmez

Kanca çalıştırıcıları istisnayı yakalar, loglar ve devam eder:

- **Eylemde**: diğer dinleyiciler çalışmaya devam eder; risk kaydetme
  işlemi etkilenmez. Kanca zaten `COMMIT`'ten *sonra* çağrılıyor.
- **Filtrede**: patlayan adım atlanır, değer o ana kadarki hâliyle
  zincire devam eder — bozuk bir eklenti menüyü silemez.
- **Açılışta**: yüklenemeyen eklenti atlanır, uygulama açılır. Aksi
  hâlde bozuk bir eklenti, kendisini kapatmayı da imkânsız kılardı.

Hatalar sessizce yutulmaz: yönetim ekranında ve hata ayıklama araç
çubuğunda görünür, `app.log`'a yazılır.

### Filtre çıktısı güvenilmez veri sayılır

`nav.items` filtresinden dönen menü üçüncü taraf kodundan geliyor.
Basma döngüsü her alanı doğruluyor:

- `href` **site içi yol olmalı** (`/` ile başlar, `//` ile başlamaz).
  `javascript:alert(1)` bir XSS vektörüdür; `//baska.site` kullanıcıyı
  dışarı taşır.
- İkon sınıfı `bi-[a-z0-9-]+` kalıbına uymalı.
- Her metin `e()` ile kaçışlanır.

## Sonuçlar

Çekirdeği çatallamadan kuruma özel davranış eklenebiliyor. Yükseltme
yolu açık kalıyor.

Sol menü artık **veri olarak** kuruluyor (`$navItems` dizisi), sonra
basılıyor. Bu zaten daha iyi bir yapı: menü bir veri, bir dizi `echo`
değil.

Rapor kayıt defteri de filtreden geçiyor, yani bir eklenti kuruma özel
rapor tanımlayabiliyor.

## Kabul edilen maliyet

**Eklentiler güvenlik sınırı içindedir, dışında değil.** Bir eklentiyi
kurmak çekirdeğe kod eklemekle aynı güven kararıdır. Bu bir eksiklik
değil, PHP'de kaçınılmaz olan durumun açıkça söylenmesi.

**Kanca çağrıları çekirdeğe dağılmış durumda.** Şu an beş nokta var;
sayı arttıkça çekirdeği değiştirmek zorlaşır (her kanca bir sözleşme).
Bu yüzden kanca eklemek ucuz bir iş sayılmıyor: her yeni kanca
belgelenmeli ve adı kalıcı kabul edilmeli.

**Açık eklenti listesi `storage/plugins.json` içinde**, ayar
tablosunda değil. Sebebi: bu liste "hangi KOD yüklenecek" sorusunun
cevabı — bir görünüm tercihi değil, dağıtım yapılandırması;
`debug.flag` ile aynı aileden. Ayrıca `setting_save()` yeni anahtar
oluşturmuyor (bilinçli bir koruma), dosya bu kısıtı yeni bir şema
göçü gerektirmeden aşıyor.

## Değerlendirilen alternatifler

**Arayüzden ZIP yükleme (WordPress modeli):** En kullanışlısı ve en
tehlikelisi. Yukarıda anlatılan sebeple elendi.

**Sınıf tabanlı eklenti arayüzü (`interface PluginInterface`):**
Kod tabanı prosedürel (ADR-0001); tek bir yerde sınıf hiyerarşisi
kurmak tutarsızlık olurdu. Kancalar fonksiyonel biçimde de aynı işi
görüyor.

**Olay kuyruğu / mesajlaşma:** Eklentiyi ayrı bir süreçte çalıştırmak
gerçek bir yalıtım sağlardı. Tek sunuculuk, bağımlılıksız kurulum
hedefiyle çelişiyor (ADR-0001). Uygulama büyürse yeniden
değerlendirilmeli.

**Yalnızca yapılandırma (kod yok):** Rapor tanımı ve menü girişi JSON
ile eklenebilirdi ve güvenli olurdu — ama "risk oluşturulunca dış
sisteme bildir" gibi asıl talebi karşılamıyordu.

## İlgili

- Testler: `tests/Unit/HookTest.php` (17), `tests/Unit/PluginDiscoveryTest.php` (18), HTTP testi (36)
- Örnek eklenti: `plugins/ornek-kayit-defteri/`
- ADR-0001: Çerçeve kullanılmadı
