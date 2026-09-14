<?php

declare(strict_types=1);

namespace RiskOps\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Kanca dağıtıcısı.
 *
 * Eklenti sisteminin çekirdeği burası. Sınanan üç güvence:
 *
 *   1. SIRALAMA belirli  - öncelik küçükten büyüğe
 *   2. FİLTRE zinciri    - değer dinleyiciden dinleyiciye geçer
 *   3. HATA YALITIMI     - bozuk bir eklenti isteği kesmez
 *
 * Üçüncüsü en önemlisi: eklenti kodu üçüncü taraftır ve hata
 * yapacağı varsayılmalıdır. Bir eklentinin istisnası risk kaydetmeyi
 * engelliyorsa, eklenti sistemi bir özellik değil bir yüktür.
 */
#[CoversFunction('hook_add')]
#[CoversFunction('hook_do')]
#[CoversFunction('hook_filter')]
#[CoversFunction('hook_listeners')]
#[CoversFunction('hook_has')]
final class HookTest extends TestCase
{
    protected function setUp(): void
    {
        /* Her test temiz kayıt defteriyle başlasın; testler arası
           sızıntı sıralama iddialarını anlamsız kılar. */
        $state = &plugins_state();
        $state['listeners'] = [];
        $state['errors']    = [];
    }

    protected function tearDown(): void
    {
        $state = &plugins_state();
        $state['listeners'] = [];
        $state['errors']    = [];
    }

    /* ------------------------------------------------------- temel */

    #[Test]
    public function dinleyicisi_olmayan_kanca_sessizdir(): void
    {
        self::assertFalse(hook_has('hic.kimse'));
        self::assertSame([], hook_listeners('hic.kimse'));

        /* Çağrılabilir olmalı ve hiçbir şey yapmamalı. */
        hook_do('hic.kimse', 1, 2, 3);

        self::assertSame([], plugins_errors());
    }

    #[Test]
    public function filtre_dinleyicisiz_degeri_oldugu_gibi_dondurur(): void
    {
        self::assertSame('deger', hook_filter('hic.kimse', 'deger'));
        self::assertSame([1, 2], hook_filter('hic.kimse', [1, 2]));
        self::assertNull(hook_filter('hic.kimse', null));
    }

    #[Test]
    public function eklenen_dinleyici_calisir(): void
    {
        $calisti = false;

        hook_add('test.olay', static function () use (&$calisti): void {
            $calisti = true;
        });

        self::assertTrue(hook_has('test.olay'));
        hook_do('test.olay');
        self::assertTrue($calisti);
    }

    #[Test]
    public function eylem_kancasina_argumanlar_gecer(): void
    {
        $alinan = null;

        hook_add('test.olay', static function (int $id, array $veri) use (&$alinan): void {
            $alinan = [$id, $veri];
        });

        hook_do('test.olay', 42, ['a' => 1]);

        self::assertSame([42, ['a' => 1]], $alinan);
    }

    /* --------------------------------------------------- sıralama */

    #[Test]
    public function oncelik_kucukten_buyuge_calisir(): void
    {
        $sira = [];

        hook_add('test.sira', static function () use (&$sira): void {
            $sira[] = 'ucuncu';
        }, 30);
        hook_add('test.sira', static function () use (&$sira): void {
            $sira[] = 'birinci';
        }, 5);
        hook_add('test.sira', static function () use (&$sira): void {
            $sira[] = 'ikinci';
        }, 10);

        hook_do('test.sira');

        self::assertSame(['birinci', 'ikinci', 'ucuncu'], $sira);
    }

    #[Test]
    public function ayni_oncelikte_ekleme_sirasi_korunur(): void
    {
        $sira = [];

        hook_add('test.sira', static function () use (&$sira): void {
            $sira[] = 'a';
        }, 10);
        hook_add('test.sira', static function () use (&$sira): void {
            $sira[] = 'b';
        }, 10);
        hook_add('test.sira', static function () use (&$sira): void {
            $sira[] = 'c';
        }, 10);

        hook_do('test.sira');

        self::assertSame(['a', 'b', 'c'], $sira);
    }

