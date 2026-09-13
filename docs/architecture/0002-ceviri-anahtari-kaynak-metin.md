# ADR-0002: Çeviri anahtarı, Türkçe metnin kendisidir

- **Durum:** Kabul edildi
- **Tarih:** 2026-09-12
- **Etkilediği yerler:** `includes/i18n.php`, `lang/tr.php`, `lang/en.php`, tüm şablonlar

## Bağlam

Arayüz Türkçe yazılmıştı ve 900'den fazla satır görünen metin içeriyordu.
İngilizce desteği eklenecekti. İki yol vardı:

- **Soyut anahtar:** `t('risks.new')` — sözlükte `risks.new => 'Yeni Risk'`
- **Kaynak metin anahtar:** `t('Yeni Risk')` — Türkçe sözlük boş, İngilizce
  sözlükte `'Yeni Risk' => 'New Risk'`

Belirleyici soru şuydu: **çeviri yarım kaldığında ne olur?** Kısmi
çevirinin geçici bir durum değil, kalıcı bir durum olacağı baştan
belliydi — 900 metin tek seferde çevrilmiyor.

## Karar

Anahtar, Türkçe kaynak metnin kendisidir. `lang/tr.php` **bilerek boş**
döner: anahtar bulunamazsa anahtarın kendisi basılır, o da zaten doğru
Türkçedir.

```php
function t(string $text, array $vars = []): string
{
    $dict = i18n_dictionary();
    $out  = $dict[$text] ?? $text;     // bulunamazsa kaynak metin
    ...
}
```

## Sonuçlar

**Eksik çeviri sayfayı bozmaz.** Soyut anahtarla, sarmalanmamış bir metin
ya boş görünür ya da ekrana `risks.new` basardı. Burada çevrilmemiş metin
doğru Türkçesiyle görünür — kullanıcı bir eksiklik fark etmez, yalnızca o
metin İngilizce olmaz.

**Kod okunur kalır.** `t('Silinen Riskler')` okunduğunda ne yazdığı
bellidir; `t('risks.deleted.title')` için sözlüğe bakmak gerekir. Bu,
şablonları gözden geçirirken ölçülebilir bir fark yaratıyor.

**Kısmi çeviri kullanılabilir bir durumdur**, kırık bir durum değil. Şu an
kenar çubuğu, giriş ekranı, profil, silinen riskler, yorum/ek katmanı ve
hata ayıklama ekranı çevrilmiş; risk listesi ve raporlar çevrilmemiş
durumda ve uygulama iki dilde de sorunsuz çalışıyor.

## Kabul edilen maliyet

**Türkçe metin değişirse çevirisi düşer.** `'Yeni Risk'` → `'Yeni Risk Ekle'`
yapıldığında İngilizcesi bulunamaz ve metin Türkçeye döner. Bu bilerek
kabul edildi: sessizce **yanlış** çeviri göstermektense doğru Türkçe
göstermek yeğdir.

**Aynı kelime farklı bağlamlarda çakışır.** Giriş ekranındaki
"Daha Güvenli" hem başlıkta hem rozette geçiyordu ve iki farklı İngilizce
karşılığı gerekiyordu; PHP dizisinde ikinci tanım birincisini sessizce
eziyordu. Çözüm, o metinleri tam ifade olarak anahtarlamaktı
(`'Daha Güvenli Operasyonlar'`). Yinelenen anahtarlar bir betikle
denetleniyor.

## Değerlendirilen alternatifler

**gettext (.po/.mo):** Olgun araç zinciri var ama derleme adımı, sunucuda
`locale` kurulumu ve ayrı bir editör gerektiriyor. "Sıfır harici bağımlılık"
hedefiyle çelişiyordu.

**Veritabanında çeviri tablosu:** Her metin için bir sorgu ya da tam
önbellek gerekir; çeviri düzenlemek için arayüz yazmak gerekir. Bu boyuttaki
bir uygulama için kazancı maliyetini karşılamıyor.

**Soyut anahtar:** Yukarıdaki "eksik çeviri" davranışı yüzünden elendi.

## İlgili

- Test: `tests/Unit/I18nTest.php`
- Durum tablosu: `README.md` → "Çok dilli arayüz: durum"
