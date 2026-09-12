<?php
declare(strict_types=1);

/**
 * RiskOps - Ayrıntılı istisna sayfası (yalnızca hata ayıklama kipinde)
 * /var/www/riskops/errors/debug_exception.php
 *
 * bootstrap.php içindeki set_exception_handler() tarafından, YALNIZCA
 * debug_enabled() true iken çağrılır. Kapalıyken kullanıcı her zamanki
 * sade 500 sayfasını görür ve ayrıntı yalnızca log dosyasına yazılır.
 *
 * Beklenen değişken: $rkThrowable
 *
 * NEDEN KENDİ KENDİNE YETİYOR
 * ---------------------------
 * İstisna, düzen dosyalarının ortasında da oluşmuş olabilir; o anda
 * header.php çalışmış, footer.php çalışmamış olur. Bu sayfa hiçbir
 * düzen parçasına bağlı değildir ve stilini nonce taşıyan tek bir
 * <style> bloğunda kendisi basar (CSP: style-src 'self' 'nonce-...').
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

/** @var Throwable $rkThrowable */
$e = $rkThrowable;

if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
}

/** Dosyadan kaynak parçası okur, hatalı satırı işaretler. */
$excerpt = static function (string $file, int $line, int $pad = 6): array {
    if ($file === '' || !is_file($file) || !is_readable($file)) {
        return [];
    }
    $lines = @file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return [];
    }
    $from = max(0, $line - $pad - 1);
    $to   = min(count($lines), $line + $pad);
    $out  = [];
    for ($i = $from; $i < $to; $i++) {
        $out[$i + 1] = $lines[$i];
    }

    return $out;
};

/** APP_ROOT'a göre kısaltılmış yol. */
$rel = static fn (string $path): string => $path !== '' && str_starts_with($path, APP_ROOT)
    ? ltrim(substr($path, strlen(APP_ROOT)), '/\\')
    : $path;

/* İstisna zinciri: asıl sebep genelde en alttakidir. */
$chain = [];
for ($t = $e; $t !== null; $t = $t->getPrevious()) {
    $chain[] = $t;
}

/**
 * Hatanın FIRLATILDIĞI yer ile İLGİLENİLEN yer aynı olmayabilir.
 *
 * Örnek: bozuk bir SQL, hata ayıklama kipinde PDO'yu saran
 * includes/db_debug.php içinde patlar. Teknik olarak doğru ama
 * geliştiriciye faydasız - onun görmek istediği, o sorguyu yazdığı
 * kendi satırı. Burada yığın taranıp APP_ROOT altındaki ilk
 * "kendi kodumuz" karesi seçiliyor; altyapı dosyaları atlanıyor.
 */
$infra = ['includes/db_debug.php', 'includes/db.php', 'includes/debug.php'];

$appFrame = static function (Throwable $t) use ($rel, $infra): array {
    $candidates = array_merge(
        [['file' => $t->getFile(), 'line' => $t->getLine()]],
        $t->getTrace()
    );

    foreach ($candidates as $f) {
        $file = (string)($f['file'] ?? '');
        if ($file === '' || !str_starts_with($file, APP_ROOT)) {
            continue;
        }
        if (in_array(str_replace('\\', '/', $rel($file)), $infra, true)) {
            continue;
        }

        return ['file' => $file, 'line' => (int)($f['line'] ?? 0)];
    }

    return ['file' => $t->getFile(), 'line' => $t->getLine()];
};

$origin  = $appFrame($e);
$thrownElsewhere = $origin['file'] !== $e->getFile() || $origin['line'] !== $e->getLine();

$store = &debug_store();
$sum   = debug_summary();

