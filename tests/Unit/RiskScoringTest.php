<?php
declare(strict_types=1);

namespace RiskOps\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Risk skoru ve seviye eşikleri.
 *
 * Skor veritabanında GENERATED kolondur; seviye DEĞİLDİR. Sebebi:
 * eşikler Ayarlar ekranından değiştirilebiliyor ve değiştiğinde tüm
 * kayıtların yeniden etiketlenmesi gerekiyor. Bu testler o ayrımın
 * iki tarafını da sınıyor.
 */
#[CoversFunction('risk_score')]
#[CoversFunction('severity_from_score')]
#[CoversFunction('is_overdue')]
final class RiskScoringTest extends TestCase
{
    protected function tearDown(): void
    {
        riskops_test_settings_reset();
    }

    /* ---------------------------------------------------------- skor */

    #[Test]
    public function skor_olasilik_carpi_etkidir(): void
    {
        self::assertSame(1, risk_score(1, 1));
        self::assertSame(12, risk_score(3, 4));
        self::assertSame(25, risk_score(5, 5));
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: int}>
     */
    public static function outOfRange(): array
    {
        return [
            'sifir olasilik'   => [0, 3, 3],     // 1'e kırpılır
            'negatif etki'     => [3, -2, 3],    // 1'e kırpılır
            'asiri olasilik'   => [99, 2, 10],   // 5'e kırpılır
            'ikisi de asiri'   => [99, 99, 25],
            'ikisi de negatif' => [-1, -1, 1],
        ];
    }

    #[Test]
    #[DataProvider('outOfRange')]
    public function aralik_disi_degerler_kirpilir(int $l, int $i, int $expected): void
    {
        /* 5x5 matris dışına çıkan bir skor, matris görünümünde yeri
           olmayan bir hücre demektir. Hesaplama aşamasında kırpmak,
           sonradan "bu risk neden matriste yok" sorusunu engeller. */
        self::assertSame($expected, risk_score($l, $i));
    }

    /* -------------------------------------------------------- seviye */

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function thresholdBoundaries(): array
    {
        return [
            'en dusuk'        => [1, 'Low'],
            'Low ust sinir'   => [4, 'Low'],
            'Medium alt'      => [5, 'Medium'],
            'Medium ust'      => [9, 'Medium'],
            'High alt'        => [10, 'High'],
            'High ust'        => [16, 'High'],
            'Critical alt'    => [17, 'Critical'],
            'Critical ust'    => [25, 'Critical'],
        ];
    }

    #[Test]
    #[DataProvider('thresholdBoundaries')]
    public function varsayilan_esikler_sinirlarda_dogru(int $score, string $expected): void
    {
        self::assertSame($expected, severity_from_score($score));
    }

    #[Test]
    public function esikler_ayarlardan_degistirilebilir(): void
    {
        riskops_test_settings(['severity_thresholds' => [
            'Low'      => [1, 2],
            'Medium'   => [3, 6],
            'High'     => [7, 12],
            'Critical' => [13, 25],
        ]]);

        self::assertSame('Low', severity_from_score(2));
        self::assertSame('Medium', severity_from_score(3));
        self::assertSame('High', severity_from_score(12));
        self::assertSame('Critical', severity_from_score(13));
    }

    #[Test]
    public function bozuk_esik_ayari_varsayilana_duser(): void
    {
        /* Ayar tablosundaki JSON bozulursa uygulama çökmemeli;
           varsayılan eşiklerle devam etmeli. */
        foreach ([[], 'bu dizi degil', null, ['Low' => 'bozuk']] as $broken) {
            riskops_test_settings(['severity_thresholds' => $broken]);

            $result = severity_from_score(12);
            self::assertContains(
                $result,
                ['Low', 'Medium', 'High', 'Critical'],
                'bozuk eşikle geçersiz seviye döndü: ' . var_export($broken, true)
            );
        }
    }

    #[Test]
    public function esik_araliklari_disinda_kalan_skor_guvenli_tarafa_yuvarlanir(): void
    {
        /* Eşikler elle düzenlenip boşluk bırakılmış olabilir. O boşluğa
           düşen bir skor sessizce "Low" görünmemeli - yüksekse yüksek
           sayılmalı. */
        riskops_test_settings(['severity_thresholds' => [
            'Low'      => [1, 4],
            'Critical' => [20, 25],
        ]]);

        self::assertSame('Critical', severity_from_score(18), 'boşluğa düşen yüksek skor');
        self::assertSame('Low', severity_from_score(10), 'boşluğa düşen düşük skor');
    }

    /* ------------------------------------------------------- gecikme */

    #[Test]
    public function acik_aksiyonun_gecmis_termini_gecikmedir(): void
    {
        $open = action_open_statuses();
        $past = date('Y-m-d', strtotime('-3 days'));

        self::assertTrue(is_overdue($past, $open[0] ?? 'Open', $open));
    }

    #[Test]
    public function kapali_aksiyon_gecikmis_sayilmaz(): void
    {
        /* Geçen ayın terminini kaçırmış ama tamamlanmış bir aksiyon
           "geciken" listesinde durmamalı; aksi hâlde liste hiç
           temizlenmez. */
        $past = date('Y-m-d', strtotime('-30 days'));

        self::assertFalse(is_overdue($past, 'Completed', action_open_statuses()));
        self::assertFalse(is_overdue($past, 'Cancelled', action_open_statuses()));
    }

    #[Test]
    public function termini_olmayan_aksiyon_gecikmis_sayilmaz(): void
    {
        self::assertFalse(is_overdue(null, 'Open', action_open_statuses()));
        self::assertFalse(is_overdue('', 'Open', action_open_statuses()));
    }

    #[Test]
    public function gelecek_termin_gecikmis_sayilmaz(): void
    {
        $future = date('Y-m-d', strtotime('+3 days'));

        self::assertFalse(is_overdue($future, 'Open', action_open_statuses()));
    }
}
