-- =====================================================================
-- database/02_catalog_stock.sql
-- YÖRESEL MARKET: Katalog ve stok katmanı
-- Motor: InnoDB | utf8mb4 / utf8mb4_unicode_ci
-- Yeniden çalıştırılabilir: CREATE ... IF NOT EXISTS, CREATE OR REPLACE VIEW
-- Seed YOK (örnek veri 06_seed.sql içinde).
-- =====================================================================

USE yoresel_market;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 1) categories: üst/alt kategori (self FK).
-- parent_id ON DELETE SET NULL: üst kategori kalıcı silinirse alt kategori kök olur.
-- Silme uygulamada soft delete (deleted_at) ile yapılır.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id        INT UNSIGNED NULL,
    name             VARCHAR(120) NOT NULL,
    slug             VARCHAR(140) NOT NULL,
    description      TEXT         NULL,
    image_path       VARCHAR(255) NULL,
    sort_order       INT          NOT NULL DEFAULT 0,
    is_active        TINYINT(1)   NOT NULL DEFAULT 1,
    meta_title       VARCHAR(160) NULL,
    meta_description VARCHAR(255) NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_parent (parent_id, sort_order),
    KEY idx_categories_active (is_active),
    CONSTRAINT fk_categories_parent
        FOREIGN KEY (parent_id) REFERENCES categories (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) regions: yöre / menşei (Karadeniz, Ege vb.).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS regions (
    id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    slug       VARCHAR(120) NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_regions_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) products: ürün ana kaydı (gıdaya özel alanlar dahil).
