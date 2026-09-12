<?php
declare(strict_types=1);

/**
 * RiskOps - Hata ayıklama araç çubuğu
 * /var/www/riskops/includes/partials/debug_toolbar.php
 *
 * footer.php tarafından, YALNIZCA debug_visible() true ise çağrılır.
 *
 * TASARIM KURALLARI
 *   - Satır içi <script> veya style="" YOK. Stil assets/css/debug.css,
 *     davranış assets/js/debug.js dosyasında; ikisi de CSP'nin 'self'
 *     kaynağından gelir.
 *   - Paneller SUNUCUDA basılır. JS'e veri taşınmaz, dolayısıyla
 *     JSON gömmek için <script> bloğuna gerek yoktur.
 *   - Basılan HER değer e() ile kaçışlanır; içerikte kullanıcı verisi
 *     (form girdileri, SQL parametreleri) var.
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}
if (!debug_visible()) {
    return;
}

$store = &debug_store();
$sum   = debug_summary();

/** Yinelenen sorgu sayısı (N+1 işareti). */
$dupTotal = 0;
foreach ($sum['duplicates'] as $n) {
    $dupTotal += $n - 1;
}

/** app.log kuyruğu. */
$logLines = [];
if (is_file(LOG_FILE)) {
    $raw = @file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $logLines = array_slice($raw, -DEBUG_LOG_TAIL_LINES);
    /* Log satırları uygulama tarafından yazılır ve sır içermemeleri
       gerekir; yine de "parola=..." biçimli bir şey kaçmışsa burada
       maskelenir - araç çubuğu tarayıcıya gider. */
    $logLines = array_map(
        static fn (string $l): string => (string)preg_replace(
            '/((?:pass|parola|token|secret|csrf)[^\s=:]*\s*[=:]\s*)(\S+)/i',
            '$1***',
            $l
        ),
        $logLines
    );
}

/**
 * Panel sekmesi. $count null ise rozet basılmaz.
 */
