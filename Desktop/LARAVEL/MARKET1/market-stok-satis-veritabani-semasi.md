# Market Stok/Satış Sistemi — Tam Veritabanı Şeması

Bu şema, Laravel + MySQL tabanlı market stok/satış sistemi için hazırlanmıştır. Claude Code’a proje geliştirmeye başlamadan önce bağlam olarak verilecektir.

## Genel Kurallar

- Veritabanı adı: `market_stok_satis`
- Karakter seti: `utf8mb4`
- Collation: `utf8mb4_unicode_ci`
- Tüm tablolarda `id`, `created_at`, `updated_at` bulunur.
- Silinebilir kayıtlarda `deleted_at` bulunur.
- Para alanları: `DECIMAL(12,2)`
- Miktar alanları: `DECIMAL(12,3)`
- Yüzde/KDV alanları: `DECIMAL(5,2)`
- Foreign key’ler Laravel migration’larında `foreignId()->constrained()` ile tanımlanır.

---

## 1. users

Kullanıcılar, yöneticiler, kasiyerler, depocular ve muhasebe personeli.

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| name | VARCHAR(255) | - | Ad Soyad |
| email | VARCHAR(255) UNIQUE | - | E-posta |
| email_verified_at | TIMESTAMP NULL | NULL | E-posta doğrulama tarihi |
| password | VARCHAR(255) | - | Hash’lenmiş şifre |
| role | VARCHAR(30) | cashier | admin, cashier, warehouse, accountant |
| phone | VARCHAR(30) NULL | NULL | Telefon |
| is_active | TINYINT(1) | 1 | Aktif/pasif |
| remember_token | VARCHAR(100) NULL | NULL | Laravel remember token |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

---

## 2. categories

Ürün kategorileri.

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

---

## 3. suppliers

Tedarikçiler.

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| name | VARCHAR(150) | - | Tedarikçi adı |
| contact_name | VARCHAR(150) NULL | NULL | Yetkili kişi |
| phone | VARCHAR(30) NULL | NULL | Telefon |
| email | VARCHAR(255) NULL | NULL | E-posta |
| tax_number | VARCHAR(20) NULL | NULL | Vergi numarası |
| address | TEXT NULL | NULL | Adres |
| balance | DECIMAL(12,2) | 0.00 | Borç/alacak bakiyesi |
| is_active | TINYINT(1) | 1 | Aktif/pasif |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |
| deleted_at | TIMESTAMP NULL | NULL | Soft delete |

---

## 4. products

Ürünler.

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
| stock | DECIMAL(12,3) | 0.000 | Mevcut stok |
| critical_stock | DECIMAL(12,3) | 0.000 | Kritik stok |
| is_weighed | TINYINT(1) | 0 | Tartılı ürün mü? |
| is_active | TINYINT(1) | 1 | Aktif/pasif |
| image | VARCHAR(255) NULL | NULL | Ürün görseli |
| description | TEXT NULL | NULL | Ürün açıklaması |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |
| deleted_at | TIMESTAMP NULL | NULL | Soft delete |

Index’ler:

- `name`
- `category_id, is_active`
- `stock, critical_stock`

---

## 5. product_barcodes

Bir ürüne bağlı barkodlar.

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| product_id | BIGINT UNSIGNED | - | products.id |
| barcode | VARCHAR(100) UNIQUE | - | Barkod numarası |
| is_primary | TINYINT(1) | 0 | Ana barkod mu? |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

Index’ler:

- `product_id`
- `barcode`

---

## 6. purchases

Tedarikçiden yapılan alım faturaları.

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| purchase_no | VARCHAR(30) UNIQUE | - | Alım fiş numarası |
| supplier_id | BIGINT UNSIGNED NULL | NULL | suppliers.id |
| user_id | BIGINT UNSIGNED NULL | NULL | users.id |
| purchase_date | DATE | - | Alım tarihi |
| total_amount | DECIMAL(12,2) | 0.00 | Toplam tutar |
| paid_amount | DECIMAL(12,2) | 0.00 | Ödenen tutar |
| remaining_amount | DECIMAL(12,2) | 0.00 | Kalan borç |
| status | VARCHAR(30) | completed | completed, pending, cancelled |
| note | TEXT NULL | NULL | Not |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |
| deleted_at | TIMESTAMP NULL | NULL | Soft delete |

