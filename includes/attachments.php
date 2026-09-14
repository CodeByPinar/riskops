<?php

declare(strict_types=1);

/**
 * RiskOps - Dosya eki yardımcıları
 * /var/www/riskops/includes/attachments.php
 *
 * DOSYA YÜKLEME, UYGULAMANIN EN TEHLİKELİ YÜZEYİDİR. Beş kural birlikte
 * uygulanır; hiçbiri tek başına yeterli değildir:
 *
 *   1. İstemciden gelen ad ASLA diske yazılmaz (ad uygulama üretir)
 *   2. Uzantı beyaz listeden (kara liste .phtml/.php5/.phar ile bitmez)
 *   3. Gerçek içerik tipi finfo ile doğrulanır (Content-Type sahtedir)
 *   4. Dosyalar web erişimine kapalı dizinde durur (.htaccess + VHost)
 *   5. İndirme her zaman octet-stream + attachment; hiçbir ek RENDER
 *      EDİLMEZ (inline SVG/HTML = uygulamanın kökeninde kalıcı XSS)
 *
 * Her kuralın hangi saldırıyı kapattığı ve alternatifler:
 *     docs/architecture/0008-dosya-eki-guvenligi.md
 */

/** Tek dosya için üst sınır. php.ini upload_max_filesize bundan büyük olmalı. */
const ATTACH_MAX_BYTES = 10485760;          // 10 MB

/** Bir riske bağlanabilecek en fazla ek. */
const ATTACH_MAX_PER_RISK = 20;

/**
 * İzin verilen uzantılar ve o uzantı için kabul edilen GERÇEK tipler.
 *
 * Office dosyaları zip kabı olduğu için finfo çoğu sistemde
 * application/zip döndürür; bu yüzden listelerde o da var.
 *
 * BURAYA .svg, .html, .htm, .xml EKLEMEYİN: hepsi tarayıcıda script
 * çalıştırabilir. Zorla indirme bunu bir kat engelliyor ama savunmayı
 * tek katmana bırakmayın.
 *
 * @return array<string, list<string>>
 */
function attach_allowed_types(): array
{
    return [
        'pdf'  => ['application/pdf'],
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/csv', 'text/plain'],
        'doc'  => ['application/msword', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                   'application/zip'],
        'xls'  => ['application/vnd.ms-excel', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                   'application/zip'],
        'zip'  => ['application/zip'],
    ];
}

/** Eklerin tutulduğu dizin. */
function attach_dir(): string
{
    return UPLOAD_PATH . '/attachments';
}

/** İnsan okunur boyut. */
function attach_format_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    }
    return $bytes . ' B';
}

/** Uzantıya göre gösterilecek ikon. */
function attach_icon(string $ext): string
{
    return match (strtolower($ext)) {
        'pdf'                  => 'bi-file-earmark-pdf',
        'doc', 'docx'          => 'bi-file-earmark-word',
        'xls', 'xlsx', 'csv'   => 'bi-file-earmark-spreadsheet',
        'png', 'jpg', 'jpeg',
        'gif', 'webp'          => 'bi-file-earmark-image',
        'zip'                  => 'bi-file-earmark-zip',
        default                => 'bi-file-earmark',
    };
}

/**
 * PHP'nin yükleme hata kodunu kullanıcıya anlatılabilir mesaja çevirir.
 *
 * UPLOAD_ERR_INI_SIZE özel olarak önemli: dosya php.ini sınırını
 * aştığında $_FILES['...']['size'] 0 gelir ve "boş dosya" gibi
 * görünür. Ayırt edilmezse kullanıcı neden başarısız olduğunu anlamaz.
 */
function attach_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
            'Dosya sunucu sınırını aşıyor (en fazla '
            . attach_format_size(ATTACH_MAX_BYTES) . ').',
        UPLOAD_ERR_PARTIAL    => 'Dosya yalnızca kısmen yüklendi, tekrar deneyin.',
        UPLOAD_ERR_NO_FILE    => 'Dosya seçilmedi.',
        UPLOAD_ERR_NO_TMP_DIR => 'Sunucuda geçici dizin bulunamadı.',
        UPLOAD_ERR_CANT_WRITE => 'Dosya diske yazılamadı.',
        UPLOAD_ERR_EXTENSION  => 'Yükleme bir PHP eklentisi tarafından durduruldu.',
        default               => 'Dosya yüklenemedi.',
    };
}

