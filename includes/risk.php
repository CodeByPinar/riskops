<?php

declare(strict_types=1);

/**
 * RiskOps - Risk is mantigi
 * /var/www/riskops/includes/risk.php
 */

/* =====================================================================
 * 5x5 MATRIS ETIKETLERI
 * ===================================================================*/

/**
 * @return array<int, string>
 */
function likelihood_labels(): array
{
    return [
        1 => 'Rare',
        2 => 'Unlikely',
        3 => 'Possible',
        4 => 'Likely',
        5 => 'Almost Certain',
    ];
}

/**
 * @return array<int, string>
 */
function impact_labels(): array
{
    return [
        1 => 'Insignificant',
        2 => 'Minor',
        3 => 'Moderate',
        4 => 'Major',
        5 => 'Severe',
    ];
}

/* =====================================================================
 * SABİT LİSTELER  (DB'deki ENUM tanimlariyla BIREBIR ayni olmali)
 * ===================================================================*/

/**
 * @return list<string>
 */
function risk_statuses(): array
{
    return ['Open', 'Under Review', 'In Progress', 'Mitigated', 'Accepted', 'Transferred', 'Closed'];
}

/**
 * Riskin hala "açık" sayildigi durumlar (overdue hesabı için).
 *
 * @return list<string>
 */
function risk_open_statuses(): array
{
    return ['Open', 'Under Review', 'In Progress'];
}

/**
 * @return list<string>
 */
function treatment_strategies(): array
{
    return ['Avoid', 'Mitigate', 'Transfer', 'Accept'];
}

/**
 * @return list<string>
 */
function severities(): array
{
    return ['Low', 'Medium', 'High', 'Critical'];
}

/**
 * @return list<string>
 */
function action_statuses(): array
{
    return ['Open', 'In Progress', 'Completed', 'Cancelled'];
}

/**
 * @return list<string>
 */
function action_open_statuses(): array
{
    return ['Open', 'In Progress'];
}

/**
 * @return list<string>
 */
function action_priorities(): array
{
    return ['Low', 'Medium', 'High', 'Critical'];
}

/* =====================================================================
 * SKOR VE SEVERITY
 * ===================================================================*/

/**
 * Risk skoru = likelihood x impact.
 * NOT: DB'de inherent_score/residual_score GENERATED kolonlardir.
 *      Bu fonksiyon yalnızca form onizlemesi ve doğrulama icindir.
 */
function risk_score(int $likelihood, int $impact): int
{
    return max(1, min(5, $likelihood)) * max(1, min(5, $impact));
}

/**
 * Skoru settings.severity_thresholds eşiklerine göre seviyeye cevirir.
 * Esikler Settings ekranindan değiştirilebilir; bu yuzden severity
 * bilerek DB'de GENERATED kolon YAPILMAMISTIR.
 */
function severity_from_score(int $score): string
{
    $default = ['Low' => [1, 4], 'Medium' => [5, 9], 'High' => [10, 16], 'Critical' => [17, 25]];

    $map = setting('severity_thresholds', $default);
    if (!is_array($map) || $map === []) {
        $map = $default;
    }

    foreach ($map as $name => $range) {
        if (!is_array($range) || count($range) < 2) {
            continue;
        }
        if ($score >= (int)$range[0] && $score <= (int)$range[1]) {
            return (string)$name;
        }
    }

    // Esiklerin disinda kalirsa en yüksek seviyeye yuvarla (güvenli taraf)
    return $score > 16 ? 'Critical' : 'Low';
}

function severity_class(?string $severity): string
{
    return match ($severity) {
        'Critical' => 'sev-critical',
        'High'     => 'sev-high',
        'Medium'   => 'sev-medium',
        'Low'      => 'sev-low',
        default    => 'sev-none',
    };
}

function status_class(?string $status): string
{
    return match ($status) {
        'Open'        => 'st-open',
        'Under Review' => 'st-review',
        'In Progress' => 'st-progress',
        'Mitigated'   => 'st-mitigated',
        'Accepted'    => 'st-accepted',
        'Transferred' => 'st-transferred',
        'Closed'      => 'st-closed',
        default       => 'st-none',
    };
}

function priority_class(?string $priority): string
{
    return match ($priority) {
        'Critical' => 'pr-critical',
        'High'     => 'pr-high',
        'Medium'   => 'pr-medium',
        'Low'      => 'pr-low',
        default    => 'pr-none',
    };
}

