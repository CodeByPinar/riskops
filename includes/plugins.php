<?php

declare(strict_types=1);

/**
 * RiskOps - Eklenti sistemi
 * /var/www/riskops/includes/plugins.php
 *
 * Üçüncü taraf kodun çekirdeğe dokunmadan menüye giriş eklemesini,
 * rapor tanımlamasını ve olaylara tepki vermesini sağlar.
 *
 * =====================================================================
 *  ÖNCE GÜVENLİK: BU BİR KUM HAVUZU DEĞİLDİR
 * =====================================================================
 *
 * Eklenti = uygulamanın kendi ayrıcalıklarıyla çalışan PHP kodu.
 * Veritabanına erişebilir, dosya yazabilir, ağa çıkabilir. PHP'de
 * gerçek bir kum havuzu yoktur; "eklentiyi kısıtladım" demek yanlış
 * olurdu, o yüzden denenmedi.
 *
 * Bunun doğrudan sonucu: BİR EKLENTİYİ KURMAK, YAZARINA ÇEKİRDEK
 * KADAR GÜVENMEKTİR.
 *
 * Tasarımın buna verdiği karşılık tek ve nettir:
 *
 *   EKLENTİ WEB ARAYÜZÜNDEN YÜKLENEMEZ.
 *
 * Yönetim ekranı yalnızca DİSKTE ZATEN VAR OLAN eklentileri açıp
 * kapatır. Dosyayı sunucuya koymak operatörün işidir (scp, git, paket
 * yöneticisi). Yükleme ucu olsaydı, ele geçirilmiş tek bir admin
 * hesabı doğrudan uzaktan kod çalıştırmaya dönüşürdü - kimlik
 * doğrulama zafiyeti ile sunucunun tamamen kaybı arasındaki fark
 * ortadan kalkardı.
 *
 * Ayrıntı: docs/architecture/0010-eklenti-sistemi.md
 *
 * =====================================================================
 *  KANCALAR
 * =====================================================================
 *
 * İki tür:
 *
 *   EYLEM   hook_do('risk.saved', $riskId, $data)
 *           Bir şey oldu; dinleyiciler tepki verir, dönüş değeri yok.
 *
 *   FİLTRE  $items = hook_filter('nav.items', $items)
 *           Bir değer dinleyicilerden sırayla geçer; her biri
 *           değiştirebilir. Zincirin sonucu döner.
 *
 * Öncelik küçükten büyüğe çalışır (varsayılan 10).
 */

/* =====================================================================
 * KAYIT DEFTERİ
 * ===================================================================*/

/**
 * Kanca dinleyicileri ve çalışma zamanı durumu.
 *
 * Referansla döner ki çağıran yazabilsin; PHP'de bir fonksiyonun
 * static değişkenine dışarıdan erişilemiyor.
 *
 * @return array{listeners: array<string, array<int, list<callable>>>, loaded: list<string>, errors: list<array{hook: string, message: string, file: string}>}
 */
function &plugins_state(): array
{
    static $state = [
        'listeners' => [],   // hook => priority => [callable, ...]
        'loaded'    => [],   // bu istekte yüklenen eklenti slug'ları
        'errors'    => [],   // yükleme / çalıştırma hataları
    ];

    return $state;
}

/** Eklentilerin bulunduğu dizin. */
function plugins_dir(): string
{
    return APP_ROOT . '/plugins';
}

/** Açık eklentilerin tutulduğu dosya. */
function plugins_state_file(): string
{
    return STORAGE_PATH . '/plugins.json';
}

/* =====================================================================
 * KANCA API'Sİ  (eklentiler bunları çağırır)
 * ===================================================================*/

/**
 * Bir kancaya dinleyici ekler.
 *
 * @param string   $hook     'risk.saved', 'nav.items' ...
 * @param callable $listener eylemde dönüş yok sayılır, filtrede ilk
 *                           argüman değer olarak gelir ve döndürülmeli
 * @param int      $priority küçük olan önce çalışır
 */
function hook_add(string $hook, callable $listener, int $priority = 10): void
{
    $state = &plugins_state();
    $state['listeners'][$hook][$priority][] = $listener;
}

