<?php
declare(strict_types=1);

/**
 * RiskOps - Termini yaklaşan ve geçmiş aksiyonlar için e-posta bildirimi
 * /var/www/riskops/tools/notify_due_actions.php
 *
 * Her aksiyon sahibine TEK bir özet e-posta gönderir. Aksiyon başına
 * ayrı e-posta gönderilmez: 12 aksiyonu olan bir kullanıcı her sabah
 * 12 posta alsaydı üçüncü günden sonra hepsini filtrelerdi ve bildirim
 * amacını kaybederdi.
 *
 * KULLANIM
 *   php tools/notify_due_actions.php --dry-run    # gönderme, ekrana yaz
 *   php tools/notify_due_actions.php              # gerçekten gönder
 *
 * CRON (her sabah 08:00)
 *   0 8 * * * cd /var/www/riskops && sudo -u www-data /usr/bin/php \
 *             tools/notify_due_actions.php >> /var/log/riskops-notify.log 2>&1
 *
 * AYARLAR (Settings ekranından)
 *   notify_enabled      bildirim açık mı
 *   notify_days_ahead   kaç gün öncesinden haber verilsin
 *   notify_from_email   gönderen adresi
 *   notify_from_name    gönderen adı
 *
 * MAIL GÖNDERİMİ
 * --------------
 * PHP'nin mail() fonksiyonu kullanılıyor; sunucuda çalışan bir MTA
 * (postfix, sendmail) gerektirir. MTA yoksa mail() false döner ve bu
 * betik HATA ile çıkar - sessizce "başarılı" demez. Gönderilemeyen
 * bildirim, gönderildiği sanılan bildirimden iyidir.
 *
 * Harici bir SMTP servisi kullanacaksanız send_notification()
 * fonksiyonunun içi değiştirilmelidir; geri kalan mantık aynı kalır.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

$argvList = array_slice($argv, 1);
$dryRun   = in_array('--dry-run', $argvList, true);
$force    = in_array('--force', $argvList, true);

$log = static function (string $m): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $m . PHP_EOL;
};

/* ------------------------------------------------------------------ */
/* Ayarlar                                                             */
/* ------------------------------------------------------------------ */

$enabled   = (int)setting('notify_enabled', '0') === 1;
$daysAhead = max(1, min(90, (int)setting('notify_days_ahead', '7')));
if (!$enabled && !$force) {
    $log('Bildirim kapali (notify_enabled = 0). --force ile zorlanabilir.');
    exit(0);
}

/* Gonderen dogrulamasi ortak katmanda (includes/mailer.php):
   ayni kontrol notify_stale_reviews.php icinde de gerekiyor. */
$sender = mail_sender();
if (!$sender['ok']) {
    $log('HATA: ' . $sender['error']);
    exit(1);
}
$fromEmail = $sender['email'];
$fromName  = $sender['name'];

$log('Termin bildirimi basliyor (' . $daysAhead . ' gun ileriye bakiliyor)'
    . ($dryRun ? ' [KURU CALISMA]' : ''));

/* ------------------------------------------------------------------ */
/* Aksiyonlar                                                          */
/*                                                                     */
/* Tamamlanmis ve iptal edilmis aksiyonlar DISARIDA: bitmis bir is icin */
/* hatirlatma gondermek bildirimlere olan guveni bitirir.               */
/*                                                                     */
/* Silinmis riskin aksiyonu da disarida: risk kayittan kaldirilmissa    */
/* aksiyonu da gundemde degildir.                                       */
/* ------------------------------------------------------------------ */

$stmt = db()->prepare(
    "SELECT a.id, a.title, a.due_date, a.priority, a.status,
            a.owner_id, u.name AS owner_name, u.email AS owner_email,
            r.id AS risk_id, r.risk_code, r.title AS risk_title,
            DATEDIFF(a.due_date, CURDATE()) AS days_left
       FROM risk_actions a
       JOIN risks r  ON r.id = a.risk_id AND r.deleted_at IS NULL
       JOIN users u  ON u.id = a.owner_id AND u.status = 1
      WHERE a.status NOT IN ('Completed', 'Cancelled')
        AND a.due_date IS NOT NULL
        AND a.due_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
      ORDER BY u.id, a.due_date ASC"
);
$stmt->execute([':days' => $daysAhead]);
$rows = $stmt->fetchAll();

if ($rows === []) {
    $log('Bildirilecek aksiyon yok.');
    exit(0);
}

