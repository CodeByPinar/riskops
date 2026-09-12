# RiskOps — Herkese açık demo kurulumu

Bu belge, RiskOps'u internete açık bir tanıtım kurulumu olarak yayına
almanın adımlarıdır. Hedef: LinkedIn ve GitHub'dan tıklanabilen,
`viewer` hesabıyla gezilebilen, verisi her gece sıfırlanan bir demo.

> **Kurum içi gerçek kullanım için bu belgeyi kullanmayın.** Orada demo
> verisi ve paylaşılan hesap silinir, gerçek kullanıcılar açılır,
> yedekleme kurulur. Farklı bir yordamdır.

---

## 0. Ön koşullar

| Gereken | Not |
|---|---|
| VPS | 1 vCPU / 1 GB RAM yeter. Hetzner CX22 (~€4/ay), DigitalOcean, Contabo |
| Alan adı | `demo.alanadiniz.com` gibi bir alt alan adı yeterli |
| Ubuntu 24.04 | Sunucuyu kurarken bu imajı seçin |

Alan adının **A kaydını** sunucunun IP'sine yönlendirin. Doğrulayın:

```bash
dig +short demo.alanadiniz.com
```

Sunucunun IP'sini dönmeli. Let's Encrypt sertifikası bu kayıt olmadan
alınamaz, bu yüzden devam etmeden önce beklenir (yayılma 5–30 dk).

---

## 1. Sunucuyu hazırlama

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 mariadb-server php php-mysql php-mbstring php-gd \
                    php-intl git certbot python3-certbot-apache ufw fail2ban
