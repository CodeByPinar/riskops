<?php
declare(strict_types=1);

/**
 * RiskOps - Risk detayı
 * /var/www/riskops/risks/view.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/attachments.php';
require_once __DIR__ . '/../includes/discussion.php';

require_login();
require_can('risk.view');

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing risk id');
}

$risk = db_row(
    'SELECT r.*,
            c.name AS category, c.color AS category_color,
            d.name AS department,
            o.name AS owner_name, o.email AS owner_email,
            cb.name AS created_by_name,
            db2.name AS deleted_by_name
     FROM risks r
     JOIN risk_categories c ON c.id = r.category_id
     JOIN departments     d ON d.id = r.department_id
     JOIN users           o ON o.id = r.owner_id
     JOIN users           cb ON cb.id = r.created_by
     LEFT JOIN users      db2 ON db2.id = r.deleted_by
     WHERE r.id = :id AND r.deleted_at IS NULL
     LIMIT 1',
    [':id' => $id]
);

if ($risk === null) {
    app_abort(404, 'Risk not found: ' . $id);
}

/* --- İlişkili kayıtlar ------------------------------------------------ */

$actions = db_all(
    'SELECT a.*, u.name AS owner_name
     FROM risk_actions a
     JOIN users u ON u.id = a.owner_id
     WHERE a.risk_id = :id
     ORDER BY FIELD(a.status, :s1, :s2, :s3, :s4),
              a.due_date IS NULL, a.due_date ASC, a.id DESC',
    [
    ':id' => $id, ':s1' => 'Open', ':s2' => 'In Progress',
    ':s3' => 'Completed', ':s4' => 'Cancelled',
]
);

$assessments = db_all(
    'SELECT a.*, u.name AS assessor
     FROM risk_assessments a
     JOIN users u ON u.id = a.assessed_by
     WHERE a.risk_id = :id
     ORDER BY a.assessed_at DESC, a.id DESC',
    [':id' => $id]
);

$auditStmt = db_stmt(
    'SELECT * FROM audit_logs
     WHERE entity_type = :t AND entity_id = :id
     ORDER BY id DESC
     LIMIT 100',
    [':t' => 'risk', ':id' => $id]
);
$auditRows = can('audit.view') || auth_role() === ROLE_ADMIN ? $auditStmt->fetchAll() : [];

/* Yorum ve ekler ortak katmandan gelir (includes/discussion.php):
   ayni kod aksiyon detayinda da kullaniliyor. */
$dType       = 'risk';
$dParentId   = (int)$risk['id'];
$comments    = discussion_comments($dType, $dParentId);
$attachments = discussion_attachments($dType, $dParentId);

/* Yorum ve ek yazma yetkisi risk.update'e bagli.
   Neden risk.view degil: viewer rolu bilincli olarak SALT OKUNURDUR.
   Yorum yazabilmek, herkese acik demo kurulumunda ziyaretcilerin
   kalici icerik birakabilmesi demekti. */
$canWrite = can('risk.update');

/* --- Türetilmiş değerler --------------------------------------------- */

$effSeverity = $risk['residual_severity'] ?? $risk['inherent_severity'];
$effScore    = $risk['residual_score'] !== null ? (int)$risk['residual_score'] : (int)$risk['inherent_score'];

$openActions = 0;
$overdueActions = 0;
foreach ($actions as $a) {
    if (in_array($a['status'], action_open_statuses(), true)) {
        $openActions++;
        if (is_overdue($a['due_date'], $a['status'])) {
            $overdueActions++;
        }
    }
}

$riskOverdue = is_overdue($risk['target_date'], $risk['status'], risk_open_statuses());

/* --- Sayfa ------------------------------------------------------------ */

$pageTitle    = (string)$risk['risk_code'];
$pageSubtitle = str_limit($risk['title'], 110);
$activeMenu   = 'risks';

$pageActions = '<a class="rk-btn" href="' . e(url('/risks/')) . '">'
             . '<i class="bi bi-arrow-left"></i> Listeye dön</a>';

if (can('risk.update')) {
    $pageActions .= '<a class="rk-btn rk-btn-primary" href="' . e(url('/risks/edit.php?id=' . $id)) . '">'
                  . '<i class="bi bi-pencil"></i> Düzenle</a>';
}

require LAYOUT_PATH . '/header.php';

/** Tanım listesi satırı. */
$row = static function (string $label, string $valueHtml, bool $raw = true): void {
    echo '<div class="rk-dl-row"><dt>' . e($label) . '</dt><dd>'
       . ($valueHtml !== '' ? $valueHtml : '<span class="text-muted">-</span>')
       . '</dd></div>';
};
?>

