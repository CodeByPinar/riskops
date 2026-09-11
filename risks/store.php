<?php
declare(strict_types=1);

/**
 * RiskOps - Yeni risk kaydı (POST handler)
 * /var/www/riskops/risks/store.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_validate.php';

csrf_require();            // POST + CSRF
require_login();
require_can('risk.create');

[$data, $errors] = risk_collect_input();

if ($errors !== []) {
    risk_fail_back($errors, '/risks/create.php');
}

$pdo = db();

try {
    $pdo->beginTransaction();

    // Race-condition güvenli kod üretimi (bkz. includes/risk.php)
    $riskCode = next_risk_code($pdo);

    $pdo->prepare(
        'INSERT INTO risks
            (risk_code, title, description, category_id, department_id, asset_name,
             threat, vulnerability, owner_id, likelihood, impact, inherent_severity,
             treatment_strategy, residual_likelihood, residual_impact, residual_severity,
             status, target_date, created_by)
         VALUES
            (:code, :title, :description, :category_id, :department_id, :asset_name,
             :threat, :vulnerability, :owner_id, :likelihood, :impact, :inherent_severity,
             :treatment_strategy, :residual_likelihood, :residual_impact, :residual_severity,
             :status, :target_date, :created_by)'
    )->execute([
        ':code'                => $riskCode,
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
        ':created_by'          => auth_id(),
    ]);

    $riskId = (int)$pdo->lastInsertId();

    // İlk değerlendirme kaydı - skor geçmişi bastan baslar
    risk_record_assessment(
        $pdo, $riskId, 'initial',
        (int)$data['likelihood'], (int)$data['impact'],
        'Risk oluşturuldu.'
    );

    if ($data['residual_likelihood'] !== null && $data['residual_impact'] !== null) {
        risk_record_assessment(
            $pdo, $riskId, 'residual',
            (int)$data['residual_likelihood'], (int)$data['residual_impact'],
            'Olusturma sırasında girilen residual değerlendirme.'
        );
    }

    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('error', 'Risk creation failed: ' . $ex->getMessage(), ['user' => auth_id()]);
    old_set($_POST);
    flash('error', 'Risk kaydedilemedi. Sorun devam ederse sistem yöneticinize başvurun.');
    redirect('/risks/create.php');
}

audit('risk_created', 'risk', $riskId, null, ['risk_code' => $riskCode] + $data);

flash('success', $riskCode . ' kodlu risk oluşturuldu.');
redirect('/risks/view.php?id=' . $riskId);