/**
 * Bir kancada dinleyici var mı?
 *
 * Pahalı bir veri hazırlamadan önce sorulur:
 *     if (hook_has('report.extra')) { ... }
 */
function hook_has(string $hook): bool
{
    $state = &plugins_state();

    return !empty($state['listeners'][$hook]);
}

/**
 * EYLEM kancası: dinleyicileri çalıştırır, dönüş değeri yok.
 *
 * Bir dinleyici hata fırlatırsa YUTULUR: bozuk bir eklenti risk
 * kaydetmeyi engellememelidir. Hata sessiz de kalmaz - loga yazılır,
 * yönetim ekranında ve hata ayıklama araç çubuğunda görünür.
 */
function hook_do(string $hook, mixed ...$args): void
{
    foreach (hook_listeners($hook) as $listener) {
        try {
            $listener(...$args);
        } catch (Throwable $e) {
            plugins_record_error($hook, $e);
        }
    }
}

/**
 * FİLTRE kancası: değer dinleyicilerden sırayla geçer.
 *
 * Bir dinleyici hata fırlatırsa O ADIM ATLANIR ve değer bir öncekiyle
 * devam eder - yani bozuk bir eklenti zinciri kesmez, yalnızca kendi
 * katkısını yapamaz.
 */
function hook_filter(string $hook, mixed $value, mixed ...$args): mixed
{
    foreach (hook_listeners($hook) as $listener) {
        try {
            $value = $listener($value, ...$args);
        } catch (Throwable $e) {
            plugins_record_error($hook, $e);
        }
    }

    return $value;
}

/**
 * Bir kancanın dinleyicileri, öncelik sırasına dizilmiş.
 *
 * @return list<callable>
 */
function hook_listeners(string $hook): array
{
    $state = &plugins_state();

    if (empty($state['listeners'][$hook])) {
        return [];
    }

    $byPriority = $state['listeners'][$hook];
    ksort($byPriority);

    $flat = [];
    foreach ($byPriority as $listeners) {
        foreach ($listeners as $listener) {
            $flat[] = $listener;
        }
    }

    return $flat;
}

/**
 * Hata ayıklama için: hangi kancada kaç dinleyici var?
 *
 * @return array<string, int>
 */
function hook_map(): array
{
    $state = &plugins_state();

    $map = [];
    foreach ($state['listeners'] as $hook => $byPriority) {
        $n = 0;
        foreach ($byPriority as $listeners) {
            $n += count($listeners);
        }
        $map[$hook] = $n;
    }
    ksort($map);

    return $map;
}

/* =====================================================================
 * KEŞİF
 * ===================================================================*/

/**
 * Diskteki eklentileri bulur ve bildirimlerini doğrular.
 *
 * @return array<string, array<string, mixed>> slug => bildirim
 */
function plugins_discover(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    $dir   = plugins_dir();

    if (!is_dir($dir)) {
        return $cache;
    }

    foreach ((scandir($dir) ?: []) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $dir . '/' . $entry;
        if (!is_dir($path)) {
            continue;
        }

        $manifest = plugins_read_manifest($entry, $path);
        if ($manifest !== null) {
            $cache[$manifest['slug']] = $manifest;
        }
    }

    ksort($cache);

    return $cache;
}

/**
 * Tek bir eklentinin bildirimini okur ve doğrular.
 *
 * Geçersizse null döner ve sebebi loglanır - bozuk bir bildirim
 * uygulamayı durdurmaz.
 *
 * @return array<string, mixed>|null
 */
