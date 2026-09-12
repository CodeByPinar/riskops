<?php
declare(strict_types=1);

/**
 * RiskOps - Yorum ve ek uç noktaları için ortak erişim kontrolü
 * /var/www/riskops/risks/_risk_access.php
 *
 * Beş uç nokta aynı üç soruyu soruyor: risk var mı, silinmiş mi,
 * kullanıcının bu işlemi yapma yetkisi var mı. Tekrarlanan bir
 * kontrol er ya da geç bir yerde eksik yazılır; tek yerde tutuluyor.
 */

/**
 * Riski getirir; yoksa veya silinmişse akışı sonlandırır.
 *
 * @param string $backTo Hata durumunda dönülecek yol
 */
function risk_or_fail(?int $riskId, string $backTo = '/risks/'): array
{
    if ($riskId === null || $riskId < 1) {
        app_abort(400, 'Missing risk id');
    }

    $stmt = db()->prepare(
        'SELECT id, risk_code, title FROM risks
          WHERE id = :id AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute([':id' => $riskId]);
    $risk = $stmt->fetch();

    if ($risk === false) {
        flash('error', 'Risk bulunamadı.');
        redirect($backTo);
    }

    return $risk;
}
