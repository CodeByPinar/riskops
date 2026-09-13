<?php
declare(strict_types=1);

namespace RiskOps\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Denetim kaydının fark hesabı ve alan gizleme.
 *
 * Denetim kaydı değiştirilemez (append-only) ve bir uyuşmazlıkta
 * kanıt olarak okunur. İki güvencesi var:
 *   1. Yalnızca GERÇEKTEN değişen alanları yazar - her güncellemede
 *      tüm satırı yazmak kaydı okunmaz hâle getirir.
 *   2. Parola gibi alanları ASLA yazmaz.
 */
#[CoversFunction('audit_diff')]
#[CoversFunction('audit_json')]
final class AuditDiffTest extends TestCase
{
    #[Test]
    public function yalnizca_degisen_alan_yakalanir(): void
    {
        /* audit_diff() İKİ dizi döndürür: [önceki, sonraki]. Denetim
           kaydı ikisini de saklar - "ne oldu" sorusunun cevabı için
           yalnızca yeni değer yetmez, eskisi de gerekir. */
        [$old, $new] = audit_diff(
            ['title' => 'Eski', 'status' => 'Open', 'owner_id' => 3],
            ['title' => 'Yeni', 'status' => 'Open', 'owner_id' => 3]
        );

        self::assertSame(['title' => 'Eski'], $old);
        self::assertSame(['title' => 'Yeni'], $new);
    }

    #[Test]
    public function hicbir_sey_degismediyse_bos_doner(): void
    {
        /* Boş fark = denetim kaydına satır yazma. "Kaydet"e basıp
           hiçbir şey değiştirmemek bir olay değildir. */
        $row = ['title' => 'Aynı', 'status' => 'Open'];

        self::assertSame([[], []], audit_diff($row, $row));
    }

    #[Test]
    public function zaman_damgalari_varsayilan_olarak_gormezden_gelinir(): void
    {
        /* updated_at her UPDATE'te değişir; farkta görünmesi gürültüdür. */
        [$old, $new] = audit_diff(
            ['title' => 'Aynı', 'updated_at' => '2026-01-01 10:00:00'],
            ['title' => 'Aynı', 'updated_at' => '2026-01-02 11:00:00']
        );

        self::assertSame([], $old);
        self::assertSame([], $new);
    }

    #[Test]
    public function gormezden_gelinecek_alanlar_ozellestirilebilir(): void
    {
        [$old, $new] = audit_diff(
            ['title' => 'Aynı', 'view_count' => 1],
            ['title' => 'Aynı', 'view_count' => 2],
            ['view_count']
        );

        self::assertSame([], $old);
        self::assertSame([], $new);
    }

    #[Test]
    public function yeni_eklenen_alan_farkta_gorunur(): void
    {
        [$old, $new] = audit_diff(
            ['title' => 'Risk'],
            ['title' => 'Risk', 'owner_id' => 7]
        );

        self::assertSame(['owner_id' => null], $old, 'önceki değer null olmalı');
        self::assertSame(['owner_id' => 7], $new);
    }

    #[Test]
    public function null_ile_bos_metin_ayni_sayilir(): void
    {
        /* Karşılaştırma metne çevirerek yapılıyor ((string)null === '').
           Sonuç: "boş bırakıldı" ile "hiç doldurulmamıştı" denetim
           kaydında ayrılmaz.

           BU BİLİNÇLİ BİR ÖDÜNDÜR: alternatifi katı karşılaştırma
           olurdu, o zaman da MySQL'den string dönen "3" ile formdan
           gelen int 3 her güncellemede sahte bir fark üretirdi -
           denetim kaydı gürültüden okunamaz hâle gelirdi. Test bu
           davranışı kilitliyor ki sessizce değişmesin. */
        [$old, $new] = audit_diff(['note' => null], ['note' => '']);

        self::assertSame([], $old);
        self::assertSame([], $new);
    }

    #[Test]
    public function tur_farki_sahte_degisiklik_uretmez(): void
    {
        /* MySQL int kolonları PHP int döndürür, form verisi string
           gelir. Katı karşılaştırma her kaydetmede "değişti" derdi. */
        [$old, $new] = audit_diff(['likelihood' => 3], ['likelihood' => '3']);

        self::assertSame([], $old);
        self::assertSame([], $new);
    }

    #[Test]
    public function sonraki_dizide_olmayan_alan_farkta_gorunmez(): void
    {
        /* audit_diff yalnızca $after anahtarlarını gezer. Kısmi bir
           güncelleme (yalnızca değişen alanları içeren dizi) gönderen
           çağıran, kalan alanların "silindi" sanılmasından korunmuş
           olur. */
        [$old, $new] = audit_diff(
            ['title' => 'Risk', 'status' => 'Open'],
            ['title' => 'Risk']
        );

        self::assertSame([], $old);
        self::assertSame([], $new);
    }

    #[Test]
    public function parola_alanlari_json_ciktisinda_maskelenir(): void
    {
        $json = audit_json([
            'email'    => 'a@b.c',
            'password' => 'GizliParola1',
        ]);

        self::assertStringNotContainsString('GizliParola1', $json);
        self::assertStringContainsString('a@b.c', $json);
    }

    #[Test]
    public function json_ciktisi_turkce_karakterleri_bozmaz(): void
    {
        /* Denetim kaydı insan tarafından okunuyor; ç dizileri
           okunabilirliği bitirir. */
        $json = audit_json(['title' => 'Yetkisiz erişim']);

        self::assertStringContainsString('Yetkisiz erişim', $json);
    }
}