function plugins_read_manifest(string $slug, string $path): ?array
{
    /* Slug dosya yolu üretiyor; beyaz liste zorunlu. */
    if (preg_match('/^[a-z0-9][a-z0-9_-]{0,39}$/', $slug) !== 1) {
        app_log('warning', 'Plugin slug rejected', ['slug' => $slug]);

        return null;
    }

    $file = $path . '/plugin.php';
    if (!is_file($file)) {
        return null;
    }

    try {
        /** @var mixed $manifest */
        $manifest = require $file;
    } catch (Throwable $e) {
        app_log('error', 'Plugin manifest failed to load', [
            'slug'  => $slug,
            'error' => $e->getMessage(),
        ]);

        return null;
    }

    if (!is_array($manifest) || !isset($manifest['name'], $manifest['version'])) {
        app_log('warning', 'Plugin manifest invalid', ['slug' => $slug]);

        return null;
    }

    /* Bildirimdeki slug dizin adıyla AYNI olmalı. Farklı olsaydı iki
       eklenti aynı slug'ı iddia edip birbirini gizleyebilirdi. */
    if (isset($manifest['slug']) && $manifest['slug'] !== $slug) {
        app_log('warning', 'Plugin slug does not match directory', [
            'directory' => $slug,
            'manifest'  => (string)$manifest['slug'],
        ]);

        return null;
    }

    $hooksFile = null;
    if (isset($manifest['hooks']) && is_string($manifest['hooks'])) {
        $hooksFile = plugins_safe_path($path, $manifest['hooks']);
        if ($hooksFile === null) {
            app_log('warning', 'Plugin hooks file outside plugin directory', ['slug' => $slug]);

            return null;
        }
    }

    return [
        'slug'        => $slug,
        'name'        => (string)$manifest['name'],
        'version'     => (string)$manifest['version'],
        'author'      => isset($manifest['author']) ? (string)$manifest['author'] : '',
        'description' => isset($manifest['description']) ? (string)$manifest['description'] : '',
        'url'         => isset($manifest['url']) ? (string)$manifest['url'] : '',
        'requires_php' => isset($manifest['requires_php']) ? (string)$manifest['requires_php'] : '',
        'path'        => $path,
        'hooks_file'  => $hooksFile,
    ];
}

/**
 * Bir yolun eklenti dizininin İÇİNDE kaldığını doğrular.
 *
 * Bildirim dosyası eklentinin kendi kodudur; oradan gelen bir yol
 * "../../includes/db.php" ya da "/etc/passwd" olabilir. Eklenti zaten
 * kendi kodunu çalıştırabildiği için bu bir ayrıcalık yükseltmesi
 * değil - ama yükleyicinin dizin dışına çıkmaması, hatayı erken ve
 * anlaşılır kılar.
 */
function plugins_safe_path(string $baseDir, string $candidate): ?string
{
    $base = realpath($baseDir);
    $real = realpath(str_starts_with($candidate, '/') ? $candidate : $baseDir . '/' . $candidate);

    if ($base === false || $real === false) {
        return null;
    }

    return str_starts_with($real, $base . DIRECTORY_SEPARATOR) ? $real : null;
}

/* =====================================================================
 * AÇIK / KAPALI DURUMU
 * ===================================================================*/

/**
 * Açık eklenti slug'ları.
 *
 * NEDEN AYAR TABLOSUNDA DEĞİL, DOSYADA
 * ------------------------------------
 * Bu liste "hangi KOD yüklenecek" sorusunun cevabı; bir görünüm
 * tercihi değil, dağıtım yapılandırması. debug.flag ile aynı aileden.
 * Ayrıca ayar tablosu yalnızca var olan anahtarları günceller
 * (setting_save yeni anahtar oluşturmaz, bilinçli bir koruma) -
 * dosya bu kısıtı yeni bir şema göçü gerektirmeden aşıyor.
 *
 * @return list<string>
 */
function plugins_enabled(): array
{
    $raw = @file_get_contents(plugins_state_file());
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['enabled']) || !is_array($data['enabled'])) {
        return [];
    }

    $slugs = [];
    foreach ($data['enabled'] as $slug) {
        if (is_string($slug) && preg_match('/^[a-z0-9][a-z0-9_-]{0,39}$/', $slug) === 1) {
            $slugs[] = $slug;
        }
    }

    return array_values(array_unique($slugs));
}

/**
 * Açık eklenti listesini yazar.
 *
 * Yetki denetimi BURADA DEĞİL: çağıran (admin/plugins/toggle.php)
 * POST + CSRF + admin kontrolünden sonra çağırır.
 *
 * @param list<string> $slugs
 */
