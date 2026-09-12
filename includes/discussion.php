<?php
declare(strict_types=1);

/**
 * RiskOps - Yorum ve ek: ortak katman
 * /var/www/riskops/includes/discussion.php
 *
 * Riskler ve aksiyonlar aynı iki yeteneği paylaşıyor: yorum ve dosya
 * eki. Uç noktaları iki kez yazmak yerine tek yerde toplandı; hangi
 * tabloya yazılacağı buradaki kayıt defterinden geliyor.
 *
 * NEDEN AYRI TABLOLAR, TEK TABLO DEĞİL
 * ------------------------------------
 * Tek bir polimorfik tablo (entity_type + entity_id) daha az tekrar
 * olurdu ama polimorfik anahtara YABANCI ANAHTAR verilemez. Silinen
 * bir aksiyonun yorumları sahipsiz kalır ve veritabanı bunu
 * engelleyemezdi. Bu uygulamada bütünlük veritabanının işi, kodun
 * değil — tekrar orada değil, BURADA ortadan kaldırıldı.
 *
 * TABLO ADLARI SORGUYA DOĞRUDAN GİRİYOR
 * -------------------------------------
 * SQL'de tablo adı placeholder olamaz. Bu yüzden adlar aşağıdaki SABİT
 * kayıt defterinden okunuyor ve tür anahtarı discussion_type() ile
 * beyaz listeden doğrulanıyor. İstemciden gelen hiçbir değer sorguya
 * girmiyor.
 */

/**
 * Desteklenen tartışma hedefleri.
 *
 * @return array<string, array<string, string>>
 */
function discussion_registry(): array
{
    return [
        'risk' => [
            'comments'     => 'risk_comments',
            'attachments'  => 'risk_attachments',
            'fk'           => 'risk_id',
            'parent'       => 'risks',
            'parent_label' => 'risk',
            'title_col'    => 'title',
            'code_col'     => 'risk_code',
            'ability'      => 'risk.update',
            'view_ability' => 'risk.view',
            'url'          => '/risks/view.php?id=',
            /* Riskler soft delete kullanir: silinmis bir riske yorum
               eklenemez, eki indirilemez. */
            'alive'        => 'deleted_at IS NULL',
        ],
        'action' => [
            'comments'     => 'action_comments',
            'attachments'  => 'action_attachments',
            'fk'           => 'action_id',
            'parent'       => 'risk_actions',
            'parent_label' => 'aksiyon',
            'title_col'    => 'title',
            'code_col'     => null,
            'ability'      => 'action.update',
            'view_ability' => 'action.view',
            'url'          => '/actions/view.php?id=',
            /* risk_actions tablosunda soft delete yok. */
            'alive'        => '1 = 1',
        ],
    ];
}

/**
 * İstemciden gelen tür anahtarını doğrular.
 *
 * Beyaz listede olmayan her değer akışı sonlandırır: bu değer tablo
 * adı seçiminde kullanılıyor, hata yapmanın maliyeti yüksek.
 */
function discussion_type(): string
{
    $type = input_enum('type', array_keys(discussion_registry()));

    if ($type === null) {
        app_abort(400, 'Unknown discussion type');
    }

    return $type;
}

/** Kayıt defteri satırı. */
function discussion_config(string $type): array
{
    $reg = discussion_registry();

    if (!isset($reg[$type])) {
        app_abort(400, 'Unknown discussion type');
    }

    return $reg[$type];
}

/**
 * Üst kaydı (risk ya da aksiyon) getirir; yoksa akışı sonlandırır.
 */
function discussion_parent(string $type, ?int $id): array
{
    $cfg = discussion_config($type);

    if ($id === null || $id < 1) {
        app_abort(400, 'Missing parent id');
    }

    $cols = 'id, ' . $cfg['title_col'] . ' AS title'
          . ($cfg['code_col'] !== null ? ', ' . $cfg['code_col'] . ' AS code' : ', NULL AS code');

    /* Tablo adi ve kolonlar SABIT kayit defterinden; istemciden gelen
       tek deger :id ve o da placeholder. */
    $stmt = db()->prepare(
        'SELECT ' . $cols . ' FROM ' . $cfg['parent']
        . ' WHERE id = :id AND ' . $cfg['alive'] . ' LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if ($row === false) {
        flash('error', ucfirst($cfg['parent_label']) . ' bulunamadı.');
        redirect($type === 'risk' ? '/risks/' : '/actions/');
    }

    return $row;
}

/** Üst kaydın görüntüleme adresi. */
function discussion_url(string $type, int $parentId, string $hash = ''): string
{
    return discussion_config($type)['url'] . $parentId . $hash;
}

/** Bir kaydın yorumları, yazarlarıyla birlikte. */
function discussion_comments(string $type, int $parentId): array
{
    $cfg = discussion_config($type);

    $stmt = db()->prepare(
        'SELECT c.id, c.body, c.created_at, c.user_id, u.name AS author_name
           FROM ' . $cfg['comments'] . ' c
           LEFT JOIN users u ON u.id = c.user_id
          WHERE c.' . $cfg['fk'] . ' = :id
          ORDER BY c.created_at DESC'
    );
    $stmt->execute([':id' => $parentId]);

    return $stmt->fetchAll();
}

/** Bir kaydın ekleri, yükleyenleriyle birlikte. */
function discussion_attachments(string $type, int $parentId): array
{
    $cfg = discussion_config($type);

    $stmt = db()->prepare(
        'SELECT a.id, a.original_name, a.mime_type, a.size_bytes, a.created_at,
                a.uploaded_by, u.name AS uploader_name
           FROM ' . $cfg['attachments'] . ' a
           LEFT JOIN users u ON u.id = a.uploaded_by
          WHERE a.' . $cfg['fk'] . ' = :id
          ORDER BY a.created_at DESC'
    );
    $stmt->execute([':id' => $parentId]);

    return $stmt->fetchAll();
}

/**
 * Bir yorumu/eki silme yetkisi var mı?
 *
 * Kendi içeriğini herkes silebilir; başkasınınkini yalnızca admin.
 * Aynı yetkiye sahip iki kullanıcıdan biri diğerinin kaydını
 * silebilseydi, tartışma tek taraflı temizlenebilirdi.
 */
function discussion_may_delete(?int $ownerId): bool
{
    if ($ownerId !== null && (int)$ownerId === auth_id()) {
        return true;
    }

    return auth_role() === ROLE_ADMIN;
}
