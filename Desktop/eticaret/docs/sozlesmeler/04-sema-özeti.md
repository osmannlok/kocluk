# 04-sema-ozeti

Kaynak: database/01..06 SQL dosyaları. DB `yoresel_market`, InnoDB, utf8mb4_unicode_ci. Para DECIMAL(12,2) KDV dahil.
Kısaltmalar: NN=NOT NULL, N=NULL, AI=AUTO_INCREMENT, U=UNSIGNED, V=VARCHAR, DT=DATETIME, TS=TIMESTAMP, TI1=TINYINT(1), VB16=VARBINARY(16), LT=LONGTEXT.
`+ts` = created_at(TS,NN,CURRENT_TIMESTAMP), updated_at(TS,NN,CURRENT_TIMESTAMP ON UPDATE). `+cr` = yalnızca created_at. `+del` = deleted_at(DT,N).

## 01_auth_rbac
- roles: id(TINYINT U,AI) name(V50,NN) label(V100,NN) description(V255,N) +cr | PK id | UNIQUE name
- permissions: id(SMALLINT U,AI) code(V100,NN) label(V150,NN) module(V50,NN) +cr | PK id | UNIQUE code
- role_permissions: role_id(TINYINT U,NN) permission_id(SMALLINT U,NN) | PK(role_id,permission_id) | FK role_id->roles CASCADE, permission_id->permissions CASCADE
- users: id(BIGINT U,AI) role_id(TINYINT U,NN) first_name(V80,NN) last_name(V80,NN) email(V190,NN) phone(V20,N) password_hash(V255,NN) email_verified_at(DT,N) status(ENUM,NN,'pending') kvkk_consent_at(DT,N) marketing_consent(TI1,NN,0) last_login_at(DT,N) last_login_ip(VB16,N) failed_login_count(TINYINT U,NN,0) locked_until(DT,N) +ts +del | PK id | FK role_id->roles RESTRICT | UNIQUE email | ENUM status
- addresses: id(BIGINT U,AI) user_id(NN) title(V60,NN) full_name(V160,NN) phone(V20,NN) city(V80,NN) district(V80,NN) neighborhood(V120,N) address_line(V500,NN) postal_code(V10,N) is_default(TI1,NN,0) invoice_type(ENUM,NN,'individual') tc_identity_no(V11,N) company_name(V190,N) tax_office(V120,N) tax_number(V20,N) +ts +del | PK id | FK user_id->users CASCADE
- login_attempts: id(BIGINT U,AI) email(V190,NN) ip_address(VB16,NN) user_agent(V255,N) success(TI1,NN,0) attempted_at(TS,NN,CURRENT_TIMESTAMP) | PK id | FK yok
- password_resets: id(BIGINT U,AI) user_id(NN) token_hash(CHAR64,NN) expires_at(DT,NN) used_at(DT,N) ip_address(VB16,N) +cr | PK id | FK user_id->users CASCADE | UNIQUE token_hash
- email_verifications: password_resets ile aynı kolonlar | PK id | FK user_id->users CASCADE | UNIQUE token_hash