    #[Test]
    public function varsayilan_oncelik_ondur(): void
    {
        $sira = [];

        hook_add('test.sira', static function () use (&$sira): void {
            $sira[] = 'varsayilan';
        });
        hook_add('test.sira', static function () use (&$sira): void {
            $sira[] = 'once';
        }, 9);
        hook_add('test.sira', static function () use (&$sira): void {
            $sira[] = 'sonra';
        }, 11);

        hook_do('test.sira');

        self::assertSame(['once', 'varsayilan', 'sonra'], $sira);
    }

    /* ----------------------------------------------------- filtre */

    #[Test]
    public function filtre_degeri_zincirden_gecirir(): void
    {
        hook_add('test.filtre', static fn (int $n): int => $n + 1, 10);
        hook_add('test.filtre', static fn (int $n): int => $n * 2, 20);

        /* (1 + 1) * 2 = 4 — sıralama sonucu değiştirir. */
        self::assertSame(4, hook_filter('test.filtre', 1));
    }

    #[Test]
    public function filtreye_ek_argumanlar_gecer(): void
    {
        hook_add('test.filtre', static fn (string $v, string $ek): string => $v . $ek);

        self::assertSame('ab', hook_filter('test.filtre', 'a', 'b'));
    }

    /* ---------------------------------------------- hata yalıtımı */

    #[Test]
    public function eylemdeki_istisna_diger_dinleyicileri_engellemez(): void
    {
        /* Asıl güvence: bozuk bir eklenti, sağlam olanı da susturmamalı. */
        $sonrakiCalisti = false;

        hook_add('test.olay', static function (): void {
            throw new RuntimeException('bozuk eklenti');
        }, 10);

        hook_add('test.olay', static function () use (&$sonrakiCalisti): void {
            $sonrakiCalisti = true;
        }, 20);

        hook_do('test.olay');

        self::assertTrue($sonrakiCalisti, 'ikinci dinleyici çalışmadı');
    }

    #[Test]
    public function eylemdeki_istisna_disari_sizmaz(): void
    {
        /* hook_do risk kaydetmenin ortasında çağrılıyor; istisna dışarı
           sızarsa bir eklenti risk oluşturmayı engelleyebilirdi. */
        hook_add('test.olay', static function (): void {
            throw new RuntimeException('bozuk eklenti');
        });

        hook_do('test.olay');

        self::assertCount(1, plugins_errors());
        self::assertStringContainsString('bozuk eklenti', plugins_errors()[0]['message']);
    }

    #[Test]
    public function filtredeki_istisna_degeri_korur(): void
    {
        hook_add('test.filtre', static fn (string $v): string => $v . '-bir', 10);
        hook_add('test.filtre', static function (string $v): string {
            throw new RuntimeException('bozuk');
        }, 20);
        hook_add('test.filtre', static fn (string $v): string => $v . '-uc', 30);

        /* Patlayan adım atlanır; değer o ana kadarki hâliyle devam eder. */
        self::assertSame('a-bir-uc', hook_filter('test.filtre', 'a'));
    }

    #[Test]
    public function hata_kaydi_kancayi_ve_yeri_tasir(): void
    {
        hook_add('test.olay', static function (): void {
            throw new RuntimeException('ayrinti');
        });

        hook_do('test.olay');

        $hata = plugins_errors()[0];
        self::assertSame('test.olay', $hata['hook']);
        self::assertStringContainsString('RuntimeException', $hata['message']);
        self::assertStringContainsString('HookTest.php', $hata['file']);
    }

    /* ------------------------------------------------------ harita */

    #[Test]
    public function harita_dinleyici_sayilarini_verir(): void
    {
        hook_add('a.olay', static fn () => null);
        hook_add('a.olay', static fn () => null, 20);
        hook_add('b.olay', static fn () => null);

        self::assertSame(['a.olay' => 2, 'b.olay' => 1], hook_map());
    }
}
