-- =====================================================================
--  RiskOps - IT & Cyber Risk Management Platform
--  FILE   : /var/www/riskops/database/schema.sql
--  TARGET : MariaDB 10.6+ (Ubuntu Server 24.04 LTS)
--  CHARSET: utf8mb4 / utf8mb4_unicode_ci
--
--  NOTLAR:
--    * Bu dosya tekrar calistirilabilir (idempotent).
--    * users tablosu 0. bolumde temel kolonlarla yaratilir, 10. bolumde
--      departments bagimli kolonlarla genisletilir (dongusel FK).
--      Mevcut kurulumlarda users SILINMEZ; CREATE IF NOT EXISTS no-op olur.
--    * inherent_score / residual_score / score kolonlari GENERATED STORED'dir.
--      Uygulama kodunda bu kolonlara ASLA INSERT/UPDATE yapilmaz.
--    * severity kolonlari GENERATED DEGILDIR; esikler settings tablosundan
--      okunur ve PHP tarafinda hesaplanir.
-- =====================================================================

SET NAMES utf8mb4;
SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION';
SET FOREIGN_KEY_CHECKS = 1;


-- ---------------------------------------------------------------------
-- 0. users  -> TEMEL TABLO
--
--    DONGUSEL BAGIMLILIK
--    -------------------
--    users.department_id  ->  departments.id
--    departments.manager_id  ->  users.id
--
--    Iki tablo birbirine FK veriyor; hangisi once yaratilirsa yaratilsin
--    digerinin FK'si olmayan tabloya isaret eder ve MariaDB ERROR 1005
--    (errno 150) dondurur. Cozum uc adimlidir:
--
--      0. users  -> departments FK'si OLMADAN yaratilir   (bu bolum)
--      1. departments -> manager_id FK'si ile yaratilir    (users artik var)
--     10. ALTER users -> department_id + FK eklenir        (departments artik var)
--
--    Bu yuzden asagidaki tabloda department_id, title, phone,
--    must_change_password, password_changed_at ve created_by KOLONLARI
--    YOKTUR - hepsini 10. bolum ekler. Kolonlari buraya tasimayin;
--    department_id'nin FK'si departments olmadan kurulamaz.
--
--    IF NOT EXISTS: mevcut kurulumlarda bu ifade no-op'tur, tablo
--    oldugu gibi kalir. Dosyanin idempotentligi korunur.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password      VARCHAR(255) NOT NULL,
    -- En az yetki ilkesi: rol acikca verilmediyse kullanici yalnizca okur.
    role          ENUM('admin','manager','analyst','viewer')
                  NOT NULL DEFAULT 'viewer',
    status        TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at DATETIME     NULL DEFAULT NULL,
    created_at    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 1. departments
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS departments (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    code        VARCHAR(20)  NOT NULL,
    description VARCHAR(255) NULL DEFAULT NULL,
    manager_id  INT UNSIGNED NULL DEFAULT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_departments_name (name),
    UNIQUE KEY uq_departments_code (code),
    KEY idx_departments_active (is_active),
    KEY idx_departments_manager (manager_id),
    CONSTRAINT fk_departments_manager
        FOREIGN KEY (manager_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. risk_categories
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS risk_categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    code        VARCHAR(20)  NOT NULL,
    description VARCHAR(255) NULL DEFAULT NULL,
    color       CHAR(7)      NOT NULL DEFAULT '#64748b',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_risk_categories_name (name),
    UNIQUE KEY uq_risk_categories_code (code),
    KEY idx_risk_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. risk_sequences  (risk_code icin race-condition guvenli sayac)
--    Kullanim (PHP):
--      INSERT INTO risk_sequences (seq_year, last_number)
--      VALUES (:y, LAST_INSERT_ID(1))
--      ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1);
--      $n = (int)$pdo->lastInsertId();
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS risk_sequences (
    seq_year    SMALLINT UNSIGNED NOT NULL,
    last_number INT UNSIGNED      NOT NULL DEFAULT 0,
    updated_at  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (seq_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 4. settings  (key/value + tip bilgisi)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key   VARCHAR(100) NOT NULL,
    setting_value TEXT         NULL DEFAULT NULL,
    setting_type  ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
    setting_group VARCHAR(50)  NOT NULL DEFAULT 'general',
    label         VARCHAR(150) NOT NULL,
    description   VARCHAR(255) NULL DEFAULT NULL,
    is_editable   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order    SMALLINT     NOT NULL DEFAULT 0,
    updated_by    INT UNSIGNED NULL DEFAULT NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (setting_key),
    KEY idx_settings_group (setting_group),
    KEY idx_settings_updated_by (updated_by),
    CONSTRAINT fk_settings_updated_by
        FOREIGN KEY (updated_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 5. risks  (ANA TABLO)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS risks (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    risk_code           VARCHAR(20)  NOT NULL,
    title               VARCHAR(200) NOT NULL,
    description         TEXT         NULL DEFAULT NULL,

    category_id         INT UNSIGNED NOT NULL,
    department_id       INT UNSIGNED NOT NULL,
    asset_name          VARCHAR(200) NULL DEFAULT NULL,
    threat              TEXT         NULL DEFAULT NULL,
    vulnerability       TEXT         NULL DEFAULT NULL,
    owner_id            INT UNSIGNED NOT NULL,

    -- Inherent (ham) risk
    likelihood          TINYINT UNSIGNED NOT NULL,
    impact              TINYINT UNSIGNED NOT NULL,
    inherent_score      TINYINT UNSIGNED GENERATED ALWAYS AS (likelihood * impact) STORED,
    inherent_severity   ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Low',

    -- Treatment
    treatment_strategy  ENUM('Avoid','Mitigate','Transfer','Accept') NULL DEFAULT NULL,

    -- Residual (kalan) risk
    residual_likelihood TINYINT UNSIGNED NULL DEFAULT NULL,
    residual_impact     TINYINT UNSIGNED NULL DEFAULT NULL,
    residual_score      TINYINT UNSIGNED GENERATED ALWAYS AS (residual_likelihood * residual_impact) STORED,
    residual_severity   ENUM('Low','Medium','High','Critical') NULL DEFAULT NULL,

    status              ENUM('Open','Under Review','In Progress','Mitigated','Accepted','Transferred','Closed')
                        NOT NULL DEFAULT 'Open',
    target_date         DATE         NULL DEFAULT NULL,

    created_by          INT UNSIGNED NOT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at           DATETIME     NULL DEFAULT NULL,
    deleted_at          DATETIME     NULL DEFAULT NULL,
    deleted_by          INT UNSIGNED NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_risks_code (risk_code),

    KEY idx_risks_status            (status),
    KEY idx_risks_inherent_severity (inherent_severity),
    KEY idx_risks_residual_severity (residual_severity),
    KEY idx_risks_owner             (owner_id),
    KEY idx_risks_category          (category_id),
    KEY idx_risks_department        (department_id),
    KEY idx_risks_target_date       (target_date),
    KEY idx_risks_deleted           (deleted_at),
    KEY idx_risks_created_by        (created_by),
    KEY idx_risks_deleted_by        (deleted_by),
    KEY idx_risks_status_target     (status, target_date),
    KEY idx_risks_created_at        (created_at),
    KEY idx_risks_live_severity     (deleted_at, inherent_severity),

    FULLTEXT KEY ft_risks_search (title, description, asset_name),

    CONSTRAINT chk_risks_likelihood
        CHECK (likelihood BETWEEN 1 AND 5),
    CONSTRAINT chk_risks_impact
        CHECK (impact BETWEEN 1 AND 5),
    CONSTRAINT chk_risks_residual_likelihood
        CHECK (residual_likelihood IS NULL OR residual_likelihood BETWEEN 1 AND 5),
    CONSTRAINT chk_risks_residual_impact
        CHECK (residual_impact IS NULL OR residual_impact BETWEEN 1 AND 5),

    CONSTRAINT fk_risks_category
        FOREIGN KEY (category_id) REFERENCES risk_categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_risks_department
        FOREIGN KEY (department_id) REFERENCES departments (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_risks_owner
        FOREIGN KEY (owner_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_risks_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_risks_deleted_by
        FOREIGN KEY (deleted_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 6. risk_assessments  (append-only degerlendirme gecmisi)
--    Bu tablo ASLA UPDATE edilmez. Sadece INSERT.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS risk_assessments (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    risk_id         INT UNSIGNED NOT NULL,
    assessment_type ENUM('initial','review','residual') NOT NULL DEFAULT 'review',
    likelihood      TINYINT UNSIGNED NOT NULL,
    impact          TINYINT UNSIGNED NOT NULL,
    score           TINYINT UNSIGNED GENERATED ALWAYS AS (likelihood * impact) STORED,
    severity        ENUM('Low','Medium','High','Critical') NOT NULL,
    notes           TEXT         NULL DEFAULT NULL,
    assessed_by     INT UNSIGNED NOT NULL,
    assessed_at     DATETIME     NOT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ra_risk_date (risk_id, assessed_at),
    KEY idx_ra_type      (assessment_type),
    KEY idx_ra_assessor  (assessed_by),
    KEY idx_ra_severity  (severity),
    CONSTRAINT chk_ra_likelihood CHECK (likelihood BETWEEN 1 AND 5),
    CONSTRAINT chk_ra_impact     CHECK (impact BETWEEN 1 AND 5),
    CONSTRAINT fk_ra_risk
        FOREIGN KEY (risk_id) REFERENCES risks (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ra_assessed_by
        FOREIGN KEY (assessed_by) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 7. risk_actions  (Mitigation / Action Plans)
--    NOT: "overdue" bir kolon DEGILDIR, sorguda hesaplanir:
--      status IN ('Open','In Progress')
--      AND due_date IS NOT NULL AND due_date < CURDATE()
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS risk_actions (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    risk_id      INT UNSIGNED NOT NULL,
    title        VARCHAR(200) NOT NULL,
    description  TEXT         NULL DEFAULT NULL,
    owner_id     INT UNSIGNED NOT NULL,
    priority     ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
    status       ENUM('Open','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Open',
    due_date     DATE         NULL DEFAULT NULL,
    completed_at DATETIME     NULL DEFAULT NULL,
    created_by   INT UNSIGNED NOT NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_actions_risk       (risk_id),
    KEY idx_actions_owner      (owner_id),
    KEY idx_actions_status     (status),
    KEY idx_actions_due        (due_date),
    KEY idx_actions_priority   (priority),
    KEY idx_actions_created_by (created_by),
    KEY idx_actions_status_due (status, due_date),
    CONSTRAINT fk_actions_risk
        FOREIGN KEY (risk_id) REFERENCES risks (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_actions_owner
        FOREIGN KEY (owner_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_actions_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 8. audit_logs
--    user_id ON DELETE SET NULL -> kullanici silinse bile log kaybolmaz.
--    user_name_snapshot -> "kim yapti" bilgisi kalicidir.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id            INT UNSIGNED NULL DEFAULT NULL,
    user_name_snapshot VARCHAR(100) NULL DEFAULT NULL,
    action             VARCHAR(60)  NOT NULL,
    entity_type        VARCHAR(50)  NULL DEFAULT NULL,
    entity_id          INT UNSIGNED NULL DEFAULT NULL,
    old_values         LONGTEXT     NULL DEFAULT NULL,
    new_values         LONGTEXT     NULL DEFAULT NULL,
    ip_address         VARCHAR(45)  NULL DEFAULT NULL,
    user_agent         VARCHAR(255) NULL DEFAULT NULL,
    created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_entity    (entity_type, entity_id),
    KEY idx_audit_user_date (user_id, created_at),
    KEY idx_audit_action    (action),
    KEY idx_audit_created   (created_at),
    CONSTRAINT chk_audit_old_json CHECK (old_values IS NULL OR json_valid(old_values)),
    CONSTRAINT chk_audit_new_json CHECK (new_values IS NULL OR json_valid(new_values)),
    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 9. login_attempts  (brute force / rate limiting)
--    Tablo simdi olusturuluyor, mantik STEP 1.7'de devreye alinacak.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email        VARCHAR(150) NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    success      TINYINT(1)   NOT NULL DEFAULT 0,
    user_agent   VARCHAR(255) NULL DEFAULT NULL,
    attempted_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_la_email_time (email, attempted_at),
    KEY idx_la_ip_time    (ip_address, attempted_at),
    KEY idx_la_time       (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 10. users  -> MEVCUT TABLO ALTER EDILIYOR (SILINMIYOR)
--     departments tablosu olustuktan SONRA calismali.
-- ---------------------------------------------------------------------
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS department_id         INT UNSIGNED NULL DEFAULT NULL AFTER role,
    ADD COLUMN IF NOT EXISTS title                 VARCHAR(100) NULL DEFAULT NULL AFTER department_id,
    ADD COLUMN IF NOT EXISTS phone                 VARCHAR(30)  NULL DEFAULT NULL AFTER title,
    ADD COLUMN IF NOT EXISTS must_change_password  TINYINT(1)   NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN IF NOT EXISTS password_changed_at   DATETIME     NULL DEFAULT NULL AFTER must_change_password,
    ADD COLUMN IF NOT EXISTS created_by            INT UNSIGNED NULL DEFAULT NULL AFTER last_login_at;

-- Arayuz dili tercihi. NULL ise sistem varsayilani kullanilir
-- (settings.default_locale). Bkz. includes/i18n.php
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS locale CHAR(2) NULL DEFAULT NULL AFTER phone;

ALTER TABLE users
    ADD INDEX IF NOT EXISTS idx_users_role       (role),
    ADD INDEX IF NOT EXISTS idx_users_status     (status),
    ADD INDEX IF NOT EXISTS idx_users_department (department_id),
    ADD INDEX IF NOT EXISTS idx_users_created_by (created_by);

ALTER TABLE users
    ADD CONSTRAINT fk_users_department
        FOREIGN KEY IF NOT EXISTS (department_id) REFERENCES departments (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE users
    ADD CONSTRAINT fk_users_created_by
        FOREIGN KEY IF NOT EXISTS (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE;


-- ---------------------------------------------------------------------
-- 11. risk_comments  (risk kayitlarina yorum)
--
--     ON DELETE CASCADE: risk kalici olarak silinirse yorumlari da
--     gider - sahipsiz yorum satiri anlamsizdir. (Uygulamadaki normal
--     silme soft delete'tir, bu yol yalnizca elle temizlikte isler.)
--
--     user_id SET NULL: kullanici silinse bile yorum KALIR. Yorum
--     kurumsal kayittir; yazani bilinmiyor olabilir ama icerigi
--     kaybolmamalidir.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS risk_comments (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    risk_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NULL DEFAULT NULL,
    body       TEXT         NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rc_risk (risk_id, created_at),
    KEY idx_rc_user (user_id),
    CONSTRAINT fk_rc_risk FOREIGN KEY (risk_id) REFERENCES risks (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rc_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 12. risk_attachments  (risk kayitlarina dosya eki)
--
--     DISKTEKI AD ILE GORUNEN AD AYRIDIR:
--       original_name -> kullaniciya gosterilen, indirirken verilen ad
--       stored_name   -> diskteki ad; uygulama uretir, rastgeledir
--
--     Istemciden gelen dosya adi ASLA diske yazilmaz. Aksi halde
--     "../../config/database.php" veya "shell.php" gibi adlarla
--     dizin disina yazma ve kod calistirma denenebilir.
--
--     stored_name UNIQUE: ayni ad iki kayda baglanamaz, boylece bir
--     kaydin silinmesi digerinin dosyasini goturmez.
--     KISIT ADLARI "att" onekli: InnoDB'de FK adlari TUM VERITABANINDA
--     benzersiz olmali ve "ra" oneki risk_assessments tarafindan
--     kullaniliyor (fk_ra_risk, chk_ra_impact...). Cakisma ERROR 1005
--     errno 121 verir.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS risk_attachments (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    risk_id       INT UNSIGNED NOT NULL,
    uploaded_by   INT UNSIGNED NULL DEFAULT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name   VARCHAR(120) NOT NULL,
    mime_type     VARCHAR(120) NOT NULL,
    size_bytes    INT UNSIGNED NOT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_att_stored (stored_name),
    KEY idx_att_risk (risk_id, created_at),
    KEY idx_att_user (uploaded_by),
    CONSTRAINT fk_att_risk FOREIGN KEY (risk_id) REFERENCES risks (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_att_user FOREIGN KEY (uploaded_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_att_size CHECK (size_bytes > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 13. action_comments  (aksiyonlara yorum)
--
--     risk_comments ile ayni yapida ama AYRI TABLO.
--
--     Neden tek bir polimorfik tablo (entity_type + entity_id) degil:
--     polimorfik anahtara YABANCI ANAHTAR verilemez. Silinen bir
--     aksiyonun yorumlari sahipsiz kalir ve veritabani bunu engelleyemez.
--     Iki tablo biraz tekrar demek, ama butunlugu veritabani garanti
--     ediyor - uygulama koduna birakilmiyor.
--
--     Kisit onekleri "acmt": mevcut adlarla cakismamali (InnoDB'de FK
--     adlari tum veritabaninda benzersizdir).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS action_comments (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NULL DEFAULT NULL,
    body       TEXT         NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_acmt_action (action_id, created_at),
    KEY idx_acmt_user (user_id),
    CONSTRAINT fk_acmt_action FOREIGN KEY (action_id) REFERENCES risk_actions (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_acmt_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 14. action_attachments  (aksiyonlara dosya eki)
--     risk_attachments ile ayni kurallar; bkz. includes/attachments.php
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS action_attachments (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_id     INT UNSIGNED NOT NULL,
    uploaded_by   INT UNSIGNED NULL DEFAULT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name   VARCHAR(120) NOT NULL,
    mime_type     VARCHAR(120) NOT NULL,
    size_bytes    INT UNSIGNED NOT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_aatt_stored (stored_name),
    KEY idx_aatt_action (action_id, created_at),
    KEY idx_aatt_user (uploaded_by),
    CONSTRAINT fk_aatt_action FOREIGN KEY (action_id) REFERENCES risk_actions (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_aatt_user FOREIGN KEY (uploaded_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_aatt_size CHECK (size_bytes > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
--  SCHEMA SONU
-- =====================================================================
