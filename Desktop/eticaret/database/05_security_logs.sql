-- =====================================================================
-- database/05_security_logs.sql
-- YÖRESEL MARKET: Denetim, güvenlik olayları, hız sınırı, oturumlar ve DB kullanıcısı
-- Motor: InnoDB | utf8mb4 / utf8mb4_unicode_ci
-- Yeniden çalıştırılabilir: CREATE ... IF NOT EXISTS
-- ÖN KOŞUL: 01, 02, 03, 04 dosyaları çalıştırılmış olmalı (GRANT satırları tabloları ister).
-- =====================================================================

USE yoresel_market;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 1) audit_logs (EKLEMELİ): yönetici işlemlerinin denetim izi (kim, ne, eski/yeni, IP).
-- Güvenlik (iç tehdit): UPDATE/DELETE YAPILMAZ; üretimde ym_app yalnızca SELECT, INSERT
-- yapabilir. Böylece ele geçirilmiş bir yönetici hesabı bile izlerini silemez.
-- old_values/new_values: JSON; parola, token, kart ve TC kimlik no İÇERMEZ (maskelenir).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NULL,
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(60)  NULL,
    entity_id   BIGINT UNSIGNED NULL,
    old_values  LONGTEXT     NULL,
    new_values  LONGTEXT     NULL,
    ip_address  VARBINARY(16) NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_user_time (user_id, created_at),
    KEY idx_audit_action_time (action, created_at),
    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) security_events (EKLEMELİ): şüpheli olaylar (CSRF hatası, kilitlenme, PayTR hash hatası vb.).
-- UPDATE/DELETE YAPILMAZ.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS security_events (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type       VARCHAR(60)  NOT NULL,
    severity   ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    user_id    BIGINT UNSIGNED NULL,
    ip_address VARBINARY(16) NOT NULL,
    detail     LONGTEXT     NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_se_type_time (type, created_at),
    KEY idx_se_severity_time (severity, created_at),
    CONSTRAINT fk_se_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) rate_limits: hız sınırı sayaçları. Anahtar SHA-256 hash'lenir (IP/e-posta düz saklanmaz).
-- Süresi dolanlar cron ile temizlenir (expires_at indeksli).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limits (
    key_hash     CHAR(64) NOT NULL,
    hits         INT UNSIGNED NOT NULL DEFAULT 0,
    window_start INT UNSIGNED NOT NULL,
    expires_at   INT UNSIGNED NOT NULL,
    PRIMARY KEY (key_hash),
    KEY idx_rl_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) sessions: veritabanı tabanlı oturum deposu (DbSessionHandler).
-- ip_address/user_agent oturum çalma tespiti ve yönetici "oturumları sonlandır" için tutulur.
-- user_id SET NULL: kullanıcı silinirse oturum anonimleşir (zaten soft delete kullanılır).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    id            VARCHAR(128) NOT NULL,
    user_id       BIGINT UNSIGNED NULL,
    payload       MEDIUMBLOB   NOT NULL,
    ip_address    VARBINARY(16) NULL,
    user_agent    VARCHAR(255) NULL,
    last_activity INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sessions_activity (last_activity),
    KEY idx_sessions_user (user_id),
    CONSTRAINT fk_sessions_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DB KULLANICISI (ayrı blok) — ÜRETİMDE çalıştır, parolayı değiştir.
-- Geliştirmede ym_dev kullanılır (bu blok yorum satırıdır, otomatik çalışmaz).
--
-- Güvenlik (iç tehdit / SQL injection hasar sınırı): uygulama kullanıcısı DROP/ALTER/CREATE/
-- GRANT yapamaz; eklemeli (append-only) tablolara yalnızca SELECT, INSERT verilir.
-- MySQL'de db.* seviyesinde verilen yetki tablo bazında geri alınamaz; bu yüzden
-- genel tablolar tek tek listelenir.
-- =====================================================================
-- #DBUSER
-- CREATE USER IF NOT EXISTS 'ym_app'@'localhost' IDENTIFIED BY 'DEGISTIR_BU_PAROLAYI';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.roles TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.permissions TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.role_permissions TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.users TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.addresses TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.login_attempts TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.password_resets TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.email_verifications TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.categories TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.regions TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.products TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.product_variants TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.product_images TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.carts TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.cart_items TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.coupons TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.shipping_rates TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.orders TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.order_items TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.payments TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.shipments TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.coupon_usages TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.banners TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.menus TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.menu_items TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.pages TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.settings TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.announcements TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.contact_messages TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.rate_limits TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON yoresel_market.sessions TO 'ym_app'@'localhost';
-- GRANT SELECT ON yoresel_market.v_low_stock TO 'ym_app'@'localhost';
-- -- EKLEMELİ tablolar: YALNIZCA SELECT, INSERT (UPDATE/DELETE bilerek verilmez)
-- GRANT SELECT, INSERT ON yoresel_market.audit_logs TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT ON yoresel_market.security_events TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT ON yoresel_market.payment_callbacks TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT ON yoresel_market.stock_movements TO 'ym_app'@'localhost';
-- GRANT SELECT, INSERT ON yoresel_market.order_status_history TO 'ym_app'@'localhost';
-- FLUSH PRIVILEGES;
-- #DBUSER-BITIS