<?php
declare(strict_types=1);

namespace RiskOps\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Hata ayıklama araç çubuğunun maskelemesi.
 *
 * Araç çubuğu $_POST ve $_SESSION içeriğini tarayıcıya basar; oralarda
 * parola, CSRF jetonu ve oturum kimliği bulunur. Maskeleme bu yüzden
 * bir kolaylık değil, bir güvenlik sınırıdır ve regresyona karşı
 * kilitlenmesi gerekir.
 *
 * (tools/debug_test.php aynı kuralları uçtan uca da sınıyor; buradaki
 * testler hızlı geri bildirim ve sınır durumları için.)
 */
#[CoversFunction('debug_mask_key')]
#[CoversFunction('debug_mask_array')]
#[CoversFunction('debug_export')]
final class DebugMaskingTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function secretKeys(): array
    {
        $keys = [
            'password', 'user_password', 'password_confirm', 'password_hash',
            'parola', 'yeni_parola', 'eski_parola',
            'csrf_token', '_csrf', '_token_csrf',
            'api_key', 'apiKey', 'API-KEY',
            'AUTHORIZATION', 'Cookie', 'PHPSESSID', 'session_id',
            'secret', 'client_secret', 'salt', 'private_key', 'db_credentials',
        ];

        return array_combine($keys, array_map(static fn ($k) => [$k], $keys));
    }

    #[Test]
    #[DataProvider('secretKeys')]
    public function sir_tasiyabilecek_anahtarlar_maskelenir(string $key): void
    {
        self::assertTrue(debug_mask_key($key), $key . ' maskelenmeli');
    }

    /** @return array<string, array{0: string}> */
    public static function innocentKeys(): array
    {
        $keys = [
            'title', 'description', 'risk_id', 'email', 'department_id',
            'status', 'severity', 'likelihood', 'impact', 'page', 'sort',
            'owner_id', 'due_date', 'category', 'locale',
        ];

        return array_combine($keys, array_map(static fn ($k) => [$k], $keys));
    }

    #[Test]
    #[DataProvider('innocentKeys')]
    public function sirasan_alanlar_maskelenmez(string $key): void
    {
        /* Aşırı maskeleme aracı körleştirir: "neden bu filtre çalışmadı"
           sorusunu cevaplamak için $_GET içeriğini GÖRMEK gerekir. */
        self::assertFalse(debug_mask_key($key), $key . ' maskelenmemeli');
    }

    #[Test]
    public function ic_ice_dizilerde_de_maskelenir(): void
    {
        $masked = debug_mask_array([
            'form' => [
                'email'    => 'a@b.c',
                'password' => 'GizliParola1',
                'derin'    => ['csrf_token' => 'abc123def456'],
            ],
        ]);

        self::assertSame('a@b.c', $masked['form']['email']);
        self::assertSame('***', $masked['form']['password']);
        self::assertSame('***', $masked['form']['derin']['csrf_token']);
    }

    #[Test]
    public function bool_ve_bayrak_degerleri_maskelenmez(): void
    {
        /* must_change_password bir bayraktır, sır değil. Maskelemek
           "neden parola değiştirme ekranına düşüyorum" sorusunu araç
           çubuğundan cevaplanamaz hâle getiriyordu. */
        $masked = debug_mask_array([
            'must_change_password' => true,
            'password_expired'     => false,
            'has_password'         => 1,
            'password_set'         => 0,
        ]);

        self::assertTrue($masked['must_change_password']);
        self::assertFalse($masked['password_expired']);
        self::assertSame(1, $masked['has_password']);
        self::assertSame(0, $masked['password_set']);
    }

    #[Test]
    public function bayrak_disi_sayilar_yine_maskelenir(): void
    {
        /* 0/1 bir bayraktır; 4 haneli bir sayı PIN olabilir. */
        $masked = debug_mask_array(['password' => 1234, 'secret' => 99]);

        self::assertSame('***', $masked['password']);
        self::assertSame('***', $masked['secret']);
    }

    #[Test]
    public function nesneler_degerlerini_sizdirmaz(): void
    {
        $masked = debug_mask_array(['pdo' => new \stdClass()]);

        self::assertSame('(stdClass)', $masked['pdo']);
    }

    #[Test]
    public function derinlik_siniri_sonsuz_donguyu_engeller(): void
    {
        $deep = 'en dipteki deger';
        for ($i = 0; $i < 12; $i++) {
            $deep = ['katman' => $deep];
        }

        $masked = debug_mask_array($deep);

        self::assertIsArray($masked);
        self::assertStringNotContainsString(
            'en dipteki deger',
            json_encode($masked, JSON_UNESCAPED_UNICODE) ?: '',
            'derinlik sınırı aşılmış'
        );
    }

    #[Test]
    public function export_ciktisinda_maskelenen_deger_gorunmez(): void
    {
        $out = debug_export([
            'user' => ['name' => 'Ada', 'password' => 'CokGizli123'],
        ]);

        self::assertStringContainsString('Ada', $out);
        self::assertStringNotContainsString('CokGizli123', $out);
        self::assertStringContainsString('***', $out);
    }

    #[Test]
    public function uzun_metinler_kisaltilir(): void
    {
        /* Araç çubuğuna 2 MB'lık bir alan gelirse sayfa kullanılamaz
           hâle gelir. */
        $out = debug_export(str_repeat('x', 5000));

        self::assertLessThan(2200, mb_strlen($out));
        self::assertStringEndsWith('…"', $out);
    }

    #[Test]
    public function skaler_turler_okunur_bicimde_doner(): void
    {
        self::assertSame('null', debug_export(null));
        self::assertSame('true', debug_export(true));
        self::assertSame('false', debug_export(false));
        self::assertSame('42', debug_export(42));
        self::assertSame('"metin"', debug_export('metin'));
    }
}
