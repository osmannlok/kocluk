# Veritabanı Şeması

Veritabanı adı: `market_stok_satis`  
Karakter seti: `utf8mb4`  
Collation: `utf8mb4_unicode_ci`

## Genel Kurallar

- Para alanları: `DECIMAL(12,2)`
- Miktar alanları: `DECIMAL(12,3)`
- Yüzde/KDV alanları: `DECIMAL(5,2)`
- Tüm tablolarda `id`, `created_at`, `updated_at` bulunur.
- Silinebilir kayıtlarda `deleted_at` bulunur.
- Foreign key’ler `foreignId()->constrained()` ile tanımlanır.

## Tablolar

### users

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| name | VARCHAR(255) | - | Ad Soyad |
| email | VARCHAR(255) UNIQUE | - | E-posta |
| email_verified_at | TIMESTAMP NULL | NULL | E-posta doğrulama |
| password | VARCHAR(255) | - | Hash’lenmiş şifre |
| role | VARCHAR(30) | cashier | admin, cashier, warehouse, accountant |
| phone | VARCHAR(30) NULL | NULL | Telefon |
| is_active | TINYINT(1) | 1 | Aktif/pasif |
| remember_token | VARCHAR(100) NULL | NULL | Laravel token |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

### categories

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| name | VARCHAR(100) UNIQUE | - | Kategori adı |
| slug | VARCHAR(120) UNIQUE | - | URL dostu ad |
| description | TEXT NULL | NULL | Açıklama |
| is_active | TINYINT(1) | 1 | Aktif/pasif |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |
| deleted_at | TIMESTAMP NULL | NULL | Soft delete |

### suppliers

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| name | VARCHAR(150) | - | Tedarikçi adı |
| contact_name | VARCHAR(150) NULL | NULL | Yetkili kişi |
| phone | VARCHAR(30) NULL | NULL | Telefon |
| email | VARCHAR(255) NULL | NULL | E-posta |
| tax_number | VARCHAR(20) NULL | NULL | Vergi numarası |
| address | TEXT NULL | NULL | Adres |
| balance | DECIMAL(12,2) | 0.00 | Borç/alacak |
| is_active | TINYINT(1) | 1 | Aktif/pasif |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |
| deleted_at | TIMESTAMP NULL | NULL | Soft delete |

### products

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| category_id | BIGINT UNSIGNED NULL | NULL | categories.id |
| supplier_id | BIGINT UNSIGNED NULL | NULL | suppliers.id |
| name | VARCHAR(200) | - | Ürün adı |
| slug | VARCHAR(220) UNIQUE | - | URL dostu ad |
| sku | VARCHAR(50) NULL UNIQUE | NULL | Stok kodu |
| unit | VARCHAR(20) | adet | adet, kg, gram, litre, paket |
| purchase_price | DECIMAL(12,2) | 0.00 | Alış fiyatı |
| sale_price | DECIMAL(12,2) | 0.00 | Satış fiyatı |
| vat_rate | DECIMAL(5,2) | 0.00 | KDV oranı |
| stock | DECIMAL(12,3) | 0.000 | Stok |
| critical_stock | DECIMAL(12,3) | 0.000 | Kritik stok |
| is_weighed | TINYINT(1) | 0 | Tartılı ürün |
| is_active | TINYINT(1) | 1 | Aktif/pasif |
| image | VARCHAR(255) NULL | NULL | Görsel |
| description | TEXT NULL | NULL | Açıklama |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |
| deleted_at | TIMESTAMP NULL | NULL | Soft delete |

Index: `name`, `category_id,is_active`, `stock,critical_stock`

### product_barcodes

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| product_id | BIGINT UNSIGNED | - | products.id |
| barcode | VARCHAR(100) UNIQUE | - | Barkod |
| is_primary | TINYINT(1) | 0 | Ana barkod |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

### purchases

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| purchase_no | VARCHAR(30) UNIQUE | - | Alım fiş no |
| supplier_id | BIGINT UNSIGNED NULL | NULL | suppliers.id |
| user_id | BIGINT UNSIGNED NULL | NULL | users.id |
| purchase_date | DATE | - | Alım tarihi |
| total_amount | DECIMAL(12,2) | 0.00 | Toplam |
| paid_amount | DECIMAL(12,2) | 0.00 | Ödenen |
| remaining_amount | DECIMAL(12,2) | 0.00 | Kalan |
| status | VARCHAR(30) | completed | completed, pending, cancelled |
| note | TEXT NULL | NULL | Not |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |
| deleted_at | TIMESTAMP NULL | NULL | Soft delete |

