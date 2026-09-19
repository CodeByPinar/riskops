<?php

declare(strict_types=1);

/**
 * RiskOps - Rapor kayıt defteri
 * /var/www/riskops/reports/_reports.php
 *
 * Her rapor burada TEK bir dizi girdisidir. Görüntüleme (view.php) ve
 * CSV dışa aktarma (export_csv.php) aynı tanımı kullanır; böylece ekranda
 * gördüğünüz ile indirdiğiniz veri birbirinden AYRIŞAMAZ.
 *
 * Yeni rapor eklemek = buraya bir girdi eklemek. Yeni dosya gerekmez.
 *
 * Girdi alanları:
 *   title, description, icon
 *   sql          -> :from / :to parametrelerini KULLANABILIR
 *   date_field   -> tarih filtresi uygulanacak kolon (null ise filtre yok)
 *   columns      -> kolon anahtarı => başlık
 *   total_row    -> toplam satırı gösterilsin mi (grup raporları)
 *   sum_columns  -> toplanacak kolonlar
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

/**
 * @return array<string, array<string, mixed>>
 */
function report_definitions(): array
{
    $open     = "'" . implode("','", risk_open_statuses()) . "'";
    $effSev   = 'COALESCE(r.residual_severity, r.inherent_severity)';
    $effScore = 'COALESCE(r.residual_score, r.inherent_score)';

    $definitions = [
        /* ---------------------------------------------------- Açık riskler */
        'open_risks' => [
            'title'       => 'Açık Riskler',
            'description' => 'Henüz kapatılmamış tüm riskler; en yüksek etkin seviyeden başlayarak.',
            'icon'        => 'bi-folder2-open',
            'date_field'  => 'r.created_at',
            'sql' => "SELECT r.risk_code, r.title, c.name AS category, d.name AS department,
                             u.name AS owner, r.inherent_score, {$effScore} AS score,
                             {$effSev} AS severity, r.status, r.target_date, r.created_at
                      FROM risks r
                      JOIN risk_categories c ON c.id = r.category_id
                      JOIN departments d     ON d.id = r.department_id
                      JOIN users u           ON u.id = r.owner_id
                      WHERE r.deleted_at IS NULL AND r.status IN ({$open}) %DATE%
                      ORDER BY FIELD({$effSev},'Critical','High','Medium','Low'),
                               {$effScore} DESC, r.target_date IS NULL, r.target_date",
            'columns' => [
                'risk_code'      => 'Kod',
                'title'          => 'Başlık',
                'category'       => 'Kategori',
                'department'     => 'Departman',
                'owner'          => 'Risk Sahibi',
                'inherent_score' => 'Inherent',
                'score'          => 'Etkin Skor',
                'severity'       => 'Seviye',
                'status'         => 'Durum',
                'target_date'    => 'Termin',
            ],
        ],

        /* ------------------------------------------------- Kritik riskler */
        'critical_risks' => [
            'title'       => 'Kritik ve Yüksek Riskler',
            'description' => 'Etkin seviyesi Critical veya High olan riskler — durum farkı gözetmeksizin.',
            'icon'        => 'bi-exclamation-octagon',
            'date_field'  => 'r.created_at',
            'sql' => "SELECT r.risk_code, r.title, c.name AS category, d.name AS department,
                             u.name AS owner, {$effScore} AS score, {$effSev} AS severity,
                             r.treatment_strategy, r.status, r.target_date,
                             (SELECT COUNT(*) FROM risk_actions a
                              WHERE a.risk_id = r.id AND a.status IN ('Open','In Progress')) AS acik_aksiyon
                      FROM risks r
                      JOIN risk_categories c ON c.id = r.category_id
                      JOIN departments d     ON d.id = r.department_id
                      JOIN users u           ON u.id = r.owner_id
                      WHERE r.deleted_at IS NULL AND {$effSev} IN ('Critical','High') %DATE%
                      ORDER BY FIELD({$effSev},'Critical','High'), {$effScore} DESC",
            'columns' => [
                'risk_code'          => 'Kod',
                'title'              => 'Başlık',
                'category'           => 'Kategori',
                'department'         => 'Departman',
                'owner'              => 'Risk Sahibi',
                'score'              => 'Etkin Skor',
                'severity'           => 'Seviye',
                'treatment_strategy' => 'Strateji',
                'status'             => 'Durum',
                'target_date'        => 'Termin',
                'acik_aksiyon'       => 'Açık Aksiyon',
            ],
        ],

        /* ------------------------------------------------ Geciken aksiyonlar */
        'overdue_actions' => [
            'title'       => 'Geciken Aksiyonlar',
            'description' => 'Termini geçmiş ancak hâlâ açık veya devam eden aksiyon planları.',
            'icon'        => 'bi-clock-history',
            'date_field'  => 'a.due_date',
            'sql' => "SELECT r.risk_code, r.title AS risk_title, a.title, u.name AS owner,
                             a.priority, a.status, a.due_date,
                             DATEDIFF(CURDATE(), a.due_date) AS gecikme_gun,
                             {$effSev} AS severity
                      FROM risk_actions a
                      JOIN risks r ON r.id = a.risk_id AND r.deleted_at IS NULL
                      JOIN users u ON u.id = a.owner_id
                      WHERE a.status IN ('Open','In Progress')
                        AND a.due_date IS NOT NULL AND a.due_date < CURDATE() %DATE%
                      ORDER BY a.due_date ASC",
            'columns' => [
                'risk_code'   => 'Risk',
                'risk_title'  => 'Risk Başlığı',
                'title'       => 'Aksiyon',
                'owner'       => 'Sorumlu',
                'priority'    => 'Öncelik',
                'status'      => 'Durum',
                'due_date'    => 'Termin',
                'gecikme_gun' => 'Gecikme (gün)',
                'severity'    => 'Risk Seviyesi',
            ],
        ],

        /* ----------------------------------------------- Departman kırılımı */
        'by_department' => [
            'title'       => 'Departmana Göre Risk Dağılımı',
            'description' => 'Hangi departman en fazla ve en ağır riski taşıyor?',
            'icon'        => 'bi-diagram-3',
            'date_field'  => 'r.created_at',
            'sql' => "SELECT d.name AS departman,
                             COUNT(r.id) AS toplam,
                             COALESCE(SUM({$effSev}='Critical'),0) AS kritik,
                             COALESCE(SUM({$effSev}='High'),0)     AS yuksek,
                             COALESCE(SUM({$effSev}='Medium'),0)   AS orta,
                             COALESCE(SUM({$effSev}='Low'),0)      AS dusuk,
                             COALESCE(SUM(r.status IN ({$open})),0) AS acik,
                             ROUND(AVG({$effScore}),1) AS ort_skor
                      FROM departments d
                      JOIN risks r ON r.department_id = d.id AND r.deleted_at IS NULL
                      WHERE 1=1 %DATE%
                      GROUP BY d.id, d.name
                      ORDER BY kritik DESC, yuksek DESC, toplam DESC",
            'columns' => [
                'departman' => 'Departman', 'toplam' => 'Toplam', 'kritik' => 'Kritik',
                'yuksek' => 'Yüksek', 'orta' => 'Orta', 'dusuk' => 'Düşük',
                'acik' => 'Açık', 'ort_skor' => 'Ort. Skor',
            ],
            'total_row'   => true,
            'sum_columns' => ['toplam', 'kritik', 'yuksek', 'orta', 'dusuk', 'acik'],
        ],

        /* ------------------------------------------------ Kategori kırılımı */
        'by_category' => [
            'title'       => 'Kategoriye Göre Risk Dağılımı',
            'description' => 'Risklerin hangi tehdit alanlarında yoğunlaştığı.',
            'icon'        => 'bi-tags',
            'date_field'  => 'r.created_at',
            'sql' => "SELECT c.name AS kategori,
                             COUNT(r.id) AS toplam,
                             COALESCE(SUM({$effSev}='Critical'),0) AS kritik,
                             COALESCE(SUM({$effSev}='High'),0)     AS yuksek,
                             COALESCE(SUM({$effSev}='Medium'),0)   AS orta,
                             COALESCE(SUM({$effSev}='Low'),0)      AS dusuk,
                             COALESCE(SUM(r.status IN ({$open})),0) AS acik,
                             ROUND(AVG({$effScore}),1) AS ort_skor
                      FROM risk_categories c
                      JOIN risks r ON r.category_id = c.id AND r.deleted_at IS NULL
                      WHERE 1=1 %DATE%
                      GROUP BY c.id, c.name
                      ORDER BY kritik DESC, yuksek DESC, toplam DESC",
            'columns' => [
                'kategori' => 'Kategori', 'toplam' => 'Toplam', 'kritik' => 'Kritik',
                'yuksek' => 'Yüksek', 'orta' => 'Orta', 'dusuk' => 'Düşük',
                'acik' => 'Açık', 'ort_skor' => 'Ort. Skor',
            ],
            'total_row'   => true,
            'sum_columns' => ['toplam', 'kritik', 'yuksek', 'orta', 'dusuk', 'acik'],
        ],

        /* --------------------------------------------- Risk sahibi kırılımı */
        'by_owner' => [
            'title'       => 'Risk Sahibine Göre Dağılım',
            'description' => 'Kim kaç risk taşıyor, kaç aksiyonu gecikmiş? Yük dengesizliğini gösterir.',
            'icon'        => 'bi-person-badge',
            'date_field'  => 'r.created_at',
            'sql' => "SELECT u.name AS sahip, u.email,
                             COUNT(r.id) AS toplam,
                             COALESCE(SUM({$effSev} IN ('Critical','High')),0) AS kritik_yuksek,
                             COALESCE(SUM(r.status IN ({$open})),0) AS acik,
                             COALESCE(SUM(r.status IN ({$open}) AND r.target_date IS NOT NULL
                                      AND r.target_date < CURDATE()),0) AS geciken_risk,
                             (SELECT COUNT(*) FROM risk_actions a
                              JOIN risks r2 ON r2.id = a.risk_id AND r2.deleted_at IS NULL
                              WHERE a.owner_id = u.id AND a.status IN ('Open','In Progress')) AS acik_aksiyon,
                             (SELECT COUNT(*) FROM risk_actions a
                              JOIN risks r3 ON r3.id = a.risk_id AND r3.deleted_at IS NULL
                              WHERE a.owner_id = u.id AND a.status IN ('Open','In Progress')
                                AND a.due_date IS NOT NULL AND a.due_date < CURDATE()) AS geciken_aksiyon
                      FROM users u
                      JOIN risks r ON r.owner_id = u.id AND r.deleted_at IS NULL
                      WHERE 1=1 %DATE%
                      GROUP BY u.id, u.name, u.email
                      ORDER BY kritik_yuksek DESC, toplam DESC",
            'columns' => [
                'sahip' => 'Risk Sahibi', 'email' => 'E-posta', 'toplam' => 'Toplam Risk',
                'kritik_yuksek' => 'Kritik + Yüksek', 'acik' => 'Açık',
                'geciken_risk' => 'Geciken Risk', 'acik_aksiyon' => 'Açık Aksiyon',
                'geciken_aksiyon' => 'Geciken Aksiyon',
            ],
            'total_row'   => true,
            'sum_columns' => ['toplam', 'kritik_yuksek', 'acik', 'geciken_risk',
                              'acik_aksiyon', 'geciken_aksiyon'],
        ],
    ];

    /* Eklentiler rapor ekleyebilir.
       Dönen tanımlar GÜVENİLMEZ: reports/index.php ve view.php her
       alanı kendi doğrulamasından geçirir, SQL yalnızca tanımdan gelir
       ve kullanıcı girdisi her zaman parametre olarak bağlanır. */
    return hook_filter('reports.definitions', $definitions);
}

