<?php
declare(strict_types=1);

/**
 * RiskOps - Risk güncelleme (POST handler)
 * /var/www/riskops/risks/update.php
 *
 * Skor değiştiğinde ESKI DEGER KAYBOLMAZ: risk_assessments tablosuna
 * yeni bir satir yazılır. Boylece riskin zaman icindeki seyri izlenebilir.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_validate.php';

csrf_require();
require_login();
require_can('risk.update');

$id = input_int('id');
if ($id === null || $id < 1) {
    app_abort(400, 'Missing risk id');
}

$stmt = db()->prepare('SELECT * FROM risks WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute([':id' => $id]);
$before = $stmt->fetch();

if ($before === false) {
    flash('error', 'Risk bulunamadı.');
    redirect('/risks/');
}

[$data, $errors] = risk_collect_input();

if ($errors !== []) {
    risk_fail_back($errors, '/risks/edit.php?id=' . $id);
}

/* --- Neler degisti? --------------------------------------------------- */

$scoreChanged =
    (int)$before['likelihood'] !== (int)$data['likelihood'] ||
    (int)$before['impact']     !== (int)$data['impact'];

$residualChanged =
    (string)($before['residual_likelihood'] ?? '') !== (string)($data['residual_likelihood'] ?? '') ||
    (string)($before['residual_impact'] ?? '')     !== (string)($data['residual_impact'] ?? '');

$statusChanged = (string)$before['status'] !== (string)$data['status'];

// Kapanış zamani: Closed'a gecince damgalanir, geri acilinca temizlenir.
$closedAt = $before['closed_at'];
if ($data['status'] === 'Closed' && $before['status'] !== 'Closed') {
    $closedAt = date('Y-m-d H:i:s');
} elseif ($data['status'] !== 'Closed' && $before['status'] === 'Closed') {
    $closedAt = null;
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $pdo->prepare(
        'UPDATE risks SET
            title = :title,
            description = :description,
            category_id = :category_id,
            department_id = :department_id,
            asset_name = :asset_name,
            threat = :threat,
            vulnerability = :vulnerability,
            owner_id = :owner_id,
            likelihood = :likelihood,
            impact = :impact,
            inherent_severity = :inherent_severity,
            treatment_strategy = :treatment_strategy,
            residual_likelihood = :residual_likelihood,
            residual_impact = :residual_impact,
            residual_severity = :residual_severity,
            status = :status,
            target_date = :target_date,
            closed_at = :closed_at
         WHERE id = :id AND deleted_at IS NULL'
    )->execute([
        ':title'               => $data['title'],
        ':description'         => $data['description'],
        ':category_id'         => $data['category_id'],
        ':department_id'       => $data['department_id'],
        ':asset_name'          => $data['asset_name'],
        ':threat'              => $data['threat'],
        ':vulnerability'       => $data['vulnerability'],
        ':owner_id'            => $data['owner_id'],
        ':likelihood'          => $data['likelihood'],
        ':impact'              => $data['impact'],
        ':inherent_severity'   => $data['inherent_severity'],
        ':treatment_strategy'  => $data['treatment_strategy'],
        ':residual_likelihood' => $data['residual_likelihood'],
        ':residual_impact'     => $data['residual_impact'],
        ':residual_severity'   => $data['residual_severity'],
        ':status'              => $data['status'],
        ':target_date'         => $data['target_date'],
        ':closed_at'           => $closedAt,
        ':id'                  => $id,
    ]);

    if ($scoreChanged) {
        risk_record_assessment(
            $pdo, $id, 'review',
            (int)$data['likelihood'], (int)$data['impact'],
            sprintf(
                'Inherent skor %d -> %d olarak güncellendi.',
                (int)$before['inherent_score'],
                (int)$data['likelihood'] * (int)$data['impact']
            )
        );
    }

    if ($residualChanged && $data['residual_likelihood'] !== null && $data['residual_impact'] !== null) {
        risk_record_assessment(
            $pdo, $id, 'residual',
            (int)$data['residual_likelihood'], (int)$data['residual_impact'],
            'Residual değerlendirme güncellendi.'
        );
    }

    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('error', 'Risk update failed: ' . $ex->getMessage(), ['risk' => $id, 'user' => auth_id()]);
    old_set($_POST);
    flash('error', 'Değişiklikler kaydedilemedi.');
    redirect('/risks/edit.php?id=' . $id);
}

/* --- Audit: yalnızca GERCEKTEN degisen alanlar loglanir ---------------- */

[$oldValues, $newValues] = audit_diff($before, $data);

if ($oldValues !== []) {
    audit('risk_updated', 'risk', $id, $oldValues, $newValues);
}
if ($statusChanged) {
    audit('risk_status_changed', 'risk', $id,
        ['status' => $before['status']],
        ['status' => $data['status']]
    );
}

flash('success', $before['risk_code'] . ' güncellendi.'
    . ($scoreChanged ? ' Skor değişikliği değerlendirme geçmişine işlendi.' : ''));

redirect('/risks/view.php?id=' . $id);
