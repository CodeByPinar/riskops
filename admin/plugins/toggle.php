<?php

declare(strict_types=1);

/**
 * RiskOps - Eklenti aç/kapat (POST handler)
 * /var/www/riskops/admin/plugins/toggle.php
 *
 * GET ile aç/kapat YOKTUR. POST + CSRF + admin rolü zorunludur.
 *
 * BU UÇ EKLENTİ YÜKLEMEZ
 * ----------------------
 * Yalnızca diskte ZATEN VAR OLAN bir eklentiyi açar ya da kapatır.
 * Dosya yükleme ucu bilerek yoktur: ele geçirilmiş tek bir admin
 * hesabı, uzaktan kod çalıştırmaya dönüşmemeli.
 * Bkz. docs/architecture/0010-eklenti-sistemi.md
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

csrf_require();
require_login();
require_role(ROLE_ADMIN);

$slug   = (string)input('slug', '');
$action = (string)input('action', '');

if (!in_array($action, ['enable', 'disable'], true)) {
    app_abort(400, 'Unknown plugin action: ' . $action);
}

$known = plugins_discover();

/* Diskte olmayan bir slug kabul edilmez. Aksi hâlde durum dosyasına
   uydurma adlar yazılabilir ve liste zamanla çöple dolardı. */
if (!isset($known[$slug])) {
    flash('error', 'Böyle bir eklenti bulunamadı.');
    redirect('/admin/plugins/');
}

$enabled = plugins_enabled();
$before  = $enabled;

if ($action === 'enable') {
    if (!in_array($slug, $enabled, true)) {
        $enabled[] = $slug;
    }
} else {
    $enabled = array_values(array_filter(
        $enabled,
        static fn (string $s): bool => $s !== $slug
    ));
}

if ($before === $enabled) {
    /* Durum zaten istenen hâlde: dosyaya dokunma, denetim kaydına
       da yazma. "Değişiklik yok" bir olay değildir. */
    redirect('/admin/plugins/');
}

if (!plugins_set_enabled($enabled)) {
    app_log('error', 'Plugin state file not writable', ['file' => plugins_state_file()]);
    flash('error', 'Eklenti durumu kaydedilemedi: storage/ dizini yazılabilir değil.');
    redirect('/admin/plugins/');
}

audit(
    $action === 'enable' ? 'plugin.enable' : 'plugin.disable',
    'plugin',
    null,
    ['enabled' => $before],
    [
        'enabled' => $enabled,
        'slug'    => $slug,
        'name'    => $known[$slug]['name'],
        'version' => $known[$slug]['version'],
    ]
);

flash('success', sprintf(
    '%s %s.',
    $known[$slug]['name'],
    $action === 'enable' ? 'açıldı' : 'kapatıldı'
));

redirect('/admin/plugins/');
