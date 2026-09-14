<?php

declare(strict_types=1);

/**
 * RiskOps - Risklerde toplu işlem (POST handler)
 * /var/www/riskops/risks/bulk.php
 *
 * Desteklenen işlemler:
 *   assign  -> sahip ata        (risk.update)
 *   status  -> durum değiştir   (risk.update)
 *   close   -> kapat            (risk.update)
 *
 * SİLME BİLEREK YOK. Toplu silme, tek bir yanlış tıklamayla onlarca
 * kaydı listeden kaldırır ve kullanıcı neyi sildiğini göremez. Silme
 * tek tek, kendi onay ekranından yapılır (risks/delete.php).
 *
 * TASARIM: her risk AYRI AYRI güncellenir, tek bir toplu UPDATE ile
 * değil. İki sebeple:
 *   1. Denetim izi kayıt bazında tutulmalı - "şu 30 risk değişti"
 *      satırı denetimde işe yaramaz, hangi riskin neyi değişti lazım.
 *   2. Bir kayıt geçersizse (silinmiş, yetkisiz) yalnızca o atlanır;
 *      diğerleri işlenir. Tek UPDATE'te ya hepsi ya hiçbiri olurdu.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/lookups.php';

csrf_require();
require_login();
require_can('risk.update');

$action = input_enum('bulk_action', ['assign', 'status', 'close']);

if ($action === null) {
    flash('error', 'Geçersiz toplu işlem.');
    redirect('/risks/');
}

/* ------------------------------------------------------------------ */
/* Seçilen kayıtlar                                                    */
/*                                                                     */
/* ids[] istemciden gelir: her elemanı ayrı ayrı int'e zorluyoruz ve   */
/* sorguya YALNIZCA placeholder olarak giriyor. Dizi uzunluğu da       */
/* sınırlı - aksi halde tek istekle tüm tablo güncellenebilirdi.       */
/* ------------------------------------------------------------------ */

const BULK_MAX = 200;

$raw = $_POST['ids'] ?? [];
if (!is_array($raw)) {
    $raw = [];
}

$ids = [];
foreach ($raw as $v) {
    $n = filter_var($v, FILTER_VALIDATE_INT);
    if ($n !== false && $n > 0) {
        $ids[$n] = true;          // anahtar olarak: tekrarlar elenir
    }
}
$ids = array_keys($ids);

if ($ids === []) {
    flash('warning', 'Hiçbir risk seçilmedi.');
    redirect('/risks/');
}

if (count($ids) > BULK_MAX) {
    flash('error', 'Tek seferde en fazla ' . BULK_MAX . ' risk işlenebilir.');
    redirect('/risks/');
}

/* ------------------------------------------------------------------ */
/* İşleme özel doğrulama                                               */
/* ------------------------------------------------------------------ */

$ownerId   = null;
$newStatus = null;

if ($action === 'assign') {
    $ownerId = input_int('bulk_owner_id');
    /* Sahip, AKTİF kullanıcılar arasından olmalı. Doğrudan id kabul
       edilseydi pasif ya da var olmayan bir kullanıcıya atama yapılabilir,
       risk sahipsiz kalırdı. */
    if ($ownerId === null || !lookup_has(users_list(), $ownerId)) {
        flash('error', 'Geçerli bir risk sahibi seçin.');
        redirect('/risks/');
    }
} elseif ($action === 'status') {
    $newStatus = input_enum('bulk_status', risk_statuses());
    if ($newStatus === null) {
        flash('error', 'Geçerli bir durum seçin.');
        redirect('/risks/');
    }
} else {                       // close
    $newStatus = 'Closed';
}

/* ------------------------------------------------------------------ */
/* Uygula                                                              */
/* ------------------------------------------------------------------ */

$pdo = db();
$done = 0;
$skipped = 0;

$find = $pdo->prepare(
    'SELECT id, risk_code, status, owner_id, closed_at
       FROM risks WHERE id = :id AND deleted_at IS NULL LIMIT 1'
);

$setOwner = $pdo->prepare('UPDATE risks SET owner_id = :o WHERE id = :id');

/* closed_at: durum Closed olurken damgalanir, Closed'dan cikarken
   temizlenir. Rapor ve panellerdeki "kapanan risk" sayaclari bu
   kolona bakiyor; bosta birakilirsa kapanmis risk hicbir trendde
   gorunmezdi. */
$setStatus = $pdo->prepare(
    "UPDATE risks
        SET status = :s,
            closed_at = CASE WHEN :s2 = 'Closed' THEN COALESCE(closed_at, NOW()) ELSE NULL END
      WHERE id = :id"
);

$pdo->beginTransaction();

try {
    foreach ($ids as $id) {
        $find->execute([':id' => $id]);
        $risk = $find->fetch();

        if ($risk === false) {
            $skipped++;
            continue;
        }

        if ($action === 'assign') {
            if ((int)$risk['owner_id'] === $ownerId) {
                $skipped++;                 // zaten o kisiye ait
                continue;
            }
            $setOwner->execute([':o' => $ownerId, ':id' => $id]);
            audit('risk_bulk_assigned', 'risk', $id,
                ['owner_id' => $risk['owner_id']],
                ['owner_id' => $ownerId]);
        } else {
            if ($risk['status'] === $newStatus) {
                $skipped++;                 // zaten o durumda
                continue;
            }
            $setStatus->execute([':s' => $newStatus, ':s2' => $newStatus, ':id' => $id]);
            audit('risk_bulk_status_changed', 'risk', $id,
                ['status' => $risk['status']],
                ['status' => $newStatus]);
        }

        $done++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('error', 'Bulk operation failed: ' . $e->getMessage(), [
        'action' => $action, 'count' => count($ids), 'user' => auth_id(),
    ]);
    flash('error', 'Toplu işlem tamamlanamadı, hiçbir kayıt değişmedi.');
    redirect('/risks/');
}

$label = match ($action) {
    'assign' => 'sahibi değiştirildi',
    'close'  => 'kapatıldı',
    default  => 'durumu güncellendi',
};

$msg = $done . ' risk ' . $label . '.';
if ($skipped > 0) {
    $msg .= ' ' . $skipped . ' kayıt atlandı (zaten o durumdaydı veya bulunamadı).';
}

flash($done > 0 ? 'success' : 'warning', $msg);
redirect('/risks/');