<?php if ($riskOverdue): ?>
<div class="rk-alert rk-alert-warning">
    <i class="bi bi-clock-history"></i>
    <div>
        Bu riskin hedef tarihi <strong><?= e(format_date($risk['target_date'])) ?></strong> idi ve
        <strong><?= abs((int)days_until($risk['target_date'])) ?> gün</strong> gecikmede.
        Durum halen &laquo;<?= e($risk['status']) ?>&raquo;.
    </div>
</div>
<?php endif; ?>

<!-- ===================== Skor özeti ===================== -->
<div class="rk-stats">
    <div class="rk-stat <?= e('is-' . strtolower($risk['inherent_severity'])) ?>">
        <div class="rk-stat-icon"><i class="bi bi-fire"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= (int)$risk['inherent_score'] ?></div>
            <div class="rk-stat-label">Inherent (<?= e($risk['inherent_severity']) ?>)</div>
        </div>
    </div>

    <div class="rk-stat <?= $risk['residual_severity'] !== null ? e('is-' . strtolower($risk['residual_severity'])) : 'is-neutral' ?>">
        <div class="rk-stat-icon"><i class="bi bi-shield-check"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= $risk['residual_score'] !== null ? (int)$risk['residual_score'] : '-' ?></div>
            <div class="rk-stat-label">
                Residual<?= $risk['residual_severity'] !== null ? ' (' . e($risk['residual_severity']) . ')' : '' ?>
            </div>
        </div>
    </div>

    <div class="rk-stat is-primary">
        <div class="rk-stat-icon"><i class="bi bi-check2-square"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= $openActions ?></div>
            <div class="rk-stat-label"><?= te('Açık Aksiyon') ?></div>
        </div>
    </div>

    <div class="rk-stat <?= $overdueActions > 0 ? 'is-critical' : 'is-neutral' ?>">
        <div class="rk-stat-icon"><i class="bi bi-clock-history"></i></div>
        <div class="rk-stat-body">
            <div class="rk-stat-value"><?= $overdueActions ?></div>
            <div class="rk-stat-label"><?= te('Geciken Aksiyon') ?></div>
        </div>
    </div>
</div>

