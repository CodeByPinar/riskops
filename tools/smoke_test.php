<?php

declare(strict_types=1);

/**
 * RiskOps - Phase 1 CLI doğrulama testi
 * Calistirma:  php /var/www/riskops/tools/smoke_test.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../includes/bootstrap.php';

$PASS = 0;
$FAIL = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $PASS, $FAIL;
    if ($ok) {
        $PASS++;
        printf("  [ OK ]  %-44s %s\n", $label, $detail);
    } else {
        $FAIL++;
        printf("  [FAIL]  %-44s %s\n", $label, $detail);
    }
}

function section(string $title): void
{
    echo "\n" . str_repeat('-', 72) . "\n  " . $title . "\n" . str_repeat('-', 72) . "\n";
}

echo "\n==================== RiskOps Phase 1 Smoke Test ====================\n";

/* ------------------------------------------------------------------ */
section('1. PHP ortami');

check('PHP >= 8.1', PHP_VERSION_ID >= 80100, PHP_VERSION);

foreach (['pdo', 'pdo_mysql', 'mbstring', 'json', 'curl', 'xml', 'intl', 'gd', 'zip'] as $ext) {
    check("extension: {$ext}", extension_loaded($ext));
}

/* ------------------------------------------------------------------ */
section('2. Dizinler ve loglama');

check('APP_ROOT doğru', APP_ROOT === '/var/www/riskops', APP_ROOT);
check('storage/logs mevcut', is_dir(LOG_PATH));
check('storage/logs yazilabilir', is_writable(LOG_PATH));

app_log('info', 'smoke_test çalıştı');
check('app.log yazıldı', is_file(LOG_FILE) && filesize(LOG_FILE) > 0,
    is_file(LOG_FILE) ? filesize(LOG_FILE) . ' bayt' : '');

/* ------------------------------------------------------------------ */
section('3. Veritabani');

$pdo = db();
check('PDO bağlantısı', $pdo instanceof PDO);
check('SELECT 1 çalışıyor', (int)db_value('SELECT 1') === 1);
check('ERRMODE_EXCEPTION aktif',
    $pdo->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION);
check('EMULATE_PREPARES kapalı',
    $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) == false);

$expected = ['action_attachments', 'action_comments', 'audit_logs', 'departments',
             'login_attempts', 'risk_actions', 'risk_assessments',
             'risk_attachments', 'risk_categories', 'risk_comments',
             'risk_sequences', 'risks', 'settings', 'users'];
$tables = db_column('SHOW TABLES');
sort($tables);
check(count($expected) . ' tablo mevcut', $tables === $expected,
    count($tables) . ' tablo: ' . implode(', ', array_diff($tables, $expected))
    . ' fazla / ' . implode(', ', array_diff($expected, $tables)) . ' eksik');

// Native int donuyor mu?
$one = db_row('SELECT COUNT(*) AS c FROM departments');
check('INT kolonlar native int', is_int($one['c']), gettype($one['c']));

/* ------------------------------------------------------------------ */
section('4. Ayarlar ve zaman dilimi');

$settings = settings_all();
// Sabit sayı beklemek yanlıştı: yeni ayar eklendiğinde test kırılıyordu.
// Doğru varsayım: settings tablosundaki her satır belleğe yüklenmiş olmalı.
$settingsInDb = (int)db_value('SELECT COUNT(*) FROM settings');
check('settings tamamı yüklendi', count($settings) === $settingsInDb,
    count($settings) . ' / ' . $settingsInDb);
check('zorunlu anahtarlar mevcut',
    isset($settings['application_name'], $settings['timezone'],
          $settings['severity_thresholds'], $settings['items_per_page']));
check('app_name()', app_name() === 'RiskOps', app_name());
check('per_page() int', per_page() === 25, (string)per_page());
check('timezone Europe/Istanbul',
    date_default_timezone_get() === 'Europe/Istanbul', date_default_timezone_get());
check('severity_thresholds json -> array',
    is_array(setting('severity_thresholds')) && count(setting('severity_thresholds')) === 4);
check('simdiki zaman', true, date('Y-m-d H:i:s T'));

/* MySQL ile PHP ayni ani gostermeli.
   Kaydiginda: tum tarihler yanlis gorunur VE brute-force penceresi,
   parola-degisti karsilastirmasi gibi zamana dayali kontroller sessizce
   calismaz hale gelir. Bu kontrol o regresyonu yakalar. */
$mysqlNow = (string)db_value('SELECT NOW()');
$skew     = abs(strtotime(date('Y-m-d H:i:s')) - strtotime($mysqlNow));
check('MySQL saat dilimi PHP ile ayni', $skew <= 2,
    'fark ' . $skew . ' sn (PHP ' . date('H:i:s') . ' / MySQL ' . substr($mysqlNow, 11) . ')');

/* ------------------------------------------------------------------ */
section('5. Risk skoru ve severity mantığı');

check('risk_score(4,5) = 20', risk_score(4, 5) === 20);
check('risk_score sinir dışı değer kirpiliyor', risk_score(9, 9) === 25);

$cases = [1 => 'Low', 4 => 'Low', 5 => 'Medium', 9 => 'Medium',
          10 => 'High', 16 => 'High', 17 => 'Critical', 25 => 'Critical'];
