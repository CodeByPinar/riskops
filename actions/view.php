<?php
declare(strict_types=1);

/**
 * RiskOps - Aksiyon detayı
 * /var/www/riskops/actions/view.php?id=<aksiyon id>
 *
 * Bu sayfa yorum ve ek özelliğiyle birlikte eklendi: yorumların
 * yaşayacağı bir yer gerekiyordu. Aksiyonların o güne kadar yalnızca
 * liste ve düzenleme ekranı vardı.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/attachments.php';
require_once __DIR__ . '/../includes/discussion.php';

require_login();
require_can('action.view');

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing action id');
}

/* Silinmis riskin aksiyonu gosterilmez: risk kayittan kaldirilmissa
   aksiyonu da gundemde degildir. */
$action = db_row(
    'SELECT a.*,
            r.risk_code, r.title AS risk_title, r.id AS risk_id,
            o.name AS owner_name, o.email AS owner_email,
            cb.name AS created_by_name,
            DATEDIFF(a.due_date, CURDATE()) AS days_left
       FROM risk_actions a
       JOIN risks r  ON r.id = a.risk_id AND r.deleted_at IS NULL
       LEFT JOIN users o  ON o.id = a.owner_id
       LEFT JOIN users cb ON cb.id = a.created_by
      WHERE a.id = :id
      LIMIT 1',
    [':id' => $id]
);

if ($action === null) {
    flash('error', t('Aksiyon bulunamadı.'));
    redirect('/actions/');
}

$dType       = 'action';
$dParentId   = (int)$action['id'];
$comments    = discussion_comments($dType, $dParentId);
$attachments = discussion_attachments($dType, $dParentId);
$canWrite    = can('action.update');

$isDone    = in_array($action['status'], ['Completed', 'Cancelled'], true);
$isOverdue = !$isDone && $action['due_date'] !== null && (int)$action['days_left'] < 0;

$pageTitle    = $action['title'];
$pageSubtitle = $action['risk_code'] . ' · ' . $action['risk_title'];
$activeMenu   = 'actions';

require LAYOUT_PATH . '/header.php';
?>

<div class="rk-page-actions">
    <a class="rk-btn" href="<?= e(url('/actions/')) ?>">
        <i class="bi bi-arrow-left"></i> <?= te('Listeye dön') ?>
    </a>
    <a class="rk-btn" href="<?= e(url('/risks/view.php?id=' . (int)$action['risk_id'])) ?>">
        <i class="bi bi-shield-exclamation"></i> <?= te('Riski aç') ?>
    </a>
    <?php if ($canWrite): ?>
    <a class="rk-btn rk-btn-primary" href="<?= e(url('/actions/edit.php?id=' . $dParentId)) ?>">
        <i class="bi bi-pencil"></i> <?= te('Düzenle') ?>
    </a>
    <?php endif; ?>
</div>

<?php if ($isOverdue): ?>
<div class="rk-alert rk-alert-warning">
    <i class="bi bi-clock-history"></i>
    <div>
        Bu aksiyonun termini <strong><?= abs((int)$action['days_left']) ?> gün</strong>
        geçti. Termin: <?= e(format_date($action['due_date'])) ?>
    </div>
</div>
<?php endif; ?>

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
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-comments"
                        type="button" role="tab">
                    <i class="bi bi-chat-left-text"></i> <?= te('Yorumlar') ?>
                    <span class="rk-tab-count"><?= count($comments) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-attachments"
                        type="button" role="tab">
                    <i class="bi bi-paperclip"></i> <?= te('Ekler') ?>
                    <span class="rk-tab-count"><?= count($attachments) ?></span>
                </button>
            </li>
        </ul>
    </div>

    <div class="tab-content">

        <!-- -------------------- Genel -------------------- -->
        <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
            <div class="rk-card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-7">
                        <dl class="rk-dl">
                            <dt><?= te('Başlık') ?></dt>
                            <dd><?= e($action['title']) ?></dd>

                            <dt><?= te('Açıklama') ?></dt>
                            <dd>
                                <?= $action['description'] !== null && $action['description'] !== ''
                                    ? nl2br(e($action['description']))
                                    : '<span class="rk-muted">-</span>' ?>
                            </dd>

                            <dt><?= te('Bağlı risk') ?></dt>
                            <dd>
                                <a class="rk-link-strong"
                                   href="<?= e(url('/risks/view.php?id=' . (int)$action['risk_id'])) ?>">
                                    <?= e($action['risk_code']) ?> — <?= e($action['risk_title']) ?>
                                </a>
                            </dd>
                        </dl>
                    </div>

                    <div class="col-12 col-lg-5">
                        <dl class="rk-dl">
                            <dt><?= te('Durum') ?></dt>
                            <dd><?= status_badge($action['status']) ?></dd>

                            <dt><?= te('Öncelik') ?></dt>
                            <dd><?= priority_badge($action['priority']) ?></dd>

                            <dt><?= te('Sorumlu') ?></dt>
                            <dd><?= e($action['owner_name'] ?? '-') ?></dd>

                            <dt><?= te('Termin') ?></dt>
                            <dd><?= due_date_cell($action['due_date'], $action['status']) ?></dd>

                            <dt><?= te('Tamamlanma') ?></dt>
                            <dd><?= e(format_datetime($action['completed_at'])) ?></dd>

                            <dt><?= te('Oluşturan') ?></dt>
                            <dd><?= e($action['created_by_name'] ?? '-') ?></dd>

                            <dt><?= te('Oluşturulma') ?></dt>
                            <dd><?= e(format_datetime($action['created_at'])) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <?php require PARTIALS_PATH . '/discussion_tabs.php'; ?>

    </div>
</div>

<?php require LAYOUT_PATH . '/footer.php'; ?>