<!-- ===================== Sekmeler ===================== -->
<div class="rk-card">
    <div class="rk-tabs">
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview"
                        type="button" role="tab">
                    <i class="bi bi-info-circle"></i> <?= te('Genel') ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-actions"
                        type="button" role="tab">
                    <i class="bi bi-check2-square"></i> Aksiyon Planları
                    <span class="rk-tab-count"><?= count($actions) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-assessments"
                        type="button" role="tab">
                    <i class="bi bi-clipboard-data"></i> Değerlendirme Geçmişi
                    <span class="rk-tab-count"><?= count($assessments) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-comments"
                        type="button" role="tab">
                    <i class="bi bi-chat-left-text"></i> Yorumlar
                    <span class="rk-tab-count"><?= count($comments) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-attachments"
                        type="button" role="tab">
                    <i class="bi bi-paperclip"></i> Ekler
                    <span class="rk-tab-count"><?= count($attachments) ?></span>
                </button>
            </li>
            <?php if ($auditRows !== []): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-audit"
                        type="button" role="tab">
                    <i class="bi bi-journal-text"></i> Audit
                    <span class="rk-tab-count"><?= count($auditRows) ?></span>
                </button>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="tab-content">

        <!-- -------------------- Genel -------------------- -->
        <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
            <div class="rk-card-body">
                <div class="row g-4">

                    <div class="col-12 col-lg-7">
                        <dl class="rk-dl">
                            <?php
                            $row('Başlık', e($risk['title']));
                            $row('Açıklama', $risk['description'] !== null && $risk['description'] !== ''
                                ? nl2br(e($risk['description'])) : '');
                            $row('Tehdit', $risk['threat'] !== null && $risk['threat'] !== ''
                                ? nl2br(e($risk['threat'])) : '');
                            $row('Zafiyet', $risk['vulnerability'] !== null && $risk['vulnerability'] !== ''
                                ? nl2br(e($risk['vulnerability'])) : '');
                            $row('Etkilenen Varlık', e((string)($risk['asset_name'] ?? '')));
                            ?>
                        </dl>
                    </div>

                    <div class="col-12 col-lg-5">
                        <dl class="rk-dl">
                            <?php
                            $row('Risk Kodu', '<span class="rk-code">' . e($risk['risk_code']) . '</span>');
                            $row('Kategori',
                                category_dot($risk['category_color']) . ' '
                                . e($risk['category']));
                            $row('Departman', e($risk['department']));
                            $row('Risk Sahibi',
                                e($risk['owner_name'])
                                . '<div class="rk-cell-sub">' . e($risk['owner_email']) . '</div>');
                            $row('Durum', status_badge($risk['status']));
                            $row('Etkin Seviye', severity_badge($effSeverity, true)
                                . ' ' . score_chip($effScore, $effSeverity));
                            $row('Treatment Strategy', $risk['treatment_strategy'] !== null
                                ? '<span class="rk-badge sev-none">' . e($risk['treatment_strategy']) . '</span>' : '');
                            $row('Hedef Tarih', due_date_cell($risk['target_date'], $risk['status'], risk_open_statuses()));
                            $row('Oluşturan', e($risk['created_by_name'])
                                . '<div class="rk-cell-sub">' . e(format_datetime($risk['created_at'])) . '</div>');
                            $row('Son Güncelleme', e(format_datetime($risk['updated_at'])));
                            if ($risk['closed_at'] !== null) {
                                $row('Kapanış', e(format_datetime($risk['closed_at'])));
                            }
                            ?>
                        </dl>
                    </div>

                </div>
            </div>

            <?php if (auth_role() === ROLE_ADMIN): ?>
            <div class="rk-danger-zone">
                <div>
                    <strong>Riski sil</strong>
                    <div class="rk-help">
                        Kayıt listelerden kaldırılır ancak veritabanından silinmez (soft delete).
                        İşlem audit log'a yazılır.
                    </div>
                </div>
                <form method="post" action="<?= e(url('/risks/delete.php')) ?>"
                      data-rk-confirm="<?= e($risk['risk_code'] . ' kodlu risk silinecek. Onaylıyor musunuz?') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="rk-btn rk-btn-danger">
                        <i class="bi bi-trash"></i> <?= te('Sil') ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- -------------------- Aksiyonlar -------------------- -->
        <div class="tab-pane fade" id="tab-actions" role="tabpanel">
            <?php if (can('action.create')): ?>
            <div class="rk-tab-toolbar">
                <a class="rk-btn rk-btn-primary rk-btn-sm"
                   href="<?= e(url('/actions/create.php?risk_id=' . $id)) ?>">
                    <i class="bi bi-plus-lg"></i> <?= te('Aksiyon Ekle') ?>
                </a>
                <a class="rk-btn rk-btn-sm" href="<?= e(url('/actions/?risk=' . $id)) ?>">
                    <i class="bi bi-list-ul"></i> <?= te('Tümünü yönet') ?>
                </a>
            </div>
            <?php endif; ?>

            <div class="rk-card-body is-flush">
                <?php if ($actions === []): ?>
                    <?= empty_state(
                        'Bu risk için aksiyon planı yok',
                        'Riski azaltacak somut adımları buradan ekleyin.',
                        'bi-check2-square',
                        can('action.create')
                            ? '<a class="rk-btn rk-btn-primary" href="'
                              . e(url('/actions/create.php?risk_id=' . $id))
                              . '"><i class="bi bi-plus-lg"></i> Aksiyon Ekle</a>'
                            : ''
                    ) ?>
                <?php else: ?>
                <div class="rk-table-wrap">
                    <table class="rk-table">
                        <thead>
                            <tr>
                                <th><?= te('Aksiyon') ?></th><th><?= te('Sorumlu') ?></th><th><?= te('Öncelik') ?></th>
                                <th>Durum</th><th><?= te('Termin') ?></th><th><?= te('Tamamlanma') ?></th>
                                <th class="rk-u-shrink"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($actions as $a): ?>
                            <tr>
                                <td><?= e(str_limit($a['title'], 56)) ?></td>
                                <td><?= e($a['owner_name']) ?></td>
                                <td><?= priority_badge($a['priority']) ?></td>
                                <td><?= status_badge($a['status']) ?></td>
                                <td><?= due_date_cell($a['due_date'], $a['status']) ?></td>
                                <td><?= e(format_datetime($a['completed_at'])) ?></td>
                                <td>
                                    <div class="rk-row-actions">
                                        <?php if (can('action.complete')
                                                  && in_array($a['status'], action_open_statuses(), true)): ?>
                                            <form method="post" action="<?= e(url('/actions/complete.php')) ?>"
                                                  class="d-inline"
                                                  data-rk-confirm="Bu aksiyon tamamlandı olarak işaretlenecek. Onaylıyor musunuz?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                                <input type="hidden" name="return" value="risk">
                                                <button type="submit" class="rk-icon-btn is-good" title="<?= te('Tamamla') ?>">
                                                    <i class="bi bi-check2"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (can('action.update')): ?>
                                            <a class="rk-icon-btn" title="<?= te('Düzenle') ?>"
                                               href="<?= e(url('/actions/edit.php?id=' . (int)$a['id'])) ?>">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (auth_role() === ROLE_ADMIN): ?>
                                            <form method="post" action="<?= e(url('/actions/delete.php')) ?>"
                                                  class="d-inline"
                                                  data-rk-confirm="Bu aksiyon kalıcı olarak silinecek. Onaylıyor musunuz?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                                <input type="hidden" name="return" value="risk">
                                                <button type="submit" class="rk-icon-btn is-danger" title="<?= te('Sil') ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- -------------------- Değerlendirme geçmişi -------------------- -->
        <div class="tab-pane fade" id="tab-assessments" role="tabpanel">
            <?php if (can('risk.assess')): ?>
            <div class="rk-tab-toolbar">
                <a class="rk-btn rk-btn-primary rk-btn-sm"
                   href="<?= e(url('/assessments/create.php?risk_id=' . $id)) ?>">
                    <i class="bi bi-plus-lg"></i> Yeniden Değerlendir
                </a>
                <a class="rk-btn rk-btn-sm" href="<?= e(url('/assessments/?risk=' . $id)) ?>">
                    <i class="bi bi-list-ul"></i> Tüm değerlendirmeler
                </a>
            </div>
            <?php endif; ?>

            <div class="rk-card-body is-flush">
                <?php if ($assessments === []): ?>
                    <?= empty_state('Değerlendirme kaydı yok', '', 'bi-clipboard-data') ?>
                <?php else: ?>
                <div class="rk-table-wrap">
                    <table class="rk-table">
                        <thead>
                            <tr>
                                <th><?= te('Tarih') ?></th><th><?= te('Tip') ?></th><th><?= te('Olasılık') ?></th><th><?= te('Etki') ?></th>
                                <th><?= te('Skor') ?></th><th><?= te('Seviye') ?></th><th><?= te('Değerlendiren') ?></th><th><?= te('Not') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $typeLabels = ['initial' => 'İlk', 'review' => 'Gözden geçirme', 'residual' => 'Residual'];
                        foreach ($assessments as $a): ?>
                            <tr>
                                <td><?= e(format_datetime($a['assessed_at'])) ?></td>
                                <td><span class="rk-badge sev-none"><?= e($typeLabels[$a['assessment_type']] ?? $a['assessment_type']) ?></span></td>
                                <td><?= (int)$a['likelihood'] ?> - <?= e(likelihood_labels()[(int)$a['likelihood']] ?? '') ?></td>
                                <td><?= (int)$a['impact'] ?> - <?= e(impact_labels()[(int)$a['impact']] ?? '') ?></td>
                                <td><?= score_chip((int)$a['score'], $a['severity']) ?></td>
                                <td><?= severity_badge($a['severity']) ?></td>
                                <td><?= e($a['assessor']) ?></td>
                                <td><?= e(str_limit($a['notes'], 60)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require PARTIALS_PATH . '/discussion_tabs.php'; ?>

        <!-- -------------------- Audit -------------------- -->
        <?php if ($auditRows !== []): ?>
        <div class="tab-pane fade" id="tab-audit" role="tabpanel">
            <div class="rk-card-body is-flush">
                <div class="rk-table-wrap">
                    <table class="rk-table">
                        <thead>
                            <tr><th><?= te('Tarih') ?></th><th><?= te('Kullanıcı') ?></th><th><?= te('İşlem') ?></th><th><?= te('Değişiklik') ?></th><th><?= te('IP') ?></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($auditRows as $log):
                            $old = json_decode((string)$log['old_values'], true) ?: [];
                            $new = json_decode((string)$log['new_values'], true) ?: [];
                            $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
                        ?>
                            <tr>
                                <td><?= e(format_datetime($log['created_at'])) ?></td>
                                <td><?= e((string)($log['user_name_snapshot'] ?? 'Sistem')) ?></td>
                                <td><span class="rk-badge sev-none"><?= e($log['action']) ?></span></td>
                                <td>
                                    <?php if ($keys === []): ?>
                                        <span class="text-muted">-</span>
                                    <?php else: ?>
                                        <ul class="rk-diff">
                                        <?php foreach (array_slice($keys, 0, 6) as $k): ?>
                                            <li>
                                                <code><?= e($k) ?></code>:
                                                <span class="rk-diff-old"><?= e(str_limit((string)($old[$k] ?? '-'), 28)) ?></span>
                                                <i class="bi bi-arrow-right"></i>
                                                <span class="rk-diff-new"><?= e(str_limit((string)($new[$k] ?? '-'), 28)) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php if (count($keys) > 6): ?>
                                            <li class="text-muted">+<?= count($keys) - 6 ?> alan daha</li>
                                        <?php endif; ?>
                                        </ul>
                                    <?php endif; ?>
                                </td>
                                <td class="rk-code"><?= e((string)$log['ip_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require LAYOUT_PATH . '/footer.php'; ?>