## 02_catalog_stock
- categories: id(INT U,AI) parent_id(INT U,N) name(V120,NN) slug(V140,NN) description(TEXT,N) image_path(V255,N) sort_order(INT,NN,0) is_active(TI1,NN,1) meta_title(V160,N) meta_description(V255,N) +ts +del | PK id | FK parent_id->categories SET NULL | UNIQUE slug
- regions: id(SMALLINT U,AI) name(V100,NN) slug(V120,NN) sort_order(INT,NN,0) is_active(TI1,NN,1) +cr | PK id | UNIQUE slug
- products: id(BIGINT U,AI) category_id(INT U,NN) region_id(SMALLINT U,N) name(V190,NN) slug(V220,NN) short_description(V500,N) description(MEDIUMTEXT,N) origin_text(V190,N) producer_name(V190,N) ingredients(TEXT,N) allergen_info(TEXT,N) storage_conditions(V255,N) shelf_life_info(V190,N) tax_rate(DEC5,2,NN,10.00) is_active(TI1,NN,1) is_featured(TI1,NN,0) meta_title(V160,N) meta_description(V255,N) created_by(N) updated_by(N) +ts +del | PK id | FK category_id->categories RESTRICT, region_id->regions SET NULL, created_by/updated_by->users SET NULL | UNIQUE slug | FULLTEXT(name,short_description)
- product_variants: id(BIGINT U,AI) product_id(NN) sku(V64,NN) barcode(V64,N) name(V100,NN) weight_grams(INT U,NN) price(NN) sale_price(N) sale_starts_at(DT,N) sale_ends_at(DT,N) stock_quantity(INT,NN,0) low_stock_threshold(INT U,NN,5) track_stock(TI1,NN,1) is_default(TI1,NN,0) is_active(TI1,NN,1) sort_order(INT,NN,0) +ts +del | PK id | FK product_id->products CASCADE | UNIQUE sku | CHECK stock_quantity>=0; price>=0; sale_price IS NULL OR (sale_price>=0 AND sale_price<price)
- product_images: id(BIGINT U,AI) product_id(NN) variant_id(N) path(V255,NN) alt_text(V190,N) sort_order(INT,NN,0) is_primary(TI1,NN,0) +cr | PK id | FK product_id->products CASCADE, variant_id->product_variants SET NULL
- stock_movements (EKLEMELİ): id(BIGINT U,AI) variant_id(NN) user_id(N) type(ENUM,NN) quantity_change(INT,NN) quantity_after(INT,NN) reference_type(V50,N) reference_id(BIGINT U,N) note(V255,N) +cr | PK id | FK variant_id->product_variants RESTRICT, user_id->users SET NULL | ENUM type
- VIEW v_low_stock: variant_id, product_id, product_name, variant_name, sku, stock_quantity, low_stock_threshold (aktif, silinmemiş, track_stock=1, stock<=eşik; stok artan)

