-- =====================================================================
-- database/01_auth_rbac.sql
-- YÖRESEL MARKET: Kimlik doğrulama ve yetkilendirme (RBAC) katmanı
-- Motor: InnoDB | Karakter seti: utf8mb4 / utf8mb4_unicode_ci
-- Yeniden çalıştırılabilir: CREATE ... IF NOT EXISTS, INSERT IGNORE
-- =====================================================================

CREATE DATABASE IF NOT EXISTS yoresel_market
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE yoresel_market;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) roles: sistem rolleri (admin, manager, customer).
-- Güvenlik: kodda rol adı sabitlenmez, yetki kontrolü permission koduyla yapılır.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(50)  NOT NULL,
    label       VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) permissions: tekil yetki kodları (örn. product.create).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS permissions (
    id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code       VARCHAR(100) NOT NULL,
    label      VARCHAR(150) NOT NULL,
    module     VARCHAR(50)  NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_code (code),
    KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) role_permissions: rol-yetki eşleşmesi.
-- ON DELETE CASCADE: rol veya yetki silinirse eşleşme satırı da gider
-- (eşleşme tek başına anlamsız bir kayıttır).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id       TINYINT UNSIGNED  NOT NULL,
    permission_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_rp_permission (permission_id),
    CONSTRAINT fk_rp_role
        FOREIGN KEY (role_id) REFERENCES roles (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rp_permission
        FOREIGN KEY (permission_id) REFERENCES permissions (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) users: müşteri, yönetici ve admin hesapları.
-- Güvenlik:
--  - password_hash: yalnızca Argon2id hash'i (düz parola ASLA saklanmaz).
--  - role_id ON DELETE RESTRICT: kullanıcısı olan rol silinemez.
--  - failed_login_count / locked_until: brute force kilitleme.
--  - deleted_at: soft delete (sipariş ve denetim kayıtları kullanıcıya bağlı kalır).
--    Not: soft delete edilen kullanıcının e-postası UNIQUE kaldığı için yeniden kullanılamaz.
--  - kvkk_consent_at: açık rıza zamanı (KVKK kanıtı).
--  - last_login_ip: VARBINARY(16), IPv4 ve IPv6 için (INET6_ATON / uygulama dönüşümü).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                 BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    role_id            TINYINT UNSIGNED NOT NULL,
    first_name         VARCHAR(80)  NOT NULL,
    last_name          VARCHAR(80)  NOT NULL,
    email              VARCHAR(190) NOT NULL,
    phone              VARCHAR(20)  NULL,
    password_hash      VARCHAR(255) NOT NULL,
    email_verified_at  DATETIME     NULL,
    status             ENUM('active','suspended','pending') NOT NULL DEFAULT 'pending',
    kvkk_consent_at    DATETIME     NULL,
    marketing_consent  TINYINT(1)   NOT NULL DEFAULT 0,
    last_login_at      DATETIME     NULL,
    last_login_ip      VARBINARY(16) NULL,
    failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until       DATETIME     NULL,
    created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at         DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id),
    KEY idx_users_status (status),
    KEY idx_users_deleted (deleted_at),
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5) addresses: teslimat ve fatura adresleri.
-- Güvenlik/KVKK: tc_identity_no HASSAS kişisel veridir; yalnızca bireysel fatura için
-- ve opsiyonel. Loglara yazılmaz, admin ekranlarında maskelenir.
-- ON DELETE CASCADE: kullanıcı kalıcı silinirse adresleri de gider. Siparişler adresin
-- JSON kopyasını (snapshot) tuttuğu için sipariş geçmişi etkilenmez.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS addresses (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       BIGINT UNSIGNED NOT NULL,
    title         VARCHAR(60)  NOT NULL,
    full_name     VARCHAR(160) NOT NULL,
    phone         VARCHAR(20)  NOT NULL,
    city          VARCHAR(80)  NOT NULL,
    district      VARCHAR(80)  NOT NULL,
    neighborhood  VARCHAR(120) NULL,
    address_line  VARCHAR(500) NOT NULL,
    postal_code   VARCHAR(10)  NULL,
    is_default    TINYINT(1)   NOT NULL DEFAULT 0,
    invoice_type  ENUM('individual','corporate') NOT NULL DEFAULT 'individual',
    tc_identity_no VARCHAR(11) NULL,
    company_name  VARCHAR(190) NULL,
    tax_office    VARCHAR(120) NULL,
    tax_number    VARCHAR(20)  NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_addresses_user (user_id, deleted_at),
    CONSTRAINT fk_addresses_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6) login_attempts: giriş denemeleri (brute force tespiti).
