-- =====================================================================
--  RiskOps - IT & Cyber Risk Management Platform
--  FILE   : /var/www/riskops/database/schema.sql
--  TARGET : MariaDB 10.6+ (Ubuntu Server 24.04 LTS)
--  CHARSET: utf8mb4 / utf8mb4_unicode_ci
--
--  NOTLAR:
--    * Bu dosya tekrar calistirilabilir (idempotent).
--    * Mevcut `users` tablosu SILINMEZ, yalnizca ALTER edilir.
--    * inherent_score / residual_score / score kolonlari GENERATED STORED'dir.
--      Uygulama kodunda bu kolonlara ASLA INSERT/UPDATE yapilmaz.
--    * severity kolonlari GENERATED DEGILDIR; esikler settings tablosundan
--      okunur ve PHP tarafinda hesaplanir.
-- =====================================================================

SET NAMES utf8mb4;
SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION';
SET FOREIGN_KEY_CHECKS = 1;


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


-- =====================================================================
--  SCHEMA SONU
-- =====================================================================
