<?php
declare(strict_types=1);

/**
 * RiskOps - Giris akisi (login flow) sozlesme regresyon testi
 * Calistirma:  php tools/login_flow_test.php
 *
 * BAGIMLILIK: yerel MariaDB (Docker konteyneri "riskops-mariadb",
 * 127.0.0.1:3306, config/database.php). Sunucu yoksa test ACIKCA
 * basarisiz olur (tools/smoke_test.php ile ayni davranis).
 *
 * KAPSAM: GERCEK auth/authenticate.php isleyicisi cocuk sureclerde
 * calistirilir (gercek oturum + CSRF + veritabani; yalnizca web ortami
 * $_SERVER/$_POST ile simule edilir) ve belgelenmis sozlesme
 * dogrulanir. Bu test bilinen bir hatayi degil, giris akisinin
 * SOZLESMESINI regresyona karsi korur (round-7 E2E avindan
 * kalicilastirilmistir):
 *
 *   S0  gecersiz CSRF  -> 403 ABORT, hicbir DB yan etkisi yok.
 *   S1  gecerli giris  -> users.last_login_at guncellenir, basarili
 *       deneme satiri yazilir, 'login' audit kaydi olur.
 *   S2  yanlis parola  -> basarisiz deneme satiri + 'login_failed'.
 *   S3  ardan basarili giris -> bu e-postanin basarisiz satirlari
 *       silinir (kilit kalkar), last_login_at yine guncellenir.
 *   S4  max_login_attempts yanlis denemeden sonra DOGRU parola bile
 *       reddedilir: 'login_rate_limited', last_login_at DEGISMEZ ve
 *       rate-limit yolu YENI deneme satiri YAZMAZ.
 *   S5  status=0 hesap -> 'login_disabled_account', basarisiz satiri.
 *
 * users / login_attempts / audit_logs anlik goruntusu alinip sonda
 * geri yuklenir; test IP'si 127.0.0.7 ile izole edilir. NOT:
 * authenticate.php gecerli ilk giriste parola hash'ini
 * PASSWORD_DEFAULT'a yeniden hesaplayabilir - bu yuzden users.password
 * da yedeklenip geri yuklenir.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

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

/* ---- Calisma aninda uretilen istek cocugu (repo DISI gecici dizin) -- */

$workerSrc = <<<'PHP'
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || $argc < 3) { fwrite(STDERR, "bad invocation\n"); exit(2); }
[, $cfgFile, $repoRoot] = $argv;
$cfg = json_decode((string)file_get_contents($cfgFile), true) ?: [];

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.7';
$_SERVER['HTTP_USER_AGENT'] = 'login-flow-test';
$_SERVER['REQUEST_URI'] = '/auth/authenticate.php';

$_POST = [
    'email'    => (string)($cfg['email'] ?? ''),
    'password' => (string)($cfg['password'] ?? ''),
];

// CLI'da bootstrap oturum blogunu atlar; oturumu test acar (dosya
// handler'i). Boylece csrf_verify / auth_start / flash uretimdeki
// gibi calisir; redirect() exit(0), app_abort STDERR'da ABORT + exit(1).
session_name('RISKOPS_SESSION');
session_start();
$_SESSION['_csrf'] = 'login-flow-test-token';
$_POST['_token'] = ($cfg['token_valid'] ?? true) ? 'login-flow-test-token' : 'WRONG-TOKEN';

require $repoRoot . '/auth/authenticate.php';
PHP;

$tmp = sys_get_temp_dir() . '/riskops-login-test-' . getmypid();
if (!is_dir($tmp)) {
    mkdir($tmp, 0777, true);
}
$workerFile = $tmp . '/request.php';
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
    echo "LOGIN FLOW TEST: FAIL\n";
    exit(1);
}

/* --- Snapshot ---------------------------------------------------------- */

