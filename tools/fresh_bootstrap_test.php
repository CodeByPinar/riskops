<?php
declare(strict_types=1);

/**
 * RiskOps - Bos veritabani kurulum (fresh bootstrap) regresyon testi
 * Calistirma:  php tools/fresh_bootstrap_test.php
 *
 * BAGIMLILIK: yerel MariaDB (Docker konteyneri "riskops-mariadb",
 * 127.0.0.1:3306, root erisimi). Sunucu yoksa test ACIKCA basarisiz olur
 * (tools/smoke_test.php ile ayni davranis).
 *
 * REGRESYON ARKAPLANI: repoda bir zamanlar CREATE TABLE users YOKTU;
 * schema.sql'in ilk tablosu (departments) users(id)'ye FK icerdigi icin
 * bos veritabaninda belgelenmis kurulum ERROR 1005 (errno 150) ile
 * kiriliyordu ve seed.sql ilk admini INSERT etmedigi icin kurulum sonunda
 * giris yapilacak hesap olmuyordu. schema.sql bolum 0'da artik users
 * tablosunu olusturuyor; seed.sql belgelenmis ilk admini ekliyor.
 *
 * Bu test GERCEK database/schema.sql ve database/seed.sql dosyalarini
 * bos bir karalama veritabaninda calistirir (mock yok) ve ayrica dosyanin
 * kendi sozu olan idempotentligi (ust uste tekrar calistirilabilirlik)
 * dogrular.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

const BOOTSTRAP_TEST_DB = 'riskops_bootstrap_test';
const BOOTSTRAP_TEST_ROOT_PASS = 'riskops_local_root';

$expectedTables = [
    'audit_logs', 'departments', 'login_attempts', 'risk_actions',
    'risk_assessments', 'risk_categories', 'risk_sequences', 'risks',
    'settings', 'users',
];

/** Gercek SQL dosyasini ifade ifade uygular; ilk hatayi dondurur. */
function bootstrap_run_sql_file(PDO $pdo, string $path): ?array
{
    $raw = (string)file_get_contents($path);
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

    $stmt = '';
    $count = 0;
    foreach ($lines as $line) {
        if (preg_match('/^\s*--/', $line) || trim($line) === '') {
            continue;
        }
        $stmt .= $line . "\n";
        if (preg_match('/;\s*$/', $line)) {
            $count++;
            try {
                $pdo->exec(trim($stmt));
            } catch (PDOException $e) {
                return ['error' => $e->getMessage(), 'sql' => trim($stmt), 'stmtno' => $count];
            }
            $stmt = '';
        }
    }
    return null;
}

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

echo "\n=============== Fresh Bootstrap Regression Test ===============";

/* --- Ortam ----------------------------------------------------------- */

try {
    $root = new PDO(
        'mysql:host=127.0.0.1;port=3306;charset=utf8mb4',
        'root',
        BOOTSTRAP_TEST_ROOT_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_TIMEOUT => 5]
    );
} catch (Throwable $e) {
    echo "\nYerel MariaDB'ye baglanilamadi (konteyner: docker start riskops-mariadb).\n";
    echo 'Hata: ' . $e->getMessage() . "\n";
    echo "FRESH BOOTSTRAP TEST: FAIL\n";
    exit(1);
}

$root->exec('DROP DATABASE IF EXISTS ' . BOOTSTRAP_TEST_DB);
$root->exec('CREATE DATABASE ' . BOOTSTRAP_TEST_DB
    . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=' . BOOTSTRAP_TEST_DB . ';charset=utf8mb4',
    'root',
    BOOTSTRAP_TEST_ROOT_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

/* ------------------------------------------------------------------ */
section('1) Bos veritabaninda belgelenmis kurulum');
/* ------------------------------------------------------------------ */

$err = bootstrap_run_sql_file($pdo, __DIR__ . '/../database/schema.sql');
check('schema.sql bos veritabaninda hatasiz uygulanir', $err === null,
    $err !== null ? sprintf('#%d: %s', $err['stmtno'], $err['error']) : '');

if ($err === null) {
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);
    check('10 tablo olusur', $tables === $expectedTables, count($tables) . ' tablo');

    $err = bootstrap_run_sql_file($pdo, __DIR__ . '/../database/seed.sql');
    check('seed.sql hatasiz uygulanir', $err === null,
        $err !== null ? sprintf('#%d: %s', $err['stmtno'], $err['error']) : '');

    $u = $pdo->query(
        "SELECT password, role, status, must_change_password, department_id
         FROM users WHERE email = 'admin@riskops.local' LIMIT 1"
    )->fetch();

    check('belgelenmis ilk admin olusur', is_array($u));
    if (is_array($u)) {
        check('admin rol/durum/parola-degisimi zorunlu',
            $u['role'] === 'admin' && (int)$u['status'] === 1 && (int)$u['must_change_password'] === 1);
        check('admin departmana bagli (IT)', $u['department_id'] !== null);
        check("password_verify('Admin123456')",
            password_verify('Admin123456', (string)$u['password']));
    }
    $settingsRows = (int)$pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn();
    check('ayarlar yuklendi', $settingsRows === 17, $settingsRows . ' satir');
}

/* ------------------------------------------------------------------ */
section('2) Idempotenti: ayni dosyalar ust uste tekrar calisir');
/* ------------------------------------------------------------------ */

$err = bootstrap_run_sql_file($pdo, __DIR__ . '/../database/schema.sql');
check('schema.sql 2. kez hatasiz uygulanir', $err === null,
    $err !== null ? sprintf('#%d: %s', $err['stmtno'], $err['error']) : '');

$err = bootstrap_run_sql_file($pdo, __DIR__ . '/../database/seed.sql');
check('seed.sql 2. kez hatasiz uygulanir', $err === null,
    $err !== null ? sprintf('#%d: %s', $err['stmtno'], $err['error']) : '');

$tables2 = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
sort($tables2);
check('tablo sayisi degismedi (10)', $tables2 === $expectedTables, count($tables2) . ' tablo');

$adminCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE email = 'admin@riskops.local'"
)->fetchColumn();
check('admin kopyalanmadi (INSERT IGNORE)', $adminCount === 1, $adminCount . ' satir');

$settings2 = (int)$pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn();
check('ayarlar kopyalanmadi', $settings2 === $settingsRows, $settings2 . ' satir');

/* --- Karalama veritabanini birak ------------------------------------ */

$root->exec('DROP DATABASE IF EXISTS ' . BOOTSTRAP_TEST_DB);

/* ------------------------------------------------------------------ */

echo "\n" . str_repeat('-', 72) . "\n";
printf("Sonuc: %d OK, %d FAIL\n", $PASS, $FAIL);
if ($FAIL > 0) {
    echo "FRESH BOOTSTRAP TEST: FAIL\n";
    exit(1);
}
echo "FRESH BOOTSTRAP TEST: PASS\n";
exit(0);
