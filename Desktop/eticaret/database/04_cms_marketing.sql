-- =====================================================================
-- database/04_cms_marketing.sql
-- YÖRESEL MARKET: İçerik (CMS) ve pazarlama katmanı
-- Motor: InnoDB | utf8mb4 / utf8mb4_unicode_ci
-- Yeniden çalıştırılabilir: CREATE ... IF NOT EXISTS
-- =====================================================================

USE yoresel_market;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 1) banners: yönetimli, sıralı, zamanlanabilir; masaüstü/mobil ayrı görsel.
-- Güvenlik: görsel yolları yükleme servisinden gelir (rastgele ad); link_url uygulamada
-- yalnızca aynı site yolu veya https olarak doğrulanır (açık yönlendirme önlemi).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS banners (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title         VARCHAR(150) NOT NULL,
    subtitle      VARCHAR(255) NULL,
    button_text   VARCHAR(60)  NULL,
    link_url      VARCHAR(255) NULL,
    image_desktop VARCHAR(255) NOT NULL,
    image_mobile  VARCHAR(255) NULL,
    alt_text      VARCHAR(190) NULL,
    position      ENUM('home_hero','home_secondary','category_top') NOT NULL DEFAULT 'home_hero',
    sort_order    INT          NOT NULL DEFAULT 0,
    starts_at     DATETIME     NULL,
    ends_at       DATETIME     NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_by    BIGINT UNSIGNED NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_banners_position (position, is_active, sort_order),
    CONSTRAINT fk_banners_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) menus: menü konumları (header, footer_1, footer_2, mobile). Konum başına tek menü.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS menus (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name      VARCHAR(80) NOT NULL,
    location  ENUM('header','footer_1','footer_2','mobile') NOT NULL,
    is_active TINYINT(1)  NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_menus_location (location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) menu_items: çok seviyeli menü (self FK), mega menü ve rozet desteği.
-- ON DELETE CASCADE: üst öğe silinirse alt öğeler de gider.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS menu_items (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    menu_id      INT UNSIGNED    NOT NULL,
    parent_id    BIGINT UNSIGNED NULL,
    label        VARCHAR(100) NOT NULL,
    link_type    ENUM('url','category','page','region','products') NOT NULL DEFAULT 'url',
    link_value   VARCHAR(255) NULL,
    icon         VARCHAR(60)  NULL,
    badge_text   VARCHAR(20)  NULL,
    is_mega      TINYINT(1)   NOT NULL DEFAULT 0,
    open_new_tab TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order   INT          NOT NULL DEFAULT 0,
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_menu_items_tree (menu_id, parent_id, sort_order),
    CONSTRAINT fk_menu_items_menu
        FOREIGN KEY (menu_id) REFERENCES menus (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_menu_items_parent
        FOREIGN KEY (parent_id) REFERENCES menu_items (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) pages: CMS sayfaları (yasal metinler dahil).
-- Güvenlik (XSS): content yalnızca HTMLPurifier'dan geçmiş HTML içerir; kaydetmeden önce
-- temizlenir. system_key: kod tarafından bilinen sistem sayfası (silinmesi engellenir).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pages (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title            VARCHAR(190) NOT NULL,
    slug             VARCHAR(160) NOT NULL,
    system_key       VARCHAR(50)  NULL,
    content          MEDIUMTEXT   NULL,
    is_active        TINYINT(1)   NOT NULL DEFAULT 1,
    show_in_footer   TINYINT(1)   NOT NULL DEFAULT 0,
    meta_title       VARCHAR(160) NULL,
    meta_description VARCHAR(255) NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pages_slug (slug),
    UNIQUE KEY uq_pages_system_key (system_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5) settings: anahtar-değer site ayarları.
-- Güvenlik: is_public=1 olanlar vitrine açılabilir; gizli anahtarlar (PayTR vb.) buraya
-- YAZILMAZ, yalnızca .env'de tutulur. Değişikliği yapan updated_by'a işlenir.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    key_name   VARCHAR(100) NOT NULL,
    value      TEXT         NULL,
    value_type ENUM('string','int','decimal','bool','json') NOT NULL DEFAULT 'string',
    group_name VARCHAR(50)  NOT NULL,
    label      VARCHAR(150) NOT NULL,
    is_public  TINYINT(1)   NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (key_name),
    KEY idx_settings_group (group_name),
    CONSTRAINT fk_settings_updated_by
        FOREIGN KEY (updated_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6) announcements: duyuru şeridi (ör. ücretsiz kargo eşiği).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS announcements (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message    VARCHAR(255) NOT NULL,
    link_url   VARCHAR(255) NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    starts_at  DATETIME     NULL,
    ends_at    DATETIME     NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_announcements_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7) contact_messages: iletişim formu mesajları (hız sınırı uygulamada uygulanır).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(120) NOT NULL,
    email      VARCHAR(190) NOT NULL,
    phone      VARCHAR(20)  NULL,
    subject    VARCHAR(190) NOT NULL,
    message    TEXT         NOT NULL,
    ip_address VARBINARY(16) NOT NULL,
    status     ENUM('new','read','replied') NOT NULL DEFAULT 'new',
    handled_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cm_status (status, created_at),
    CONSTRAINT fk_cm_handled_by
        FOREIGN KEY (handled_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TEST BLOĞU (yorum satırı; TEST komutuyla çalıştırılır)
-- =====================================================================
-- #TEST
-- INSERT INTO banners (title, image_desktop) VALUES ('T04 Banner', '/assets/img/t04.jpg');
-- INSERT INTO menus (name, location) VALUES ('T04 Menü', 'mobile'); SET @m = LAST_INSERT_ID();
-- INSERT INTO menu_items (menu_id, parent_id, label, sort_order) VALUES (@m, NULL, 'Üst', 1); SET @i = LAST_INSERT_ID();
-- INSERT INTO menu_items (menu_id, parent_id, label, sort_order) VALUES (@m, @i, 'Alt 2', 2), (@m, @i, 'Alt 1', 1);
-- SELECT id, parent_id, label, sort_order FROM menu_items WHERE menu_id = @m ORDER BY parent_id, sort_order; -- BEKLENEN: Üst, Alt 1, Alt 2
-- INSERT INTO menus (name, location) VALUES ('Kopya', 'mobile'); -- BEKLENEN: UNIQUE hatası
-- DELETE FROM banners WHERE title = 'T04 Banner';
-- DELETE FROM menus WHERE id = @m;