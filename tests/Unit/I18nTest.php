<?php
declare(strict_types=1);

namespace RiskOps\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Çeviri katmanı.
 *
 * Tasarım kararı: ÇEVİRİ ANAHTARI TÜRKÇE METNİN KENDİSİDİR
 * (bkz. docs/architecture/0002-ceviri-anahtari-kaynak-metin.md).
 * Bunun en önemli sonucu, eksik çevirinin sayfayı BOZMAMASIdır -
 * metin doğru Türkçesiyle görünür. Aşağıdaki testler o güvenceyi
 * kilitliyor.
 */
#[CoversFunction('t')]
#[CoversFunction('te')]
#[CoversFunction('locale')]
#[CoversFunction('i18n_locales')]
final class I18nTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        /* locale() sonucu istek başına önbelleğe alınır; her test
           kendi senaryosunu kursun. */
        locale_reset();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        locale_reset();
        riskops_test_settings_reset();
    }

    #[Test]
    public function desteklenen_diller_kod_ve_ad_tasir(): void
    {
        $locales = i18n_locales();

        self::assertArrayHasKey('tr', $locales);
        self::assertArrayHasKey('en', $locales);

        foreach ($locales as $code => $name) {
            self::assertMatchesRegularExpression('/^[a-z]{2}$/', $code);
            self::assertNotSame('', trim($name), $code . ' için görünen ad yok');
        }
    }

    #[Test]
    public function sozlukte_olmayan_metin_oldugu_gibi_doner(): void
    {
        /* Bu davranış çeviri stratejisinin temel güvencesidir: kısmi
           çeviri kullanılabilir bir durumdur, kırık bir durum değil. */
        $_SESSION['locale'] = 'en';

        $uncevrilmis = 'Bu metin hiçbir sözlükte yok #' . random_int(1000, 9999);

        self::assertSame($uncevrilmis, t($uncevrilmis));
    }

    #[Test]
    public function turkce_sozluk_bos_olsa_da_metin_dogru_gorunur(): void
    {
        $_SESSION['locale'] = 'tr';

        self::assertSame('Yeni Risk', t('Yeni Risk'));
        self::assertSame('Silinen Riskler', t('Silinen Riskler'));
    }

    #[Test]
    public function ingilizce_sozluk_menu_metinlerini_cevirir(): void
    {
        $_SESSION['locale'] = 'en';

        self::assertSame('Dashboard', t('Panel'));
        self::assertSame('Risk Register', t('Risk Kaydı'));
        self::assertSame('Settings', t('Ayarlar'));
    }

    #[Test]
    public function adlandirilmis_yer_tutucular_degistirilir(): void
    {
        $_SESSION['locale'] = 'tr';

        self::assertSame(
            'Merhaba Ada',
            t('Merhaba :name', [':name' => 'Ada'])
        );
    }

    #[Test]
    public function sirali_argumanlar_sprintf_ile_islenir(): void
    {
        $_SESSION['locale'] = 'tr';

        self::assertSame('5 risk seçildi', t('%d risk seçildi', [5]));
    }

    #[Test]
    public function te_ciktisi_html_kacislanir(): void
    {
        /* te() şablonlarda doğrudan basılıyor; kaçışlama burada
           olmazsa çeviri sözlüğü bir XSS yüzeyine dönerdi. */
        $_SESSION['locale'] = 'tr';

        $out = te('<script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', $out);
        self::assertStringContainsString('&lt;script&gt;', $out);
    }

    #[Test]
    public function oturumdaki_secim_hesabin_tercihini_yener(): void
    {
        /* Kullanıcı kenar çubuğundan dili değiştirdiğinde o seçim bu
           oturum boyunca geçerlidir. */
        $_SESSION['user_locale'] = 'tr';
        $_SESSION['locale']      = 'en';

        self::assertSame('en', locale());
    }

    #[Test]
    public function gecersiz_dil_kodu_yok_sayilir(): void
    {
        /* $_SESSION['locale'] elle bozulmuş olabilir; sözlük dosyası
           yolu buradan üretildiği için beyaz liste dışına çıkılmamalı. */
        $_SESSION['locale'] = '../../etc/passwd';

        self::assertContains(locale(), array_keys(i18n_locales()));
    }
}
