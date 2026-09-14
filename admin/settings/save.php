<?php

declare(strict_types=1);

/**
 * RiskOps - Ayarları kaydet (POST handler)
 * /var/www/riskops/admin/settings/save.php
 *
 * Her ayarın kendi sınırı vardır. "int" olması tek başına yetmez:
 * session_lifetime = 5 saniye de geçerli bir tam sayıdır ama sistemi
 * kullanılamaz hâle getirir. Sınırlar aşağıda anahtara göre tanımlıdır.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

csrf_require();
require_login();
require_role(ROLE_ADMIN);

/** Anahtar bazlı tam sayı sınırları: [min, max] */
const SETTING_INT_RANGES = [
    'items_per_page'       => [5, 200],
    'overdue_warning_days' => [1, 365],
    'session_lifetime'     => [300, 86400],
    'max_login_attempts'   => [3, 50],
    'lockout_duration'     => [60, 86400],
    'password_min_length'  => [8, 64],
    'audit_retention_days' => [0, 3650],
];

$rows = db()->query('SELECT * FROM settings WHERE is_editable = 1')->fetchAll();

$errors  = [];
$changes = [];   // key => [eski, yeni]
$thresholdsChanged = false;

foreach ($rows as $row) {
    $key     = $row['setting_key'];
    $type    = $row['setting_type'];
    $oldRaw  = (string)($row['setting_value'] ?? '');

    /* ---------------- severity_thresholds: özel editör ------------- */
    if ($key === 'severity_thresholds') {
        $bands = [];
        $covered = [];
        $bandError = null;

        foreach (['Low', 'Medium', 'High', 'Critical'] as $sev) {
            $min = input_int_range('threshold_' . $sev . '_min', 1, 25);
            $max = input_int_range('threshold_' . $sev . '_max', 1, 25);

            if ($min === null || $max === null) {
                $bandError = $sev . ' bandı 1-25 arasında iki sayı gerektirir.';
                break;
            }
            if ($min > $max) {
                $bandError = $sev . " bandında alt sınır ({$min}) üst sınırdan ({$max}) büyük olamaz.";
                break;
            }
            for ($s = $min; $s <= $max; $s++) {
                if (isset($covered[$s])) {
                    $bandError = "Skor {$s} birden fazla banda giriyor ({$covered[$s]} ve {$sev}).";
                    break 2;
                }
                $covered[$s] = $sev;
            }
            $bands[$sev] = [$min, $max];
        }

        if ($bandError === null) {
            $missing = [];
            for ($s = 1; $s <= 25; $s++) {
                if (!isset($covered[$s])) {
                    $missing[] = $s;
                }
            }
            if ($missing !== []) {
                $bandError = 'Şu skorlar hiçbir banda girmiyor: ' . implode(', ', array_slice($missing, 0, 8))
                           . (count($missing) > 8 ? '…' : '');
            }
        }

        if ($bandError !== null) {
            $errors[$key] = $bandError;
            continue;
        }

        $newRaw = (string)json_encode($bands, JSON_UNESCAPED_UNICODE);
        if ($newRaw !== $oldRaw) {
            $changes[$key]     = [$oldRaw, $newRaw];
            $thresholdsChanged = true;
        }
        continue;
    }

    /* ---------------- bool ------------------------------------------ */
    if ($type === 'bool') {
        $newRaw = input($key) === '1' ? '1' : '0';
        if ($newRaw !== $oldRaw) {
            $changes[$key] = [$oldRaw, $newRaw];
        }
        continue;
    }

    /* ---------------- int ------------------------------------------- */
    if ($type === 'int') {
        [$min, $max] = SETTING_INT_RANGES[$key] ?? [0, 1000000];
        $value = input_int_range($key, $min, $max);
        if ($value === null) {
            $errors[$key] = "Değer {$min} ile {$max} arasında olmalıdır.";
            continue;
        }
        $newRaw = (string)$value;
        if ($newRaw !== $oldRaw) {
            $changes[$key] = [$oldRaw, $newRaw];
        }
        continue;
    }

    /* ---------------- string ---------------------------------------- */
    $value = input($key) ?? '';

    if ($key === 'timezone') {
        if (!in_array($value, DateTimeZone::listIdentifiers(), true)) {
            $errors[$key] = 'Geçerli bir zaman dilimi tanımlayıcısı giriniz (örn: Europe/Istanbul).';
            continue;
        }
    } elseif ($key === 'date_format' || $key === 'datetime_format') {
        if ($value === '' || @date($value) === '' || @date($value) === $value) {
            $errors[$key] = 'Geçerli bir PHP tarih biçimi giriniz.';
            continue;
        }
    } elseif ($key === 'risk_code_prefix') {
        $value = mb_strtoupper($value, 'UTF-8');
        if (preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $value) !== 1) {
            $errors[$key] = 'Ön ek 2-10 karakter olmalı, harfle başlamalı, yalnızca harf ve rakam içermelidir.';
            continue;
        }
    } elseif ($key === 'application_name') {
        if (mb_strlen($value) < 2 || mb_strlen($value) > 60) {
            $errors[$key] = 'Uygulama adı 2-60 karakter olmalıdır.';
            continue;
        }
    } elseif (mb_strlen($value) > 255) {
        $errors[$key] = 'En fazla 255 karakter olabilir.';
        continue;
    }

    if ($value !== $oldRaw) {
        $changes[$key] = [$oldRaw, $value];
    }
}