/**
 * Raporu çalıştırır ve satırları döndürür.
 *
 * Tarih filtresi SQL'e %DATE% yer tutucusundan enjekte edilir; değerler
 * her zaman prepared statement parametresidir.
 *
 * @return array{rows: list<array<string, mixed>>, sql: string}
 * @param array<string, mixed> $def
 */
function report_run(array $def, ?string $from, ?string $to): array
{
    $dateSql = '';
    $params  = [];

    if ($def['date_field'] !== null) {
        if ($from !== null) {
            $dateSql .= ' AND ' . $def['date_field'] . ' >= :from';
            $params[':from'] = $from . (str_contains($def['date_field'], '_at') ? ' 00:00:00' : '');
        }
        if ($to !== null) {
            $dateSql .= ' AND ' . $def['date_field'] . ' <= :to';
            $params[':to'] = $to . (str_contains($def['date_field'], '_at') ? ' 23:59:59' : '');
        }
    }

    $sql = (string)str_replace('%DATE%', $dateSql, (string)$def['sql']);

    return ['rows' => db_all($sql, $params), 'sql' => $sql];
}

/**
 * Kolon adından görüntüleme biçimini çıkarır.
 *
 * Kural tabanlı: her rapor için ayrı biçimlendirici tanımlamak yerine
 * kolon adı biçimi belirler. Yeni rapor eklendiğinde rozetler ve tarih
 * biçimleri kendiliğinden doğru gelir.
 *
 * @param mixed $value
 */