```

### Güvenlik duvarı

```bash
sudo ufw allow OpenSSH && sudo ufw allow "Apache Full" && sudo ufw --force enable
```

`ufw status` çıktısında yalnızca 22, 80, 443 görünmeli. **MariaDB'nin
3306 portu dışarı açılmamalıdır** — uygulama ile aynı makinede.

### Veritabanını sıkılaştırma

```bash
sudo mysql_secure_installation
```

Sorulara: anonim kullanıcıları sil **evet**, uzaktan root girişi
kapat **evet**, test veritabanını sil **evet**.

---

## 2. Uygulamayı yerleştirme

```bash
sudo git clone https://github.com/CodeByPinar/riskops.git /var/www/riskops
sudo chown -R www-data:www-data /var/www/riskops
```

### Veritabanı

Parolayı önce üretin, sonra kullanın — akılda kalan bir parola seçmeyin:

```bash
openssl rand -base64 24
```

Çıkan değeri `<DB_PAROLA>` yerine yazarak:

```bash
sudo mysql -e "CREATE DATABASE riskops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'riskops_user'@'localhost' IDENTIFIED BY '<DB_PAROLA>';"
sudo mysql -e "GRANT SELECT,INSERT,UPDATE,DELETE ON riskops.* TO 'riskops_user'@'localhost';"
```

> Uygulama kullanıcısına bilerek `DROP` ve `ALTER` verilmiyor. Bir SQL
> enjeksiyonu açığı bulunsa bile tablo düşürülemez. Şema değişikliğini
> `root` ile siz yaparsınız.

```bash
sudo mysql riskops < /var/www/riskops/database/schema.sql
sudo mysql riskops < /var/www/riskops/database/seed.sql
```

### Yapılandırma

```bash
sudo cp /var/www/riskops/config/database.example.php /var/www/riskops/config/database.php
sudo nano /var/www/riskops/config/database.php          # 'pass' -> <DB_PAROLA>
sudo chown www-data:www-data /var/www/riskops/config/database.php
sudo chmod 640 /var/www/riskops/config/database.php
```

`640` önemli: dosyayı yalnızca sahibi ve grubu okuyabilir. Sunucuda
başka bir kullanıcı varsa veritabanı parolasını göremez.

### Yazılabilir dizin

```bash
sudo mkdir -p /var/www/riskops/storage/logs /var/www/riskops/storage/uploads
sudo chown -R www-data:www-data /var/www/riskops/storage
sudo chmod -R 750 /var/www/riskops/storage
```

---

## 3. Apache

```bash
sudo cp /var/www/riskops/deploy/riskops-demo.conf /etc/apache2/sites-available/
sudo sed -i 's/<DOMAIN>/demo.alanadiniz.com/g' /etc/apache2/sites-available/riskops-demo.conf
```

Sertifika henüz yok; 443 bloğu bu hâlde Apache'yi başlatmaz. Önce
yalnızca 80 ile açıp sertifikayı alın:

```bash
sudo a2enmod headers rewrite expires ssl
sudo a2dissite 000-default
sudo a2ensite riskops-demo
sudo apache2ctl configtest
```

`configtest` sertifika dosyası bulunamadığı için hata verirse, 443
bloğunu geçici olarak yorum satırına alın, `systemctl reload apache2`
yapın, sertifikayı alın, sonra yorumu kaldırın.

### HTTPS

```bash
sudo certbot --apache -d demo.alanadiniz.com
```

Certbot sertifikayı alır ve yapılandırmayı düzenler. Yenileme otomatik
kurulur; doğrulayın:

```bash
sudo certbot renew --dry-run
```

---

## 4. Demo kipini açma

Demo kipi bir **ortam değişkeni**dir, kod değişikliği değil.
`riskops-demo.conf` içinde zaten tanımlı:

```apache
SetEnv RISKOPS_ENV  production
SetEnv RISKOPS_DEMO 1
```

Etkisi: giriş ekranında deneme hesabının bilgileri görünür, e-posta
alanı önceden dolu gelir, verinin her gece sıfırlandığı yazar. Demo
hesabı parolasını **değiştiremez** (yoksa bir ziyaretçi tüm demoyu
kilitleyebilirdi).

**Yetkilendirmeye hiçbir etkisi yoktur.** Ziyaretçinin ne yapabileceğini
hesabın rolü belirler; rol `viewer`'dır ve hiçbir yazma ucuna erişemez.

### Deneme hesabını oluşturma

```bash
cd /var/www/riskops && sudo -u www-data env RISKOPS_DEMO=1 php tools/demo_reset.php
```

---

## 5. Admin parolası

Bu adımı atlamayın. Varsayılan parola (`Admin123456`) README'de yazılı
ve depoda herkese açık.

```bash
openssl rand -base64 18
```

Çıkan parolayı bir yere kaydedin, sonra:

```bash
cd /var/www/riskops && sudo -u www-data php -r 'require "includes/bootstrap.php"; $p = $argv[1]; db()->prepare("UPDATE users SET password=?, must_change_password=0, password_changed_at=NOW() WHERE email=?")->execute([password_hash($p, PASSWORD_DEFAULT), "admin@riskops.local"]); echo "admin parolasi guncellendi\n";' '<YENI_PAROLA>'
```

> Bu parolayı hiçbir yerde yayınlamayın. Demo ziyaretçisinin admin
> hesabına ihtiyacı yok; `viewer` hesabı uygulamanın tamamını gezmeye
> yeter.

Demo seed'i ile gelen `manager@` / `analyst@` hesapları da yazma
yetkisine sahiptir. Herkese açık kurulumda pasifleştirin:

```bash
sudo mysql riskops -e "UPDATE users SET status=0 WHERE email IN ('manager@riskops.local','analyst@riskops.local','analyst2@riskops.local');"
```

---

## 6. Gece sıfırlaması

Ziyaretçiler veriyi bozar, tarih geçer, audit kaydı şişer. Her gece
04:00'te baştan kurulsun:

```bash
sudo crontab -e
```

Şu satırı ekleyin:

```
0 4 * * * cd /var/www/riskops && sudo -u www-data env RISKOPS_DEMO=1 /usr/bin/php tools/demo_reset.php >> /var/log/riskops-demo-reset.log 2>&1
```

`RISKOPS_DEMO=1` burada **zorunludur**: betik bu değişken olmadan
hiçbir şey yapmadan çıkar. Gerçek bir kuruluma yanlışlıkla kopyalansa
bile veri silemez.

Giriş ekranındaki metin "04:00" diyor; saati değiştirirseniz
`auth/login.php` içindeki yazıyı da güncelleyin.

---

## 7. tools/ dizini

Kurum kurulumunda `tools/` tamamen kaldırılır. **Demoda kaldırılamaz** —
gece sıfırlaması `demo_reset.php` ve `seed_demo.php`'ye ihtiyaç duyar.

Bunun yerine iki katmanla korunur:

1. `riskops-demo.conf` içindeki `DirectoryMatch` `tools/` dizinine web
   erişimini reddeder
2. `demo_reset.php` `PHP_SAPI !== 'cli'` ise 404 döndürür

Doğrulayın — 403 veya 404 dönmeli, asla 200:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://demo.alanadiniz.com/tools/demo_reset.php
curl -s -o /dev/null -w "%{http_code}\n" https://demo.alanadiniz.com/config/database.php
```

