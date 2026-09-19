<?php

declare(strict_types=1);

/**
 * RiskOps - Herkese açık demo kurulumunu sıfırlar
 * /var/www/riskops/tools/demo_reset.php
 *
 * Açık bir demoda ziyaretçiler veriyi bozar. Bu betik her gece çalışır,
 * örnek veriyi baştan kurar ve deneme hesabını yerine koyar.
 *
 *   RISKOPS_DEMO=1 php /var/www/riskops/tools/demo_reset.php
 *
 * BU BETİK VERİ SİLER.
 * ---------------------
 * Koruma: RISKOPS_DEMO ortam değişkeni tam olarak "1" değilse hiçbir şey
 * yapmadan çıkar. Böylece gerçek bir kuruluma yanlışlıkla kopyalansa ve
 * çalıştırılsa bile veriyi silemez. Koruma bilinçli olarak ortam
 * değişkenine bağlıdır: bir dosyanın varlığına bakmak, dosya yanlışlıkla
 * kopyalandığında sessizce devre dışı kalır.
 *
 * Yalnızca komut satırından çalışır (web üzerinden erişilemez).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$demoFlag = $_SERVER['RISKOPS_DEMO'] ?? getenv('RISKOPS_DEMO') ?: '';
if ($demoFlag !== '1') {
    fwrite(STDERR, "demo_reset: RISKOPS_DEMO=1 degil, hicbir sey yapilmadi.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/bootstrap.php';

$root = dirname(__DIR__);
$php  = PHP_BINARY;
$log  = static function (string $m): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $m . PHP_EOL;
};

$log('Demo sifirlama basliyor');

/* ------------------------------------------------------------------ */
/* 1. Ornek veriyi temizle ve yeniden kur                              */
/*                                                                     */
/* seed_demo.php'yi cagiriyoruz: silme ve kurma mantigi tek yerde      */
/* kalsin, iki dosyada birbirinden sapmasin.                           */
/* ------------------------------------------------------------------ */

$run = static function (array $args) use ($php, $root, $log): void {
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($root . '/tools/seed_demo.php')
         . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
    exec($cmd, $out, $code);
    if ($code !== 0) {
        $log('HATA: seed_demo ' . implode(' ', $args) . ' -> cikis ' . $code);
        foreach ($out as $line) {
        $log('  ' . $line);
        }
        exit(1);
    }
};

$run(['--purge', '--yes']);
$log('Eski demo verisi silindi');

$run([]);
$log('Ornek veri yeniden kuruldu');

/* ------------------------------------------------------------------ */
/* 2. Deneme hesabi                                                    */
/*                                                                     */
/* Giris ekraninda gosterilen hesap (bkz. config/config.php).          */
/* Rolu 'viewer': ziyaretci hicbir sey degistiremez. Parolasi zaten    */
/* herkese acik oldugu icin gizlilik beklentisi yok; onemli olan       */
/* rolunun her gece geri alinmasi.                                     */
/* ------------------------------------------------------------------ */

$hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);

db_run(
    'INSERT INTO users (name, email, password, role, status, must_change_password,
                        password_changed_at, created_at)
     VALUES (:n, :e, :p, :r, 1, 0, NOW(), NOW())
     ON DUPLICATE KEY UPDATE
        name = :n2, password = :p2, role = :r2, status = 1,
        must_change_password = 0, password_changed_at = NOW()',
    [
    ':n'  => 'Demo Kullanıcı', ':n2' => 'Demo Kullanıcı',
    ':e'  => DEMO_EMAIL,
    ':p'  => $hash,            ':p2' => $hash,
    ':r'  => ROLE_VIEWER,      ':r2' => ROLE_VIEWER,
]
);

$log('Deneme hesabi hazir: ' . DEMO_EMAIL . ' (viewer)');

/* ------------------------------------------------------------------ */
/* 3. Gurultu temizligi                                                */
/*                                                                     */
/* Denetim kaydi normalde SILINMEZ - append-only olmasi tasarim        */
/* geregi. Ama acik bir demoda bot trafigi birkac gunde on binlerce    */
/* satir biriktirir ve Audit Logs ekrani kullanilmaz hale gelir.       */
/* Yalnizca demoda, yalnizca 7 gunden eskiler.                         */
/* ------------------------------------------------------------------ */

$n = db()->exec('DELETE FROM audit_logs WHERE created_at < NOW() - INTERVAL 7 DAY');
$log('Denetim kaydi budandi: ' . (int)$n . ' satir');

$n = db()->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
$log('Giris denemesi kaydi budandi: ' . (int)$n . ' satir');

$log('Demo sifirlama tamamlandi');
exit(0);