$tab = static function (string $id, string $icon, string $label, ?int $count = null, string $tone = ''): string {
    return '<button type="button" class="rkdbg-tab' . ($tone !== '' ? ' is-' . e($tone) : '') . '"'
        . ' data-rkdbg-tab="' . e($id) . '">'
        . '<i class="bi bi-' . e($icon) . '"></i>'
        . '<span>' . e($label) . '</span>'
        . ($count !== null ? '<em class="rkdbg-count">' . e((string)$count) . '</em>' : '')
        . '</button>';
};
?>
<div class="rkdbg" id="rkdbg" data-rkdbg>

    <div class="rkdbg-bar">

        <span class="rkdbg-brand" title="Hata ayıklama kipi açık (RISKOPS_DEBUG)">
            <i class="bi bi-bug-fill"></i> DEBUG
        </span>

        <?= $tab('queries', 'database', 'Sorgular', $sum['query_count'],
                 $sum['query_failed'] > 0 ? 'bad' : ($sum['query_slow'] > 0 || $dupTotal > 0 ? 'warn' : '')) ?>
        <?= $tab('timeline', 'stopwatch', 'Zaman', $sum['mark_count']) ?>
        <?= $tab('request',  'box-arrow-in-right', 'İstek') ?>
        <?= $tab('session',  'person-badge', 'Oturum') ?>
        <?= $tab('config',   'sliders', 'Ortam') ?>
        <?= $tab('notes',    'journal-text', 'Notlar', $sum['note_count'] + $sum['dump_count'],
                 $sum['dump_count'] > 0 ? 'warn' : '') ?>
        <?= $tab('log',      'file-text', 'Log', count($logLines)) ?>

        <span class="rkdbg-spacer"></span>

        <span class="rkdbg-stat" title="Toplam istek süresi">
            <i class="bi bi-clock"></i> <?= e(debug_ms($sum['time_ms'])) ?>
        </span>
        <span class="rkdbg-stat" title="Sorgularda geçen süre">
            <i class="bi bi-database"></i> <?= e(debug_ms($sum['query_ms'])) ?>
        </span>
        <span class="rkdbg-stat" title="Tepe bellek kullanımı / sınır">
            <i class="bi bi-memory"></i> <?= e(debug_bytes($sum['memory_peak'])) ?>
        </span>
        <span class="rkdbg-stat" title="HTTP durum kodu">
            <i class="bi bi-hash"></i> <?= e((string)$sum['status']) ?>
        </span>

        <a class="rkdbg-x" href="?rkdebug=off" title="Bu oturum için araç çubuğunu gizle">
            <i class="bi bi-x-lg"></i>
        </a>
    </div>

    <div class="rkdbg-body" hidden>

        <!-- ------------------------------------------------ Sorgular -->
        <section class="rkdbg-panel" data-rkdbg-panel="queries" hidden>
            <?php if ($sum['query_count'] === 0): ?>
                <p class="rkdbg-empty">Bu istekte hiç sorgu çalışmadı.</p>
            <?php else: ?>

                <p class="rkdbg-lead">
                    <?= e((string)$sum['query_count']) ?> sorgu ·
                    <?= e(debug_ms($sum['query_ms'])) ?> ·
                    <?= e((string)$sum['query_slow']) ?> yavaş
                    (<?= e((string)DEBUG_SLOW_QUERY_MS) ?> ms üstü)
                    <?php if ($dupTotal > 0): ?>
                        · <strong><?= e((string)$dupTotal) ?> gereksiz tekrar</strong>
                    <?php endif; ?>
                </p>

                <?php if ($sum['duplicates'] !== []): ?>
                <div class="rkdbg-warnbox">
                    <strong>Yinelenen sorgu</strong> — aynı SQL birden fazla kez çalıştı.
                    Çoğu zaman döngü içinde sorgu (N+1) anlamına gelir:
                    <ul>
                        <?php foreach (array_slice($sum['duplicates'], 0, 5, true) as $norm => $n): ?>
                            <li><em><?= e((string)$n) ?>×</em> <?= e(mb_substr((string)$norm, 0, 160)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <ol class="rkdbg-queries">
                    <?php foreach ($store['queries'] as $i => $q): ?>
                        <?php
                        $isDup  = ($sum['duplicates'][$q['norm']] ?? 0) > 1;
                        $isSlow = $q['ms'] >= DEBUG_SLOW_QUERY_MS;
                        $cls    = $q['error'] !== null ? ' is-bad' : ($isSlow ? ' is-slow' : ($isDup ? ' is-dup' : ''));
                        ?>
                        <li class="rkdbg-q<?= $cls ?>">
                            <div class="rkdbg-q-head">
                                <span class="rkdbg-q-no"><?= e((string)($i + 1)) ?></span>
                                <span class="rkdbg-q-ms"><?= e(debug_ms($q['ms'])) ?></span>
                                <?php if ($q['rows'] >= 0): ?>
                                    <span class="rkdbg-q-rows"><?= e((string)$q['rows']) ?> satır</span>
                                <?php endif; ?>
                                <?php if ($isDup): ?>
                                    <span class="rkdbg-q-flag">tekrar</span>
                                <?php endif; ?>
                                <?php if ($isSlow): ?>
                                    <span class="rkdbg-q-flag">yavaş</span>
                                <?php endif; ?>
                                <span class="rkdbg-q-origin"><?= e($q['origin']) ?></span>
                            </div>
                            <pre class="rkdbg-sql"><?= e(debug_format_sql($q['sql'])) ?></pre>
                            <?php if ($q['params'] !== []): ?>
                                <div class="rkdbg-params">
                                    <?php foreach ($q['params'] as $k => $v): ?>
                                        <span><b><?= e((string)$k) ?></b>=<?= e(debug_export($v)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($q['error'] !== null): ?>
                                <p class="rkdbg-q-err"><?= e($q['error']) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>

        <!-- ------------------------------------------------- Zaman -->
        <section class="rkdbg-panel" data-rkdbg-panel="timeline" hidden>
            <p class="rkdbg-lead">
                İstek <?= e(debug_ms($sum['time_ms'])) ?> sürdü; bunun
                <?= e(debug_ms($sum['query_ms'])) ?> kadarı veritabanında geçti.
            </p>

            <?php if ($store['timers'] !== []): ?>
                <h4>Süreölçerler</h4>
                <table class="rkdbg-table">
                    <thead><tr><th>Ad</th><th>Çağrı</th><th>Toplam</th></tr></thead>
                    <tbody>
                    <?php foreach ($store['timers'] as $name => $t): ?>
                        <tr>
                            <td><?= e((string)$name) ?></td>
                            <td><?= e((string)($t['calls'] ?? 0)) ?></td>
                            <td><?= e(debug_ms((float)($t['total'] ?? 0))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($store['counters'] !== []): ?>
                <h4>Sayaçlar</h4>
                <table class="rkdbg-table">
                    <tbody>
                    <?php foreach ($store['counters'] as $name => $n): ?>
                        <tr><td><?= e((string)$name) ?></td><td><?= e((string)$n) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h4>İşaretler</h4>
            <?php if ($store['marks'] === []): ?>
                <p class="rkdbg-empty">
                    İşaret yok. Kod içine <code>debug_mark('etiket')</code> koyarak
                    zaman çizelgesine nokta ekleyebilirsiniz.
                </p>
            <?php else: ?>
                <table class="rkdbg-table">
                    <thead><tr><th>Anı</th><th>İşaret</th><th>Bellek</th></tr></thead>
                    <tbody>
                    <?php foreach ($store['marks'] as $m): ?>
                        <tr>
                            <td><?= e(debug_ms((float)$m['at_ms'])) ?></td>
                            <td><?= e((string)$m['label']) ?></td>
                            <td><?= e(debug_bytes((int)$m['memory'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <!-- -------------------------------------------------- İstek -->
        <section class="rkdbg-panel" data-rkdbg-panel="request" hidden>
            <p class="rkdbg-lead">
                <?= e($sum['method']) ?> <?= e($sum['path']) ?> &rarr; <?= e((string)$sum['status']) ?>
            </p>
            <?php
            $serverKeys = ['REQUEST_METHOD', 'REQUEST_URI', 'SCRIPT_NAME', 'QUERY_STRING',
                           'REMOTE_ADDR', 'SERVER_PORT', 'HTTPS', 'HTTP_HOST',
                           'HTTP_USER_AGENT', 'HTTP_REFERER', 'HTTP_ACCEPT_LANGUAGE',
                           'CONTENT_TYPE', 'CONTENT_LENGTH', 'SERVER_SOFTWARE'];
            $server = [];
            foreach ($serverKeys as $k) {
                if (isset($_SERVER[$k])) { $server[$k] = $_SERVER[$k]; }
            }

            /* $_POST parola taşıyabilir; maskeleme ZORUNLU. */
            $blocks = [
                'GET'    => debug_mask_array($_GET),
                'POST'   => debug_mask_array($_POST),
                'FILES'  => debug_mask_array($_FILES),
                'COOKIE' => debug_mask_array($_COOKIE),
                'SERVER' => debug_mask_array($server),
            ];
            ?>
            <?php foreach ($blocks as $name => $rows): ?>
                <h4>$_<?= e($name) ?><?= $rows === [] ? ' <span class="rkdbg-dim">(boş)</span>' : '' ?></h4>
                <?php if ($rows !== []): ?>
                    <table class="rkdbg-table rkdbg-kv">
                        <tbody>
                        <?php foreach ($rows as $k => $v): ?>
                            <tr><th><?= e((string)$k) ?></th><td><?= e(debug_export($v)) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>

        <!-- ------------------------------------------------- Oturum -->
        <section class="rkdbg-panel" data-rkdbg-panel="session" hidden>
            <p class="rkdbg-lead">
                <?= e($sum['user']) ?>
                <?php if ($sum['user_id'] !== null): ?>
                    · kullanıcı #<?= e((string)$sum['user_id']) ?>
                <?php endif; ?>
                · dil <?= e((string)$sum['locale']) ?>
            </p>

            <h4>Yetkiler</h4>
            <?php if (!auth_check()): ?>
                <p class="rkdbg-empty">Oturum açık değil.</p>
            <?php else: ?>
                <div class="rkdbg-abilities">
                    <?php foreach (role_permissions()[auth_role()] ?? [] as $ability): ?>
                        <span><?= e((string)$ability) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h4>$_SESSION</h4>
            <?php $sess = debug_mask_array($_SESSION ?? []); ?>
            <?php if ($sess === []): ?>
                <p class="rkdbg-empty">(boş)</p>
            <?php else: ?>
                <table class="rkdbg-table rkdbg-kv">
                    <tbody>
                    <?php foreach ($sess as $k => $v): ?>
                        <tr><th><?= e((string)$k) ?></th><td><?= e(debug_export($v)) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <p class="rkdbg-note">
                Oturum kimliği, CSRF jetonu ve parola alanları burada
                <code>***</code> olarak görünür. Bu maskeleme
                <code>tools/debug_test.php</code> ile doğrulanır.
            </p>
        </section>

        <!-- -------------------------------------------------- Ortam -->
        <section class="rkdbg-panel" data-rkdbg-panel="config" hidden>
            <?php
            $pdoDriver = '-';
            $dbVersion = '-';
            try {
                $pdoDriver = (string)db()->getAttribute(PDO::ATTR_DRIVER_NAME);
                $dbVersion = (string)db()->getAttribute(PDO::ATTR_SERVER_VERSION);
            } catch (Throwable) {
                /* Bağlantı yoksa araç çubuğu yine de çizilmeli. */
            }

            $rows = [
                'APP_ENV'          => APP_ENV,
                'APP_DEBUG'        => 'açık',
                'APP_DEMO'         => APP_DEMO ? 'açık' : 'kapalı',
                'BASE_PATH'        => BASE_PATH === '' ? '(kök)' : BASE_PATH,
                'PHP'              => PHP_VERSION . ' / ' . PHP_SAPI,
                'Veritabanı'       => $pdoDriver . ' ' . $dbVersion,
                'Zaman dilimi'     => date_default_timezone_get() . ' (' . date('P') . ')',
                'Bellek sınırı'    => $sum['memory_limit'],
                'Yüklenen dosya'   => (string)$sum['includes'] . ' PHP dosyası',
                'Yavaş sorgu eşiği'=> DEBUG_SLOW_QUERY_MS . ' ms',
                'Debug log'        => str_replace(APP_ROOT . '/', '', DEBUG_LOG_FILE),
                'Debug log kapsamı'=> DEBUG_LOG_ONLY_SLOW
                                        ? 'yalnızca yavaş/hatalı istekler'
                                        : 'her istek',
            ];
            ?>
            <table class="rkdbg-table rkdbg-kv">
                <tbody>
                <?php foreach ($rows as $k => $v): ?>
                    <tr><th><?= e((string)$k) ?></th><td><?= e((string)$v) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="rkdbg-note">
                Veritabanı sunucusu, kullanıcı adı ve parola burada
                <strong>gösterilmez</strong>. Bağlantı bilgisi yalnızca
                <code>config/database.php</code> içindedir ve o dosya
                sürüm kontrolüne girmez.
            </p>
        </section>

        <!-- ------------------------------------------------- Notlar -->
        <section class="rkdbg-panel" data-rkdbg-panel="notes" hidden>
            <h4>dbg() dökümleri</h4>
            <?php if ($store['dumps'] === []): ?>
                <p class="rkdbg-empty">
                    Döküm yok. Kod içinde <code>dbg($deger, 'etiket')</code> yazarak
                    bir değeri buraya basabilirsiniz — <code>var_dump()</code> gibi
                    sayfanın ortasına düşmez, kapalı kipte hiç çalışmaz.
                </p>
            <?php else: ?>
                <?php foreach ($store['dumps'] as $d): ?>
                    <div class="rkdbg-dump">
                        <div class="rkdbg-q-head">
                            <span class="rkdbg-q-ms"><?= e(debug_ms((float)$d['at_ms'])) ?></span>
                            <?php if ($d['label'] !== ''): ?>
                                <span class="rkdbg-q-flag"><?= e((string)$d['label']) ?></span>
                            <?php endif; ?>
                            <span class="rkdbg-q-origin"><?= e((string)$d['origin']) ?></span>
                        </div>
                        <pre class="rkdbg-sql"><?= e((string)$d['value']) ?></pre>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <h4>Notlar</h4>
            <?php if ($store['notes'] === []): ?>
                <p class="rkdbg-empty">
                    Not yok. <code>debug_note('kanal', 'mesaj', [...])</code>
                    ile bu isteğe özel not bırakılabilir (diske yazılmaz).
                </p>
            <?php else: ?>
                <table class="rkdbg-table">
                    <thead><tr><th>Anı</th><th>Kanal</th><th>Mesaj</th><th>Kaynak</th></tr></thead>
                    <tbody>
                    <?php foreach ($store['notes'] as $n): ?>
                        <tr>
                            <td><?= e(debug_ms((float)$n['at_ms'])) ?></td>
                            <td><?= e((string)$n['channel']) ?></td>
                            <td>
                                <?= e((string)$n['message']) ?>
                                <?php if ($n['context'] !== []): ?>
                                    <span class="rkdbg-dim"><?= e(debug_export($n['context'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="rkdbg-q-origin"><?= e((string)$n['origin']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <!-- ---------------------------------------------------- Log -->
        <section class="rkdbg-panel" data-rkdbg-panel="log" hidden>
            <p class="rkdbg-lead">
                <code><?= e(str_replace(APP_ROOT . '/', '', LOG_FILE)) ?></code>
                son <?= e((string)count($logLines)) ?> satır
            </p>
            <?php if ($logLines === []): ?>
                <p class="rkdbg-empty">Log dosyası boş ya da okunamıyor.</p>
            <?php else: ?>
                <pre class="rkdbg-log"><?php foreach ($logLines as $l): ?><?= e($l) ?>
<?php endforeach; ?></pre>
            <?php endif; ?>
        </section>

    </div>
</div>
