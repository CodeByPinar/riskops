<?php

declare(strict_types=1);

/**
 * RiskOps - Risk formu sunucu tarafi doğrulaması
 * /var/www/riskops/risks/_validate.php
 *
 * store.php ve update.php tarafindan kullanılır.
 *
 * KURAL: Tarayıcıdaki "required" ve "maxlength" nitelikleri bir doğrulama
 *        DEĞİLDİR, yalnızca kullanıcı kolaylığıdır. Gerçek doğrulama burada.
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

/**
 * POST verisini okur, doğrular ve DB'ye yazılmaya hazır hale getirir.
 *
 * @return array{0: array<string,mixed>, 1: array<string,string>} [data, errors]
 */
function risk_collect_input(): array
{
    $errors = [];

    /* --- Metin alanları --- */
    $title = input('title');
    if ($title === null) {
        $errors['title'] = 'Başlık zorunludur.';
    } elseif (mb_strlen($title) < 5) {
        $errors['title'] = 'Başlık en az 5 karakter olmalıdır.';
    } elseif (mb_strlen($title) > 200) {
        $errors['title'] = 'Başlık en fazla 200 karakter olabilir.';
    }

    $asset = input('asset_name');
    if ($asset !== null && mb_strlen($asset) > 200) {
        $errors['asset_name'] = 'En fazla 200 karakter olabilir.';
    }

    /* --- Referanslar --- */
    $categoryId = input_int('category_id');
    if (!lookup_has(categories_list(), $categoryId)) {
        $errors['category_id'] = 'Geçerli bir kategori seçiniz.';
    }

    $departmentId = input_int('department_id');
    if (!lookup_has(departments_list(), $departmentId)) {
        $errors['department_id'] = 'Geçerli bir departman seçiniz.';
    }

    $ownerId = input_int('owner_id');
    if (!lookup_has(users_list(), $ownerId)) {
        $errors['owner_id'] = 'Geçerli bir risk sahibi seçiniz.';
    }

    /* --- Inherent skor --- */
    $likelihood = input_int_range('likelihood', 1, 5);
    if ($likelihood === null) {
        $errors['likelihood'] = 'Olasılık 1-5 arasında seçilmelidir.';
    }

    $impact = input_int_range('impact', 1, 5);
    if ($impact === null) {
        $errors['impact'] = 'Etki 1-5 arasında seçilmelidir.';
    }

    /* --- Residual skor: ikisi birlikte ya da hiç --- */
    $resLikelihood = input_int_range('residual_likelihood', 1, 5);
    $resImpact     = input_int_range('residual_impact', 1, 5);

    if ($resLikelihood !== null xor $resImpact !== null) {
        $missing = $resLikelihood === null ? 'residual_likelihood' : 'residual_impact';
        $errors[$missing] = 'Residual skor için olasılık ve etkinin ikisi de girilmelidir.';
    }

    // Residual risk inherent'ten BUYUK olamaz: kontroller riski artırmaz.
    if ($resLikelihood !== null && $resImpact !== null
        && $likelihood !== null && $impact !== null
        && ($resLikelihood * $resImpact) > ($likelihood * $impact)) {
        $errors['residual_impact'] =
            'Residual skor (' . ($resLikelihood * $resImpact) . ') inherent skordan ('
            . ($likelihood * $impact) . ') büyük olamaz.';
    }

    /* --- Enum alanları --- */
    $status = input_enum('status', risk_statuses());
    if ($status === null) {
        $errors['status'] = 'Geçerli bir durum seçiniz.';
    }

    $treatment = input_enum('treatment_strategy', treatment_strategies());

    /* --- Tarih --- */
    $targetDate = null;
    if (input('target_date') !== null) {
        $targetDate = input_date('target_date');
        if ($targetDate === null) {
            $errors['target_date'] = 'Geçerli bir tarih giriniz (YYYY-AA-GG).';
        }
    }

    /* --- Severity hesabı (settings eşiklerine göre) --- */
    $inherentSeverity = ($likelihood !== null && $impact !== null)
        ? severity_from_score($likelihood * $impact)
        : 'Low';

    $residualSeverity = ($resLikelihood !== null && $resImpact !== null)
        ? severity_from_score($resLikelihood * $resImpact)
        : null;

    $data = [
        'title'               => $title,
        'description'         => input('description'),
        'category_id'         => $categoryId,
        'department_id'       => $departmentId,
        'asset_name'          => $asset,
        'threat'              => input('threat'),
        'vulnerability'       => input('vulnerability'),
        'owner_id'            => $ownerId,
        'likelihood'          => $likelihood,
        'impact'              => $impact,
        'inherent_severity'   => $inherentSeverity,
        'treatment_strategy'  => $treatment,
        'residual_likelihood' => $resLikelihood,
        'residual_impact'     => $resImpact,
        'residual_severity'   => $residualSeverity,
        'status'              => $status,
        'target_date'         => $targetDate,
    ];

    return [$data, $errors];
}

/**
 * Doğrulama hatasında formu tekrar gösterir.
 * Girilen değerler ve alan hatalari oturumda taşınır.
 *
 * @param array<string, string> $errors
 */
function risk_fail_back(array $errors, string $backUrl): never
{
    old_set($_POST);
    errors_set($errors);
    flash('error', 'Formda ' . count($errors) . ' hata var. Lütfen işaretli alanları düzeltin.');
    redirect($backUrl);
}

/** risk_assessments tablosuna değerlendirme satırı yazar (append-only). */
function risk_record_assessment(
    PDO $pdo,
    int $riskId,
    string $type,
    int $likelihood,
    int $impact,
    ?string $notes,
    ?int $userId = null
): void {
    $pdo->prepare(
        'INSERT INTO risk_assessments
            (risk_id, assessment_type, likelihood, impact, severity, notes, assessed_by, assessed_at)
         VALUES (:rid, :type, :l, :i, :sev, :notes, :by, NOW())'
    )->execute([
        ':rid'   => $riskId,
        ':type'  => $type,
        ':l'     => $likelihood,
        ':i'     => $impact,
        ':sev'   => severity_from_score($likelihood * $impact),
        ':notes' => $notes,
        ':by'    => $userId ?? auth_id(),
    ]);
}