---

## 8. Son kontrol

```bash
cd /var/www/riskops && sudo -u www-data php tools/go_live_check.php
```

Herkese açık demo için beklenen sonuç:

| Madde | Beklenen |
|---|---|
| APP_ENV | `production` — **FAIL kalmamalı** |
| Admin varsayılan parola | değiştirilmiş — **FAIL kalmamalı** |
| HTTPS | uygun |
| Örnek risk verisi | WARN kalır — demoda olması gerekiyor |
| Demo kullanıcı hesapları | WARN kalır — deneme hesabı kasıtlı |
| tools/ dizini | WARN kalır — sıfırlama için gerekli, web'e kapalı |

**İki FAIL de kapanmalı.** Kalan WARN'lar bu kurulum için doğrudur.

### Elle doğrulama

```bash
curl -sI https://demo.alanadiniz.com/ | grep -iE "strict-transport|content-security|x-frame|x-robots"
```

Dördü de görünmeli. Sonra tarayıcıda:

- Giriş ekranında deneme hesabı kutusu görünüyor mu
- `demo@riskops.local` ile girilebiliyor mu
- Kenar çubuğunda "Yeni Risk", "Users", "Settings" **görünmüyor** mu
- Parola değiştirme anahtar ikonu **görünmüyor** mu
- Yönetici özeti raporu açılıyor ve yazdırma önizlemesi düzgün mü

Kenar çubuğunda bağlantının olmaması bir güvenlik önlemi **değildir** —
yalnızca görsel bir düzenlemedir. Asıl kontrolün sunucuda olduğunu
doğrulamak için adresi doğrudan yazın:

```
https://demo.alanadiniz.com/risks/create.php
https://demo.alanadiniz.com/admin/users/
```

İkisi de yetki hatası vermeli. Form görünüyorsa durun — `viewer` rolü
yazma ucuna erişebiliyor demektir ve demo yayına alınmamalıdır.

---

## 9. Bakım

**Yedek gerekmez.** Demo verisi her gece yeniden üretiliyor; kaybedilecek
bir şey yok. Yedeklemeniz gereken tek şey `config/database.php` (ki onu
yeniden yazmak 1 dakika).

**Güncelleme:**

```bash
cd /var/www/riskops && sudo -u www-data git pull && sudo -u www-data php tools/smoke_test.php
```

Şema değiştiyse `database/schema.sql` farkını `root` ile elle uygulayın —
uygulama kullanıcısının `ALTER` yetkisi yoktur.

**Log takibi:**

```bash
sudo tail -f /var/log/apache2/riskops-error.log
sudo tail -20 /var/log/riskops-demo-reset.log
```

---

## 10. Bundan sonra

README'ye canlı demo rozeti ekleyin:

```markdown
<p align="center">
  <a href="https://demo.alanadiniz.com"><strong>Canlı demo →</strong></a><br>
  <code>demo@riskops.local</code> / <code>RiskOpsDemo2026</code> (salt okunur)
</p>
```

GitHub deposunun **About** bölümündeki **Website** alanına da aynı
adresi yazın.

---

## 11. Termin bildirimi (isteğe bağlı)

Termini yaklaşan ve geçmiş aksiyonlar için her sabah sahiplerine tek
bir özet e-posta gönderir.

**Gereksinim:** sunucuda çalışan bir MTA. Yoksa `mail()` başarısız olur
ve betik hata ile çıkar — sessizce "gönderildi" demez.

```bash
sudo apt install -y postfix     # kurulumda "Internet Site" seçin
```

Ayarları Settings ekranından girin: **Termin Bildirimi** açık,
**Gönderen Adresi** dolu olmalı. Boşsa bildirim gönderilmez.

Göndermeden önce ne çıkacağını görün:

```bash
cd /var/www/riskops && sudo -u www-data php tools/notify_due_actions.php --dry-run
```

Sonra cron'a ekleyin:

```
0 8 * * * cd /var/www/riskops && sudo -u www-data /usr/bin/php tools/notify_due_actions.php >> /var/log/riskops-notify.log 2>&1
```

> Demo kurulumunda **açmayın**. Demo verisindeki e-posta adresleri
> gerçek değildir; gönderim denemeleri sunucunun posta itibarını
> zedeler.