## 03_cart_orders_payments
- carts: id(BIGINT U,AI) user_id(N) guest_token(CHAR64,N; imzalı çerezin SHA-256'sı) expires_at(DT,NN) +ts | PK id | FK user_id->users CASCADE | UNIQUE user_id, guest_token | CHECK user_id IS NOT NULL OR guest_token IS NOT NULL
- cart_items: id(BIGINT U,AI) cart_id(NN) variant_id(NN) quantity(SMALLINT U,NN) +ts | PK id | FK cart_id->carts CASCADE, variant_id->product_variants CASCADE | UNIQUE(cart_id,variant_id) | CHECK quantity BETWEEN 1 AND 99 (fiyat saklanmaz)
- coupons: id(BIGINT U,AI) code(V40,NN) description(V255,N) discount_type(ENUM,NN) discount_value(NN) max_discount_amount(N) min_cart_total(NN,0) usage_limit(INT U,N) usage_per_user(INT U,N) used_count(INT U,NN,0) starts_at(DT,N) ends_at(DT,N) is_active(TI1,NN,1) created_by(N) +ts +del | PK id | FK created_by->users SET NULL | UNIQUE code | CHECK discount_value>0; type<>'percent' OR value<=100
- shipping_rates: id(BIGINT U,AI) min_weight_grams(INT U,NN) max_weight_grams(INT U,NN) price(NN) is_active(TI1,NN,1) +ts | PK id | CHECK min<=max; price>=0
- orders: id(BIGINT U,AI) order_number(V30,N) user_id(NN) status(ENUM,NN,'pending_payment') payment_method(ENUM,NN) payment_status(ENUM,NN,'pending') currency(CHAR3,NN,'TRY') subtotal(NN) discount_total(NN,0) coupon_id(N) coupon_code(V40,N) shipping_total(NN,0) cod_fee(NN,0) grand_total(NN) tax_total(NN,0) total_weight_grams(INT U,NN,0) shipping_address(LT,NN) billing_address(LT,NN) customer_note(V500,N) kvkk_consent_at(DT,NN) distance_sales_consent_at(DT,NN) consent_ip(VB16,NN) consent_user_agent(V255,N) ip_address(VB16,N) expires_at(DT,N) paid_at/shipped_at/delivered_at/cancelled_at(DT,N) cancel_reason(V255,N) +ts | PK id | FK user_id->users RESTRICT, coupon_id->coupons SET NULL | UNIQUE order_number | CHECK grand_total>=0 | ENUM status, payment_method, payment_status
- order_items: id(BIGINT U,AI) order_id(NN) variant_id(NN) product_name(V190,NN) variant_name(V100,NN) sku(V64,NN) list_price(NN) unit_price(NN) quantity(SMALLINT U,NN) tax_rate(DEC5,2,NN) line_total(NN) weight_grams(INT U,NN) | PK id | FK order_id->orders CASCADE, variant_id->product_variants RESTRICT
- order_status_history (EKLEMELİ): id(BIGINT U,AI) order_id(NN) from_status(V30,N) to_status(V30,NN) changed_by(N) note(V255,N) +cr | PK id | FK order_id->orders CASCADE, changed_by->users SET NULL
- payments: id(BIGINT U,AI) order_id(NN) provider(ENUM,NN) merchant_oid(V64,N) amount(NN) currency(CHAR3,NN,'TRY') status(ENUM,NN,'initiated') payment_type(V20,N) installment_count(TINYINT U,N) failed_reason_code(V20,N) failed_reason_msg(V255,N) callback_received_at(DT,N) confirmed_by(N) confirmed_at(DT,N) note(V255,N) +ts | PK id | FK order_id->orders RESTRICT, confirmed_by->users SET NULL | UNIQUE merchant_oid | CHECK merchant_oid NULL veya ^[A-Za-z0-9]+$ | ENUM provider, status
- payment_callbacks (EKLEMELİ): id(BIGINT U,AI) merchant_oid(V64,N) payload(LT,NN; kart verisi yok) hash_valid(TI1,NN) ip_address(VB16,NN) result(V50,N: processed/duplicate/amount_mismatch/bad_hash) +cr | PK id | FK yok
- shipments: id(BIGINT U,AI) order_id(NN) carrier(V60,NN) tracking_number(V80,NN) tracking_url(V255,N) shipped_at(DT,NN) delivered_at(DT,N) note(V255,N) created_by(N) +ts | PK id | FK order_id->orders CASCADE, created_by->users SET NULL
- coupon_usages: id(BIGINT U,AI) coupon_id(NN) user_id(NN) order_id(NN) discount_amount(NN) +cr | PK id | FK coupon_id->coupons RESTRICT, user_id->users RESTRICT, order_id->orders RESTRICT | UNIQUE order_id

## 04_cms_marketing
- banners: id(BIGINT U,AI) title(V150,NN) subtitle(V255,N) button_text(V60,N) link_url(V255,N) image_desktop(V255,NN) image_mobile(V255,N) alt_text(V190,N) position(ENUM,NN,'home_hero') sort_order(INT,NN,0) starts_at(DT,N) ends_at(DT,N) is_active(TI1,NN,1) created_by(N) +ts +del | PK id | FK created_by->users SET NULL
- menus: id(INT U,AI) name(V80,NN) location(ENUM,NN) is_active(TI1,NN,1) | PK id | UNIQUE location
- menu_items: id(BIGINT U,AI) menu_id(INT U,NN) parent_id(BIGINT U,N) label(V100,NN) link_type(ENUM,NN,'url') link_value(V255,N) icon(V60,N) badge_text(V20,N) is_mega(TI1,NN,0) open_new_tab(TI1,NN,0) sort_order(INT,NN,0) is_active(TI1,NN,1) +ts | PK id | FK menu_id->menus CASCADE, parent_id->menu_items CASCADE
- pages: id(BIGINT U,AI) title(V190,NN) slug(V160,NN) system_key(V50,N) content(MEDIUMTEXT,N; temizlenmiş HTML) is_active(TI1,NN,1) show_in_footer(TI1,NN,0) meta_title(V160,N) meta_description(V255,N) +ts +del | PK id | UNIQUE slug, system_key
- settings: key_name(V100,NN) value(TEXT,N) value_type(ENUM,NN,'string') group_name(V50,NN) label(V150,NN) is_public(TI1,NN,0) updated_by(N) updated_at(TS,NN,ON UPDATE) | PK key_name | FK updated_by->users SET NULL
- announcements: id(BIGINT U,AI) message(V255,NN) link_url(V255,N) sort_order(INT,NN,0) starts_at(DT,N) ends_at(DT,N) is_active(TI1,NN,1) +ts | PK id
- contact_messages: id(BIGINT U,AI) name(V120,NN) email(V190,NN) phone(V20,N) subject(V190,NN) message(TEXT,NN) ip_address(VB16,NN) status(ENUM,NN,'new') handled_by(N) +cr | PK id | FK handled_by->users SET NULL

## 05_security_logs
- audit_logs (EKLEMELİ): id(BIGINT U,AI) user_id(N) action(V100,NN) entity_type(V60,N) entity_id(BIGINT U,N) old_values(LT,N) new_values(LT,N) ip_address(VB16,N) user_agent(V255,N) +cr | PK id | FK user_id->users SET NULL
- security_events (EKLEMELİ): id(BIGINT U,AI) type(V60,NN) severity(ENUM,NN,'info') user_id(N) ip_address(VB16,NN) detail(LT,N) +cr | PK id | FK user_id->users SET NULL
- rate_limits: key_hash(CHAR64,NN) hits(INT U,NN,0) window_start(INT U,NN) expires_at(INT U,NN) | PK key_hash
- sessions: id(V128,NN) user_id(N) payload(MEDIUMBLOB,NN) ip_address(VB16,N) user_agent(V255,N) last_activity(INT U,NN) | PK id | FK user_id->users SET NULL
- DB kullanıcısı: ym_app (yorumlu blok). Genel tablolar SELECT/INSERT/UPDATE/DELETE; audit_logs, security_events, payment_callbacks, stock_movements, order_status_history yalnızca SELECT/INSERT. Dev: ym_dev.

## Settings anahtarları (06_seed)
site.name, site.tagline | contact.email/phone/whatsapp/address | social.instagram/facebook | shipping.free_threshold=750.00 | cod.enabled, cod.fee=15.00 | bank_transfer.enabled/bank_name/account_holder/iban/note | card.enabled | order.card_expiry_minutes=30, order.bank_expiry_hours=48, order.min_amount=0 | stock.default_low_threshold=5 | mail.from_name | seo.default_title/default_description | legal.company_title/tax_office/tax_number/mersis | tax.prices_include_vat=1

## Permission kodları ve rol eşleşmesi
Kodlar (23): dashboard.view, product.view/create/update/delete, category.manage, stock.manage, order.view/update_status/cancel/refund, payment.confirm, shipment.manage, customer.view, coupon.manage, banner.manage, menu.manage, page.manage, setting.manage, user.manage, role.manage, report.view, audit.view
- admin: tümü (23)
- manager (11): dashboard.view, product.view, product.create, product.update, category.manage, stock.manage, order.view, order.update_status, shipment.manage, customer.view, report.view
- customer: yok

## ENUM sözlüğü
users.status: active,suspended,pending | addresses.invoice_type: individual,corporate | stock_movements.type: initial,purchase,sale,cancel_return,manual_adjust,correction | coupons.discount_type: percent,fixed | orders.status: pending_payment,paid,preparing,shipped,delivered,cancelled,refunded | orders.payment_method: credit_card,bank_transfer,cod | orders.payment_status: pending,paid,failed,refunded | payments.provider: paytr,bank_transfer,cod | payments.status: initiated,pending,success,failed,refunded | banners.position: home_hero,home_secondary,category_top | menus.location: header,footer_1,footer_2,mobile | menu_items.link_type: url,category,page,region,products | settings.value_type: string,int,decimal,bool,json | contact_messages.status: new,read,replied | security_events.severity: info,warning,critical