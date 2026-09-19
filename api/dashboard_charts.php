<?php

declare(strict_types=1);

/**
 * RiskOps - Dashboard grafik verisi (JSON)
 * /var/www/riskops/api/dashboard_charts.php
 *
 * Tek istekte tum grafik veri setlerini doner. Ayri ayri uc nokta yerine
 * tek uc nokta: dashboard acilisinda 4 yerine 1 HTTP turu yapilir.
 *
 * Yetkisiz istekte 302 yerine 401 JSON doner; fetch() bir HTML giris
 * sayfasini JSON diye ayristirmaya calismaz.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!auth_check()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!can('risk.view')) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();

/* ------------------------------------------------------------------ */
/* 1) Trend - son 12 ay                                                */
/*    Iki seri: acilan ve kapanan risk sayisi (ikisi de "adet",         */
/*    tek eksende gosterilebilir).                                      */
/* ------------------------------------------------------------------ */

/* Pencere recent_months() ile kurulur: strtotime("-N month") ayin
   29-31'inde gun tasmasi yapip ayni Y-m anahtarini iki kez uretiyor ve
   12 aylik pencere 7 aya kadar dusuyordu. Bkz. includes/functions.php. */
$monthKeys = recent_months(12);

$months = [];
foreach ($monthKeys as $key) {
    $months[$key] = ['opened' => 0, 'closed' => 0];
}

/* SQL alt siniri pencerenin EN ESKI ayindan turetilir - ayri bir
   strtotime cagrisindan degil. Iki kaynak ayri olursa biri duzeltilip
   digeri unutulabilir. */
$since = $monthKeys[0] . '-01';

$rows = $pdo->prepare(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ay, COUNT(*) AS adet
     FROM risks
     WHERE deleted_at IS NULL AND created_at >= :since
     GROUP BY ay"
);
$rows->execute([':since' => $since]);
foreach ($rows->fetchAll() as $r) {
    if (isset($months[$r['ay']])) {
        $months[$r['ay']]['opened'] = (int)$r['adet'];
    }
}

$rows = $pdo->prepare(
    "SELECT DATE_FORMAT(closed_at, '%Y-%m') AS ay, COUNT(*) AS adet
     FROM risks
     WHERE deleted_at IS NULL AND closed_at IS NOT NULL AND closed_at >= :since
     GROUP BY ay"
);
$rows->execute([':since' => $since]);
foreach ($rows->fetchAll() as $r) {
    if (isset($months[$r['ay']])) {
        $months[$r['ay']]['closed'] = (int)$r['adet'];
    }
}

$trLabels = ['01' => 'Oca', '02' => 'Şub', '03' => 'Mar', '04' => 'Nis',
             '05' => 'May', '06' => 'Haz', '07' => 'Tem', '08' => 'Ağu',
             '09' => 'Eyl', '10' => 'Eki', '11' => 'Kas', '12' => 'Ara'];

$trend = ['labels' => [], 'opened' => [], 'closed' => []];
foreach ($months as $key => $vals) {
    [$y, $m] = explode('-', $key);
    $trend['labels'][] = $trLabels[$m] . ' ' . substr($y, 2);
    $trend['opened'][] = $vals['opened'];
    $trend['closed'][] = $vals['closed'];
}

/* ------------------------------------------------------------------ */
/* 2) Kategoriye gore - tek seri (13 kategori icin 13 renk URETILMEZ;  */
/*    kimlik etiketten gelir, renk yalnizca buyuklugu tasir)           */
/* ------------------------------------------------------------------ */

$category = db_all(
    "SELECT c.name AS ad, COUNT(r.id) AS adet
     FROM risk_categories c
     JOIN risks r ON r.category_id = c.id AND r.deleted_at IS NULL
     GROUP BY c.id, c.name
     ORDER BY adet DESC, c.name"
);

/* ------------------------------------------------------------------ */
/* 3) Departmana gore - toplam + (kritik|yuksek) sayisi                */
/*    Yigilmis bar KULLANILMIYOR: yigilmis segmentlerde komsu status   */
/*    renkleri (warning/serious) yalnizca hue ile ayrilirdi.           */
/* ------------------------------------------------------------------ */

$department = db_all(
    "SELECT d.name AS ad,
            COUNT(r.id) AS adet,
            COALESCE(SUM(COALESCE(r.residual_severity, r.inherent_severity)
                     IN ('Critical','High')), 0) AS onemli
     FROM departments d
     JOIN risks r ON r.department_id = d.id AND r.deleted_at IS NULL
     GROUP BY d.id, d.name
     ORDER BY onemli DESC, adet DESC"
);

/* ------------------------------------------------------------------ */
/* 4) Duruma gore                                                      */
/* ------------------------------------------------------------------ */

$statusRaw = [];
foreach (db_all(
    "SELECT status, COUNT(*) AS adet FROM risks WHERE deleted_at IS NULL GROUP BY status"
) as $r) {
    $statusRaw[$r['status']] = (int)$r['adet'];
}

$status = [];
foreach (risk_statuses() as $s) {
    if (($statusRaw[$s] ?? 0) > 0) {
        $status[] = ['ad' => $s, 'adet' => $statusRaw[$s]];
    }
}

/* ------------------------------------------------------------------ */

echo json_encode([
    'trend'      => $trend,
    'category'   => array_map(static fn ($r) => ['ad' => $r['ad'], 'adet' => (int)$r['adet']], $category),
    'department' => array_map(
        static fn ($r) => ['ad' => $r['ad'], 'adet' => (int)$r['adet'], 'onemli' => (int)$r['onemli']],
        $department
    ),
    'status'     => $status,
], JSON_UNESCAPED_UNICODE);
