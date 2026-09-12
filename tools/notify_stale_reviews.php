<?php
declare(strict_types=1);

/**
 * RiskOps - Uzun süre gözden geçirilmeyen riskler için hatırlatma
 * /var/www/riskops/tools/notify_stale_reviews.php
 *
 * Bir risk kaydı, kaydedildiği gün doğrudur; altı ay sonra doğru olup
 * olmadığı bilinmez. Gözden geçirilmeyen bir risk envanteri, kurumun
 * gerçek durumunu değil geçmişteki durumunu gösterir — ve bu, envanteri
 * hiç tutmamaktan daha tehlikelidir, çünkü yanlış bir güven verir.
 *
 * Bu betik, son değerlendirmesinin üzerinden N gün geçmiş AÇIK riskleri
 * sahiplerine hatırlatır.
 *
 * KULLANIM
 *   php tools/notify_stale_reviews.php --dry-run
 *   php tools/notify_stale_reviews.php
 *
 * CRON (her pazartesi 08:30)
 *   30 8 * * 1 cd /var/www/riskops && sudo -u www-data /usr/bin/php \
 *              tools/notify_stale_reviews.php >> /var/log/riskops-notify.log 2>&1
 *
 * AYARLAR
 *   notify_enabled      bildirim açık mı (aksiyon bildirimi ile ortak)
 *   notify_stale_days   kaç gün sonra "gözden geçirilmedi" sayılsın
 *   notify_from_email   gönderen adresi
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

$args   = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$force  = in_array('--force', $args, true);

$log = static function (string $m): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $m . PHP_EOL;
};

/* ------------------------------------------------------------------ */

if ((int)setting('notify_enabled', '0') !== 1 && !$force) {
    $log('Bildirim kapali (notify_enabled = 0). --force ile zorlanabilir.');
    exit(0);
}

$sender = mail_sender();
if (!$sender['ok']) {
    $log('HATA: ' . $sender['error']);
    exit(1);
}

$staleDays = max(30, min(1095, (int)setting('notify_stale_days', '180')));

$log('Gozden gecirme hatirlatmasi (' . $staleDays . ' gun esigi)'
    . ($dryRun ? ' [KURU CALISMA]' : ''));

/* ------------------------------------------------------------------ */
/* Bayat riskler                                                       */
/*                                                                     */
/* "Son degerlendirme" = risk_assessments icindeki en son assessed_at.  */
/* Hic degerlendirmesi olmayan riskler de dahildir: COALESCE ile risk   */
/* olusturma tarihine duser, yani "olusturuldugundan beri hic gozden    */
/* gecirilmemis" kayitlar da yakalanir.                                 */
/*                                                                     */
/* KAPANMIS VE DEVREDILMIS RISKLER DISARIDA: kapanmis bir risk icin     */
/* "gozden gecirin" demek, bildirimlere olan guveni bitirir.            */
/* ------------------------------------------------------------------ */

$stmt = db()->prepare(
    "SELECT r.id, r.risk_code, r.title, r.status,
            r.inherent_severity, r.residual_severity,
            COALESCE(r.residual_score, r.inherent_score) AS effective_score,
            r.owner_id, u.name AS owner_name, u.email AS owner_email,
            COALESCE(MAX(a.assessed_at), r.created_at) AS last_review,
            DATEDIFF(CURDATE(), COALESCE(MAX(a.assessed_at), r.created_at)) AS days_stale
       FROM risks r
       JOIN users u ON u.id = r.owner_id AND u.status = 1
       LEFT JOIN risk_assessments a ON a.risk_id = r.id
      WHERE r.deleted_at IS NULL
        AND r.status NOT IN ('Closed', 'Transferred')
      GROUP BY r.id
     HAVING days_stale >= :days
      ORDER BY u.id, days_stale DESC"
);
$stmt->execute([':days' => $staleDays]);
$rows = $stmt->fetchAll();

if ($rows === []) {
    $log('Gozden gecirilmesi gereken risk yok.');
    exit(0);
}

$byOwner = [];
foreach ($rows as $r) {
    $byOwner[(int)$r['owner_id']][] = $r;
}

$log(count($rows) . ' risk, ' . count($byOwner) . ' kisi.');

/* ------------------------------------------------------------------ */

function stale_body(array $risks, string $ownerName, int $threshold): string
{
    $out = 'Merhaba ' . $ownerName . ",\n\n"
         . 'Size ait ' . count($risks) . ' risk ' . $threshold
         . " gunden uzun suredir gozden gecirilmedi.\n\n"
         . "Bir risk kaydi, kaydedildigi gun dogrudur; aradan gecen surede\n"
         . "tehdit de kontroller de degismis olabilir.\n\n"
         . str_repeat('-', 60) . "\n";

    foreach ($risks as $r) {
        $sev = $r['residual_severity'] ?? $r['inherent_severity'];

        $out .= sprintf(
            "  - [%s] %s\n    Son gozden gecirme: %s (%d gun once)\n"
            . "    Etkin seviye: %s (%d) | Durum: %s\n",
            $r['risk_code'],
            $r['title'],
            format_date(substr((string)$r['last_review'], 0, 10)),
            (int)$r['days_stale'],
            (string)$sev,
            (int)$r['effective_score'],
            $r['status']
        );
    }

    return $out . "\n" . mail_footer();
}

/* ------------------------------------------------------------------ */

$sent = 0;
$failed = 0;

foreach ($byOwner as $ownerId => $risks) {
    $owner = $risks[0];

    $subject = sprintf(
        '[%s] %d risk gozden gecirilmeyi bekliyor',
        app_name(),
        count($risks)
    );

    $body = stale_body($risks, (string)$owner['owner_name'], $staleDays);

    if ($dryRun) {
        echo str_repeat('=', 70) . PHP_EOL;
        echo 'KIME : ' . $owner['owner_name'] . ' <' . $owner['owner_email'] . '>' . PHP_EOL;
        echo 'KONU : ' . $subject . PHP_EOL;
        echo str_repeat('-', 70) . PHP_EOL;
        echo $body . PHP_EOL;
        $sent++;
        continue;
    }

    if (mail_send((string)$owner['owner_email'], $subject, $body,
                  $sender['email'], $sender['name'])) {
        $sent++;
        $log('gonderildi -> ' . $owner['owner_email'] . ' (' . count($risks) . ' risk)');
    } else {
        $failed++;
        $log('BASARISIZ  -> ' . $owner['owner_email']);
        app_log('error', 'Stale-review notification could not be sent', [
            'owner' => $ownerId, 'email' => $owner['owner_email'],
        ]);
    }
}

/* ------------------------------------------------------------------ */

if ($dryRun) {
    $log('KURU CALISMA: ' . $sent . ' e-posta gonderilecekti, hicbiri gonderilmedi.');
    exit(0);
}

$log('Tamamlandi: ' . $sent . ' gonderildi, ' . $failed . ' basarisiz.');

/* Basarisiz gonderim SESSIZCE gecilmez: gonderilemeyen bildirim,
   gonderildigi sanilan bildirimden iyidir. */
if ($failed > 0) {
    $log('UYARI: gonderilemeyen bildirimler var. Sunucuda calisan bir MTA');
    $log('       (postfix/sendmail) var mi kontrol edin.');
    exit(1);
}

exit(0);