function report_cell_html(string $column, $value): string
{
    if ($value === null || $value === '') {
        return '<span class="text-muted">—</span>';
    }

    if ($column === 'severity' || str_ends_with($column, '_severity')) {
        return severity_badge((string)$value);
    }
    if ($column === 'status') {
        return status_badge((string)$value);
    }
    if ($column === 'priority') {
        return priority_badge((string)$value);
    }
    if ($column === 'score' || str_ends_with($column, '_score')) {
        return score_chip((int)$value);
    }
    if ($column === 'risk_code') {
        return '<span class="rk-code">' . e((string)$value) . '</span>';
    }
    // Uzun serbest metin: daralma yükünü bu kolon üstlenir, böylece
    // yazdırmada isim/tarih gibi kısa kolonlar ortadan bölünmez.
    if ($column === 'title' || $column === 'risk_title' || $column === 'notes') {
        return '<span class="rk-cell-wrap">' . e((string)$value) . '</span>';
    }
    if (str_ends_with($column, '_date')) {
        return e(format_date((string)$value));
    }
    if (str_ends_with($column, '_at')) {
        return e(format_datetime((string)$value));
    }
    if ($column === 'gecikme_gun') {
        return '<span class="rk-overdue">' . (int)$value . '</span>';
    }

    return e((string)$value);
}
