<?php
declare(strict_types=1);

/**
 * RiskOps - Son aktif admin korumasi (TOCTOU) regresyon testi
 * Calistirma:  php tools/last_admin_race_test.php
 *
 * BAGIMLILIK: yerel MariaDB (Docker konteyneri "riskops-mariadb",
 * 127.0.0.1:3306, config/database.php). Sunucu yoksa test ACIKCA
 * basarisiz olur (tools/smoke_test.php ile ayni davranis).
 *
 * REGRESYON ARKAPLANI: "son aktif admin" korumasi bir zamanlar
 * check-then-act'ti - other_active_admin_exists() (kilitsiz SELECT
 * COUNT) ve ayri bir UPDATE. Iki aktif admin birbirini ayni anda
 * pasiflestirdiginde her iki istek de "baska admin var" goruyor ve
 * sistem SIFIR aktif adminle kaliyordu (2026-09 round-6 proof'u ile
 * olculdu). Simdi koruma last_admin_atomic_guard() ile cagiranin
 * transaction'i icinde tum aktif admin satirlarini FOR UPDATE ile
 * kilitleyip karari atomik veriyor.
 *
 * Test GERCEK uretim fonksiyonunu (last_admin_atomic_guard) iki ayri
 * islem surecinde - her birinin kendi PDO baglantisiyla - zorlanmis
 * kesisme (marker dosyalariyla) altinda calistirir; ayrica tek
 * baglantili sinir durumlarini da dogrular. users tablosu
 * anlik goruntusu alinip geri yuklenir.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

const RACE_SCRATCH_EMAIL = 'race-test.%@riskops.local';

$repoRoot = dirname(__DIR__);

$PASS = 0;
$FAIL = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $PASS, $FAIL;
    if ($ok) {
        $PASS++;
        printf("  [ OK ]  %-56s %s\n", $label, $detail);
    } else {
        $FAIL++;
        printf("  [FAIL]  %-56s %s\n", $label, $detail);
    }
}

function section(string $title): void
{
    echo "\n" . str_repeat('-', 72) . "\n  " . $title . "\n" . str_repeat('-', 72) . "\n";
}

/* ---- Calisma aninda olusturulan isci (repo DISINDA gecici dizin) ----- */

$workerSrc = <<<'PHP'
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || $argc < 6) { fwrite(STDERR, "bad invocation\n"); exit(2); }
[, $targetId, $waitMarker, $doneMarker, $resultFile, $repoRoot] = $argv;
$targetId = (int)$targetId;

define('RISKOPS_BOOTSTRAPPED', true);
require $repoRoot . '/config/config.php';
require INCLUDES_PATH . '/functions.php';
require INCLUDES_PATH . '/db.php';
require INCLUDES_PATH . '/auth.php';
require $repoRoot . '/admin/users/_validate.php';

const WAIT_TIMEOUT = 20.0;
function wait_marker(string $file): bool
{
    $deadline = microtime(true) + WAIT_TIMEOUT;
    while (microtime(true) < $deadline) {
        clearstatcache(true, $file);
        if (is_file($file)) { return true; }
        usleep(2000);
    }
    return false;
}
function finish(string $resultFile, array $payload): never
{
    file_put_contents($resultFile, json_encode($payload));
    exit(0);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT role, status FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $targetId]);
$row = $stmt->fetch();
$wasActiveAdmin = is_array($row)
    && $row['role'] === ROLE_ADMIN && (int)$row['status'] === 1;

$pdo->beginTransaction();
try {
    touch($doneMarker); // kilit alma denemesini once bildir (sonra bloklansa da)

    if (!last_admin_atomic_guard($pdo, $targetId, $wasActiveAdmin)) {
        $pdo->rollBack();
        finish($resultFile, ['completed' => true, 'guard_rejected' => true, 'updated' => false]);
    }
    if (!wait_marker($waitMarker)) {
        $pdo->rollBack();
        finish($resultFile, ['completed' => false, 'error' => 'marker timeout']);
    }
    $pdo->prepare('UPDATE users SET status = 0 WHERE id = :id')->execute([':id' => $targetId]);
    $pdo->commit();
    finish($resultFile, ['completed' => true, 'guard_rejected' => false, 'updated' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    finish($resultFile, ['completed' => false, 'error' => $e->getMessage()]);
}
PHP;

$tmp = sys_get_temp_dir() . '/riskops-race-' . getmypid();
if (!is_dir($tmp)) {
    mkdir($tmp, 0777, true);
}
$workerFile = $tmp . '/worker.php';
file_put_contents($workerFile, $workerSrc);

/* ---- Ortam ----------------------------------------------------------- */

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=riskops;charset=utf8mb4',
        'riskops_user',
        'riskops_local_dev',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_TIMEOUT => 5]
    );
} catch (Throwable $e) {
    echo "\nYerel MariaDB'ye baglanilamadi (konteyner: docker start riskops-mariadb).\n";
    echo 'Hata: ' . $e->getMessage() . "\n";
    echo "LAST ADMIN RACE TEST: FAIL\n";
    exit(1);
}

if (!function_exists('last_admin_atomic_guard')) {
    define('RISKOPS_BOOTSTRAPPED', true);   // _validate.php direct-access guard'i icin
    require_once $repoRoot . '/config/config.php';
    require_once $repoRoot . '/admin/users/_validate.php';
}

/* Kalinti scratch kayitlari temizle, users durumunu yedekle */
$pdo->exec("DELETE FROM users WHERE email LIKE '" . RACE_SCRATCH_EMAIL . "'");
$snapshot = $pdo->query('SELECT id, role, status, must_change_password FROM users ORDER BY id')->fetchAll();

