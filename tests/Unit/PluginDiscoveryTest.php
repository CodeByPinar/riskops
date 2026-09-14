<?php

declare(strict_types=1);

namespace RiskOps\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Eklenti keşfi ve yol doğrulaması.
 *
 * Keşif, DİSKTEN OKUNAN veriyi kabul ediyor: bir eklentinin
 * plugin.php dosyası ne isterse döndürebilir. Bu testler o verinin
 * doğrulandığını kilitliyor - özellikle dosya yolu üreten alanları.
 *
 * NOT: Bunlar bir kum havuzu değil. Eklenti zaten kendi kodunu
 * çalıştırabiliyor (bkz. ADR-0010). Buradaki doğrulamanın amacı
 * yükleyicinin kendi dizininin dışına çıkmaması ve bozuk bir
 * bildirimin uygulamayı durdurmaması.
 */
#[CoversFunction('plugins_safe_path')]
#[CoversFunction('plugins_read_manifest')]
#[CoversFunction('plugins_discover')]
final class PluginDiscoveryTest extends TestCase
{
    /* --------------------------------------------- yol doğrulama */

    #[Test]
    public function eklenti_dizini_icindeki_yol_kabul_edilir(): void
    {
        $base = plugins_dir() . '/ornek-kayit-defteri';

        self::assertNotNull(plugins_safe_path($base, 'hooks.php'));
    }

    /** @return array<string, array{0: string}> */
    public static function escapingPaths(): array
    {
        return [
            'ust dizine cikis'     => ['../../includes/db.php'],
            'derin cikis'          => ['../../../../etc/passwd'],
            'mutlak yol'           => ['/etc/passwd'],
            'config dosyasi'       => ['../../config/database.php'],
        ];
    }

    #[Test]
    #[DataProvider('escapingPaths')]
    public function dizin_disina_cikan_yol_reddedilir(string $path): void
    {
        /* Bildirim eklentinin kendi kodu; oradan gelen yol dizin
           dışını gösterebilir. Yükleyici buna uymaz. */
        $base = plugins_dir() . '/ornek-kayit-defteri';

        self::assertNull(plugins_safe_path($base, $path), $path . ' kabul edildi');
    }

    #[Test]
    public function var_olmayan_yol_reddedilir(): void
    {
        $base = plugins_dir() . '/ornek-kayit-defteri';

        self::assertNull(plugins_safe_path($base, 'boyle-bir-dosya-yok.php'));
    }

    /* -------------------------------------------------- slug kuralı */

    /** @return array<string, array{0: string}> */
    public static function badSlugs(): array
    {
        return [
            'yol ayraci'     => ['../kotu'],
            'buyuk harf'     => ['Kotu'],
            'bosluk'         => ['kotu eklenti'],
            'nokta'          => ['.gizli'],
            'tire ile baslar' => ['-kotu'],
            'bos'            => [''],
        ];
    }

    #[Test]
    #[DataProvider('badSlugs')]
    public function gecersiz_slug_reddedilir(string $slug): void
    {
        /* Slug doğrudan dosya yolu üretiyor: plugins/<slug>/plugin.php */
        self::assertNull(
            plugins_read_manifest($slug, plugins_dir() . '/' . $slug),
            $slug . ' kabul edildi'
        );
    }

    #[Test]
    public function gecerli_slug_bicimi_kabul_edilir(): void
    {
        foreach (['a', 'eklenti', 'eklenti-adi', 'eklenti_adi', 'ek2'] as $slug) {
            self::assertMatchesRegularExpression('/^[a-z0-9][a-z0-9_-]{0,39}$/', $slug);
        }
    }

    /* ------------------------------------------------------- keşif */

    #[Test]
    public function ornek_eklenti_bulunur_ve_alanlari_tam(): void
    {
        $found = plugins_discover();

        self::assertArrayHasKey('ornek-kayit-defteri', $found);

        $plugin = $found['ornek-kayit-defteri'];
        foreach (['slug', 'name', 'version', 'path', 'hooks_file'] as $key) {
            self::assertArrayHasKey($key, $plugin);
        }

        self::assertSame('ornek-kayit-defteri', $plugin['slug']);
        self::assertNotSame('', $plugin['name']);
        self::assertNotNull($plugin['hooks_file'], 'kanca dosyası çözümlenemedi');
    }

    #[Test]
    public function bildirimdeki_slug_dizin_adiyla_ayni_olmali(): void
    {
        /* Farklı olsaydı iki eklenti aynı slug'ı iddia edip birbirini
           gizleyebilir; açık olan A, aslında B'nin kodunu çalıştırırdı. */
        $plugin = plugins_discover()['ornek-kayit-defteri'] ?? null;

        self::assertNotNull($plugin);
        self::assertSame('ornek-kayit-defteri', $plugin['slug']);
    }

    #[Test]
    public function kesif_sonuclari_slug_ile_anahtarlanir(): void
    {
        foreach (plugins_discover() as $key => $plugin) {
            self::assertSame($key, $plugin['slug']);
        }
    }

    /* ------------------------------------------------ açık listesi */

    #[Test]
    public function acik_listesi_her_zaman_liste_dondurur(): void
    {
        $enabled = plugins_enabled();

        self::assertIsList($enabled);
        foreach ($enabled as $slug) {
            self::assertIsString($slug);
            self::assertMatchesRegularExpression('/^[a-z0-9][a-z0-9_-]{0,39}$/', $slug);
        }
    }
}