function plugins_set_enabled(array $slugs): bool
{
    /* Yalnızca diskte GERÇEKTEN var olan eklentiler yazılır: kaldırılmış
       bir eklentinin adı listede kalıp her istekte uyarı üretmesin. */
    $known = plugins_discover();
    $clean = [];
    foreach ($slugs as $slug) {
        if (is_string($slug) && isset($known[$slug])) {
            $clean[] = $slug;
        }
    }
    $clean = array_values(array_unique($clean));

    if (!is_dir(STORAGE_PATH)) {
        @mkdir(STORAGE_PATH, 0775, true);
    }

    $payload = json_encode([
        'enabled'    => $clean,
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by' => auth_id(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return @file_put_contents(plugins_state_file(), $payload, LOCK_EX) !== false;
}

/* =====================================================================
 * AÇILIŞ
 * ===================================================================*/

/**
 * Açık eklentilerin kanca dosyalarını yükler.
 *
 * bootstrap.php sonunda, ayarlar okunduktan SONRA çağrılır - eklenti
 * kodu setting(), db() ve auth_*() kullanabilsin.
 *
 * Bir eklenti yüklenirken hata verirse ATLANIR ve uygulama açılmaya
 * devam eder. Bozuk bir eklenti yüzünden sisteme hiç girilememesi,
 * onu kapatmayı da imkânsız kılardı.
 */
function plugins_boot(): void
{
    $state   = &plugins_state();
    $known   = plugins_discover();
    $enabled = plugins_enabled();

    foreach ($enabled as $slug) {
        if (!isset($known[$slug])) {
            continue;
        }

        $manifest = $known[$slug];

        if ($manifest['requires_php'] !== ''
            && version_compare(PHP_VERSION, $manifest['requires_php'], '<')) {
            plugins_record_error(
                'boot:' . $slug,
                new RuntimeException(sprintf(
                    'PHP %s gerekiyor, çalışan sürüm %s',
                    $manifest['requires_php'],
                    PHP_VERSION
                ))
            );

            continue;
        }

        if ($manifest['hooks_file'] === null) {
            $state['loaded'][] = $slug;

            continue;
        }

        try {
            plugins_load_hooks($manifest['hooks_file']);
            $state['loaded'][] = $slug;
        } catch (Throwable $e) {
            plugins_record_error('boot:' . $slug, $e);
        }
    }
}

/**
 * Kanca dosyasını KENDİ KAPSAMINDA yükler.
 *
 * Ayrı bir fonksiyon olmasının sebebi kapsam yalıtımı: dosya doğrudan
 * plugins_boot() içinde require edilseydi, oradaki $state, $known,
 * $enabled değişkenlerini görür ve yanlışlıkla (ya da bilerek)
 * değiştirebilirdi.
 */
function plugins_load_hooks(string $file): void
{
    require $file;
}

/**
 * Bu istekte yüklenen eklentiler.
 *
 * @return list<string>
 */
function plugins_loaded(): array
{
    $state = &plugins_state();

    return $state['loaded'];
}

/**
 * Yükleme ve çalıştırma hataları.
 *
 * @return list<array{hook: string, message: string, file: string}>
 */
function plugins_errors(): array
{
    $state = &plugins_state();

    return $state['errors'];
}

/**
 * Bir eklenti hatasını kaydeder.
 *
 * Hem loga yazılır (kalıcı iz) hem istek içinde tutulur (yönetim
 * ekranı ve hata ayıklama araç çubuğu gösterebilsin).
 */
function plugins_record_error(string $hook, Throwable $e): void
{
    $state = &plugins_state();

    $state['errors'][] = [
        'hook'    => $hook,
        'message' => get_class($e) . ': ' . $e->getMessage(),
        'file'    => str_replace(APP_ROOT . '/', '', $e->getFile()) . ':' . $e->getLine(),
    ];

    app_log('error', 'Plugin hook failed', [
        'hook'  => $hook,
        'error' => $e->getMessage(),
        'file'  => $e->getFile() . ':' . $e->getLine(),
    ]);
}
