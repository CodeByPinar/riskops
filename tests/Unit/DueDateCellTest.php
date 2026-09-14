<?php

declare(strict_types=1);

namespace RiskOps\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Termin hücresinin gecikme vurgusu.
 *
 * BU TESTİN VAR OLMA SEBEBİ
 * -------------------------
 * due_date_cell() iki parametre alıyordu; risk ekranları ise ÜÇ
 * argümanla çağırıyordu. PHP, kullanıcı tanımlı bir fonksiyona fazla
 * argüman verilmesini HATA SAYMAZ - sessizce atar. Atılan argüman
 * risk_open_statuses() idi; is_overdue() de varsayılan olarak
 * action_open_statuses() listesine düşüyordu.
 *
 * İki listenin farkı tek bir durum: "Under Review". Sonuç, termini
 * geçmiş incelemedeki bir riskin risk listesinde, panelde, risk
 * detayında ve yönetici özetinde gecikmiş GÖRÜNMEMESİydi. Hiçbir uyarı
 * üretmiyor, yalnızca eksik vurgu olarak ortaya çıkıyordu.
 *
 * Hatayı statik çözümleme (PHPStan) buldu; gözle dört ekranda da
 * fark edilmemişti.
 */
#[CoversFunction('due_date_cell')]
final class DueDateCellTest extends TestCase
{
    private static function past(): string
    {
        return date('Y-m-d', strtotime('-10 days'));
    }

    private static function future(): string
    {
        return date('Y-m-d', strtotime('+10 days'));
    }

    private static function isOverdue(string $html): bool
    {
        return str_contains($html, 'gecikti');
    }

    /** @return array<string, array{0: string}> */
    public static function openRiskStatuses(): array
    {
        return [
            'Open'         => ['Open'],
            'Under Review' => ['Under Review'],
            'In Progress'  => ['In Progress'],
        ];
    }

    #[Test]
    #[DataProvider('openRiskStatuses')]
    public function acik_riskin_gecmis_termini_gecikmis_gorunur(string $status): void
    {
        $html = due_date_cell(self::past(), $status, risk_open_statuses());

        self::assertTrue(
            self::isOverdue($html),
            $status . ' durumundaki risk gecikmiş görünmeli'
        );
    }

    /** @return array<string, array{0: string}> */
    public static function closedRiskStatuses(): array
    {
        return [
            'Mitigated'   => ['Mitigated'],
            'Accepted'    => ['Accepted'],
            'Transferred' => ['Transferred'],
            'Closed'      => ['Closed'],
        ];
    }

    #[Test]
    #[DataProvider('closedRiskStatuses')]
    public function kapali_riskin_gecmis_termini_gecikme_sayilmaz(string $status): void
    {
        /* Kapatılmış bir kaydın geçmiş termini gecikme değildir;
           aksi hâlde "geciken riskler" listesi hiç temizlenmez. */
        $html = due_date_cell(self::past(), $status, risk_open_statuses());

        self::assertFalse(self::isOverdue($html), $status . ' gecikmiş sayılmamalı');
    }

    #[Test]
    public function ucuncu_arguman_sessizce_dusmuyor(): void
    {
        /* Asıl regresyon: iki listenin farkı "Under Review". Üçüncü
           argüman atılırsa bu durum aksiyon listesinde bulunmadığı için
           gecikmiş sayılmaz. */
        self::assertContains('Under Review', risk_open_statuses());
        self::assertNotContains('Under Review', action_open_statuses());

        $withList    = due_date_cell(self::past(), 'Under Review', risk_open_statuses());
        $withoutList = due_date_cell(self::past(), 'Under Review');

        self::assertTrue(
            self::isOverdue($withList),
            'risk listesi verildiğinde Under Review gecikmiş olmalı'
        );
        self::assertFalse(
            self::isOverdue($withoutList),
            'liste verilmediğinde aksiyon davranışı beklenir - ikisi AYNI olsaydı '
            . 'parametre hiç geçmiyor demektir'
        );
    }

    #[Test]
    public function aksiyon_ekranlari_iki_argumanla_calismaya_devam_eder(): void
    {
        /* Aksiyon ekranları üçüncü argümanı vermiyor ve vermemeli;
           varsayılan action_open_statuses() onların doğru listesi. */
        self::assertTrue(self::isOverdue(due_date_cell(self::past(), 'Open')));
        self::assertTrue(self::isOverdue(due_date_cell(self::past(), 'In Progress')));
        self::assertFalse(self::isOverdue(due_date_cell(self::past(), 'Completed')));
        self::assertFalse(self::isOverdue(due_date_cell(self::past(), 'Cancelled')));
    }

    #[Test]
    public function gelecek_termin_hicbir_durumda_gecikmis_degildir(): void
    {
        foreach (risk_statuses() as $status) {
            self::assertFalse(
                self::isOverdue(due_date_cell(self::future(), $status, risk_open_statuses())),
                $status
            );
        }
    }

    #[Test]
    public function termini_olmayan_kayit_tire_gosterir(): void
    {
        foreach ([null, ''] as $empty) {
            $html = due_date_cell($empty, 'Open', risk_open_statuses());

            self::assertStringContainsString('-', $html);
            self::assertFalse(self::isOverdue($html));
        }
    }

    #[Test]
    public function tarih_kacislanarak_basilir(): void
    {
        /* Termin veritabanından geliyor ama hücre HTML üretiyor;
           kaçışlama burada olmalı. */
        $html = due_date_cell(self::past(), 'Open', risk_open_statuses());

        self::assertStringNotContainsString('<script', $html);
    }
}
