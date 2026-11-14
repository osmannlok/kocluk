-- =====================================================================
-- database/03_cart_orders_payments.sql
-- YÖRESEL MARKET: Sepet, kupon, kargo tarifesi, sipariş, ödeme
-- Motor: InnoDB | utf8mb4 / utf8mb4_unicode_ci
-- Yeniden çalıştırılabilir: CREATE ... IF NOT EXISTS
-- NOT: Tüm fiyatlar KDV DAHİL, DECIMAL(12,2). Sunucu tarafında Money ile kuruş hesabı yapılır.
-- NOT: carts üzerindeki CHECK, user_id FK'si CASCADE olduğu için MariaDB'de çalışır;
--      MySQL 8'de 3823 hatası verir (ortam MariaDB).
-- TABLO SIRASI (FK bağımlılığı): carts, cart_items, coupons, shipping_rates, orders,
-- order_items, order_status_history, payments, payment_callbacks, shipments, coupon_usages
-- =====================================================================

USE yoresel_market;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 1) carts: kullanıcı veya misafir sepeti.
-- Güvenlik: guest_token düz saklanmaz; imzalı çerez değerinin SHA-256 hash'i tutulur.
-- user_id UNIQUE: kullanıcı başına tek sepet.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS carts (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NULL,
    guest_token CHAR(64)  NULL,
    expires_at  DATETIME  NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_carts_user (user_id),
    UNIQUE KEY uq_carts_guest (guest_token),
    KEY idx_carts_expires (expires_at),
    CONSTRAINT fk_carts_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_carts_owner CHECK (user_id IS NOT NULL OR guest_token IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) cart_items: sepet kalemleri.
-- FİYAT SAKLANMAZ: fiyat her seferinde sunucuda product_variants'tan hesaplanır
-- (istemciden gelen fiyat güvenilmez, fiyat manipülasyonunu önler).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cart_items (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cart_id    BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NOT NULL,
    quantity   SMALLINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_items_cart_variant (cart_id, variant_id),
    KEY idx_cart_items_variant (variant_id),
    CONSTRAINT fk_cart_items_cart
        FOREIGN KEY (cart_id) REFERENCES carts (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cart_items_variant
        FOREIGN KEY (variant_id) REFERENCES product_variants (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_cart_items_qty CHECK (quantity BETWEEN 1 AND 99)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) coupons: indirim kuponları (yüzde/sabit).
-- used_count sunucuda atomik artırılır; kullanıcı başı limit coupon_usages ile denetlenir.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupons (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code                VARCHAR(40)  NOT NULL,
    description         VARCHAR(255) NULL,
    discount_type       ENUM('percent','fixed') NOT NULL,
    discount_value      DECIMAL(12,2) NOT NULL,
    max_discount_amount DECIMAL(12,2) NULL,
    min_cart_total      DECIMAL(12,2) NOT NULL DEFAULT 0,
    usage_limit         INT UNSIGNED NULL,
    usage_per_user      INT UNSIGNED NULL,
    used_count          INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at           DATETIME     NULL,
    ends_at             DATETIME     NULL,
    is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    created_by          BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_coupons_code (code),
    KEY idx_coupons_active (is_active, starts_at, ends_at),
    CONSTRAINT fk_coupons_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_coupons_value CHECK (discount_value > 0),
    CONSTRAINT chk_coupons_percent CHECK (discount_type <> 'percent' OR discount_value <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) shipping_rates: ağırlık bazlı kargo tarifesi.
-- Ücretsiz kargo eşiği settings tablosundadır (shipping.free_threshold).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipping_rates (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    min_weight_grams INT UNSIGNED  NOT NULL,
    max_weight_grams INT UNSIGNED  NOT NULL,
    price            DECIMAL(12,2) NOT NULL,
    is_active        TINYINT(1)    NOT NULL DEFAULT 1,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_shipping_weight (is_active, min_weight_grams, max_weight_grams),
    CONSTRAINT chk_shipping_range CHECK (min_weight_grams <= max_weight_grams),
    CONSTRAINT chk_shipping_price CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5) orders: sipariş başlığı.
-- order_number: insan okunur (YM-2026-000123); ödeme eşleştirmesi için payments.merchant_oid kullanılır.
-- user_id RESTRICT: siparişi olan kullanıcı kalıcı silinemez (soft delete).
-- shipping_address / billing_address: sipariş anındaki JSON kopya (snapshot).
-- KVKK/mesafeli satış onay zamanı ve IP kaydı hukuki kanıttır.
-- ip_address / consent_ip: VARBINARY(16) (IPv4/IPv6).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number             VARCHAR(30)  NULL,
    user_id                  BIGINT UNSIGNED NOT NULL,
    status                   ENUM('pending_payment','paid','preparing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending_payment',
    payment_method           ENUM('credit_card','bank_transfer','cod') NOT NULL,
    payment_status           ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    currency                 CHAR(3)      NOT NULL DEFAULT 'TRY',
    subtotal                 DECIMAL(12,2) NOT NULL,
    discount_total           DECIMAL(12,2) NOT NULL DEFAULT 0,
    coupon_id                BIGINT UNSIGNED NULL,
    coupon_code              VARCHAR(40)  NULL,
    shipping_total           DECIMAL(12,2) NOT NULL DEFAULT 0,
    cod_fee                  DECIMAL(12,2) NOT NULL DEFAULT 0,
    grand_total              DECIMAL(12,2) NOT NULL,
    tax_total                DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_weight_grams       INT UNSIGNED NOT NULL DEFAULT 0,
    shipping_address         LONGTEXT     NOT NULL,
    billing_address          LONGTEXT     NOT NULL,
    customer_note            VARCHAR(500) NULL,
    kvkk_consent_at          DATETIME     NOT NULL,
    distance_sales_consent_at DATETIME    NOT NULL,
    consent_ip               VARBINARY(16) NOT NULL,
    consent_user_agent       VARCHAR(255) NULL,
    ip_address               VARBINARY(16) NULL,
    expires_at               DATETIME     NULL,
    paid_at                  DATETIME     NULL,
    shipped_at               DATETIME     NULL,
    delivered_at             DATETIME     NULL,
    cancelled_at             DATETIME     NULL,
    cancel_reason            VARCHAR(255) NULL,
    created_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_number (order_number),
    KEY idx_orders_user (user_id, created_at),
    KEY idx_orders_status (status, created_at),
    KEY idx_orders_payment (payment_method, payment_status),
    KEY idx_orders_expires (expires_at, status),
    KEY idx_orders_coupon (coupon_id),
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_orders_coupon
        FOREIGN KEY (coupon_id) REFERENCES coupons (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_orders_total CHECK (grand_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6) order_items: sipariş kalemleri (fiyat/ürün adı anlık görüntüsü).
-- Ürün sonradan değişse/silinse bile sipariş geçmişi bozulmaz.
-- variant_id RESTRICT: siparişte kullanılan varyant kalıcı silinemez.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id     BIGINT UNSIGNED NOT NULL,
    variant_id   BIGINT UNSIGNED NOT NULL,
    product_name VARCHAR(190)  NOT NULL,
    variant_name VARCHAR(100)  NOT NULL,
    sku          VARCHAR(64)   NOT NULL,
    list_price   DECIMAL(12,2) NOT NULL,
    unit_price   DECIMAL(12,2) NOT NULL,
    quantity     SMALLINT UNSIGNED NOT NULL,
    tax_rate     DECIMAL(5,2)  NOT NULL,
    line_total   DECIMAL(12,2) NOT NULL,
    weight_grams INT UNSIGNED  NOT NULL,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_variant (variant_id),
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_order_items_variant
        FOREIGN KEY (variant_id) REFERENCES product_variants (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7) order_status_history (EKLEMELİ): her durum değişikliği burada izlenir.
-- Güvenlik (iç tehdit): UPDATE/DELETE yapılmaz; üretimde ym_app yalnızca SELECT, INSERT yapabilir.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_status_history (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id    BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(30)  NULL,
    to_status   VARCHAR(30)  NOT NULL,
    changed_by  BIGINT UNSIGNED NULL,
    note        VARCHAR(255) NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_osh_order_time (order_id, created_at),
    CONSTRAINT fk_osh_order
        FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_osh_user
        FOREIGN KEY (changed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8) payments: ödeme kayıtları.
-- merchant_oid: yalnızca harf+rakam (PayTR eşleştirme anahtarı); UNIQUE sayesinde
-- aynı bildirim iki kez işlenemez (idempotentlik desteği).
-- KART VERİSİ ASLA SAKLANMAZ.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id            BIGINT UNSIGNED NOT NULL,
    provider            ENUM('paytr','bank_transfer','cod') NOT NULL,
    merchant_oid        VARCHAR(64)  NULL,
    amount              DECIMAL(12,2) NOT NULL,
    currency            CHAR(3)      NOT NULL DEFAULT 'TRY',
    status              ENUM('initiated','pending','success','failed','refunded') NOT NULL DEFAULT 'initiated',
    payment_type        VARCHAR(20)  NULL,
    installment_count   TINYINT UNSIGNED NULL,
    failed_reason_code  VARCHAR(20)  NULL,
    failed_reason_msg   VARCHAR(255) NULL,
    callback_received_at DATETIME    NULL,
    confirmed_by        BIGINT UNSIGNED NULL,
    confirmed_at        DATETIME     NULL,
    note                VARCHAR(255) NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_merchant_oid (merchant_oid),
    KEY idx_payments_order (order_id),
    KEY idx_payments_status (status),
    CONSTRAINT fk_payments_order
        FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_payments_confirmed_by
        FOREIGN KEY (confirmed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_payments_oid CHECK (merchant_oid IS NULL OR merchant_oid REGEXP '^[A-Za-z0-9]+$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9) payment_callbacks (EKLEMELİ): PayTR bildirimlerinin ham kaydı (adli inceleme).
-- payload KART VERİSİ İÇERMEZ. UPDATE/DELETE yapılmaz.
-- result: processed / duplicate / amount_mismatch / bad_hash
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payment_callbacks (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_oid VARCHAR(64)   NULL,
    payload      LONGTEXT      NOT NULL,
    hash_valid   TINYINT(1)    NOT NULL,
    ip_address   VARBINARY(16) NOT NULL,
    result       VARCHAR(50)   NULL,
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pc_merchant_oid (merchant_oid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10) shipments: manuel kargo bilgisi (firma + takip no).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipments (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        BIGINT UNSIGNED NOT NULL,
    carrier         VARCHAR(60)  NOT NULL,
    tracking_number VARCHAR(80)  NOT NULL,
    tracking_url    VARCHAR(255) NULL,
    shipped_at      DATETIME     NOT NULL,
    delivered_at    DATETIME     NULL,
    note            VARCHAR(255) NULL,
    created_by      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_shipments_order (order_id),
    CONSTRAINT fk_shipments_order
        FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_shipments_user
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11) coupon_usages: kupon kullanım kaydı.
-- order_id UNIQUE: bir siparişte tek kupon; kullanıcı başı limit bu tablodan sayılır.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupon_usages (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_id       BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    order_id        BIGINT UNSIGNED NOT NULL,
    discount_amount DECIMAL(12,2) NOT NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cu_order (order_id),
    KEY idx_cu_coupon_user (coupon_id, user_id),
    CONSTRAINT fk_cu_coupon
        FOREIGN KEY (coupon_id) REFERENCES coupons (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cu_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cu_order
        FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TEST BLOĞU (yorum satırı; TEST komutuyla çalıştırılır)
-- =====================================================================
-- #TEST
-- INSERT INTO categories (name, slug) VALUES ('T', 't03-cat'); SET @c = LAST_INSERT_ID();
-- INSERT INTO products (category_id, name, slug) VALUES (@c, 'T', 't03-prod'); SET @p = LAST_INSERT_ID();
-- INSERT INTO product_variants (product_id, sku, name, weight_grams, price, stock_quantity) VALUES (@p, 'T03-1', '500 g', 500, 100.00, 10); SET @v = LAST_INSERT_ID();
-- INSERT INTO users (role_id, first_name, last_name, email, password_hash) SELECT id, 'T', 'T', 't03@example.invalid', 'x' FROM roles WHERE name = 'customer'; SET @u = LAST_INSERT_ID();
-- INSERT INTO orders (order_number, user_id, payment_method, subtotal, grand_total, tax_total, total_weight_grams, shipping_address, billing_address, kvkk_consent_at, distance_sales_consent_at, consent_ip) VALUES ('T03-1', @u, 'credit_card', 200.00, 200.00, 18.18, 1000, '{}', '{}', NOW(), NOW(), UNHEX('7F000001')); SET @o = LAST_INSERT_ID();
-- INSERT INTO order_items (order_id, variant_id, product_name, variant_name, sku, list_price, unit_price, quantity, tax_rate, line_total, weight_grams) VALUES (@o, @v, 'T', '500 g', 'T03-1', 100.00, 100.00, 1, 10.00, 100.00, 500), (@o, @v, 'T', '500 g', 'T03-1', 100.00, 100.00, 1, 10.00, 100.00, 500);
-- INSERT INTO payments (order_id, provider, merchant_oid, amount) VALUES (@o, 'paytr', 'T03OID1', 200.00);
-- INSERT INTO payments (order_id, provider, merchant_oid, amount) VALUES (@o, 'paytr', 'T03OID1', 200.00); -- BEKLENEN: UNIQUE hatası
-- INSERT INTO carts (user_id, expires_at) VALUES (@u, NOW() + INTERVAL 1 DAY); SET @cart = LAST_INSERT_ID();
-- INSERT INTO cart_items (cart_id, variant_id, quantity) VALUES (@cart, @v, 0); -- BEKLENEN: CHECK hatası
-- INSERT INTO coupons (code, discount_type, discount_value) VALUES ('T03CPN', 'percent', 150); -- BEKLENEN: CHECK hatası
-- SELECT o.order_number, COUNT(i.id) AS kalem, p.merchant_oid FROM orders o JOIN order_items i ON i.order_id = o.id JOIN payments p ON p.order_id = o.id WHERE o.id = @o GROUP BY o.id, p.id;
-- DELETE FROM payments WHERE order_id = @o;
-- DELETE FROM orders WHERE id = @o;
-- DELETE FROM users WHERE id = @u;
-- DELETE FROM product_variants WHERE id = @v;
-- DELETE FROM products WHERE id = @p;
-- DELETE FROM categories WHERE id = @c;