<?php
declare(strict_types=1);

/**
 * RiskOps - son aktif admin korumasi regresyon testi
 * tools/last_admin_race_test.php
 *
 * last_admin_atomic_guard() dogrulamasi.
 *
 * Bolum 1: tek baglantida karar semasi
 * Bolum 2: iki gercek surecle es zamanli pasiflestirme yarisi
 *
 * GUVENLIK: users tablosunun anlik goruntusu alinir ve finally
 * blogunda HER KOSULDA geri yuklenir - istisna atilsa bile. (Geri
 * yuklemeyi try disinda birakmak, hata halinde tum hesaplari pasif
 * birakip sistemi kilitleyebilirdi.)
 */

if (PHP_SAPI !== 'cli') { exit(1); }

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../admin/users/_validate.php';

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $note = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("  [%s] %s%s\n", $ok ? 'OK  ' : 'FAIL', $label, $note !== '' ? "  ($note)" : '');
}

$pdo = db();
$snapshot = $pdo->query('SELECT id, role, status FROM users ORDER BY id')->fetchAll();

$restore = static function () use ($pdo, $snapshot): void {
    $st = $pdo->prepare('UPDATE users SET role = :r, status = :s WHERE id = :id');
    foreach ($snapshot as $u) {
        $st->execute([':r' => $u['role'], ':s' => $u['status'], ':id' => $u['id']]);
    }
};

try {
    /* ---------------------------------------------------------------- */
    echo "\n1) Tek baglanti: karar semasi\n";
    /* ---------------------------------------------------------------- */

    $ids = array_column($snapshot, 'id');
    [$a, $b] = [(int)$ids[0], (int)$ids[1]];

    // Tam iki aktif admin
    $pdo->exec('UPDATE users SET status = 0, role = "viewer"');
    $pdo->exec("UPDATE users SET role = 'admin', status = 1 WHERE id IN ($a, $b)");

    $pdo->beginTransaction();
    check('2 aktif admin varken A dusurulebilir', last_admin_atomic_guard($pdo, $a) === true);
    $pdo->rollBack();

    // Tek aktif admin
    $pdo->exec("UPDATE users SET status = 0 WHERE id = $b");

    $pdo->beginTransaction();
    check('son aktif admin reddedilir', last_admin_atomic_guard($pdo, $a) === false);
    $pdo->rollBack();

    // Hedef admin degil
    $pdo->beginTransaction();
    check('admin olmayan hedef etkilenmez', last_admin_atomic_guard($pdo, $b) === true);
    $pdo->rollBack();

    /* ---------------------------------------------------------------- */
    echo "\n2) Iki surec: es zamanli pasiflestirme yarisi\n";
    /* ---------------------------------------------------------------- */

    $pdo->exec("UPDATE users SET role = 'admin', status = 1 WHERE id IN ($a, $b)");
    $before = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status=1")->fetchColumn();
    check('baslangic: tam 2 aktif admin', $before === 2, "$before");

    $dir = sys_get_temp_dir() . '/rk-race-' . bin2hex(random_bytes(8));
    mkdir($dir, 0700, true);

    /* Isci scripti CALISMA ANINDA, repo DISINDA uretilir: test dosyasi
       kendi kendine yeter, depoya yarim bir yardimci dosya girmez.
       Yollar APP_ROOT'tan turetilir - sabit /var/www/riskops yazilirsa
       test yalnizca tek bir yerlesimde calisirdi. */
    $root = APP_ROOT;
    $worker = <<<PHP
<?php
declare(strict_types=1);
require_once '{$root}/includes/bootstrap.php';
require_once '{$root}/admin/users/_validate.php';
\$target = (int)\$argv[1];
\$gate   = \$argv[2];
\$pdo = db();
\$pdo->beginTransaction();
// Iki isci de korumaya AYNI ANDA girsin: kapi dosyasi acilana kadar bekle.
\$t = microtime(true);
while (!file_exists(\$gate) && microtime(true) - \$t < 10) { usleep(2000); }
\$allowed = last_admin_atomic_guard(\$pdo, \$target);
if (!\$allowed) { \$pdo->rollBack(); echo json_encode(['guard'=>'red','updated'=>false]); exit(0); }
\$pdo->prepare('UPDATE users SET status = 0 WHERE id = :id')->execute([':id' => \$target]);
\$pdo->commit();
echo json_encode(['guard'=>'gecti','updated'=>true]);
PHP;
    file_put_contents("$dir/worker.php", $worker);

    $outA = "$dir/a.out"; $outB = "$dir/b.out"; $gate = "$dir/gate";
    // Her isci digerini pasiflestirmeye calisir.
    $php = PHP_BINARY;
    exec(sprintf('%s %s %d %s > %s 2>&1 &', escapeshellarg($php), escapeshellarg("$dir/worker.php"), $b, escapeshellarg($gate), escapeshellarg($outA)));
    exec(sprintf('%s %s %d %s > %s 2>&1 &', escapeshellarg($php), escapeshellarg("$dir/worker.php"), $a, escapeshellarg($gate), escapeshellarg($outB)));

    usleep(700000);          // ikisi de korumanin onunde beklesin
    touch($gate);            // kapiyi ac: ayni anda girsinler
    sleep(3);                // bitmelerini bekle

    $ra = trim((string)@file_get_contents($outA));
    $rb = trim((string)@file_get_contents($outB));
    echo "     isci A: $ra\n";
    echo "     isci B: $rb\n";

    $after = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status=1")->fetchColumn();
    check('YARIS SONRASI: en az 1 aktif admin kaldi (invariant)', $after >= 1, "$after aktif admin");
    check('tam olarak 1 isci guncelledi', substr_count($ra . $rb, '"updated":true') === 1);

    array_map('unlink', glob("$dir/*")); rmdir($dir);

} finally {
    $restore();
    $now = $pdo->query('SELECT id, role, status FROM users ORDER BY id')->fetchAll();
    check('users tablosu geri yuklendi', $now == $snapshot);
}

echo "\n  SONUC: $pass OK / $fail FAIL\n";
exit($fail === 0 ? 0 : 1);
