<p align="center">
  <img src="assets/img/logo-wordmark@2x.png" alt="RiskOps" width="280">
</p>

<p align="center">
  <strong>BT ve Siber Güvenlik Risk Yönetimi Platformu</strong><br>
  Kurumsal risk envanteri, 5×5 değerlendirme, aksiyon takibi ve yönetim raporlaması
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white" alt="PHP 8.3">
  <img src="https://img.shields.io/badge/MariaDB-10.11-003545?logo=mariadb&logoColor=white" alt="MariaDB 10.11">
  <img src="https://img.shields.io/badge/framework-yok-lightgrey" alt="Framework yok">
  <img src="https://img.shields.io/badge/CDN-yok-lightgrey" alt="CDN yok">
  <img src="https://img.shields.io/badge/lisans-MIT-green" alt="MIT">
</p>

---

## RiskOps nedir?

RiskOps, bir kurumun **BT ve siber güvenlik risklerini** kayıt altına almak,
olasılık × etki üzerinden puanlamak, azaltıcı aksiyonları takip etmek ve
yönetime sunulabilir belgeler üretmek için yazılmış bir web uygulamasıdır.

Risk yaşam döngüsünün tamamını kapsar:

```
Risk kaydı  →  5×5 değerlendirme  →  Seviye ataması  →  Aksiyon planı
     ↑                                                        ↓
     └────────  Yeniden değerlendirme  ←──────  Aksiyon tamamlanır
```

## RiskOps ne DEĞİLDİR

Bu ayrım projenin en önemli tasarım kararıdır, bu yüzden başta duruyor:

| Değildir | Neden önemli |
|---|---|
| Helpdesk / ticket sistemi | Burada "kapatılacak talep" yok; **süregiden bir risk durumu** var |
| ITSM aracı | Varlık envanteri, değişiklik yönetimi, SLA takibi kapsam dışı |
| Zafiyet tarayıcı | Tarama yapmaz; tarama **sonuçlarının yönetildiği** yerdir |
| SIEM | Log toplamaz, korelasyon kurmaz |

Bir risk çözülmez, **seviyesi düşürülür**. Veri modeli bu farkın üzerine kurulu:
bir riskin birden fazla `risk_assessment` kaydı vardır ve geçmiş değerlendirmeler
silinmez — böylece "bu risk 6 ayda nereden nereye geldi" sorusu cevaplanabilir.

---

## Ekran görüntüleri

| Panel | Risk Kaydı |
|---|---|
| ![Dashboard](docs/screenshots/dashboard.png) | ![Risk detayı](docs/screenshots/risk-detail.png) |

| 5×5 Risk Matrisi | Yönetici Özeti (yazdırma) |
|---|---|
| ![Matris](docs/screenshots/matrix.png) | ![Rapor](docs/screenshots/executive-summary.png) |

---

## Öne çıkan özellikler

**Risk yönetimi**
- Otomatik risk kodu (`RSK-2026-0001`) — yarış koşuluna dayanıklı sıra üreteci
- 5×5 olasılık/etki matrisi, sunucu tarafında üretilir (JavaScript kapalıyken de çalışır)
- Skor `GENERATED ALWAYS AS (likelihood * impact) STORED` — veritabanı seviyesinde
- Seviye eşikleri **ayarlardan** yönetilir; eşik değişince tüm kayıtlar yeniden etiketlenir
- 10 filtre + 12 sıralanabilir kolon, FULLTEXT arama
- Yumuşak silme (soft delete) — kayıt kaybolmaz

**Aksiyon takibi**
- Riske bağlı azaltıcı aksiyonlar, sorumlu ve termin tarihi
- Geciken aksiyonlar panelde ve raporda ayrıca işaretlenir