/* Sahibe gore grupla */
$byOwner = [];
foreach ($rows as $r) {
    $byOwner[(int)$r['owner_id']][] = $r;
}

$log(count($rows) . ' aksiyon, ' . count($byOwner) . ' kisi.');

/* ------------------------------------------------------------------ */
/* E-posta gövdesi                                                     */
/*                                                                     */
/* DÜZ METİN: HTML e-posta, istemciye göre bozulan ve kaçış hatası      */
/* yaparsa enjeksiyon yüzeyi açan bir biçimdir. Bir hatırlatma için     */
/* gereken her şey düz metinle anlatılabilir.                           */
/* ------------------------------------------------------------------ */

function build_body(array $actions, string $ownerName): string
{
    $overdue  = array_filter($actions, static fn($a) => (int)$a['days_left'] < 0);
    $today    = array_filter($actions, static fn($a) => (int)$a['days_left'] === 0);
    $upcoming = array_filter($actions, static fn($a) => (int)$a['days_left'] > 0);

    $line = static function (array $a): string {
        $d = (int)$a['days_left'];
        $when = $d < 0 ? abs($d) . ' gun gecikti'
              : ($d === 0 ? 'BUGUN' : $d . ' gun kaldi');

        return sprintf(
            "  - [%s] %s\n    Risk : %s - %s\n    Termin: %s (%s) | Oncelik: %s | Durum: %s\n",
            $a['risk_code'],
            $a['title'],
            $a['risk_code'],
            $a['risk_title'],
            format_date($a['due_date']),
            $when,
            $a['priority'],
            $a['status']
        );
    };

    $out = "Merhaba " . $ownerName . ",\n\n"
         . "Size atanmis aksiyonlardan " . count($actions) . " tanesi dikkat bekliyor.\n\n";

    if ($overdue !== []) {
        $out .= "GECIKMIS (" . count($overdue) . ")\n"
              . str_repeat('-', 60) . "\n";
        foreach ($overdue as $a) { $out .= $line($a); }
        $out .= "\n";
    }

    if ($today !== []) {
        $out .= "BUGUN TESLIM (" . count($today) . ")\n"
              . str_repeat('-', 60) . "\n";
        foreach ($today as $a) { $out .= $line($a); }
        $out .= "\n";
    }

    if ($upcoming !== []) {
        $out .= "YAKLASAN (" . count($upcoming) . ")\n"
              . str_repeat('-', 60) . "\n";
        foreach ($upcoming as $a) { $out .= $line($a); }
        $out .= "\n";
    }

    return $out . mail_footer();
}

/* ------------------------------------------------------------------ */
/* Gönder                                                              */
/* ------------------------------------------------------------------ */

$sent = 0;
$failed = 0;

foreach ($byOwner as $ownerId => $actions) {
    $owner = $actions[0];
    $overdueCount = count(array_filter($actions, static fn($a) => (int)$a['days_left'] < 0));

    $subject = sprintf(
        '[%s] %d aksiyon dikkat bekliyor%s',
        app_name(),
        count($actions),
        $overdueCount > 0 ? ' (' . $overdueCount . ' gecikmis)' : ''
    );

    $body = build_body($actions, (string)$owner['owner_name']);

    if ($dryRun) {
        echo str_repeat('=', 70) . PHP_EOL;
        echo 'KIME   : ' . $owner['owner_name'] . ' <' . $owner['owner_email'] . '>' . PHP_EOL;
        echo 'KONU   : ' . $subject . PHP_EOL;
        echo str_repeat('-', 70) . PHP_EOL;
        echo $body . PHP_EOL;
        $sent++;
        continue;
    }

    $ok = mail_send(
        (string)$owner['owner_email'],
        $subject,
        $body,
        $fromEmail,
        $fromName
    );

    if ($ok) {
        $sent++;
        $log('gonderildi -> ' . $owner['owner_email'] . ' (' . count($actions) . ' aksiyon)');
    } else {
        $failed++;
        $log('BASARISIZ  -> ' . $owner['owner_email']);
        app_log('error', 'Due-action notification could not be sent', [
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

/* Basarisiz gonderim SESSIZCE gecilmez: cron ciktisini izleyen kisi
   ya da izleme sistemi bunu gormeli. MTA kurulu degilse mail() her
   zaman false doner ve bu satir her calismada uyarir. */
if ($failed > 0) {
    $log('UYARI: gonderilemeyen bildirimler var. Sunucuda calisan bir MTA');
    $log('       (postfix/sendmail) var mi kontrol edin.');
    exit(1);
}

exit(0);
