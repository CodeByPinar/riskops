# =====================================================================
#  RiskOps - uygulama imajı
#
#  Tek komutla ayağa kaldırmak için:  docker compose up
#
#  Taban imaj olarak php:8.3-apache seçildi: uygulama Apache + mod_php
#  varsayımıyla yazıldı (.htaccess korumaları, SetEnv ile ortam seçimi).
#  php-fpm + nginx'e geçirmek bu varsayımları kırardı.
# =====================================================================

FROM php:8.3-apache

# ---------------------------------------------------------------------
#  PHP eklentileri
#
#  Yalnızca pdo_mysql gerekiyor. mbstring resmi imajda zaten derlenmiş
#  durumda; gd, intl, curl uygulamanın web yolunda kullanılmıyor
#  (ikonlar depoya üretilmiş olarak giriyor).
# ---------------------------------------------------------------------
RUN docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------
#  Apache modülleri
#
#  headers  : güvenlik başlıkları
#  rewrite  : .htaccess yönlendirmeleri
#  expires  : statik varlık önbelleği
# ---------------------------------------------------------------------
RUN a2enmod headers rewrite expires

# ---------------------------------------------------------------------
#  Apache yapılandırması
#
#  KRİTİK: Debian'ın varsayılan apache2.conf dosyasında /var/www/ için
#  AllowOverride None yazar. Bu hâliyle uygulamanın ALTI .htaccess
#  dosyası sessizce yok sayılır ve config/database.php internetten
#  okunabilir hâle gelir. Aşağıdaki dosya bunu AllowOverride All yapar
#  ve ayrıca .htaccess'e güvenmeyen ikinci bir koruma katmanı koyar.
# ---------------------------------------------------------------------
COPY docker/apache-riskops.conf /etc/apache2/conf-available/riskops.conf
RUN a2enconf riskops

# ---------------------------------------------------------------------
#  Uygulama
# ---------------------------------------------------------------------
WORKDIR /var/www/html
COPY . /var/www/html

# Ortam değişkeninden okuyan veritabanı yapılandırması.
# Depodaki config/database.php .gitignore içindedir; imajda bu geçerli.
COPY docker/database.php /var/www/html/config/database.php

# Çalışma zamanı dizinleri
RUN mkdir -p storage/logs storage/uploads \
    && chown -R www-data:www-data storage \
    && chmod -R 750 storage

COPY docker/entrypoint.sh /usr/local/bin/riskops-entrypoint
RUN chmod +x /usr/local/bin/riskops-entrypoint

EXPOSE 80

ENTRYPOINT ["riskops-entrypoint"]
CMD ["apache2-foreground"]