### purchase_items

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| purchase_id | BIGINT UNSIGNED | - | purchases.id |
| product_id | BIGINT UNSIGNED | - | products.id |
| quantity | DECIMAL(12,3) | 0.000 | Miktar |
| unit_price | DECIMAL(12,2) | 0.00 | Birim fiyat |
| total_price | DECIMAL(12,2) | 0.00 | Toplam |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

### customers

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| name | VARCHAR(150) | - | Müşteri adı |
| phone | VARCHAR(30) NULL UNIQUE | NULL | Telefon |
| email | VARCHAR(255) NULL | NULL | E-posta |
| address | TEXT NULL | NULL | Adres |
| balance | DECIMAL(12,2) | 0.00 | Borç |
| is_active | TINYINT(1) | 1 | Aktif/pasif |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |
| deleted_at | TIMESTAMP NULL | NULL | Soft delete |

### sales

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| sale_no | VARCHAR(30) UNIQUE | - | Satış fiş no |
| user_id | BIGINT UNSIGNED NULL | NULL | Kasiyer |
| customer_id | BIGINT UNSIGNED NULL | NULL | Müşteri |
| sale_date | DATETIME | - | Satış tarihi |
| subtotal | DECIMAL(12,2) | 0.00 | Ara toplam |
| discount | DECIMAL(12,2) | 0.00 | İndirim |
| vat_total | DECIMAL(12,2) | 0.00 | KDV |
| total | DECIMAL(12,2) | 0.00 | Toplam |
| paid_total | DECIMAL(12,2) | 0.00 | Ödenen |
| change_amount | DECIMAL(12,2) | 0.00 | Para üstü |
| status | VARCHAR(30) | completed | completed, cancelled, returned |
| note | TEXT NULL | NULL | Not |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |
| deleted_at | TIMESTAMP NULL | NULL | Soft delete |

### sale_items

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| sale_id | BIGINT UNSIGNED | - | sales.id |
| product_id | BIGINT UNSIGNED | - | products.id |
| quantity | DECIMAL(12,3) | 0.000 | Miktar |
| unit_price | DECIMAL(12,2) | 0.00 | Satış fiyatı |
| purchase_price | DECIMAL(12,2) | 0.00 | Satış anındaki maliyet |
| discount | DECIMAL(12,2) | 0.00 | İndirim |
| vat_rate | DECIMAL(5,2) | 0.00 | KDV |
| total_price | DECIMAL(12,2) | 0.00 | Toplam |
| profit | DECIMAL(12,2) | 0.00 | Kâr |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

### payments

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| sale_id | BIGINT UNSIGNED NULL | NULL | sales.id |
| customer_id | BIGINT UNSIGNED NULL | NULL | customers.id |
| user_id | BIGINT UNSIGNED NULL | NULL | users.id |
| payment_type | VARCHAR(30) | - | cash, card, transfer, credit, refund |
| amount | DECIMAL(12,2) | 0.00 | Tutar |
| payment_date | DATETIME | - | Tarih |
| note | TEXT NULL | NULL | Not |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

### stock_movements

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| product_id | BIGINT UNSIGNED | - | products.id |
| user_id | BIGINT UNSIGNED NULL | NULL | users.id |
| purchase_id | BIGINT UNSIGNED NULL | NULL | purchases.id |
| sale_id | BIGINT UNSIGNED NULL | NULL | sales.id |
| movement_type | VARCHAR(30) | - | purchase, sale, return, damage, correction, initial |
| quantity | DECIMAL(12,3) | 0.000 | Miktar |
| stock_before | DECIMAL(12,3) | 0.000 | Önceki stok |
| stock_after | DECIMAL(12,3) | 0.000 | Sonraki stok |
| note | TEXT NULL | NULL | Not |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

### settings

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| key | VARCHAR(100) UNIQUE | - | Ayar anahtarı |
| value | TEXT NULL | NULL | Ayar değeri |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

### activity_logs

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| user_id | BIGINT UNSIGNED NULL | NULL | users.id |
| action | VARCHAR(100) | - | İşlem |
| model_type | VARCHAR(100) NULL | NULL | Model |
| model_id | BIGINT UNSIGNED NULL | NULL | Kayıt id |
| changes | JSON NULL | NULL | Değişiklik |
| ip_address | VARCHAR(45) NULL | NULL | IP |
| user_agent | TEXT NULL | NULL | Tarayıcı |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

## Migration Sırası

1. users  
2. categories  
3. suppliers  
4. products  
5. product_barcodes  
6. purchases  
7. purchase_items  
8. customers  
9. sales  
10. sale_items  
11. payments  
12. stock_movements  
13. settings  
14. activity_logs  

## Kâr Hesabı

```text
Satır Kârı = Miktar x (Satış Birim Fiyatı - Satır İndirimi - Alış Birim Fiyatı)
```
