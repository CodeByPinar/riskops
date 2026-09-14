<?php

declare(strict_types=1);

/**
 * Örnek eklenti - bildirim (manifest)
 * /var/www/riskops/plugins/ornek-kayit-defteri/plugin.php
 *
 * Bu dosya YALNIZCA bir dizi döndürür. Kod çalıştırmaz, veritabanına
 * dokunmaz - çünkü uygulama, eklenti KAPALIYKEN de bu dosyayı okumak
 * zorunda (yönetim ekranında listelemek için). Kapalı bir eklentinin
 * bildirimi yan etki üretirse "kapalı" sözü boşa çıkar.
 *
 * Asıl kod hooks.php içinde ve yalnızca eklenti AÇIKKEN yüklenir.
 *
 * ZORUNLU ALANLAR
 *   name, version
 *
 * İSTEĞE BAĞLI
 *   slug          verilirse DİZİN ADIYLA aynı olmalı
 *   author, description, url
 *   requires_php  bundan eski PHP'de eklenti yüklenmez
 *   hooks         kanca dosyası; eklenti dizininin İÇİNDE olmalı
 */

return [
    'slug'        => 'ornek-kayit-defteri',
    'name'        => 'Örnek: Risk Olay Günlüğü',
    'version'     => '1.0.0',
    'author'      => 'RiskOps',
    'description' => 'Eklenti sisteminin nasıl çalıştığını gösteren örnek. '
                   . 'Menüye bir giriş ekler ve risk olaylarını ayrı bir '
                   . 'günlük dosyasına yazar.',
    'requires_php' => '8.2',
    'hooks'        => 'hooks.php',
];