$css = <<<'CSS'
*{box-sizing:border-box}
body{margin:0;background:#f4f8fd;color:#041f3c;
     font:13.5px/1.6 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
.wrap{max-width:1180px;margin:0 auto;padding:26px 22px 60px}
.head{background:#fff;border:1px solid #e6eefa;border-radius:16px;padding:22px 24px;
      box-shadow:0 2px 4px rgba(6,32,63,.03),0 18px 40px rgba(6,32,63,.08)}
.tag{display:inline-block;background:#b91c1c;color:#fff;border-radius:7px;
     padding:3px 10px;font-size:11px;font-weight:700;letter-spacing:.6px}
.cls{margin:12px 0 4px;font-size:15px;color:#991b1b;font-weight:700}
.msg{margin:0 0 12px;font-size:19px;line-height:1.45;font-weight:600;
     font-family:-apple-system,"Segoe UI",Roboto,sans-serif;color:#06203f}
.loc{color:#5c7897;font-size:12.5px}
.loc b{color:#041f3c}
.meta{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
.meta span{background:#f3f8fe;border:1px solid #e1ecfb;border-radius:8px;
           padding:4px 10px;font-size:11.5px;color:#234066}
h2{font-family:-apple-system,"Segoe UI",Roboto,sans-serif;font-size:13px;
   text-transform:uppercase;letter-spacing:.8px;color:#5c7897;margin:30px 0 10px}
.card{background:#fff;border:1px solid #e6eefa;border-radius:14px;overflow:hidden;
      box-shadow:0 1px 2px rgba(6,32,63,.03),0 8px 20px rgba(6,32,63,.05);margin-bottom:14px}
.src{margin:0;overflow-x:auto}
.src table{border-collapse:collapse;width:100%;font-size:12.5px}
.src td{padding:2px 12px;white-space:pre}
.src td.n{width:56px;text-align:right;color:#90a7c2;background:#f8fbff;
          border-right:1px solid #e6eefa;user-select:none}
.src tr.hit td{background:#fef2f2}
.src tr.hit td.n{background:#fee2e2;color:#991b1b;font-weight:700}
.frame{border-bottom:1px solid #e6eefa;padding:10px 14px;display:flex;gap:10px;
       align-items:baseline;flex-wrap:wrap}
.frame:last-child{border-bottom:0}
.frame .i{color:#90a7c2;width:26px;flex:none}
.frame .fn{color:#0056b4;font-weight:600}
.frame .at{color:#5c7897;font-size:12px}
.frame.app{background:#f8fbff}
.q{border-bottom:1px solid #e6eefa;padding:10px 14px}
.q:last-child{border-bottom:0}
.q pre{margin:6px 0 0;white-space:pre-wrap;font-size:12.5px;color:#234066}
.q .h{display:flex;gap:10px;font-size:11.5px;color:#5c7897}
.q.bad{background:#fef2f2}
.kv{width:100%;border-collapse:collapse;font-size:12.5px}
.kv th{text-align:left;width:210px;padding:6px 14px;color:#5c7897;font-weight:600;
       border-bottom:1px solid #eef4fb;vertical-align:top}
.kv td{padding:6px 14px;border-bottom:1px solid #eef4fb;word-break:break-word}
.empty{padding:14px;color:#90a7c2}
.foot{margin-top:26px;color:#90a7c2;font-size:11.5px;
      font-family:-apple-system,"Segoe UI",Roboto,sans-serif}
CSS;
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(get_class($e)) ?> - RiskOps</title>
<?= style_block($css) ?>
</head>
<body>
<div class="wrap">

    <div class="head">
        <span class="tag">HATA AYIKLAMA</span>
        <div class="cls"><?= e(get_class($e)) ?></div>
        <p class="msg"><?= e($e->getMessage()) ?></p>
        <div class="loc">
            <b><?= e($rel($origin['file'])) ?></b>:<?= e((string)$origin['line']) ?>
            <?php if ($e->getCode() !== 0 && $e->getCode() !== ''): ?>
                &middot; kod <?= e((string)$e->getCode()) ?>
            <?php endif; ?>
            <?php if ($thrownElsewhere): ?>
                <br>fırlatıldığı yer:
                <?= e($rel($e->getFile())) ?>:<?= e((string)$e->getLine()) ?>
            <?php endif; ?>
        </div>
        <div class="meta">
            <span><?= e($sum['method']) ?> <?= e($sum['path']) ?></span>
            <span><?= e($sum['user']) ?></span>
            <span><?= e(debug_ms($sum['time_ms'])) ?></span>
            <span><?= e((string)$sum['query_count']) ?> sorgu</span>
            <span><?= e(debug_bytes($sum['memory_peak'])) ?></span>
            <span>PHP <?= e(PHP_VERSION) ?></span>
            <span><?= e(APP_ENV) ?></span>
        </div>
    </div>

    <?php foreach ($chain as $ci => $t): ?>
        <?php $loc = $appFrame($t); ?>
        <?php if ($ci > 0): ?>
            <h2>Önceki istisna: <?= e(get_class($t)) ?> — <?= e($t->getMessage()) ?></h2>
        <?php else: ?>
            <h2>
                Hatanın oluştuğu yer
                &mdash; <?= e($rel($loc['file'])) ?>:<?= e((string)$loc['line']) ?>
            </h2>
        <?php endif; ?>

        <div class="card src">
            <?php $lines = $excerpt($loc['file'], $loc['line']); ?>
            <?php if ($lines === []): ?>
                <p class="empty">Kaynak dosya okunamadı: <?= e($rel($loc['file'])) ?></p>
            <?php else: ?>
                <table>
                    <tbody>
                    <?php foreach ($lines as $no => $code): ?>
                        <tr<?= $no === $loc['line'] ? ' class="hit"' : '' ?>>
                            <td class="n"><?= e((string)$no) ?></td>
                            <td><?= e($code) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <h2>Çağrı yığını</h2>
    <div class="card">
        <?php foreach ($e->getTrace() as $i => $f): ?>
            <?php
            $file  = (string)($f['file'] ?? '');
            $isApp = $file !== '' && str_starts_with($file, APP_ROOT)
                  && !in_array(str_replace('\\', '/', $rel($file)), $infra, true);
            $fn    = ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '?');
            ?>
            <div class="frame<?= $isApp ? ' app' : '' ?>">
                <span class="i">#<?= e((string)$i) ?></span>
                <span class="fn"><?= e($fn) ?>()</span>
                <span class="at">
                    <?= $file !== '' ? e($rel($file)) . ':' . e((string)($f['line'] ?? 0)) : '[dahili]' ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <h2>Bu istekte çalışan sorgular (<?= e((string)$sum['query_count']) ?>)</h2>
    <div class="card">
        <?php if ($store['queries'] === []): ?>
            <p class="empty">Hiç sorgu çalışmadı.</p>
        <?php else: ?>
            <?php foreach ($store['queries'] as $i => $q): ?>
                <div class="q<?= $q['error'] !== null ? ' bad' : '' ?>">
                    <div class="h">
                        <span>#<?= e((string)($i + 1)) ?></span>
                        <span><?= e(debug_ms((float)$q['ms'])) ?></span>
                        <span><?= e((string)$q['origin']) ?></span>
                        <?php if ($q['error'] !== null): ?>
                            <span><?= e((string)$q['error']) ?></span>
                        <?php endif; ?>
                    </div>
                    <pre><?= e(debug_format_sql((string)$q['sql'])) ?></pre>
                    <?php if ($q['params'] !== []): ?>
                        <pre><?= e(debug_export($q['params'])) ?></pre>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <h2>İstek</h2>
    <div class="card">
        <table class="kv">
            <tbody>
            <?php
            $blocks = [
                '$_GET'     => debug_mask_array($_GET),
                '$_POST'    => debug_mask_array($_POST),
                '$_SESSION' => debug_mask_array($_SESSION ?? []),
            ];
            foreach ($blocks as $name => $rows):
                ?>
                <tr>
                    <th><?= e($name) ?></th>
                    <td><?= $rows === [] ? '<span class="empty">(boş)</span>' : e(debug_export($rows)) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr><th>Log dosyası</th><td><?= e($rel(LOG_FILE)) ?></td></tr>
            </tbody>
        </table>
    </div>

    <p class="foot">
        Bu sayfa yalnızca hata ayıklama kipinde görünür
        (<code>RISKOPS_DEBUG=1</code>). Kip kapalıyken aynı hata sade bir
        500 sayfası olarak görünür ve ayrıntı yalnızca
        <?= e($rel(LOG_FILE)) ?> dosyasına yazılır.
    </p>
</div>
</body>
</html>