**Raporlama**
- 6 hazır rapor + tek sayfalık **Yönetici Risk Özeti**
- Antetli, gizlilik ibareli, imza bloklu **yazdırma çıktısı** (A4, `@page`)
- Çift formatlı CSV: `excel` (Türkçe Excel'de çift tıkla açılır) ve `raw` (RFC 4180)

**Yönetim**
- 4 rol × yetki matrisi (`admin` / `manager` / `analyst` / `viewer`)
- Departman ve risk kategorisi yönetimi
- Değiştirilemez (append-only) denetim kaydı

---

## Güvenlik yaklaşımı

Projeyi yazarken baştan koyduğum kurallar — hiçbirinden taviz verilmedi:

> Asla: düz metin parola · SQL string birleştirme · kaçışsız çıktı ·
> yalnızca arayüzde rol kontrolü · CSRF'siz POST · GET ile silme

Uygulanan önlemler:

| Konu | Uygulama |
|---|---|
| Parola | `password_hash()` / `password_verify()`, giriş anında `password_needs_rehash` kontrolü |
| SQL enjeksiyonu | Tüm sorgular PDO prepared statement; `ATTR_EMULATE_PREPARES = false` |
| XSS | Çıktıların tamamı `e()` (htmlspecialchars) üzerinden |
| CSRF | Oturum token'ı + `hash_equals()`, her POST'ta zorunlu |
| Oturum sabitleme | Giriş ve parola değişiminde `session_regenerate_id(true)` |
| Yetkilendirme | `require_can()` / `require_role()` — **her sayfanın başında, sunucuda** |
| Kaba kuvvet | `login_attempts` tablosu, e-posta ve IP için ayrı sayaçlar |
| Kullanıcı sayımı | Sabit maliyetli sahte hash ile zamanlama farkı eşitlenir |
| Oturum geçersizleştirme | `auth_revalidate()` her istekte rol/durum/parola damgasını doğrular |
| Sıralama enjeksiyonu | `ORDER BY` yalnızca beyaz listeden |
| `LIKE` kaçışı | `addcslashes($q, '\\%_')` |
| CSV formül enjeksiyonu | `= + - @` ile başlayan hücreler tek tırnakla metne zorlanır |
| Açık yönlendirme | Giriş sonrası `redirect` hedefi katı regex'ten geçer |
| Dizin koruması | `.htaccess` **ve** VirtualHost — `AllowOverride` kapatılsa da geçerli |
| Başlıklar | CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy |

**Bilinen sınır:** CSP'de hâlâ `'unsafe-inline'` var — birkaç sayfa satır içi
`<script>` kullanıyor. Bunlar harici dosyaya taşındığında kaldırılacak.
Gerekçesi `deploy/riskops.conf` içinde yorum olarak yazılıdır.

---

## Mimari

Framework yok. Her şey açıkça görünür olsun diye bilinçli bir tercih.

```
istek
  │
  └─ <sayfa>.php
       └─ includes/bootstrap.php        ← tek giriş noktası
            ├─ config/        ortam ayarları, hata yöneticileri
            ├─ db.php         lazy PDO singleton — db()
            ├─ settings.php   veritabanından ayarlar
            ├─ csrf.php       token üretimi/doğrulaması
            ├─ auth.php       rol matrisi, auth_revalidate()
            ├─ audit.php      denetim kaydı
            ├─ risk.php       skor, seviye, kod üreteci
            └─ ui.php         bileşen yardımcıları
       └─ iş mantığı + sorgular
       └─ layout/header.php → içerik → layout/footer.php
```

**Neden framework yok?** Bu proje aynı zamanda bir öğrenme aracı.
Composer paketlerinin arkasına saklanmak yerine oturum güvenliğini,
CSRF'yi, yetki matrisini ve sorgu katmanını elle yazmak; her kararın
*neden* öyle olduğunu görünür kılıyor. Kod, kararların gerekçesiyle
birlikte yorumlanmış durumda.

### Veri modeli

10 tablo:

`users` · `departments` · `risk_categories` · `risks` · `risk_assessments`
· `risk_actions` · `settings` · `audit_logs` · `login_attempts` · `risk_sequences`

Dikkate değer birkaç karar:

- **`risks.inherent_score`** `GENERATED ALWAYS AS (likelihood * impact) STORED` —
  uygulama kodu bu değeri hiç hesaplamaz, tutarsızlık imkânsız.
- **Seviye (severity) bilinçli olarak generated DEĞİL** — eşikler ayarlardan
  değişebildiği için üretilmiş kolon yanlış olurdu. `risk_recalculate_severities()`
  eşik değişiminde tüm kayıtları yeniden etiketler.
- **`risk_sequences`** yarış koşulunu tek sorguyla çözer:
  `INSERT ... ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)`
- **Yabancı anahtarlar** amaca göre ayrışır: `RESTRICT` (kullanımdaki departman
  silinemez), `CASCADE` (risk silinince değerlendirmeleri de gider),
  `SET NULL` (kullanıcı silinince kayıt sahipsiz kalır ama kaybolmaz).

Tam şema: [`database/schema.sql`](database/schema.sql)

### Ön yüz

- Bootstrap 5.3.3 + Bootstrap Icons + Chart.js 4.4.1 — **tamamı kendi sunucusunda**
- Inter değişken font, `latin` + `latin-ext` (Türkçe karakterler için)
- **Hiçbir CDN yok.** Kapalı ağda çalışan kurumlarda bu bir zorunluluktur;
  ayrıca üçüncü taraf bir sunucunun kullanıcıları izlemesini engeller.
- Renk paleti `validate_palette.js` ile renk körlüğü açısından doğrulandı;
  durum renkleri her zaman ikon + etiketle birlikte gelir, renk tek başına anlam taşımaz

---

## Kurulum

Ubuntu Server 24.04 · PHP 8.3 · MariaDB 10.11 · Apache 2.4

```bash
# 1. Kodu yerleştirin
sudo git clone https://github.com/<kullanici>/riskops.git /var/www/riskops
sudo chown -R www-data:www-data /var/www/riskops

# 2. Veritabanını oluşturun
sudo mysql -e "CREATE DATABASE riskops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'riskops_user'@'localhost' IDENTIFIED BY 'GUCLU_BIR_PAROLA';"
sudo mysql -e "GRANT SELECT,INSERT,UPDATE,DELETE ON riskops.* TO 'riskops_user'@'localhost';"

# 3. Şemayı ve başlangıç verisini yükleyin
sudo mysql riskops < /var/www/riskops/database/schema.sql
sudo mysql riskops < /var/www/riskops/database/seed.sql

# 4. Yapılandırma
sudo cp /var/www/riskops/config/database.example.php /var/www/riskops/config/database.php
sudo nano /var/www/riskops/config/database.php     # 'pass' değerini girin

# 5. Apache
sudo cp /var/www/riskops/deploy/riskops.conf /etc/apache2/sites-available/
sudo a2enmod headers rewrite expires
sudo a2ensite riskops
sudo apache2ctl configtest && sudo systemctl reload apache2
```

**İlk giriş:** `admin@riskops.local` / `Admin123456` —
uygulama ilk girişte parola değiştirmeye zorlar.

### İsteğe bağlı: demo verisi

Nasıl göründüğünü hızlıca görmek için 18 gerçekçi risk, 22 aksiyon ve
3 demo kullanıcı yükler:

```bash
sudo -u www-data php /var/www/riskops/tools/seed_demo.php
```

> `tools/seed_demo.php` bir `--purge` anahtarı da kabul eder ve bu anahtar
> **tüm risk verisini siler**. Üretim sunucusunda çalıştırmayın; canlıya
> alırken `tools/` dizinini tamamen kaldırın.

### Canlıya alma kontrolü

```bash
sudo -u www-data php /var/www/riskops/tools/go_live_check.php
```

Veri değiştirmeyen bir ön kontroldür: dizin izinleri, güvenlik başlıkları,
ortam değişkeni, varsayılan parola, saat dilimi hizası gibi 31 maddeyi
denetler ve eksikleri listeler.

---

## Proje büyüklüğü

| | |
|---|---|
| PHP dosyası | 83 |
| PHP satırı | ~11.960 |
| CSS satırı | ~1.510 (`app.css`) + yazdırma stili |
| Veritabanı tablosu | 10 |
| Duman testi | 59 doğrulama |
| Harici PHP bağımlılığı | **0** |

```bash
php tools/smoke_test.php        # 59/59
find . -name "*.php" -not -path "./assets/*" -exec php -l {} \;
```

---

## Yol haritası

- [ ] CSP'den `'unsafe-inline'` kaldırma (satır içi script'leri taşı)
- [ ] Risk kayıtlarına yorum ve dosya eki
- [ ] Termin yaklaşan aksiyonlar için e-posta bildirimi
- [ ] Toplu işlemler (çoklu risk atama / kapatma)
- [ ] Kullanıcı profil sayfası
- [ ] Silinen riski geri alma ekranı

---

## Lisans

MIT — bkz. [LICENSE](LICENSE).

---

<details>
<summary><strong>In English</strong></summary>

**RiskOps** is an IT & cyber security risk management platform: risk register,
5×5 likelihood/impact assessment, mitigation action tracking, and printable
executive reporting.

It is deliberately **not** a helpdesk, ITSM tool, vulnerability scanner, or SIEM.
A risk isn't "closed" like a ticket — it's reassessed, and its level goes down.
The data model reflects that: every risk owns a history of assessments that is
never deleted.

Built with PHP 8.3, MariaDB 10.11 and Apache — **no framework, no Composer
dependencies, no CDN** (all assets self-hosted, which matters for air-gapped
corporate networks). Security was a first-class constraint rather than a
later pass: prepared statements with emulation disabled, CSRF on every POST,
server-side authorisation on every page, per-request session revalidation,
rate limiting with timing-equalised login, and CSV formula-injection escaping.

The UI and all documentation are in Turkish.

</details>
