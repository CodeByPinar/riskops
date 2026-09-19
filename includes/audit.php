<?php

declare(strict_types=1);

/**
 * RiskOps - Audit log
 * /var/www/riskops/includes/audit.php
 *
 * Örnek:
 *   audit('risk_created', 'risk', $riskId, null, $newData);
 *   [$old, $new] = audit_diff($before, $after);
 *   audit('risk_updated', 'risk', $riskId, $old, $new);
 */

/**
 * Audit kaydı yazar.
 * Bu fonksiyon ASLA exception firlatmaz; başarısız olursa app.log'a yazar.
 * Cunku audit hatası yuzunden kullanıcının risk kaydı kaybolmamalidir.
 *
 * @param array<string, mixed>|null $oldValues
 * @param array<string, mixed>|null $newValues
 */
function audit(
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?array $oldValues = null,
    ?array $newValues = null,
    ?int $userId = null,
    ?string $userName = null
): void {
    try {
        $userId   = $userId   ?? auth_id();
        $userName = $userName ?? (auth_name() !== '' ? auth_name() : null);

        db_run(
            'INSERT INTO audit_logs
                (user_id, user_name_snapshot, action, entity_type, entity_id,
                 old_values, new_values, ip_address, user_agent)
             VALUES
                (:uid, :uname, :action, :etype, :eid, :old, :new, :ip, :ua)',
            [
            ':uid'    => $userId,
            ':uname'  => $userName,
            ':action' => $action,
            ':etype'  => $entityType,
            ':eid'    => $entityId,
            ':old'    => $oldValues === null ? null : audit_json($oldValues),
            ':new'    => $newValues === null ? null : audit_json($newValues),
            ':ip'     => client_ip(),
            ':ua'     => client_agent(),
        ]
        );
    } catch (Throwable $e) {
        app_log('error', 'Audit log write failed: ' . $e->getMessage(), [
            'action' => $action,
            'entity' => $entityType,
            'id'     => $entityId,
        ]);
    }
}

/**
 * Parola gibi hassas alanlar audit'e ASLA yazilmaz.
 *
 * @param array<string, mixed> $data
 */
function audit_json(array $data): string
{
    $sensitive = ['password', 'password_hash', 'password_confirm', '_token', 'pass'];
    foreach ($sensitive as $key) {
        if (array_key_exists($key, $data)) {
            $data[$key] = '***';
        }
    }
    return (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Iki durum arasindaki farki cikarir.
 * Donus: [degisen_eski_degerler, degisen_yeni_degerler]
 * Sadece GERCEKTEN degisen alanlar loglanir -> audit tablosu sismez.
 *
 * @param array<string, mixed> $before
 * @param array<string, mixed> $after
 * @param list<string> $ignore
 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
 */
function audit_diff(array $before, array $after, array $ignore = ['updated_at', 'created_at']): array
{
    $old = [];
    $new = [];

    foreach ($after as $key => $value) {
        if (in_array($key, $ignore, true)) {
            continue;
        }
        $prev = $before[$key] ?? null;
        if ((string)$prev !== (string)$value) {
            $old[$key] = $prev;
            $new[$key] = $value;
        }
    }

    return [$old, $new];
}
