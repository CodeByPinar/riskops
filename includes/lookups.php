<?php
declare(strict_types=1);

/**
 * RiskOps - Referans listeleri
 * /var/www/riskops/includes/lookups.php
 *
 * Departman / kategori / kullanıcı listeleri istek başına BIR KEZ okunur.
 * Form <select>'leri ve liste ekranlarindaki filtreler bunlari kullanir.
 */

/** @return list<array{id:int,name:string,code:string,is_active:int}> */
function departments_list(bool $onlyActive = true): array
{
    static $cache = [];
    $key = $onlyActive ? 'active' : 'all';

    if (!isset($cache[$key])) {
        $cache[$key] = db()->query(
            'SELECT id, name, code, is_active FROM departments'
            . ($onlyActive ? ' WHERE is_active = 1' : '')
            . ' ORDER BY sort_order, name'
        )->fetchAll();
    }
    return $cache[$key];
}

/** @return list<array{id:int,name:string,code:string,color:string,is_active:int}> */
function categories_list(bool $onlyActive = true): array
{
    static $cache = [];
    $key = $onlyActive ? 'active' : 'all';

    if (!isset($cache[$key])) {
        $cache[$key] = db()->query(
            'SELECT id, name, code, color, is_active FROM risk_categories'
            . ($onlyActive ? ' WHERE is_active = 1' : '')
            . ' ORDER BY sort_order, name'
        )->fetchAll();
    }
    return $cache[$key];
}

/**
 * Risk/aksiyon sahibi olabilecek kullanıcılar.
 * viewer rolu sahip olarak atanabilir (izler ama duzenleyemez).
 */
function users_list(bool $onlyActive = true): array
{
    static $cache = [];
    $key = $onlyActive ? 'active' : 'all';

    if (!isset($cache[$key])) {
        $cache[$key] = db()->query(
            'SELECT u.id, u.name, u.email, u.role, u.status, d.name AS department
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id'
            . ($onlyActive ? ' WHERE u.status = 1' : '')
            . ' ORDER BY u.name'
        )->fetchAll();
    }
    return $cache[$key];
}

/** Verilen id listede var mi? Form dogrulamasinda kullanılır. */
function lookup_has(array $rows, ?int $id): bool
{
    if ($id === null) {
        return false;
    }
    foreach ($rows as $row) {
        if ((int)$row['id'] === $id) {
            return true;
        }
    }
    return false;
}

/** <option> listesi üretir. $selected esitse seçili işaretlenir. */
function options_html(array $rows, ?int $selected, string $labelField = 'name'): string
{
    $out = '';
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $out .= '<option value="' . $id . '"'
              . ($selected === $id ? ' selected' : '') . '>'
              . e((string)$row[$labelField])
              . '</option>';
    }
    return $out;
}

/** Duz değer listesinden <option> üretir (status, severity vb.). */
function options_from_values(array $values, ?string $selected): string
{
    $out = '';
    foreach ($values as $value) {
        $out .= '<option value="' . e($value) . '"'
              . ($selected === $value ? ' selected' : '') . '>'
              . e($value)
              . '</option>';
    }
    return $out;
}

/** 1-5 olcek listesi: "3 - Possible" seklinde. */
function options_from_scale(array $labels, ?int $selected): string
{
    $out = '';
    foreach ($labels as $value => $label) {
        $out .= '<option value="' . (int)$value . '"'
              . ($selected === (int)$value ? ' selected' : '') . '>'
              . (int)$value . ' - ' . e($label)
              . '</option>';
    }
    return $out;
}