-- Fiyat ve stok BURADA DEĞİL, product_variants tablosundadır.
-- category_id RESTRICT: ürünü olan kategori kalıcı silinemez.
-- tax_rate: KDV oranı muhasebeciyle netleştirilecek (varsayılan geçici).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id       INT UNSIGNED      NOT NULL,
    region_id         SMALLINT UNSIGNED NULL,
    name              VARCHAR(190) NOT NULL,
    slug              VARCHAR(220) NOT NULL,
    short_description VARCHAR(500) NULL,
    description       MEDIUMTEXT   NULL,
    origin_text       VARCHAR(190) NULL,
    producer_name     VARCHAR(190) NULL,
    ingredients       TEXT         NULL,
    allergen_info     TEXT         NULL,
    storage_conditions VARCHAR(255) NULL,
    shelf_life_info   VARCHAR(190) NULL,
    tax_rate          DECIMAL(5,2) NOT NULL DEFAULT 10.00, -- KDV oranı muhasebeciyle netleştirilecek
    is_active         TINYINT(1)   NOT NULL DEFAULT 1,
    is_featured       TINYINT(1)   NOT NULL DEFAULT 0,
    meta_title        VARCHAR(160) NULL,
    meta_description  VARCHAR(255) NULL,
    created_by        BIGINT UNSIGNED NULL,
    updated_by        BIGINT UNSIGNED NULL,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at        DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    KEY idx_products_category (category_id, is_active),
    KEY idx_products_region (region_id),
    KEY idx_products_featured (is_featured, is_active),
    FULLTEXT KEY idx_products_search (name, short_description),
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_products_region
        FOREIGN KEY (region_id) REFERENCES regions (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_products_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_products_updated_by
        FOREIGN KEY (updated_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) product_variants: gramaj/boyut bazlı SKU, fiyat ve stok.
-- Her ürünün en az bir varyantı olur. Fiyatlar KDV DAHİL, DECIMAL(12,2).
-- Güvenlik/bütünlük: CHECK kısıtları negatif stok ve geçersiz indirimli fiyatı
-- veritabanı seviyesinde engeller (uygulama hatası olsa bile).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_variants (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id         BIGINT UNSIGNED NOT NULL,
    sku                VARCHAR(64)  NOT NULL,
    barcode            VARCHAR(64)  NULL,
    name               VARCHAR(100) NOT NULL,
    weight_grams       INT UNSIGNED NOT NULL,
    price              DECIMAL(12,2) NOT NULL,
    sale_price         DECIMAL(12,2) NULL,
    sale_starts_at     DATETIME     NULL,
    sale_ends_at       DATETIME     NULL,
    stock_quantity     INT          NOT NULL DEFAULT 0,
    low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 5,
    track_stock        TINYINT(1)   NOT NULL DEFAULT 1,
    is_default         TINYINT(1)   NOT NULL DEFAULT 0,
    is_active          TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order         INT          NOT NULL DEFAULT 0,
    created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at         DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_variants_sku (sku),
    KEY idx_variants_product (product_id, is_active, sort_order),
    KEY idx_variants_stock (stock_quantity, low_stock_threshold),
    CONSTRAINT fk_variants_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_variants_stock CHECK (stock_quantity >= 0),
    CONSTRAINT chk_variants_price CHECK (price >= 0),
    CONSTRAINT chk_variants_sale CHECK (sale_price IS NULL OR (sale_price >= 0 AND sale_price < price))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5) product_images: çoklu ürün görseli (isteğe bağlı varyanta bağlı).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_images (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NULL,
    path       VARCHAR(255) NOT NULL,
    alt_text   VARCHAR(190) NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    is_primary TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_images_product (product_id, sort_order),
    KEY idx_images_variant (variant_id),
    CONSTRAINT fk_images_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_images_variant
        FOREIGN KEY (variant_id) REFERENCES product_variants (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6) stock_movements: stok hareket defteri (EKLEMELİ / append-only).
-- Güvenlik (iç tehdit): bu tabloya UPDATE/DELETE YAPILMAZ. Düzeltme gerekirse yeni
-- 'correction' satırı eklenir. Üretimde ym_app kullanıcısına yalnızca SELECT, INSERT
-- verilir (bkz. 05_security_logs.sql).
-- variant_id RESTRICT: hareketi olan varyant kalıcı silinemez (soft delete kullanılır).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_movements (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    variant_id      BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NULL,
    type            ENUM('initial','purchase','sale','cancel_return','manual_adjust','correction') NOT NULL,
    quantity_change INT          NOT NULL,
    quantity_after  INT          NOT NULL,
    reference_type  VARCHAR(50)  NULL,
    reference_id    BIGINT UNSIGNED NULL,
    note            VARCHAR(255) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sm_variant_time (variant_id, created_at),
    KEY idx_sm_reference (reference_type, reference_id),
    CONSTRAINT fk_sm_variant
        FOREIGN KEY (variant_id) REFERENCES product_variants (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_sm_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- VIEW v_low_stock: dashboard kritik stok uyarısı (stok artan sıralı).
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_low_stock AS
SELECT
    v.id                  AS variant_id,
    p.id                  AS product_id,
    p.name                AS product_name,
    v.name                AS variant_name,
    v.sku                 AS sku,
    v.stock_quantity      AS stock_quantity,
    v.low_stock_threshold AS low_stock_threshold
FROM product_variants v
JOIN products p ON p.id = v.product_id
WHERE v.is_active = 1
  AND v.deleted_at IS NULL
  AND p.deleted_at IS NULL
  AND v.track_stock = 1
  AND v.stock_quantity <= v.low_stock_threshold
ORDER BY v.stock_quantity ASC;

-- =====================================================================
-- TEST BLOĞU (yorum satırı; TEST komutuyla çalıştırılır)
-- =====================================================================
-- #TEST
-- INSERT INTO categories (name, slug) VALUES ('Test Kat', 't02-cat'); SET @c = LAST_INSERT_ID();
-- INSERT INTO regions (name, slug) VALUES ('Test Yöre', 't02-region'); SET @r = LAST_INSERT_ID();
-- INSERT INTO products (category_id, region_id, name, slug) VALUES (@c, @r, 'Test Ürün', 't02-prod'); SET @p = LAST_INSERT_ID();
-- INSERT INTO product_variants (product_id, sku, name, weight_grams, price, stock_quantity) VALUES (@p, 'T02-500', '500 g', 500, 100.00, 10);
-- UPDATE product_variants SET stock_quantity = -1 WHERE sku = 'T02-500'; -- BEKLENEN: CHECK hatası
-- INSERT INTO product_variants (product_id, sku, name, weight_grams, price, sale_price) VALUES (@p, 'T02-SALE', '1 kg', 1000, 100.00, 100.00); -- BEKLENEN: CHECK hatası
-- INSERT INTO product_variants (product_id, sku, name, weight_grams, price) VALUES (@p, 'T02-500', 'Kopya', 500, 50.00); -- BEKLENEN: UNIQUE hatası
-- UPDATE product_variants SET stock_quantity = 2 WHERE sku = 'T02-500';
-- SELECT * FROM v_low_stock WHERE sku = 'T02-500'; -- BEKLENEN: 1 satır
-- DELETE FROM product_variants WHERE sku LIKE 'T02-%';
-- DELETE FROM products WHERE slug = 't02-prod';
-- DELETE FROM regions WHERE slug = 't02-region';
-- DELETE FROM categories WHERE slug = 't02-cat';