-- FK YOK: olmayan e-postayla yapılan denemeler de kaydedilmeli.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email        VARCHAR(190)  NOT NULL,
    ip_address   VARBINARY(16) NOT NULL,
    user_agent   VARCHAR(255)  NULL,
    success      TINYINT(1)    NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_la_email_time (email, attempted_at),
    KEY idx_la_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7) password_resets: parola sıfırlama token'ları.
-- Güvenlik: düz token ASLA saklanmaz, yalnızca SHA-256 hash'i (token_hash).
-- Veritabanı sızsa bile token'lar kullanılamaz. Tek kullanımlık (used_at), süreli.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64)  NOT NULL,
    expires_at DATETIME  NOT NULL,
    used_at    DATETIME  NULL,
    ip_address VARBINARY(16) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pr_token_hash (token_hash),
    KEY idx_pr_user (user_id),
    KEY idx_pr_expires (expires_at),
    CONSTRAINT fk_pr_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8) email_verifications: e-posta doğrulama token'ları (password_resets ile aynı mantık).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_verifications (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64)  NOT NULL,
    expires_at DATETIME  NOT NULL,
    used_at    DATETIME  NULL,
    ip_address VARBINARY(16) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ev_token_hash (token_hash),
    KEY idx_ev_user (user_id),
    KEY idx_ev_expires (expires_at),
    CONSTRAINT fk_ev_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED
-- =====================================================================

INSERT IGNORE INTO roles (name, label, description) VALUES
    ('admin',    'Yönetici (Admin)',  'Tüm siteyi yönetir'),
    ('manager',  'Yönetici',          'Sipariş takibi, ürün ekleme ve güncelleme'),
    ('customer', 'Müşteri',           'Alışveriş yapan üye');

INSERT IGNORE INTO permissions (code, label, module) VALUES
    ('dashboard.view',      'Dashboard görüntüle',        'dashboard'),
    ('product.view',        'Ürünleri görüntüle',         'product'),
    ('product.create',      'Ürün ekle',                  'product'),
    ('product.update',      'Ürün güncelle',              'product'),
    ('product.delete',      'Ürün sil',                   'product'),
    ('category.manage',     'Kategori ve yöre yönet',     'catalog'),
    ('stock.manage',        'Stok yönet',                 'stock'),
    ('order.view',          'Siparişleri görüntüle',      'order'),
    ('order.update_status', 'Sipariş durumu güncelle',    'order'),
    ('order.cancel',        'Sipariş iptal et',           'order'),
    ('order.refund',        'Sipariş iade et',            'order'),
    ('payment.confirm',     'Ödeme onayla (havale/kapıda)','payment'),
    ('shipment.manage',     'Kargo bilgisi yönet',        'shipment'),
    ('customer.view',       'Müşterileri görüntüle',      'customer'),
    ('coupon.manage',       'Kupon yönet',                'marketing'),
    ('banner.manage',       'Banner ve duyuru yönet',     'marketing'),
    ('menu.manage',         'Menü yönet',                 'content'),
    ('page.manage',         'Sayfa yönet',                'content'),
    ('setting.manage',      'Site ayarlarını yönet',      'system'),
    ('user.manage',         'Kullanıcı yönet',            'system'),
    ('role.manage',         'Rol ve yetki yönet',         'system'),
    ('report.view',         'Raporları görüntüle',        'report'),
    ('audit.view',          'Denetim kayıtlarını görüntüle','system');

-- admin: TÜM yetkiler
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.name = 'admin';

-- manager: yalnızca operasyonel yetkiler.
-- VERİLMEYENLER: product.delete, order.cancel, order.refund, payment.confirm,
-- coupon/banner/menu/page/setting/user/role/audit (iç tehdit riskini azaltır).
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
    'dashboard.view',
    'product.view', 'product.create', 'product.update',
    'category.manage', 'stock.manage',
    'order.view', 'order.update_status',
    'shipment.manage',
    'customer.view', 'report.view'
)
WHERE r.name = 'manager';

-- customer: hiçbir yönetim yetkisi yok (bilerek boş).

-- İlk admin kullanıcısı BURADA OLUŞTURULMAZ: Argon2id hash'i uygulama tarafında
-- üretilir. Oturum 5'te yazılacak scripts/create_admin.php ile eklenecek.