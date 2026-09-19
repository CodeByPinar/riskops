<?php

declare(strict_types=1);

/**
 * RiskOps - Değerlendirme formu doğrulaması
 * /var/www/riskops/assessments/_validate.php
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

/**
 * Elle girilebilen değerlendirme türleri.
 *
 * 'initial' listede YOKTUR: ilk değerlendirme risk oluşturulurken
 * otomatik yazılır ve sonradan elle eklenemez - aksi halde bir riskin
 * birden fazla "ilk" değerlendirmesi olurdu.
 *
 * @return list<string>
 */
function assessment_types(): array
{
    return ['review', 'residual'];
}

function assessment_type_label(string $type): string
{
    return t(match ($type) {
        'initial'  => 'İlk değerlendirme',
        'review'   => 'Gözden geçirme',
        'residual' => 'Residual (kalan risk)',
        default    => $type,
    });
}

/**
 * Silinmemiş riski döndürür.
 *
 * @return array<string, mixed>
 */
function assessment_find_risk(?int $riskId): ?array
{
    if ($riskId === null || $riskId < 1) {
        return null;
    }
    $stmt = db_stmt(
        'SELECT id, risk_code, title, status, likelihood, impact, inherent_score,
                residual_likelihood, residual_impact, residual_score
         FROM risks WHERE id = :id AND deleted_at IS NULL LIMIT 1',
        [':id' => $riskId]
    );
    return $stmt->fetch() ?: null;
}

/**
 * @return array{0: array<string,mixed>, 1: array<string,string>}
 */
function assessment_collect_input(): array
{
    $errors = [];

    $riskId = input_int('risk_id');
    $risk   = assessment_find_risk($riskId);
    if ($risk === null) {
        $errors['risk_id'] = 'Geçerli bir risk seçiniz.';
    }

    $type = input_enum('assessment_type', assessment_types());
    if ($type === null) {
        $errors['assessment_type'] = 'Geçerli bir değerlendirme türü seçiniz.';
    }

    $likelihood = input_int_range('likelihood', 1, 5);
    if ($likelihood === null) {
        $errors['likelihood'] = 'Olasılık 1-5 arasında seçilmelidir.';
    }

    $impact = input_int_range('impact', 1, 5);
    if ($impact === null) {
        $errors['impact'] = 'Etki 1-5 arasında seçilmelidir.';
    }

    /* Residual değerlendirme, inherent skoru AŞAMAZ: kontroller riski
       artırmaz. Riskin kendisi arttıysa bu bir 'review'dir, residual değil. */
    if ($type === 'residual' && $risk !== null && $likelihood !== null && $impact !== null) {
        $inherent = (int)$risk['inherent_score'];
        if (($likelihood * $impact) > $inherent) {
            $errors['impact'] = sprintf(
                'Residual skor (%d) inherent skordan (%d) büyük olamaz. '
                . 'Risk gerçekten arttıysa "Gözden geçirme" türünü kullanın.',
                $likelihood * $impact, $inherent
            );
        }
    }

    /* Review degerlendirme, inherent skoru mevcut residual skorun ALTINA
       indiremez.

       NEDEN: residual "kontrollerden sonra kalan risk"tir; tanimi geregi
       riskin kendisinden buyuk olamaz. Bu koruma 'residual' tipinde zaten
       vardi (yukaridaki dal), ama 'review' tipi de likelihood/impact'i -
       dolayisiyla uretilen inherent_score'u - degistirdigi icin ayni
       invarianti kirabiliyordu.

       ETKISI: tum "etkin skor" hesaplari COALESCE(residual_score,
       inherent_score) kullanir (risks/index.php filtreleri,
       reports/_reports.php, api/dashboard_charts.php,
       reports/executive_summary.php). Invariant kirilinca bu hesaplar
       riskin kendisinden BUYUK bir deger gosterir; register ile matris
       birbiriyle celisir.

       Kullaniciya ne yapacagi soylenir: once residual guncellenmelidir. */
    if ($type === 'review' && $risk !== null && $likelihood !== null && $impact !== null
        && $risk['residual_score'] !== null) {
        $residual = (int)$risk['residual_score'];
        if (($likelihood * $impact) < $residual) {
            $errors['impact'] = sprintf(
                'Inherent skor (%d) mevcut residual skordan (%d) kucuk olamaz. '
                . 'Once "Residual (kalan risk)" degerlendirmesini guncelleyin.',
                $likelihood * $impact, $residual
            );
        }
    }

    $notes = input('notes');
    if ($notes !== null && mb_strlen($notes) > 2000) {
        $errors['notes'] = 'Not en fazla 2000 karakter olabilir.';
    }

    $assessedAt = input_date('assessed_at') ?? date('Y-m-d');
    if (input('assessed_at') !== null && input_date('assessed_at') === null) {
        $errors['assessed_at'] = 'Geçerli bir tarih giriniz.';
    } elseif ($assessedAt > date('Y-m-d')) {
        $errors['assessed_at'] = 'Değerlendirme tarihi gelecekte olamaz.';
    }

    return [[
        'risk_id'         => $riskId,
        'assessment_type' => $type,
        'likelihood'      => $likelihood,
        'impact'          => $impact,
        'notes'           => $notes,
        'assessed_at'     => $assessedAt,
    ], $errors];
}

/**
 * @param array<string, string> $errors
 */
function assessment_fail_back(array $errors, string $backUrl): never
{
    old_set($_POST);
    errors_set($errors);
    flash('error', 'Formda ' . count($errors) . ' hata var. Lütfen işaretli alanları düzeltin.');
    redirect($backUrl);
}
