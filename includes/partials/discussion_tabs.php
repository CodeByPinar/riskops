<?php
declare(strict_types=1);

/**
 * RiskOps - Yorumlar ve Ekler sekme icerikleri (ortak parca)
 * /var/www/riskops/includes/partials/discussion_tabs.php
 *
 * Hem risk hem aksiyon detayinda ayni markup kullaniliyor. Iki yere
 * kopyalansaydi biri degistiginde digeri sessizce eskimeye baslardi.
 *
 * Cagirmadan once tanimlanmali:
 *   $dType       'risk' | 'action'
 *   $dParentId   ust kaydin id'si
 *   $comments    discussion_comments() ciktisi
 *   $attachments discussion_attachments() ciktisi
 *   $canWrite    yazma yetkisi var mi
 */

/* Bu parça bir GİRİŞ NOKTASI DEĞİL: risk ve aksiyon detay sayfaları
   include ediyor ve değişkenleri onların kapsamından alıyor.
   Bildirimler yukarıdaki düz metin sözleşmeyi denetlenebilir kılar. */
/** @var string $dType */
/** @var int $dParentId */
/** @var array<int, array<string, mixed>> $comments */
/** @var array<int, array<string, mixed>> $attachments */
/** @var bool $canWrite */

$dUrl = static fn (string $file): string => url('/discussion/' . $file);
?>

<!-- -------------------- Yorumlar -------------------- -->
<div class="tab-pane fade" id="tab-comments" role="tabpanel">
    <div class="rk-card-body">
        <span id="yorumlar"></span>

        <?php if ($canWrite): ?>
        <form method="post" action="<?= e($dUrl('comment_store.php')) ?>" class="rk-comment-form">
            <?= csrf_field() ?>
            <input type="hidden" name="type" value="<?= e($dType) ?>">
            <input type="hidden" name="parent_id" value="<?= (int)$dParentId ?>">
            <div class="rk-field">
                <label class="rk-label" for="comment_body"><?= te('Yorum ekle') ?></label>
                <textarea class="rk-input" id="comment_body" name="body" rows="3"
                          maxlength="4000" required
                          placeholder="<?= te('Bu kayıtla ilgili notunuz...') ?>"></textarea>
            </div>
            <button type="submit" class="rk-btn rk-btn-primary rk-btn-sm">
                <i class="bi bi-send"></i> <?= te('Gönder') ?>
            </button>
        </form>
        <?php endif; ?>

        <?php if ($comments === []): ?>
            <?= empty_state(
                t('Henüz yorum yok'),
                $canWrite ? t('İlk yorumu siz ekleyin.')
                          : 'Yorum eklemek için güncelleme yetkisi gerekir.',
                'bi-chat-left-text'
            ) ?>
        <?php else: ?>
        <ul class="rk-comments">
            <?php foreach ($comments as $c):
                $mayDel = $canWrite && discussion_may_delete(
                    $c['user_id'] !== null ? (int)$c['user_id'] : null
                );
            ?>
            <li class="rk-comment">
                <div class="rk-comment-avatar"><?= e(initials($c['author_name'] ?? '?')) ?></div>
                <div class="rk-comment-main">
                    <div class="rk-comment-head">
                        <strong><?= e($c['author_name'] ?? 'Silinmiş kullanıcı') ?></strong>
                        <span><?= e(format_datetime($c['created_at'])) ?></span>
                        <?php if ($mayDel): ?>
                        <form method="post" action="<?= e($dUrl('comment_delete.php')) ?>" class="rk-u-m0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="<?= e($dType) ?>">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button type="submit" class="rk-comment-del"
                                    data-rk-confirm="Yorum silinecek. Onaylıyor musunuz?"
                                    title="<?= te('Sil') ?>" aria-label="<?= te('Sil') ?>">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="rk-comment-body"><?= nl2br(e($c['body'])) ?></div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<!-- -------------------- Ekler -------------------- -->
<div class="tab-pane fade" id="tab-attachments" role="tabpanel">
    <div class="rk-card-body">
        <span id="ekler"></span>

        <?php if ($canWrite): ?>
        <form method="post" action="<?= e($dUrl('attachment_store.php')) ?>"
              enctype="multipart/form-data" class="rk-upload-form">
            <?= csrf_field() ?>
            <input type="hidden" name="type" value="<?= e($dType) ?>">
            <input type="hidden" name="parent_id" value="<?= (int)$dParentId ?>">
            <input type="file" class="rk-input" name="attachment" required
                   accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip">
            <button type="submit" class="rk-btn rk-btn-primary rk-btn-sm">
                <i class="bi bi-upload"></i> <?= te('Yükle') ?>
            </button>
        </form>
        <div class="rk-help">
            <?= te('En fazla :boyut, kayıt başına :adet dosya. İzin verilen türler:', [
                ':boyut' => attach_format_size(ATTACH_MAX_BYTES),
                ':adet'  => (string)ATTACH_MAX_PER_RISK,
            ]) ?>
            <?= e(implode(', ', array_keys(attach_allowed_types()))) ?>.
            Dosya içeriği uzantısıyla karşılaştırılır; uyuşmayan dosya reddedilir.
        </div>
        <?php endif; ?>

        <?php if ($attachments === []): ?>
            <?= empty_state(t('Ek yok'), t('Kanıt belgelerini buraya yükleyebilirsiniz.'), 'bi-paperclip') ?>
        <?php else: ?>
        <ul class="rk-attachments">
            <?php foreach ($attachments as $a):
                $ext    = strtolower((string)pathinfo($a['original_name'], PATHINFO_EXTENSION));
                $mayDel = $canWrite && discussion_may_delete(
                    $a['uploaded_by'] !== null ? (int)$a['uploaded_by'] : null
                );
            ?>
            <li class="rk-attachment">
                <i class="bi <?= e(attach_icon($ext)) ?>"></i>
                <div class="rk-attachment-main">
                    <a href="<?= e($dUrl('attachment_download.php')
                        . '?type=' . urlencode($dType) . '&id=' . (int)$a['id']) ?>">
                        <?= e($a['original_name']) ?>
                    </a>
                    <span>
                        <?= e(attach_format_size((int)$a['size_bytes'])) ?> &middot;
                        <?= e($a['uploader_name'] ?? 'Silinmiş kullanıcı') ?> &middot;
                        <?= e(format_datetime($a['created_at'])) ?>
                    </span>
                </div>
                <?php if ($mayDel): ?>
                <form method="post" action="<?= e($dUrl('attachment_delete.php')) ?>" class="rk-u-m0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="type" value="<?= e($dType) ?>">
                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button type="submit" class="rk-comment-del"
                            data-rk-confirm="Dosya kalıcı olarak silinecek. Onaylıyor musunuz?"
                            title="<?= te('Sil') ?>" aria-label="<?= te('Sil') ?>">
                        <i class="bi bi-trash3"></i>
                    </button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
