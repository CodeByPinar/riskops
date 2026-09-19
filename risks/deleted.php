<?php
declare(strict_types=1);

/**
 * RiskOps - Silinen riskler (geri alma ekranı)
 * /var/www/riskops/risks/deleted.php
 *
 * Silme işlemi "soft delete"tir: kayıt fiziksel olarak silinmez,
 * yalnızca deleted_at damgalanır. Bu ekran o kayıtları görünür kılar
 * ve geri almayı mümkün hâle getirir.
 *
 * NEDEN YALNIZCA ADMIN: silme yetkisi admin'de (bkz. risks/delete.php).
 * Geri alma da aynı yetkiye bağlı olmalı; aksi hâlde daha düşük yetkili
 * bir kullanıcı, admin'in bilinçli olarak kaldırdığı bir kaydı geri
 * getirebilirdi.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_role(ROLE_ADMIN);

$total = (int)db_value(
    'SELECT COUNT(*) FROM risks WHERE deleted_at IS NOT NULL'
);

$perPage = per_page();
$page    = paginate($total, $perPage, input_int('page', 1) ?? 1);
$offset  = $page['offset'];

/* LIMIT/OFFSET tamsayı olarak bağlanamaz (MySQL prepared statement
   kısıtı); değerler yukarıda int'e zorlandığı için doğrudan gömülüyor. */
$rows = db_all(
    "SELECT r.id, r.risk_code, r.title, r.status, r.inherent_severity,
            r.inherent_score, r.deleted_at,
            d.name  AS department_name,
            c.name  AS category_name,
            du.name AS deleted_by_name
       FROM risks r
       LEFT JOIN departments     d  ON d.id  = r.department_id
       LEFT JOIN risk_categories c  ON c.id  = r.category_id
       LEFT JOIN users           du ON du.id = r.deleted_by
      WHERE r.deleted_at IS NOT NULL
      ORDER BY r.deleted_at DESC
      LIMIT {$perPage} OFFSET {$offset}"
);

$pageTitle    = t('Silinen Riskler');
$pageSubtitle = $total . ' silinmiş kayıt · geri alınabilir';
$activeMenu   = 'risks.deleted';

require LAYOUT_PATH . '/header.php';
?>

<div class="rk-alert rk-alert-info">
    <i class="bi bi-info-circle-fill"></i>
    <div>
        Bu kayıtlar <strong>silinmedi</strong>, yalnızca listelerden kaldırıldı
        (soft delete). Risk kayıtları kurumsal kanıt niteliğindedir; denetimde
        &laquo;kim, ne zaman, neyi kaldırdı&raquo; sorusu cevaplanabilmelidir.
        Geri alma işlemi de audit log'a yazılır.
    </div>
</div>

<div class="rk-card">
    <div class="rk-card-body p-0">
        <?php if ($rows === []): ?>
            <?= empty_state(
                t('Silinmiş risk kaydı yok'),
                t('Bir risk silindiğinde burada listelenir ve geri alınabilir.'),
                'bi-trash3'
            ) ?>
        <?php else: ?>
        <div class="rk-table-wrap">
            <table class="rk-table">
                <thead>
                    <tr>
                        <th><?= te('Kod') ?></th>
                        <th><?= te('Başlık') ?></th>
                        <th><?= te('Departman') ?></th>
                        <th><?= te('Kategori') ?></th>
                        <th><?= te('Seviye') ?></th>
                        <th><?= te('Silinme') ?></th>
                        <th><?= te('Silen') ?></th>
                        <th class="text-end"><?= te('İşlem') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><code class="rk-code"><?= e($r['risk_code']) ?></code></td>
                        <td><span class="rk-cell-wrap"><?= e($r['title']) ?></span></td>
                        <td><?= e($r['department_name'] ?? '-') ?></td>
                        <td><?= e($r['category_name'] ?? '-') ?></td>
                        <td>
                            <?= severity_badge($r['inherent_severity'], true) ?>
                            <?= score_chip((int)$r['inherent_score']) ?>
                        </td>
                        <td><?= e(format_datetime($r['deleted_at'])) ?></td>
                        <td><?= e($r['deleted_by_name'] ?? '-') ?></td>
                        <td class="text-end">
                            <?php /* GET ile geri alma YOK: durum degistiren her
                                     islem POST + CSRF ister. */ ?>
                            <form method="post"
                                  action="<?= e(url('/risks/restore.php')) ?>"
                                  class="d-inline m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="rk-btn rk-btn-sm">
                                    <i class="bi bi-arrow-counterclockwise"></i> <?= te('Geri al') ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php if ($page['pages'] > 1): ?>
<div class="rk-pagination">
    <div class="rk-pagination-info">
        <?= $page['from'] ?>-<?= $page['to'] ?> / <?= $page['total'] ?> kayıt
    </div>
    <div class="rk-pagination-links">
        <?php if ($page['has_prev']): ?>
            <a class="rk-btn rk-btn-sm" href="<?= e(query_url(['page' => $page['current'] - 1])) ?>">Önceki</a>
        <?php endif; ?>
        <?php
        $start = max(1, $page['current'] - 2);
        $end   = min($page['pages'], $start + 4);
        $start = max(1, $end - 4);
        for ($p = $start; $p <= $end; $p++): ?>
            <a class="rk-btn rk-btn-sm<?= $p === $page['current'] ? ' is-current' : '' ?>"
               href="<?= e(query_url(['page' => $p])) ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page['has_next']): ?>
            <a class="rk-btn rk-btn-sm" href="<?= e(query_url(['page' => $page['current'] + 1])) ?>">Sonraki</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require LAYOUT_PATH . '/footer.php'; ?>