/* Basit geri yukleme: snapshot degerlerini tekrar uygula */
$restoreSnapshot = static function () use ($pdo, $snapshot): void {
    $pdo->exec("DELETE FROM users WHERE email LIKE '" . RACE_SCRATCH_EMAIL . "'");
    $upd = $pdo->prepare('UPDATE users SET role = :r, status = :s, must_change_password = :m WHERE id = :id');
    foreach ($snapshot as $row) {
        $upd->execute([':r' => $row['role'], ':s' => $row['status'],
                       ':m' => $row['must_change_password'], ':id' => $row['id']]);
    }
};

echo "\n================= Last Admin Race Regression Test =================";

/* ------------------------------------------------------------------ */
section('1) Tek baglanti: guard karar semasi');
/* ------------------------------------------------------------------ */

$pdo->exec('UPDATE users SET status = 0');
$ins = $pdo->prepare('INSERT INTO users (name, email, password, role, status) VALUES (:n, :e, :p, \'admin\', 1)');
$ins->execute([':n' => 'Race A', ':e' => 'race-test.a@riskops.local', ':p' => password_hash('x', PASSWORD_DEFAULT)]);
$aId = (int)$pdo->lastInsertId();
$ins->execute([':n' => 'Race B', ':e' => 'race-test.b@riskops.local', ':p' => password_hash('x', PASSWORD_DEFAULT)]);
$bId = (int)$pdo->lastInsertId();

$pdo->beginTransaction();
check('iki aktif admin varken hedef admin izinli',
    last_admin_atomic_guard($pdo, $aId, true) === true);
$pdo->rollBack();

$pdo->prepare('UPDATE users SET status = 0 WHERE id = :id')->execute([':id' => $bId]);
$pdo->beginTransaction();
check('tek kalan aktif admin REDDEDILIR',
    last_admin_atomic_guard($pdo, $aId, true) === false);
$pdo->rollBack();

$pdo->prepare("UPDATE users SET role = 'viewer' WHERE id = :id")->execute([':id' => $bId]);
$pdo->beginTransaction();
check('admin olmayan hedeften etkilenmez',
    last_admin_atomic_guard($pdo, $bId, false) === true);
$pdo->rollBack();

/* ------------------------------------------------------------------ */
section('2) Zorlanmis kesisme: iki islem, iki baglanti');
/* ------------------------------------------------------------------ */

$pdo->exec('UPDATE users SET status = 0');
$pdo->exec("UPDATE users SET role = 'admin', status = 1 WHERE id IN ({$aId}, {$bId})");

$mk = static fn(string $n): string => $tmp . '/' . $n . '.mk';
array_map('unlink', glob($tmp . '/*.mk') ?: []);

$specs = [
    [$bId, 'a_guard_done', 'b_guard_done', 'result_a.json'],
    [$aId, 'b_guard_done', 'a_guard_done', 'result_b.json'],
];
$procs = [];
foreach ($specs as [$target, $waitM, $doneM, $resultF]) {
    $cmd = 'php ' . escapeshellarg($workerFile)
         . ' ' . escapeshellarg((string)$target)
         . ' ' . escapeshellarg($mk($waitM))
         . ' ' . escapeshellarg($mk($doneM))
         . ' ' . escapeshellarg($tmp . '/' . $resultF)
         . ' ' . escapeshellarg($repoRoot);
    $descriptors = [1 => ['file', $tmp . '/' . $resultF . '.log', 'w'],
                    2 => ['file', $tmp . '/' . $resultF . '.err', 'w']];
    $pipes = [];
    $procs[] = [proc_open($cmd, $descriptors, $pipes), $resultF];
}

$results = [];
foreach ($procs as [$proc, $resultF]) {
    proc_close($proc);
    $path = $tmp . '/' . $resultF;
    $results[$resultF] = is_file($path)
        ? (json_decode((string)file_get_contents($path), true) ?: [])
        : ['missing' => true];
}

$activeAdmins = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 1"
)->fetchColumn();

/* --- Geri yukle ------------------------------------------------------ */

$restoreSnapshot();
$leftover = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE email LIKE '" . RACE_SCRATCH_EMAIL . "' OR id IN ({$aId}, {$bId})"
)->fetchColumn();
check('scratch kayitlar temizlendi, users geri yuklendi', $leftover === 0, $leftover . ' kayit');

/* --- Kararlar -------------------------------------------------------- */

check('her iki isci tamamlandi',
    ($results['result_a.json']['completed'] ?? false) && ($results['result_b.json']['completed'] ?? false),
    json_encode($results['result_b.json']));
check('tam olarak bir isci guard tarafindan reddedildi',
    (($results['result_a.json']['guard_rejected'] ?? false) ? 1 : 0)
  + (($results['result_b.json']['guard_rejected'] ?? false) ? 1 : 0) === 1);
check('yaris sonrasi tam olarak 1 aktif admin kaldi', $activeAdmins === 1, (string)$activeAdmins);

/* --- Gecici isciyi sil ----------------------------------------------- */

@unlink($workerFile);
array_map('unlink', glob($tmp . '/*') ?: []);
@rmdir($tmp);

/* ------------------------------------------------------------------ */

echo "\n" . str_repeat('-', 72) . "\n";
printf("Sonuc: %d OK, %d FAIL\n", $PASS, $FAIL);
if ($FAIL > 0) {
    echo "LAST ADMIN RACE TEST: FAIL\n";
    exit(1);
}
echo "LAST ADMIN RACE TEST: PASS\n";
exit(0);
