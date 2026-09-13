# ADR-0003: CSP'den `unsafe-inline` kaldırıldı

- **Durum:** Kabul edildi
- **Tarih:** 2026-09-12
- **Etkilediği yerler:** `includes/bootstrap.php`, `includes/functions.php`, `includes/ui.php`, `assets/js/`, `deploy/riskops.conf`

## Bağlam

İçerik Güvenliği Politikası (CSP), XSS'in etkisini sınırlayan son savunma
hattıdır. `unsafe-inline` açıkken bu hat yoktur: enjekte edilen bir
`<script>` ya da `onclick` çalışır.

Başlangıçta iki yerde de `unsafe-inline` vardı:

- `script-src` — satır içi `<script>` blokları ve `onclick` nitelikleri
- `style-src` — `style="width: 60%"` gibi dinamik ölçü nitelikleri

## Karar

İkisi de kaldırıldı. Sonuç politika:

```
default-src 'self'; img-src 'self' data:;
style-src 'self' 'nonce-<her istekte yeni>'; script-src 'self';
font-src 'self'; connect-src 'self'; form-action 'self';
frame-ancestors 'self'; base-uri 'self'; object-src 'none'
```

**Betikler:** Tüm JavaScript harici dosyada (`assets/js/app.js`,
`assets/js/debug.js`). Davranışlar `data-rk-*` nitelikleriyle bağlanıyor:

```html
<button data-rk-sidebar-toggle>…</button>
```

**Stiller:** Veritabanından gelen renkler (kullanıcının seçtiği kategori
rengi, herhangi bir hex olabilir) statik sınıfa çevrilemez. Bunlar
**nonce taşıyan tek bir `<style>` bloğunda** üretiliyor:

```php
echo style_block(category_color_styles());   // <style nonce="…">
```

Nonce her istekte yeniden üretilir; saldırganın enjekte ettiği bir blok
doğru nonce'u bilemez.

**CSP başlığı yalnızca PHP'den gönderilir.** Apache'deki kopya kaldırıldı.

## Sonuçlar

**Satır içi betik sessizce çalışmaz.** Yeni kod `<script>alert(1)</script>`
ya da `onclick="..."` eklerse tarayıcı bunu bloklar ve **hiçbir hata
görünmez** — yalnızca davranış olmaz. Bu tuzağı kapatmak için
`tools/check_conventions.php` satır içi betik ve stili denetliyor ve CI'da
çalışıyor.

**`style=""` niteliği artık hiç yok.** Nonce YALNIZCA `<style>` ve
`<script>` **bloklarında** işe yarar; `style=""` **niteliği** için
`'unsafe-hashes'` gerekirdi. Dinamik ölçüler CSS değişkenine, oradan da
utility sınıflarına taşındı (`.rk-w-0` … `.rk-w-100`).

## Kabul edilen maliyet

**Apache'de CSP kopyası tutulamaz.** Nonce her istekte değişir, Apache
bunu üretemez. Kopya bırakılsaydı tarayıcı iki başlığın **kesişimini**
uygulardı; Apache'ninki nonce içermediği için kesişim nonce'suz kalır ve
kendi stil bloklarımız bloklanırdı. Bunun bedeli: `mod_headers` yüklü
değilse statik dosyalar CSP'siz servis edilir.

**Diğer güvenlik başlıkları iki yerde de duruyor** (`X-Content-Type-Options`,
`X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`) çünkü onlar
sabittir ve Apache kopyası statik dosyaları da kapsar. İkisi **aynı**
kalmalı; biri değişirse diğeri de değişmeli.

## Değerlendirilen alternatifler

**`'unsafe-hashes'` + hash listesi:** Her `style=""` niteliğinin hash'ini
politikaya eklemek gerekirdi; dinamik değerlerde bu imkânsız.

**Satır içi stilleri CSS değişkenine taşımak:** Bu, ilk yol haritasında
"CSS değişkenlerine taşınmalı" diye yazılmıştı ama **sorunu çözmüyor**:
`style="--w: 50%"` hâlâ satır içi bir stil niteliğidir. Değişken yaklaşımı
yalnızca nonce'lu blokla birlikte işe yarıyor.

**`report-only` ile başlamak:** Uygulama tek operatörlü ve küçük; ihlalleri
toplayıp sonra sıkılaştırmak yerine doğrudan sıkı politika uygulanıp
kırılan yerler tarayıcı testiyle bulundu (25/25 ihlal yok).

## İlgili

- Denetim: `tools/check_conventions.php`
- `csp_nonce()`, `style_block()`: `includes/functions.php`