$usersSnap = $pdo->query(
    'SELECT id, password, status, must_change_password, last_login_at FROM users ORDER BY id'
)->fetchAll();
$laMax = (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM login_attempts')->fetchColumn();
$auMax = (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM audit_logs')->fetchColumn();

$restore = static function () use ($pdo, $usersSnap, $laMax, $auMax): void {
    $pdo->exec("DELETE FROM users WHERE email LIKE 'login-flow-test.%@riskops.local'");
    $pdo->prepare('DELETE FROM login_attempts WHERE id > :m OR ip_address = :ip')
        ->execute([':m' => $laMax, ':ip' => '127.0.0.7']);
    $pdo->prepare('DELETE FROM audit_logs WHERE id > :m')->execute([':m' => $auMax]);
    $upd = $pdo->prepare(
        'UPDATE users SET password = :p, status = :s, must_change_password = :mcp,
                last_login_at = :ll WHERE id = :id'
    );
    foreach ($usersSnap as $u) {
        $upd->execute([':p' => $u['password'], ':s' => $u['status'],
                       ':mcp' => $u['must_change_password'], ':ll' => $u['last_login_at'],
                       ':id' => $u['id']]);
    }
};

/** Bir "giris istegi" calistirir: [exitCode, stderr]. */
function run_login_request(array $cfg, string $tmp, string $workerFile, string $repoRoot): array
{
    file_put_contents($tmp . '/cfg.json', json_encode($cfg, JSON_UNESCAPED_UNICODE));
    $cmd = 'php ' . escapeshellarg($workerFile) . ' ' . escapeshellarg($tmp . '/cfg.json')
         . ' ' . escapeshellarg($repoRoot) . ' 2>' . escapeshellarg($tmp . '/stderr.txt');
    exec($cmd, $out, $code);
    return [$code, (string)@file_get_contents($tmp . '/stderr.txt')];
}

/** Snapshot'tan bu yana yazilan audit action'lari. */
function audits_since(PDO $pdo, int $auMax): array
{
    $st = $pdo->prepare('SELECT action FROM audit_logs WHERE id > :m ORDER BY id');
    $st->execute([':m' => $auMax]);
    return array_column($st->fetchAll(), 'action');
}

/** Test IP'si icin e-posta bazli basarisiz deneme sayisi. */
function fail_count(PDO $pdo, string $email): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND email = :e');
    $st->execute([':e' => $email]);
    return (int)$st->fetchColumn();
}

function last_login_of(PDO $pdo, string $email): string
{
    $st = $pdo->prepare('SELECT last_login_at FROM users WHERE email = :e');
    $st->execute([':e' => $email]);
    return (string)$st->fetchColumn();
}

echo "\n================= Login Flow Contract Test =================";

try {
    $adminEmail = 'admin@riskops.local';
    $adminPass  = 'Admin123456';
    $maxAttempts = max(1, (int)$pdo->query(
        "SELECT setting_value FROM settings WHERE setting_key = 'max_login_attempts'"
    )->fetchColumn() ?: 5);

    /* ------------------------------------------------------------------ */
    section('1) CSRF ve gecerli giris');
    /* ------------------------------------------------------------------ */

    $t0 = date('Y-m-d H:i:s', time() - 1);
    [$code, $err] = run_login_request(
        ['email' => $adminEmail, 'password' => 'x', 'token_valid' => false],
        $tmp, $workerFile, $repoRoot
    );
    $sideEffects = (int)$pdo->query(
        "SELECT (SELECT COUNT(*) FROM login_attempts WHERE ip_address = '127.0.0.7')"
        . " + (SELECT COUNT(*) FROM users WHERE last_login_at >= '{$t0}')"
    )->fetchColumn();
    check('gecersiz CSRF: 403 ABORT ve DB yan etkisi yok',
        $code !== 0 && str_contains($err, 'ABORT 403') && $sideEffects === 0,
        "exit={$code} effects={$sideEffects}");

    [$code] = run_login_request(['email' => $adminEmail, 'password' => $adminPass], $tmp, $workerFile, $repoRoot);
    $successRows = (int)$pdo->query(
        "SELECT COUNT(*) FROM login_attempts WHERE success = 1 AND email = '{$adminEmail}'"
    )->fetchColumn();
    check('gecerli giris: last_login_at + basarili satir + login audit',
        $code === 0 && last_login_of($pdo, $adminEmail) >= $t0 && $successRows === 1
        && in_array('login', audits_since($pdo, $auMax), true),
        'll=' . last_login_of($pdo, $adminEmail) . ' ok_rows=' . $successRows);

    /* ------------------------------------------------------------------ */
    section('2) Basarisiz denemeler ve kilit acma');
    /* ------------------------------------------------------------------ */

    run_login_request(['email' => $adminEmail, 'password' => 'WRONG-1'], $tmp, $workerFile, $repoRoot);
    run_login_request(['email' => $adminEmail, 'password' => 'WRONG-2'], $tmp, $workerFile, $repoRoot);
    check('yanlis parola: basarisiz satir + login_failed audit',
        fail_count($pdo, $adminEmail) === 2
        && count(array_keys(audits_since($pdo, $auMax), 'login_failed', true)) >= 2,
        'fails=' . fail_count($pdo, $adminEmail));

    $beforeS3 = last_login_of($pdo, $adminEmail);
    sleep(1);
    [$code] = run_login_request(['email' => $adminEmail, 'password' => $adminPass], $tmp, $workerFile, $repoRoot);
    check('basari, basarisiz satirlari temizler (kilit kalkar)',
        $code === 0 && fail_count($pdo, $adminEmail) === 0 && last_login_of($pdo, $adminEmail) > $beforeS3,
        'fails=' . fail_count($pdo, $adminEmail));

    /* ------------------------------------------------------------------ */
    section("3) Brute-force kilidi (max_login_attempts={$maxAttempts})");
    /* ------------------------------------------------------------------ */

    for ($i = 1; $i <= $maxAttempts; $i++) {
        run_login_request(['email' => $adminEmail, 'password' => "WRONG-{$i}"], $tmp, $workerFile, $repoRoot);
    }
    $lockedAt  = last_login_of($pdo, $adminEmail);
    $failsBefore = fail_count($pdo, $adminEmail);

    run_login_request(['email' => $adminEmail, 'password' => $adminPass], $tmp, $workerFile, $repoRoot);
    $rateLimited = in_array('login_rate_limited', audits_since($pdo, $auMax), true);
    check('kilit: DOGRU parola bile reddedilir, last_login degismez',
        last_login_of($pdo, $adminEmail) === $lockedAt && $rateLimited,
        'll_ayni=' . var_export(last_login_of($pdo, $adminEmail) === $lockedAt, true)
        . ' rate_limited=' . var_export($rateLimited, true));
    check('rate-limit yolu YENI deneme satiri yazmaz',
        fail_count($pdo, $adminEmail) === $failsBefore && $failsBefore === $maxAttempts,
        'fails=' . fail_count($pdo, $adminEmail));

    /* ------------------------------------------------------------------ */
    section('4) Pasif hesap');
    /* ------------------------------------------------------------------ */

    $ins = $pdo->prepare(
        'INSERT INTO users (name, email, password, role, status) VALUES (:n, :e, :p, \'viewer\', 0)'
    );
    $ins->execute([':n' => 'Login Flow Disabled', ':e' => 'login-flow-test.disabled@riskops.local',
                   ':p' => password_hash('Disabled123', PASSWORD_DEFAULT)]);
    run_login_request(
        ['email' => 'login-flow-test.disabled@riskops.local', 'password' => 'Disabled123'],
        $tmp, $workerFile, $repoRoot
    );
    check('pasif hesap: login_disabled_account + basarisiz satir',
        in_array('login_disabled_account', audits_since($pdo, $auMax), true)
        && fail_count($pdo, 'login-flow-test.disabled@riskops.local') === 1);
} finally {
    $restore();
}

/* ------------------------------------------------------------------ */
section('5) Geri yukleme');
/* ------------------------------------------------------------------ */

$leftUsers = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE email LIKE 'login-flow-test.%@riskops.local'"
)->fetchColumn();
$leftAttempts = (int)$pdo->query(
    "SELECT COUNT(*) FROM login_attempts WHERE ip_address = '127.0.0.7' OR id > {$laMax}"
)->fetchColumn();
$leftAudit = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE id > {$auMax}")->fetchColumn();
$hashRestored = true;
$recheck = $pdo->query('SELECT id, password FROM users ORDER BY id')->fetchAll();
foreach ($recheck as $i => $row) {
    if (($usersSnap[$i]['id'] ?? null) !== $row['id']
        || ($usersSnap[$i]['password'] ?? null) !== $row['password']) {
        $hashRestored = false;
    }
}
check('scratch kayitlar ve test satirlari temizlendi',
    $leftUsers === 0 && $leftAttempts === 0 && $leftAudit === 0,
    "users={$leftUsers} attempts={$leftAttempts} audit={$leftAudit}");
check('users.password dahil anlik durum geri yuklendi', $hashRestored);

/* --- Gecici cocuk/ayar dosyalarini sil --------------------------------- */

@unlink($workerFile);
@unlink($tmp . '/cfg.json');
@unlink($tmp . '/stderr.txt');
@rmdir($tmp);

/* ------------------------------------------------------------------ */

echo "\n" . str_repeat('-', 72) . "\n";
printf("Sonuc: %d OK, %d FAIL\n", $PASS, $FAIL);
if ($FAIL > 0) {
    echo "LOGIN FLOW TEST: FAIL\n";
    exit(1);
}
echo "LOGIN FLOW TEST: PASS\n";
exit(0);
