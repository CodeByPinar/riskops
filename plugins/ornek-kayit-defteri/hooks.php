<?php

declare(strict_types=1);

/**
 * Örnek eklenti - kancalar
 * /var/www/riskops/plugins/ornek-kayit-defteri/hooks.php
 *
 * Bu dosya YALNIZCA eklenti açıkken yüklenir (plugins_boot).
 *
 * Gösterdiği üç şey:
 *   1. EYLEM kancası   - risk olaylarını ayrı bir dosyaya yazmak
 *   2. FİLTRE kancası  - menüye giriş eklemek
 *   3. Öncelik         - sıralamanın nasıl belirlendiği
 *
 * KAPSAM UYARISI
 * --------------
 * Bu dosya plugins_load_hooks() içinde require ediliyor, yani
 * fonksiyon kapsamında çalışıyor. Burada tanımlanan DEĞİŞKENLER o
 * kapsamda kalır ve dışarı sızmaz; FONKSİYONLAR ise global olur.
 * Bu yüzden eklenti fonksiyonlarının adı benzersiz olmalı - aşağıdaki
 * gibi eklenti adıyla öneklemek yeterli bir kuraldır.
 */

/**
 * Risk olaylarının yazılacağı dosya.
 *
 * storage/logs altında: web'den erişilemeyen tek yazılabilir dizin.
 * Eklentiler kendi dosyalarını APP_ROOT altına serbestçe yazmamalı.
 */
function ornek_kayit_defteri_dosya(): string
{
    return LOG_PATH . '/ornek-risk-olaylari.log';
}

/** Tek satır yazar. Hiçbir koşulda istisna fırlatmaz. */
function ornek_kayit_defteri_yaz(string $olay, int $riskId, array $veri = []): void
{
    $satir = sprintf(
        "[%s] %-14s risk=%d kullanici=%s %s\n",
        date('Y-m-d H:i:s'),
        $olay,
        $riskId,
        auth_check() ? auth_name() : '-',
        $veri !== [] ? json_encode($veri, JSON_UNESCAPED_UNICODE) : ''
    );

    @file_put_contents(ornek_kayit_defteri_dosya(), $satir, FILE_APPEND | LOCK_EX);
}

/* =====================================================================
 * 1) EYLEM KANCALARI
 *
 * Dönüş değeri yok sayılır. Bir hata fırlatırsa risk kaydetme İŞLEMİ
 * ETKİLENMEZ - çekirdek hatayı yakalar, loglar ve devam eder.
 * ===================================================================*/

hook_add('risk.created', static function (int $riskId, array $veri): void {
    ornek_kayit_defteri_yaz('OLUSTURULDU', $riskId, [
        'kod'    => $veri['risk_code'] ?? '?',
        'baslik' => $veri['title'] ?? '?',
    ]);
});

hook_add('risk.updated', static function (int $riskId, array $eski, array $yeni): void {
    /* Eski ve yeni birlikte geliyor: "hangi alan değişti" sorusunun
       cevabı yalnızca yeni değerden çıkarılamaz. */
    ornek_kayit_defteri_yaz('GUNCELLENDI', $riskId, [
        'degisen' => array_keys($yeni),
    ]);
});

hook_add('risk.deleted', static function (int $riskId, array $veri): void {
    ornek_kayit_defteri_yaz('SILINDI', $riskId, ['kod' => $veri['risk_code'] ?? '?']);
});

/* =====================================================================
 * 2) FİLTRE KANCASI
 *
 * Değer zincirden geçer; DÖNDÜRMEK ZORUNLUDUR. Döndürülmezse menü
 * null olur ve çekirdek bunu boş menü olarak görür.
 * ===================================================================*/

hook_add('nav.items', static function (array $items): array {
    /* Yalnızca admin görsün. Bu GÖRSEL bir kontroldür - eklentinin
       açtığı bir sayfa varsa yetkisini o sayfa kendi kontrol etmeli
       (require_role), tıpkı çekirdek sayfaları gibi. */
    if (auth_role() !== ROLE_ADMIN) {
        return $items;
    }

    /* Menünün SONUNA değil, "Analiz" bölümünün hemen ardına ekliyoruz:
       araya girmek de mümkün, sıralama eklentinin elinde. */
    $yeni = [];
    foreach ($items as $item) {
        $yeni[] = $item;

        if (isset($item['key']) && $item['key'] === 'reports') {
            $yeni[] = [
                'key'   => 'ornek.gunluk',
                'label' => 'Risk Olay Günlüğü',
                'icon'  => 'bi-journal-code',
                /* Site içi yol zorunlu; çekirdek dışarıya çıkan ya da
                   javascript: ile başlayan girişleri reddediyor. */
                'href'  => '/admin/plugins/',
            ];
        }
    }

    return $yeni;
}, 20);

/* =====================================================================
 * 3) ÖNCELİK
 *
 * Küçük olan önce çalışır (varsayılan 10). Yukarıdaki menü kancası 20
 * ile eklendi: başka bir eklenti menüyü yeniden sıralamak isterse 10
 * ile eklenip bizden önce çalışabilir.
 * ===================================================================*/