/* ------------------------------------------------------------------ */

if ($errors !== []) {
    old_set($_POST);
    errors_set($errors);
    flash('error', 'Formda ' . count($errors) . ' hata var. Hiçbir ayar kaydedilmedi.');
    redirect('/admin/settings/');
}

if ($changes === []) {
    flash('info', 'Değişiklik yapılmadı.');
    redirect('/admin/settings/');
}

/* ---------------------------------------------------------- Yazma -- */

$pdo = db();

try {
    $pdo->beginTransaction();

    $upd = $pdo->prepare(
        'UPDATE settings SET setting_value = :v, updated_by = :u WHERE setting_key = :k'
    );
    foreach ($changes as $key => [$old, $new]) {
        $upd->execute([':v' => $new, ':u' => auth_id(), ':k' => $key]);
    }

    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('error', 'Settings save failed: ' . $ex->getMessage(), ['admin' => auth_id()]);
    old_set($_POST);
    flash('error', 'Ayarlar kaydedilemedi.');
    redirect('/admin/settings/');
}

settings_all(true);   // önbelleği tazele

audit('settings_updated', 'settings', null,
    array_map(static fn ($p) => $p[0], $changes),
    array_map(static fn ($p) => $p[1], $changes)
);

flash('success', count($changes) . ' ayar güncellendi.');

/* ------------------------------------- Severity yeniden hesaplama -- */

if ($thresholdsChanged) {
    if (input('recalculate') === '1') {
        try {
            $result = risk_recalculate_severities($pdo);
            audit('severity_recalculated', 'settings', null, null, $result);
            flash('success', sprintf(
                'Eşikler değişti: %d riskin ve %d değerlendirme kaydının seviye etiketi yeniden hesaplandı.',
                $result['risks'], $result['assessments']
            ));
        } catch (Throwable $ex) {
            app_log('error', 'Severity recalculation failed: ' . $ex->getMessage());
            flash('error', 'Eşikler kaydedildi ancak yeniden hesaplama başarısız oldu. '
                . 'Risk listesi geçici olarak matris ile tutarsız olabilir.');
        }
    } else {
        flash('warning', 'Eşikler değişti ancak yeniden hesaplama yapılmadı. '
            . 'Mevcut riskler eski etiketleri taşımaya devam ediyor.');
    }
}

redirect('/admin/settings/');