Index’ler:

- `purchase_date`
- `supplier_id, purchase_date`

---

## 7. purchase_items

Alım faturası satırları.

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| purchase_id | BIGINT UNSIGNED | - | purchases.id |
| product_id | BIGINT UNSIGNED | - | products.id |
| quantity | DECIMAL(12,3) | 0.000 | Alınan miktar |
| unit_price | DECIMAL(12,2) | 0.00 | Birim alış fiyatı |
| total_price | DECIMAL(12,2) | 0.00 | Satır toplamı |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

Index’ler:

- `purchase_id`
- `product_id`

---

## 8. customers

Müşteriler ve veresiye takibi.

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| name | VARCHAR(150) | - | Müşteri adı |
| phone | VARCHAR(30) NULL UNIQUE | NULL | Telefon |
| email | VARCHAR(255) NULL | NULL | E-posta |
| address | TEXT NULL | NULL | Adres |
| balance | DECIMAL(12,2) | 0.00 | Borç bakiyesi |
| is_active | TINYINT(1) | 1 | Aktif/pasif |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |
| deleted_at | TIMESTAMP NULL | NULL | Soft delete |

---

## 9. sales

Satış fişleri.

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| sale_no | VARCHAR(30) UNIQUE | - | Satış fiş numarası |
| user_id | BIGINT UNSIGNED NULL | NULL | Satışı yapan kullanıcı |
| customer_id | BIGINT UNSIGNED NULL | NULL | customers.id |
| sale_date | DATETIME | - | Satış tarihi |
| subtotal | DECIMAL(12,2) | 0.00 | Ara toplam |
| discount | DECIMAL(12,2) | 0.00 | Genel indirim |
| vat_total | DECIMAL(12,2) | 0.00 | Toplam KDV |
| total | DECIMAL(12,2) | 0.00 | Genel toplam |
| paid_total | DECIMAL(12,2) | 0.00 | Ödenen tutar |
| change_amount | DECIMAL(12,2) | 0.00 | Para üstü |
| status | VARCHAR(30) | completed | completed, cancelled, returned |
| note | TEXT NULL | NULL | Not |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |
| deleted_at | TIMESTAMP NULL | NULL | Soft delete |

Index’ler:

- `sale_date`
- `user_id, sale_date`
- `customer_id, sale_date`

---

## 10. sale_items

Satış satırları.

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| sale_id | BIGINT UNSIGNED | - | sales.id |
| product_id | BIGINT UNSIGNED | - | products.id |
| quantity | DECIMAL(12,3) | 0.000 | Satılan miktar |
| unit_price | DECIMAL(12,2) | 0.00 | Satış birim fiyatı |
| purchase_price | DECIMAL(12,2) | 0.00 | Satış anındaki alış fiyatı |
| discount | DECIMAL(12,2) | 0.00 | Satır indirimi |
| vat_rate | DECIMAL(5,2) | 0.00 | KDV oranı |
| total_price | DECIMAL(12,2) | 0.00 | Satır toplamı |
| profit | DECIMAL(12,2) | 0.00 | Satır kârı |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

Index’ler:

- `sale_id`
- `product_id`

---

## 11. payments

Satış ve müşteri ödemeleri.

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| sale_id | BIGINT UNSIGNED NULL | NULL | sales.id |
| customer_id | BIGINT UNSIGNED NULL | NULL | customers.id |
| user_id | BIGINT UNSIGNED NULL | NULL | Ödemeyi alan kullanıcı |
| payment_type | VARCHAR(30) | - | cash, card, transfer, credit, refund |
| amount | DECIMAL(12,2) | 0.00 | Ödeme tutarı |
| payment_date | DATETIME | - | Ödeme tarihi |
| note | TEXT NULL | NULL | Not |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

Index’ler:

- `sale_id`
- `customer_id, payment_date`
- `payment_type, payment_date`

---

## 12. stock_movements

