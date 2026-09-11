<?php
declare(strict_types=1);

/**
 * RiskOps - Ayar yonetimi
 * /var/www/riskops/includes/settings.php
 *
 * settings tablosu istek başına BIR KEZ okunur ve bellekte tutulur.
 */

/** Tüm ayarlari tip donusumu yapilmis halde döndürür. */
function settings_all(bool $refresh = false): array
{
    static $cache = null;

    if ($cache !== null && !$refresh) {
        return $cache;
    }

    $cache = [];
    try {
        $rows = db()->query(
            'SELECT setting_key, setting_value, setting_type FROM settings'
        )->fetchAll();

        foreach ($rows as $row) {
            $cache[$row['setting_key']] = settings_cast(
                $row['setting_value'],
                (string)$row['setting_type']
            );
        }
    } catch (Throwable $e) {
        // Ayarlar okunamazsa uygulama COKMEMELI; fallback değerler devreye girer.
        app_log('error', 'Settings could not be loaded: ' . $e->getMessage());
        $cache = [];
    }

    return $cache;
}

function settings_cast(?string $value, string $type): mixed
{
    return match ($type) {
        'int'  => (int)$value,
        'bool' => in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true),
        'json' => json_decode((string)$value, true) ?? [],
        default => (string)$value,
    };
}

function setting(string $key, mixed $default = null): mixed
{
    $all = settings_all();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

/** Var olan bir ayari gunceller. Yeni anahtar OLUSTURMAZ. */
function setting_save(string $key, mixed $value, ?int $userId = null): bool
{
    $stmt = db()->prepare('SELECT setting_type, is_editable FROM settings WHERE setting_key = :k LIMIT 1');
    $stmt->execute([':k' => $key]);
    $row = $stmt->fetch();

    if ($row === false || (int)$row['is_editable'] !== 1) {
        return false;
    }

    $raw = match ($row['setting_type']) {
        'json'  => is_string($value) ? $value : (string)json_encode($value, JSON_UNESCAPED_UNICODE),
        'bool'  => $value ? '1' : '0',
        'int'   => (string)(int)$value,
        default => (string)$value,
    };

    $upd = db()->prepare(
        'UPDATE settings SET setting_value = :v, updated_by = :u WHERE setting_key = :k'
    );
    $upd->execute([':v' => $raw, ':u' => $userId, ':k' => $key]);

    settings_all(true); // onbellegi tazele
    return true;
}

function app_name(): string
{
    $n = (string)setting('application_name', FALLBACK_APP_NAME);
    return $n !== '' ? $n : FALLBACK_APP_NAME;
}

function per_page(): int
{
    $n = (int)setting('items_per_page', FALLBACK_PER_PAGE);
    return ($n >= 5 && $n <= 200) ? $n : FALLBACK_PER_PAGE;
}
