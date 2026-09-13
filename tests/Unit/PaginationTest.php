<?php
declare(strict_types=1);

namespace RiskOps\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Sayfalama hesabı.
 *
 * Sayfa numarası kullanıcıdan gelir ve doğrudan SQL LIMIT/OFFSET
 * üretir. Uydurma bir sayfa numarası ile boş liste ya da negatif
 * OFFSET oluşmamalı.
 */
#[CoversFunction('paginate')]
final class PaginationTest extends TestCase
{
    #[Test]
    public function tipik_durum(): void
    {
        $p = paginate(total: 100, perPage: 25, currentPage: 2);

        self::assertSame(4, $p['pages']);
        self::assertSame(2, $p['current']);
        self::assertSame(25, $p['offset']);
    }

    #[Test]
    public function kayit_yokken_tek_sayfa_vardir(): void
    {
        /* pages = 0 olsaydı "1 / 0" gibi bir arayüz çıkardı. */
        $p = paginate(total: 0, perPage: 25, currentPage: 1);

        self::assertSame(1, $p['pages']);
        self::assertSame(0, $p['offset']);
    }

    #[Test]
    public function son_sayfanin_otesi_son_sayfaya_kirpilir(): void
    {
        /* ?page=9999 yazan biri boş liste değil, son sayfayı görmeli. */
        $p = paginate(total: 30, perPage: 25, currentPage: 9999);

        self::assertSame(2, $p['current']);
        self::assertSame(25, $p['offset']);
    }

    #[Test]
    public function sifir_ve_negatif_sayfa_ilk_sayfaya_kirpilir(): void
    {
        /* Negatif OFFSET SQL hatası verirdi. */
        foreach ([0, -1, -500] as $page) {
            $p = paginate(total: 100, perPage: 25, currentPage: $page);

            self::assertSame(1, $p['current'], 'page=' . $page);
            self::assertSame(0, $p['offset'], 'page=' . $page);
        }
    }

    #[Test]
    public function tam_bolunen_toplam_fazladan_sayfa_uretmez(): void
    {
        $p = paginate(total: 50, perPage: 25, currentPage: 1);

        self::assertSame(2, $p['pages']);
    }

    #[Test]
    public function bir_fazla_kayit_yeni_sayfa_acar(): void
    {
        $p = paginate(total: 51, perPage: 25, currentPage: 1);

        self::assertSame(3, $p['pages']);
    }

    #[Test]
    public function goruntulenen_aralik_dogru_hesaplanir(): void
    {
        /* "100 kayıttan 26-50 arası" metni bu alanlardan üretiliyor. */
        $p = paginate(total: 100, perPage: 25, currentPage: 2);

        self::assertSame(26, $p['from']);
        self::assertSame(50, $p['to']);
    }

    #[Test]
    public function son_sayfada_to_toplami_asmaz(): void
    {
        $p = paginate(total: 30, perPage: 25, currentPage: 2);

        self::assertSame(26, $p['from']);
        self::assertSame(30, $p['to'], 'to, toplam kayıt sayısını aşmamalı');
    }

    #[Test]
    public function kayit_yokken_aralik_sifirdir(): void
    {
        $p = paginate(total: 0, perPage: 25, currentPage: 1);

        self::assertSame(0, $p['from']);
        self::assertSame(0, $p['to']);
        self::assertFalse($p['has_prev']);
        self::assertFalse($p['has_next']);
    }

    #[Test]
    public function sayfa_basina_sifir_kayit_istenirse_bire_kirpilir(): void
    {
        /* perPage=0 sıfıra bölme hatası verirdi. */
        $p = paginate(total: 10, perPage: 0, currentPage: 1);

        self::assertSame(1, $p['per_page']);
        self::assertSame(10, $p['pages']);
    }

    #[Test]
    public function offset_hicbir_zaman_negatif_degildir(): void
    {
        foreach ([[0, 25, -3], [10, 25, 0], [0, 1, -1]] as [$total, $per, $page]) {
            self::assertGreaterThanOrEqual(
                0,
                paginate($total, $per, $page)['offset'],
                sprintf('total=%d per=%d page=%d', $total, $per, $page)
            );
        }
    }
}