/* =====================================================================
 * RISK KODU URETIMI
 * ===================================================================*/

/**
 * Race-condition güvenli risk kodu üretir: RISK-2026-0001
 *
 * Neden bu yontem:
 *   SELECT MAX(...) + 1 yaklasimi iki es zamanli istekte AYNI kodu üretir.
 *   INSERT ... ON DUPLICATE KEY UPDATE ... LAST_INSERT_ID(x+1) ise tek
 *   atomik satir kilidiyle çalışır ve okuma gerektirmez.
 *   risks.risk_code uzerindeki UNIQUE index son savunma hattidir.
 */
function next_risk_code(?PDO $pdo = null): string
{
    $pdo  = $pdo ?? db();
    $year = (int)date('Y');

    $stmt = $pdo->prepare(
        'INSERT INTO risk_sequences (seq_year, last_number)
         VALUES (:y, LAST_INSERT_ID(1))
         ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)'
    );
    $stmt->execute([':y' => $year]);

    $number = (int)$pdo->lastInsertId();

    $prefix = (string)setting('risk_code_prefix', 'RISK');
    if ($prefix === '') {
        $prefix = 'RISK';
    }

    return sprintf('%s-%d-%04d', $prefix, $year, $number);
}

/* =====================================================================
 * SEVERITY YENIDEN HESAPLAMA
 * ===================================================================*/

/**
 * Tüm risklerin ve değerlendirme kayıtlarının severity etiketlerini
 * güncel eşiklere göre yeniden hesaplar.
 *
 * NEDEN GEREKLI: severity, skorun bir YORUMUDUR, bağımsız bir yargı değil.
 * Settings'ten eşikler değiştirildiğinde yeniden hesaplanmazsa risk
 * kayıtları matris ile çelişir: aynı skor listede "High", matriste
 * "Critical" görünür. Skorlar (tarihsel olgu) hiç değişmez, yalnızca
 * etiketler tazelenir.
 *
 * @return array{risks:int, assessments:int} güncellenen satır sayıları
 */
function risk_recalculate_severities(?PDO $pdo = null): array
{
    $pdo = $pdo ?? db();
    settings_all(true);   // eşikler önbellekten değil, veritabanından

    $riskCount = 0;
    $updRisk = $pdo->prepare(
        'UPDATE risks SET inherent_severity = :i, residual_severity = :r WHERE id = :id'
    );

    foreach ($pdo->query(
        'SELECT id, inherent_score, inherent_severity, residual_score, residual_severity FROM risks'
    )->fetchAll() as $row) {
        $newInherent = severity_from_score((int)$row['inherent_score']);
        $newResidual = $row['residual_score'] !== null
            ? severity_from_score((int)$row['residual_score'])
            : null;

        if ($newInherent !== $row['inherent_severity'] || $newResidual !== $row['residual_severity']) {
            $updRisk->execute([
                ':i'  => $newInherent,
                ':r'  => $newResidual,
                ':id' => (int)$row['id'],
            ]);
            $riskCount++;
        }
    }

    $assessCount = 0;
    $updAssess = $pdo->prepare('UPDATE risk_assessments SET severity = :s WHERE id = :id');

    foreach ($pdo->query('SELECT id, score, severity FROM risk_assessments')->fetchAll() as $row) {
        $new = severity_from_score((int)$row['score']);
        if ($new !== $row['severity']) {
            $updAssess->execute([':s' => $new, ':id' => (int)$row['id']]);
            $assessCount++;
        }
    }

    return ['risks' => $riskCount, 'assessments' => $assessCount];
}

/* =====================================================================
 * GECIKME (OVERDUE) HESABI
 * ===================================================================*/

/**
 * Overdue DB'de kolon olarak TUTULMAZ; her zaman anlik hesaplanir.
 * Aksi halde her gece cron ile güncellenmesi gerekirdi.
 *
 * @param list<string> $openStatuses
 */
function is_overdue(?string $dueDate, ?string $status, array $openStatuses = []): bool
{
    if ($dueDate === null || $dueDate === '') {
        return false;
    }
    if ($openStatuses === []) {
        $openStatuses = action_open_statuses();
    }
    if ($status !== null && !in_array($status, $openStatuses, true)) {
        return false;
    }
    return $dueDate < date('Y-m-d');
}
