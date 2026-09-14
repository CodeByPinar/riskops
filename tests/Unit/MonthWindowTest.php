<?php

declare(strict_types=1);

namespace RiskOps\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Panel ve rapor grafiklerinin ay penceresi.
 *
 * BU TESTİN VAR OLMA SEBEBİ
 * -------------------------
 * Pencere `strtotime("-$i months")` ile üretiliyordu. O ifade AYIN
 * GÜNÜNÜ korur: 31 Mart'ta "-1 month" 31 Şubat'tır, PHP bunu 3 Mart'a
 * taşır. Sonuç: ayın 29-31'inde grafikte bir ay tekrarlanır, bir ay
 * hiç görünmez. Hata yalnızca uzun ayların sonunda ortaya çıktığı için
 * elle fark edilmesi zordur - testi bu yüzden tarih sabitlenerek
 * yazılıyor.
 *
 * recent_months() ayın 1'ine sabitlenerek bu tuzağı kapatır.
 */
#[CoversFunction('recent_months')]
final class MonthWindowTest extends TestCase
{
    #[Test]
    public function istenen_sayida_ay_doner(): void
    {
        foreach ([1, 6, 12, 24] as $count) {
            self::assertCount($count, recent_months($count));
        }
    }

    #[Test]
    public function aylar_benzersiz_ve_artan_siradadir(): void
    {
        $months = recent_months(12);

        self::assertSame($months, array_values(array_unique($months)), 'tekrarlanan ay var');

        $sorted = $months;
        sort($sorted);
        self::assertSame($sorted, $months, 'aylar artan sırada değil');
    }

    /**
     * Ayın 31'inde üretilen pencere de kusursuz olmalı.
     *
     * 31 Mart 2026 için beklenen son 6 ay:
     *   2025-10, 2025-11, 2025-12, 2026-01, 2026-02, 2026-03
     * Hatalı sürüm 2026-02 yerine ikinci bir 2026-03 üretiyordu.
     */
    #[Test]
    public function ay_sonunda_da_hicbir_ay_atlanmaz(): void
    {
        $base = mktime(12, 0, 0, 3, 31, 2026);
        self::assertIsInt($base);

        self::assertSame(
            ['2025-10', '2025-11', '2025-12', '2026-01', '2026-02', '2026-03'],
            recent_months(6, $base)
        );
    }

    #[Test]
    public function ayin_29_30_ve_31_inde_ayni_sekilde_calisir(): void
    {
        /* Şubat'ın kısalığı 29, 30 ve 31'inde farklı biçimlerde
           tetikliyordu; üçünü de sınıyoruz. */
        foreach ([29, 30, 31] as $day) {
            $base = mktime(12, 0, 0, 3, $day, 2026);
            self::assertIsInt($base);

            $months = recent_months(6, $base);

            self::assertSame(
                ['2025-10', '2025-11', '2025-12', '2026-01', '2026-02', '2026-03'],
                $months,
                "mart ayının {$day}. günü"
            );
        }
    }

    #[Test]
    public function yil_siniri_dogru_asilir(): void
    {
        $base = mktime(12, 0, 0, 1, 15, 2026);
        self::assertIsInt($base);

        self::assertSame(
            ['2025-08', '2025-09', '2025-10', '2025-11', '2025-12', '2026-01'],
            recent_months(6, $base)
        );
    }

    #[Test]
    public function son_ay_her_zaman_icinde_bulunulan_aydir(): void
    {
        $months = recent_months(12);

        self::assertSame(date('Y-m'), end($months));
    }

    #[Test]
    public function her_deger_yyyy_mm_bicimindedir(): void
    {
        /* Değerler hem grafiğin x ekseninde etiket, hem de SQL'de
           tarih aralığı olarak kullanılıyor; biçim sabit olmalı. */
        foreach (recent_months(12) as $month) {
            self::assertMatchesRegularExpression('/^\d{4}-(0[1-9]|1[0-2])$/', $month);
        }
    }

    #[Test]
    public function pencere_boyutu_makul_sinirlar_icinde_tutulur(): void
    {
        /* recent_months(100000) bellek tüketen bir dizi üretmemeli. */
        self::assertCount(1, recent_months(0));
        self::assertCount(1, recent_months(-5));
        self::assertCount(120, recent_months(99999));
    }
}
