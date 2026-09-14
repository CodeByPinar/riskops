<?php

declare(strict_types=1);

/**
 * RiskOps - Değerlendirme kaydı (POST handler)
 * /var/www/riskops/assessments/store.php
 *
 * Tek bir işlemde iki şey yapılır ve ikisi de ya olur ya olmaz:
 *   1. risk_assessments'a append-only bir satır yazılır (geçmiş)
 *   2. risks tablosundaki güncel skor güncellenir (bugünkü durum)
 *
 * Biri yazılıp diğeri yazılmazsa geçmiş ile güncel durum çelişir;
 * bu yüzden transaction zorunludur.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_validate.php';

csrf_require();
require_login();
require_can('risk.assess');

[$data, $errors] = assessment_collect_input();

$backUrl = '/assessments/create.php'
    . ($data['risk_id'] !== null ? '?risk_id=' . (int)$data['risk_id'] : '');

if ($errors !== []) {
    assessment_fail_back($errors, $backUrl);
}

$risk = assessment_find_risk((int)$data['risk_id']);
if ($risk === null) {
    flash('error', 'Risk bulunamadı.');
    redirect('/assessments/');
}

$score    = (int)$data['likelihood'] * (int)$data['impact'];
$severity = severity_from_score($score);
$isReview = $data['assessment_type'] === 'review';

$pdo = db();

try {
    $pdo->beginTransaction();

    /* OTORITER INVARIANT KONTROLU - kilitli, transaction icinde.
       ------------------------------------------------------------------
       _validate.php icindeki ayni kontrol kilitsiz okur ve yalnizca
       kullaniciya forma hata basmak icindir. Aradaki pencerede baska bir
       istek residual'i yukseltip commit edebilir; o zaman bu review
       inherent'i yeni residual'in altina indirir ve
       COALESCE(residual_score, inherent_score) riskin kendisinden buyuk
       cikar. FOR UPDATE ile risk satirini kilitleyip guncel degeri
       okumak bu pencereyi kapatir. */
    if ($isReview) {
        $lock = $pdo->prepare(
            'SELECT residual_score FROM risks
              WHERE id = :id AND deleted_at IS NULL
              FOR UPDATE'
        );
        $lock->execute([':id' => $data['risk_id']]);
        $lockedResidual = $lock->fetchColumn();

        if ($lockedResidual !== false && $lockedResidual !== null
            && ($data['likelihood'] * $data['impact']) < (int)$lockedResidual) {
            $pdo->rollBack();
            errors_set(['impact' => sprintf(
                'Inherent skor (%d) mevcut residual skordan (%d) kucuk olamaz. '
                . 'Once "Residual (kalan risk)" degerlendirmesini guncelleyin.',
                $data['likelihood'] * $data['impact'], (int)$lockedResidual
            )]);
            old_set($_POST);
            flash('error', 'Degerlendirme kaydedilemedi. Isaretli alani duzeltin.');
            redirect('/assessments/create.php?risk_id=' . (int)$data['risk_id']);
        }
    }

    $pdo->prepare(
        'INSERT INTO risk_assessments
            (risk_id, assessment_type, likelihood, impact, severity, notes, assessed_by, assessed_at)
         VALUES (:rid, :type, :l, :i, :sev, :notes, :by, :at)'
    )->execute([
        ':rid'   => $data['risk_id'],
        ':type'  => $data['assessment_type'],
        ':l'     => $data['likelihood'],
        ':i'     => $data['impact'],
        ':sev'   => $severity,
        ':notes' => $data['notes'],
        ':by'    => auth_id(),
        // Geriye dönük tarihte bile saat bilgisi kalsın; bugünse şimdiki saat
        ':at'    => $data['assessed_at'] === date('Y-m-d')
                        ? date('Y-m-d H:i:s')
                        : $data['assessed_at'] . ' 12:00:00',
    ]);

    $assessmentId = (int)$pdo->lastInsertId();

    if ($isReview) {
        $pdo->prepare(
            'UPDATE risks SET likelihood = :l, impact = :i, inherent_severity = :sev
             WHERE id = :id AND deleted_at IS NULL'
        )->execute([
            ':l'   => $data['likelihood'],
            ':i'   => $data['impact'],
            ':sev' => $severity,
            ':id'  => $data['risk_id'],
        ]);
    } else {
        $pdo->prepare(
            'UPDATE risks SET residual_likelihood = :l, residual_impact = :i, residual_severity = :sev
             WHERE id = :id AND deleted_at IS NULL'
        )->execute([
            ':l'   => $data['likelihood'],
            ':i'   => $data['impact'],
            ':sev' => $severity,
            ':id'  => $data['risk_id'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('error', 'Assessment creation failed: ' . $ex->getMessage(), [
        'risk' => $data['risk_id'], 'user' => auth_id(),
    ]);
    old_set($_POST);
    flash('error', 'Değerlendirme kaydedilemedi.');
    redirect($backUrl);
}

$oldScore = $isReview
    ? (int)$risk['inherent_score']
    : (($risk['residual_likelihood'] !== null && $risk['residual_impact'] !== null)
        ? (int)$risk['residual_likelihood'] * (int)$risk['residual_impact']
        : null);

audit('risk_assessed', 'risk', (int)$data['risk_id'],
    ['score' => $oldScore],
    [
        'assessment_id' => $assessmentId,
        'type'          => $data['assessment_type'],
        'score'         => $score,
        'severity'      => $severity,
    ]
);

$typeLabel = mb_strtolower(assessment_type_label((string)$data['assessment_type']), 'UTF-8');

if ($oldScore === null) {
    $scoreText = sprintf('Skor %d (%s) olarak belirlendi.', $score, $severity);
} elseif ($score === $oldScore) {
    $scoreText = sprintf('Skor %d (%s) olarak doğrulandı, değişmedi.', $score, $severity);
} else {
    $scoreText = sprintf(
        'Skor %d → %d (%s), %s.',
        $oldScore, $score, $severity,
        $score < $oldScore ? 'düştü' : 'yükseldi'
    );
}

flash('success', sprintf('%s için %s kaydedildi. %s', $risk['risk_code'], $typeLabel, $scoreText));

redirect('/risks/view.php?id=' . (int)$data['risk_id']);