/**
 * Yüklenen dosyayı doğrular ve diske yazar.
 *
 * @param array $file $_FILES['...'] dizisi
 * @return array{ok:bool, error?:string, stored?:string, original?:string,
 *                mime?:string, size?:int}
 *
 * @param array<string, mixed> $file
 */
function attach_store(array $file): array
{
    /* --- 1. PHP'nin kendi hata kodu --- */
    $code = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($code !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => attach_error_message($code)];
    }

    $tmp = (string)($file['tmp_name'] ?? '');

    /* --- 2. Gercekten HTTP ile yuklenmis bir dosya mi? ---
       is_uploaded_file olmadan, tmp_name yerine "/etc/passwd" gibi bir
       yol gecirilebilirse sunucudaki herhangi bir dosya kopyalanabilirdi. */
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Geçersiz yükleme.'];
    }

    /* --- 3. Boyut ---
       Guvenilir olan disktekidir; $file['size'] istemciden gelir. */
    $size = (int)filesize($tmp);
    if ($size <= 0) {
        return ['ok' => false, 'error' => 'Dosya boş.'];
    }
    if ($size > ATTACH_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Dosya çok büyük (en fazla '
            . attach_format_size(ATTACH_MAX_BYTES) . ').'];
    }

    /* --- 4. Uzanti beyaz listesi --- */
    $originalRaw = (string)($file['name'] ?? '');
    $ext = strtolower((string)pathinfo($originalRaw, PATHINFO_EXTENSION));
    $allowed = attach_allowed_types();

    if ($ext === '' || !isset($allowed[$ext])) {
        return ['ok' => false, 'error' => 'Bu dosya türüne izin verilmiyor. '
            . 'İzin verilenler: ' . implode(', ', array_keys($allowed)) . '.'];
    }

    /* --- 5. GERCEK icerik tipi ---
       Istemcinin bildirdigi $file['type'] KULLANILMAZ; tamamen sahtedir. */
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);

    if (!in_array($mime, $allowed[$ext], true)) {
        return ['ok' => false, 'error' => 'Dosya içeriği uzantısıyla uyuşmuyor '
            . '(' . e($mime) . '). Yükleme reddedildi.'];
    }

    /* --- 6. Diskteki ad: UYGULAMA URETIR, istemciden gelmez --- */
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;

    $dir = attach_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        app_log('error', 'Attachment directory could not be created', ['dir' => $dir]);
        return ['ok' => false, 'error' => 'Yükleme dizini oluşturulamadı.'];
    }

    $target = $dir . '/' . $stored;

    if (!move_uploaded_file($tmp, $target)) {
        app_log('error', 'move_uploaded_file failed', ['target' => $target]);
        return ['ok' => false, 'error' => 'Dosya kaydedilemedi.'];
    }

    /* Calistirma bitini kapat; dizin zaten web'e kapali ama dosya
       izninin de acik kalmasi icin bir sebep yok. */
    @chmod($target, 0640);

    /* Gorunen ad: yalnizca temel ad alinir (dizin bileseni atilir) ve
       kontrol karakterleri temizlenir. Diske YAZILMAZ, sadece
       veritabaninda saklanip ekranda e() ile kacisli gosterilir. */
    $original = (string)preg_replace('/[\x00-\x1F\x7F]/u', '', basename($originalRaw));
    if ($original === '') {
        $original = 'dosya.' . $ext;
    }
    if (mb_strlen($original) > 255) {
        $original = mb_substr($original, 0, 255);
    }

    return [
        'ok'       => true,
        'stored'   => $stored,
        'original' => $original,
        'mime'     => $mime,
        'size'     => $size,
    ];
}

/**
 * Diskteki dosyayı siler. Veritabanı satırını SİLMEZ - çağıran yapar.
 *
 * stored_name doğrudan birleştirilmez: veritabanından gelse bile
 * dizin dışına çıkan bir değer olmadığı doğrulanır. Savunma katmanı
 * ucuz, hata pahalı.
 */
function attach_delete_file(string $storedName): bool
{
    if ($storedName === '' || basename($storedName) !== $storedName) {
        app_log('warning', 'Suspicious attachment name rejected', ['name' => $storedName]);
        return false;
    }

    $path = attach_dir() . '/' . $storedName;

    return !is_file($path) || @unlink($path);
}
