<?php

declare(strict_types=1);

/**
 * RiskOps - Aksiyon formu sunucu tarafı doğrulaması
 * /var/www/riskops/actions/_validate.php
 *
 * store.php ve update.php tarafından kullanılır.
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

/**
 * Verilen id'ye ait silinmemiş riski döndürür, yoksa null.
 * Aksiyon her zaman yaşayan bir riske bağlı olmalıdır.
 *
 * @return array<string, mixed>
 */
function action_find_risk(?int $riskId): ?array
{
    if ($riskId === null || $riskId < 1) {
        return null;
    }
    $row = db_row(
        'SELECT id, risk_code, title, status FROM risks
         WHERE id = :id AND deleted_at IS NULL LIMIT 1',
        [':id' => $riskId]
    );

    return $row === null ? null : $row;
}

/**
 * POST verisini okur, doğrular ve DB'ye yazılmaya hazır hâle getirir.
 *
 * @return array{0: array<string,mixed>, 1: array<string,string>} [data, errors]
 */
function action_collect_input(): array
{
    $errors = [];

    $riskId = input_int('risk_id');
    if (action_find_risk($riskId) === null) {
        $errors['risk_id'] = 'Geçerli bir risk seçiniz.';
    }

    $title = input('title');
    if ($title === null) {
        $errors['title'] = 'Aksiyon başlığı zorunludur.';
    } elseif (mb_strlen($title) < 5) {
        $errors['title'] = 'Başlık en az 5 karakter olmalıdır.';
    } elseif (mb_strlen($title) > 200) {
        $errors['title'] = 'Başlık en fazla 200 karakter olabilir.';
    }

    $ownerId = input_int('owner_id');
    if (!lookup_has(users_list(), $ownerId)) {
        $errors['owner_id'] = 'Geçerli bir sorumlu seçiniz.';
    }

    $priority = input_enum('priority', action_priorities());
    if ($priority === null) {
        $errors['priority'] = 'Geçerli bir öncelik seçiniz.';
    }

    $status = input_enum('status', action_statuses());
    if ($status === null) {
        $errors['status'] = 'Geçerli bir durum seçiniz.';
    }

    $dueDate = null;
    if (input('due_date') !== null) {
        $dueDate = input_date('due_date');
        if ($dueDate === null) {
            $errors['due_date'] = 'Geçerli bir tarih giriniz (YYYY-AA-GG).';
        }
    }

    return [[
        'risk_id'     => $riskId,
        'title'       => $title,
        'description' => input('description'),
        'owner_id'    => $ownerId,
        'priority'    => $priority,
        'status'      => $status,
        'due_date'    => $dueDate,
    ], $errors];
}

/**
 * Doğrulama hatasında formu tekrar gösterir.
 *
 * @param array<string, string> $errors
 */
function action_fail_back(array $errors, string $backUrl): never
{
    old_set($_POST);
    errors_set($errors);
    flash('error', 'Formda ' . count($errors) . ' hata var. Lütfen işaretli alanları düzeltin.');
    redirect($backUrl);
}

/**
 * Tamamlanma zamanı kuralı:
 *   Completed'a geçince damgalanır, geri açılınca temizlenir.
 *   Zaten Completed ise mevcut damga korunur (tarih ileri kaymaz).
 */
function action_completed_at(?string $currentCompletedAt, string $oldStatus, string $newStatus): ?string
{
    if ($newStatus === 'Completed') {
        return $oldStatus === 'Completed' && $currentCompletedAt !== null
            ? $currentCompletedAt
            : date('Y-m-d H:i:s');
    }
    return null;
}