$sevOk = true;
$sevDetail = [];
foreach ($cases as $score => $want) {
    $got = severity_from_score($score);
    $sevDetail[] = "{$score}:{$got}";
    if ($got !== $want) {
        $sevOk = false;
    }
}
check('severity eşikleri doğru', $sevOk, implode(' ', $sevDetail));

check('severity_class(Critical)', severity_class('Critical') === 'sev-critical');
check('is_overdue(dun, Open)', is_overdue(date('Y-m-d', strtotime('-1 day')), 'Open') === true);
check('is_overdue(dun, Completed)', is_overdue(date('Y-m-d', strtotime('-1 day')), 'Completed') === false);
check('is_overdue(yarin, Open)', is_overdue(date('Y-m-d', strtotime('+1 day')), 'Open') === false);

/* ------------------------------------------------------------------ */
section('6. Risk kodu üretimi (transaction icinde, geri alınır)');

// Sayacin rollback ile eski degerine dondugunu dogrula.
// (Tablonun BOS kalmasini beklemek yanlisti: gerçek risk kayıtları
//  olduğunda o yilin sayac satırı kalici olarak var olur.)
$seqSql = 'SELECT COALESCE(MAX(last_number), 0) FROM risk_sequences WHERE seq_year = ' . (int)date('Y');
$seqBefore = (int)db_value($seqSql);

$pdo->beginTransaction();
$c1 = next_risk_code($pdo);
$c2 = next_risk_code($pdo);
$pdo->rollBack();

$seqAfter = (int)db_value($seqSql);

$year = date('Y');
check('format RISK-YYYY-NNNN',
    (bool)preg_match('/^RISK-' . $year . '-\d{4}$/', $c1), $c1);
check('ardisik kod uretiliyor', $c1 !== $c2, "{$c1} -> {$c2}");

check('rollback sayacı geri aldi', $seqBefore === $seqAfter, "{$seqBefore} -> {$seqAfter}");

/* ------------------------------------------------------------------ */
section('7. Yetki matrisi');

$matrix = [
    ['admin',   'risk.delete',     true],
    ['admin',   'settings.manage', true],
    ['manager', 'risk.create',     true],
    ['manager', 'risk.delete',     false],
    ['manager', 'settings.manage', false],
    ['analyst', 'risk.assess',     true],
    ['analyst', 'user.view',       false],
    ['viewer',  'risk.view',       true],
    ['viewer',  'risk.create',     false],
    ['viewer',  'action.complete', false],
];

foreach ($matrix as [$role, $ability, $want]) {
    $_SESSION['user_id']   = 1;
    $_SESSION['user_role'] = $role;
    $got = can($ability);
    check(sprintf('%-8s -> %-16s = %s', $role, $ability, $want ? 'izinli' : 'yasak'), $got === $want);
}
$_SESSION = [];

/* ------------------------------------------------------------------ */
section('8. Çıktı kaçışlama (XSS)');

check('e() script etiketini kaçırıyor',
    e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;');
check('e() çift tırnağı kaçırıyor', e('a"b') === 'a&quot;b');
check('e() tek tırnağı kaçırıyor', e("a'b") === 'a&#039;b');
check('e(null) boş string', e(null) === '');
check('str_limit kisaltiyor', str_limit(str_repeat('a', 100), 20) === str_repeat('a', 19) . '...');

/* ------------------------------------------------------------------ */
section('9. Audit log yazimi (test kaydı silinir)');

audit('smoke_test', 'system', 0, ['before' => 1], ['after' => 2, 'password' => 'gizli'], 1, 'Smoke Test');

$row = db_row(
    "SELECT * FROM audit_logs WHERE action='smoke_test' ORDER BY id DESC LIMIT 1"
);

check('audit kaydı oluştu', $row !== null);
if ($row !== null) {
    $new = json_decode((string)$row['new_values'], true);
    check('old_values geçerli JSON', json_decode((string)$row['old_values'], true) === ['before' => 1]);
    check('parola maskelendi', ($new['password'] ?? '') === '***');
    check('user_name_snapshot yazıldı', $row['user_name_snapshot'] === 'Smoke Test');
    $pdo->exec("DELETE FROM audit_logs WHERE action='smoke_test'");
}
$left = (int)db_value("SELECT COUNT(*) FROM audit_logs WHERE action='smoke_test'");
check('test kaydı temizlendi', $left === 0);

/* ------------------------------------------------------------------ */
section('10. audit_diff');

[$old, $new] = audit_diff(
    ['title' => 'Eski', 'status' => 'Open',        'updated_at' => 'x'],
    ['title' => 'Yeni', 'status' => 'Open',        'updated_at' => 'y']
);
check('sadece degisen alan yakalandi', $old === ['title' => 'Eski'] && $new === ['title' => 'Yeni'],
    (string)json_encode($new, JSON_UNESCAPED_UNICODE));

/* ------------------------------------------------------------------ */
echo "\n" . str_repeat('=', 72) . "\n";
printf("  SONUC:  %d başarılı,  %d başarısız\n", $PASS, $FAIL);
echo str_repeat('=', 72) . "\n\n";

exit($FAIL === 0 ? 0 : 1);
