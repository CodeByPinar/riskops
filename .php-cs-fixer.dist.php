<?php
declare(strict_types=1);

/**
 * RiskOps - Kod biçimi kuralları (php-cs-fixer)
 *
 * Çalıştırma:
 *     composer cs        # yalnızca rapor, dosyaya dokunmaz
 *     composer cs:fix    # düzelt
 *
 * NEDEN PSR-12 TABANLI AMA AYNEN DEĞİL
 * ------------------------------------
 * Amaç, var olan kod tabanını yeniden yazmak değil; biçimi bir
 * tartışma konusu olmaktan çıkarmak. Bu yüzden yalnızca "zaten öyle
 * yazılmış" kuralları açıyoruz. Kod tabanının okunabilirlik için
 * bilerek yaptığı şeyler (hizalanmış dizi değerleri, hizalanmış
 * atamalar, bölüm ayracı yorumlar) KORUNUR.
 *
 * Bir kural burada kapalıysa sebebi vardır; açmadan önce
 * `composer cs` çıktısına bakın - kaç dosyayı değiştireceğini
 * görmeden karar vermeyin.
 */

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude([
        'vendor',
        'storage',
        'assets/vendor',
        '.phpunit.cache',
    ]);

return (new PhpCsFixer\Config())
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRules([
        '@PSR12' => true,

        /* --- PSR-12 setinden GERİ ALINANLAR --------------------------
         *
         * Bu ikisi kod tabanının 64 dosyasını yeniden biçimlendiriyordu
         * ve sonuç daha kötü okunuyordu. Ayrıntı aşağıda.
         * -------------------------------------------------------------*/

        // Çok satırlı çağrıları "her argüman ayrı satıra" biçimine
        // zorlamaz. audit('risk_assessed', 'risk', $id, $old, $new)
        // altı satıra bölünüyordu; kod tabanı argümanları anlamlı
        // yerlerden kırıyor ve bu bilinçli.
        'method_argument_space' => ['on_multiline' => 'ignore'],

        // Şablonlardaki karışık HTML/PHP girintisine dokunmaz.
        // Şablonlarda "if (...):" blokları HTML'in girintisini takip
        // ediyor; PHP'nin girintisine zorlanınca şablon okunmaz oluyor.
        //
        // (Bu yorumda kapanış etiketi YAZILMAZ - burası bir PHP
        //  dosyası, etiket yorumun içinde bile bloğu kapatır.)
        'statement_indentation' => false,

        /* --- Zaten uyulan, bozulmasını istemediğimiz kurallar ------- */

        // Her dosya declare(strict_types=1) ile başlar.
        // tools/check_conventions.php bunu ayrıca denetliyor.
        'declare_strict_types' => true,

        // Kullanılmayan import kalmasın (az sayıda var ama sızmasın).
        'no_unused_imports' => true,

        // Dizi sözdizimi kısa biçim: [] - uzun biçim array() yok.
        'array_syntax' => ['syntax' => 'short'],

        // Karşılaştırmalar katı: == yerine ===
        // RISKLI kural: davranışı değiştirebilir, o yüzden yalnızca
        // RAPORLANIR. Otomatik düzeltme için önce testler yeşil olmalı.
        'strict_comparison' => false,

        // Sondaki virgül çok satırlı dizilerde - diff gürültüsünü azaltır.
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],

        // Boş satır yönetimi: iki ardışık boş satır olmasın.
        'no_extra_blank_lines' => [
            'tokens' => ['extra', 'curly_brace_block', 'square_brace_block'],
        ],

        // Dosya sonunda tek satır sonu.
        'single_blank_line_at_eof' => true,

        // Satır sonlarındaki boşluklar temizlensin.
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,

        /* --- BİLEREK KAPALI ------------------------------------------
         *
         * Aşağıdakiler PSR-12 setinde yok ama sık açılır. Bu kod
         * tabanında okunabilirliği DÜŞÜRDÜKLERİ için kapalılar.
         * -------------------------------------------------------------*/

        // Hizalanmış => ve = korunsun. Ayar dizileri ve etiket
        // haritaları (lang/en.php, includes/risk.php) bu hizalama
        // sayesinde tablo gibi okunuyor.
        'binary_operator_spaces' => false,

        // Yorum bloklarının biçimine dokunma. Bölüm ayraçları
        // (/* ---- Başlık ---- */) bilinçli bir düzen aracı.
        'phpdoc_separation'      => false,
        'phpdoc_align'           => false,
        'comment_to_phpdoc'      => false,
        'phpdoc_to_comment'      => false,

        // Yoruma dayalı @var açıklamaları olduğu gibi kalsın.
        'phpdoc_types_order'     => false,
    ]);
