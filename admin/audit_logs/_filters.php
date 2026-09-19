<?php

declare(strict_types=1);

/**
 * RiskOps - Denetim kaydı filtreleri (ortak)
 * /var/www/riskops/admin/audit_logs/_filters.php
 *
 * Hem liste ekranı hem CSV dışa aktarma aynı filtreleri kullanır.
 * İki yere kopyalansaydı, ekranda görünen ile indirilen dosya
 * ayrışabilirdi — denetim çıktısında bu kabul edilemez.
 *
 * Döndürür: ['f' => filtreler, 'where' => SQL, 'params' => bağlamalar]
 *
 * @return array<string, mixed>
 */

function audit_filters(): array
{
    /* Secenekler tablodan gelir: elle yazilan bir liste, yeni bir islem
       turu eklendiginde sessizce eskir. */
    /* Iki bicim de gerekiyor: duz liste input_enum dogrulamasi icin,
       sayimli liste acilir menude "(12)" gostermek icin. */
    $actionCounts = db_all(
        'SELECT action, COUNT(*) AS adet FROM audit_logs GROUP BY action ORDER BY action'
    );
    $actionOptions = array_column($actionCounts, 'action');

    $entityOptions = db()->query(
        'SELECT entity_type FROM audit_logs WHERE entity_type IS NOT NULL
         GROUP BY entity_type ORDER BY entity_type'
    )->fetchAll(PDO::FETCH_COLUMN);

    $f = [
        'action' => input_enum('action', $actionOptions),
        'entity' => input_enum('entity', $entityOptions),
        'user'   => input_int('user'),
        'from'   => input_date('from'),
        'to'     => input_date('to'),
        'q'      => input('q'),
    ];

    /* Tarih araligi tersse yok say: kullaniciya bos bir sonuc yerine
       filtresiz liste vermek daha az kafa karistirici. */
    if ($f['from'] !== null && $f['to'] !== null && $f['from'] > $f['to']) {
        $f['from'] = $f['to'] = null;
    }

    $where  = ['1=1'];
    $params = [];

    if ($f['action'] !== null) {
        $where[] = 'l.action = :action';
        $params[':action'] = $f['action'];
    }
    if ($f['entity'] !== null) {
        $where[] = 'l.entity_type = :entity';
        $params[':entity'] = $f['entity'];
    }
    if ($f['user'] !== null) {
        $where[] = 'l.user_id = :user';
        $params[':user'] = $f['user'];
    }
    if ($f['from'] !== null) {
        $where[] = 'l.created_at >= :dfrom';
        $params[':dfrom'] = $f['from'] . ' 00:00:00';
    }
    if ($f['to'] !== null) {
        $where[] = 'l.created_at <= :dto';
        $params[':dto'] = $f['to'] . ' 23:59:59';
    }
    if ($f['q'] !== null && trim($f['q']) !== '') {
        /* Ayri placeholder'lar: EMULATE_PREPARES=false ayni adin tekrar
           kullanilmasina izin vermez (SQLSTATE HY093). */
        $where[] = '(l.user_name_snapshot LIKE :q1 OR l.old_values LIKE :q2'
                 . ' OR l.new_values LIKE :q3 OR l.ip_address LIKE :q4)';
        $like = '%' . addcslashes(trim($f['q']), '\\%_') . '%';
        $params += [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
    }

    return [
        'f'        => $f,
        'actions'  => $actionOptions,
        'counts'   => $actionCounts,
        'entities' => $entityOptions,
        'where'    => implode(' AND ', $where),
        'params'   => $params,
        'active'   => count(array_filter($f, static fn ($v) => $v !== null && $v !== '')),
    ];
}
