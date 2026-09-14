<?php

declare(strict_types=1);

namespace RiskOps\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Giriş anında dil devri kuralı.
 *
 * BU TESTİN VAR OLMA SEBEBİ
 * -------------------------
 * Kullanıcının kayıtlı dil tercihi girişte hiç uygulanmıyordu:
 * authenticate.php sorgusu `locale` kolonunu SEÇİYOR, auth_start()
 * onu BEKLİYOR, ama ikisinin arasındaki açık dizi o alanı
 * taşımıyordu. Belirti sinsiydi - hesabında İngilizce seçili biri
 * giriş yapınca arayüz Türkçe açılıyor, kenar çubuğundan EN'e
 * basınca düzeliyor, ertesi gün yine Türkçe açılıyordu.
 *
 * Kural artık saf bir fonksiyonda (auth_locale_handoff) ve burada
 * kilitli: dört senaryonun dördü de doğrulanıyor.
 */
#[CoversFunction('auth_locale_handoff')]
final class AuthLocaleHandoffTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: ?string, 3: ?string}>
     *         hesap, ekran, beklenen oturum, beklenen kalıcı yazma
     */
    public static function scenarios(): array
    {
        return [
            // Hesabın tercihi varsa O kazanır; ekrandaki seçim silinir.
            // (Ortak bilgisayarda önceki kişinin seçimi benim ayarımı bastırmasın.)
            'hesap tr, ekranda en secilmis' => ['tr', 'en', null, null],
            'hesap en, ekranda tr secilmis' => ['en', 'tr', null, null],
            'hesap tr, ekranda da tr'       => ['tr', 'tr', null, null],

            // Hesapta tercih yoksa ekrandaki bilinçli seçim kalıcılaşır.
            'hesap yok, ekranda en'         => [null, 'en', 'en', 'en'],
            'hesap yok, ekranda tr'         => [null, 'tr', 'tr', 'tr'],

            // Karar verecek bir şey yok -> sistem varsayılanı geçerli.
            'ikisi de yok'                  => [null, null, null, null],

            // Geçersiz değerler yok sayılır (sözlükte olmayan dil kodu).
            'hesapta gecersiz kod'          => ['de', 'en', 'en', 'en'],
            'ekranda gecersiz kod'          => [null, 'de', null, null],
            'ikisi de gecersiz'             => ['zz', 'xx', null, null],
        ];
    }

    #[Test]
    #[DataProvider('scenarios')]
    public function hesabin_tercihi_ekran_secimini_yener(
        ?string $account,
        ?string $session,
        ?string $expectedSession,
        ?string $expectedPersist
    ): void {
        $result = auth_locale_handoff($account, $session);

        self::assertSame(
            $expectedSession,
            $result['session'],
            'oturuma yazılacak değer beklenenden farklı'
        );
        self::assertSame(
            $expectedPersist,
            $result['persist'],
            'hesaba kalıcı yazılacak değer beklenenden farklı'
        );
    }

    #[Test]
    public function donen_dizi_her_zaman_iki_anahtar_tasir(): void
    {
        foreach ([['tr', 'en'], [null, null], ['zz', 'en']] as [$a, $s]) {
            $result = auth_locale_handoff($a, $s);

            self::assertArrayHasKey('session', $result);
            self::assertArrayHasKey('persist', $result);
            self::assertCount(2, $result);
        }
    }

    #[Test]
    public function hesabin_tercihi_varken_asla_kalici_yazma_yapilmaz(): void
    {
        /* Giriş her yapıldığında users.locale'e yazmak gereksiz bir
           UPDATE olurdu; hesapta zaten değer var. */
        foreach (array_keys(i18n_locales()) as $account) {
            foreach ([null, 'tr', 'en', 'gecersiz'] as $session) {
                self::assertNull(
                    auth_locale_handoff($account, $session)['persist'],
                    "hesap={$account} ekran=" . var_export($session, true)
                );
            }
        }
    }

    #[Test]
    public function kalici_yazma_her_zaman_gecerli_bir_dil_kodudur(): void
    {
        $valid = array_keys(i18n_locales());

        foreach ([null, 'tr', 'en', 'de', '', 'TR'] as $session) {
            $persist = auth_locale_handoff(null, $session)['persist'];

            if ($persist !== null) {
                self::assertContains($persist, $valid, 'geçersiz kod kalıcılaştırıldı: ' . $persist);
            }
        }
    }
}
