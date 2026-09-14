<?php

declare(strict_types=1);

/**
 * RiskOps - Hata ayıklama kipini aç/kapat (POST handler)
 * /var/www/riskops/admin/debug/toggle.php
 *
 * GET ile aç/kapat YOKTUR. POST + CSRF + admin rolü zorunludur.
 *
 * NEDEN BU KADAR KORUNUYOR
 * ------------------------
 * Kip açıkken araç çubuğu çalışan SQL sorgularını, dosya yollarını ve
 * oturum içeriğini basar; istisnalar yığın iziyle görünür. Bunu açmak
 * bir yapılandırma değişikliğidir, bir görünüm tercihi değil.
 *
 * GET ile açılabilseydi, bir admin'e gönderilen bağlantı (ya da bir
 * sayfaya gömülü görsel) onun adına kipi açardı - klasik CSRF. Bu
 * yüzden POST + jeton + rol; üçü birden.
 *
 * Her iki işlem de denetim kaydına yazılır: kipin ne zaman, kim
 * tarafından, ne kadar süreyle açıldığı sonradan sorulabilmeli.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

csrf_require();
require_login();
require_role(ROLE_ADMIN);

$action = (string)input('action', '');

if ($action === 'enable') {
    $minutes = input_int('minutes', 0) ?? 0;
    if (!array_key_exists($minutes, DEBUG_FLAG_DURATIONS)) {
        /* Süre listede yoksa en kısasına düş. Kullanıcıya hata
           göstermek yerine güvenli tarafa düşmek doğru: amaç kipi
           açmak, süre yalnızca bir üst sınır. */
        $minutes = (int)array_key_first(DEBUG_FLAG_DURATIONS);
    }

    if (!debug_flag_enable($minutes)) {
        app_log('error', 'Debug flag could not be written', ['file' => DEBUG_FLAG_FILE]);
        flash('error', 'Kip açılamadı: storage/ dizini yazılabilir değil.');
        redirect('/admin/debug/');
    }

    audit('debug.enable', 'system', null, null, [
        'minutes' => $minutes,
        'until'   => date('Y-m-d H:i:s', time() + $minutes * 60),
        'env'     => APP_ENV,
    ]);

    flash('success', sprintf(
        'Hata ayıklama kipi açıldı — %s sonra kendiliğinden kapanacak.',
        DEBUG_FLAG_DURATIONS[$minutes]
    ));
} elseif ($action === 'disable') {
    /* Ortam değişkeninden gelen kip dosya silinerek kapanmaz; bunu
       söylemek, "kapattım ama hâlâ açık" şaşkınlığını önler. */
    $stillOn = defined('APP_DEBUG_FROM_ENV') && APP_DEBUG_FROM_ENV;

    if (!debug_flag_disable()) {
        app_log('error', 'Debug flag could not be removed', ['file' => DEBUG_FLAG_FILE]);
        flash('error', 'Kip kapatılamadı: bayrak dosyası silinemiyor.');
        redirect('/admin/debug/');
    }

    audit('debug.disable', 'system', null, null, ['env' => APP_ENV]);

    flash(
        $stillOn ? 'warning' : 'success',
        $stillOn
            ? 'Panel bayrağı silindi, ancak kip sunucu yapılandırmasından '
              . '(RISKOPS_DEBUG) hâlâ açık. Kapatmak için VirtualHost düzenlenmeli.'
            : 'Hata ayıklama kipi kapatıldı.'
    );
} else {
    app_abort(400, 'Unknown debug toggle action: ' . $action);
}

redirect('/admin/debug/');