Tüm stok giriş, çıkış, iade, fire ve sayım hareketleri.

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| product_id | BIGINT UNSIGNED | - | products.id |
| user_id | BIGINT UNSIGNED NULL | NULL | İşlemi yapan kullanıcı |
| purchase_id | BIGINT UNSIGNED NULL | NULL | purchases.id |
| sale_id | BIGINT UNSIGNED NULL | NULL | sales.id |
| movement_type | VARCHAR(30) | - | purchase, sale, return, damage, correction, initial |
| quantity | DECIMAL(12,3) | 0.000 | Değişim miktarı |
| stock_before | DECIMAL(12,3) | 0.000 | İşlem öncesi stok |
| stock_after | DECIMAL(12,3) | 0.000 | İşlem sonrası stok |
| note | TEXT NULL | NULL | Not |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

Index’ler:

- `product_id, created_at`
- `movement_type, created_at`

---

## 13. settings

Market ve sistem ayarları.

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| key | VARCHAR(100) UNIQUE | - | Ayar anahtarı |
| value | TEXT NULL | NULL | Ayar değeri |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

Örnek ayarlar:

- `market_name`
- `market_phone`
- `market_address`
- `currency`
- `receipt_header`
- `default_vat_rate`
- `low_stock_alert`

---

## 14. activity_logs

Kullanıcı işlem kayıtları.

| Sütun | Tip | Varsayılan | Açıklama |
|---|---|---:|---|
| id | BIGINT UNSIGNED AI PK | - | Birincil anahtar |
| user_id | BIGINT UNSIGNED NULL | NULL | users.id |
| action | VARCHAR(100) | - | Yapılan işlem |
| model_type | VARCHAR(100) NULL | NULL | İlgili model adı |
| model_id | BIGINT UNSIGNED NULL | NULL | İlgili kayıt id |
| changes | JSON NULL | NULL | Değişiklik detayı |
| ip_address | VARCHAR(45) NULL | NULL | IP adresi |
| user_agent | TEXT NULL | NULL | Tarayıcı bilgisi |
| created_at | TIMESTAMP NULL | NULL | Oluşturma |
| updated_at | TIMESTAMP NULL | NULL | Güncelleme |

Index’ler:

- `user_id, created_at`
- `model_type, model_id`

---

## İlişkiler

```text
categories 1---* products
suppliers 1---* products
products 1---* product_barcodes
products 1---* purchase_items
products 1---* sale_items
products 1---* stock_movements

suppliers 1---* purchases
purchases 1---* purchase_items
purchases 1---* stock_movements

customers 1---* sales
customers 1---* payments

users 1---* sales
users 1---* purchases
users 1---* payments
users 1---* stock_movements
users 1---* activity_logs

sales 1---* sale_items
sales 1---* payments
sales 1---* stock_movements
```

---

## Migration Oluşturma Sırası

1. `users`
2. `categories`
3. `suppliers`
4. `products`
5. `product_barcodes`
6. `purchases`
7. `purchase_items`
8. `customers`
9. `sales`
10. `sale_items`
11. `payments`
12. `stock_movements`
13. `settings`
14. `activity_logs`

---

## Kâr Hesabı

```text
Satır Kârı = Miktar x (Satış Birim Fiyatı - Satır İndirimi - Alış Birim Fiyatı)
```

`sale_items.purchase_price`, satışın yapıldığı andaki ürün maliyetini sabitler. Ürünün sonradan alış fiyatı değişse bile geçmiş satışın kârı doğru kalır.

---

## Stok Hareket Türleri

| Tür | Anlamı | Stok Etkisi |
|---|---|---|
| purchase | Tedarikçiden ürün girişi | Artar |
| sale | Satış nedeniyle çıkış | Azalır |
| return | Müşteri iadesi | Artar |
| damage | Fire, bozulma, kırılma | Azalır |
| correction | Stok sayım düzeltmesi | Artar veya azalır |
| initial | Başlangıç stok girişi | Artar |

---

## Claude Code İçin Talimat

Bu şema, projenin veritabanı temelidir. Claude Code:

- Bu tablo ve sütun yapısına birebir uymalıdır.
- Yeni bir sütun eklemek gerekiyorsa önce gerekçesini açıklamalıdır.
- Mevcut tabloları silmemeli veya gereksiz şekilde değiştirmemelidir.
- Her yeni özellik için önce ilgili tablo ve sütunları kontrol etmelidir.
- Satış, iade, fire ve sayım işlemlerinde mutlaka `stock_movements` kaydı oluşturmalıdır.
- Satış ve stok işlemlerini `DB::transaction` içinde yapmalıdır.
