-- =====================================================================
-- database/06_seed.sql
-- YÖRESEL MARKET: Başlangıç verisi (ayarlar, kargo tarifesi, yöre, kategori, menü, sayfa, demo ürün)
-- Yeniden çalıştırılabilir: INSERT IGNORE / NOT EXISTS korumalı (tekrar çalıştırınca çoğalmaz).
-- Admin kullanıcısı seed EDİLMEZ (Argon2id hash'i uygulama tarafında üretilir).
-- =====================================================================

USE yoresel_market;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- A) settings (anahtar, değer, tip, grup, etiket, is_public)
-- Güvenlik: gizli anahtarlar (PayTR vb.) burada DEĞİL, .env içindedir.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO settings (key_name, value, value_type, group_name, label, is_public) VALUES
    ('site.name',                    'Yöresel Market',                                  'string',  'general',  'Site adı', 1),
    ('site.tagline',                 'Yöresel lezzetler, kapınıza kadar',               'string',  'general',  'Site sloganı', 1),
    ('contact.email',                'info@ornek-yoresel.test',                         'string',  'contact',  'İletişim e-postası', 1),
    ('contact.phone',                '0 (000) 000 00 00',                               'string',  'contact',  'İletişim telefonu', 1),
    ('contact.whatsapp',             '905000000000',                                    'string',  'contact',  'WhatsApp numarası', 1),
    ('contact.address',              'Örnek Mah. Örnek Cad. No:1 Merkez / Kahramanmaraş', 'string', 'contact',  'Adres', 1),
    ('social.instagram',             'https://www.instagram.com/',                      'string',  'social',   'Instagram bağlantısı', 1),
    ('social.facebook',              'https://www.facebook.com/',                       'string',  'social',   'Facebook bağlantısı', 1),
    ('shipping.free_threshold',      '750.00',                                          'decimal', 'shipping', 'Ücretsiz kargo eşiği (TL)', 1),
    ('cod.enabled',                  '1',                                               'bool',    'payment',  'Kapıda ödeme açık', 1),
    ('cod.fee',                      '15.00',                                           'decimal', 'payment',  'Kapıda ödeme ücreti (TL)', 1),
    ('bank_transfer.enabled',        '1',                                               'bool',    'payment',  'Havale/EFT açık', 1),
    -- ÖRNEK banka bilgileri: yayına almadan önce GERÇEK bilgilerle değiştir.
    ('bank_transfer.bank_name',      'Örnek Bankası',                                   'string',  'payment',  'Banka adı', 0),
    ('bank_transfer.account_holder', 'Örnek Gıda Ltd. Şti.',                            'string',  'payment',  'Hesap sahibi', 0),
    ('bank_transfer.iban',           'TR00 0000 0000 0000 0000 0000 00',                'string',  'payment',  'IBAN', 0),
    ('bank_transfer.note',           'Açıklama kısmına sipariş numaranızı yazınız.',    'string',  'payment',  'Havale açıklama notu', 0),
    ('card.enabled',                 '1',                                               'bool',    'payment',  'Kart ile ödeme açık', 1),
    ('order.card_expiry_minutes',    '30',                                              'int',     'order',    'Kartlı ödeme bekleme süresi (dk)', 0),
    ('order.bank_expiry_hours',      '48',                                              'int',     'order',    'Havale bekleme süresi (saat)', 0),
    ('order.min_amount',             '0',                                               'decimal', 'order',    'Minimum sipariş tutarı (TL)', 1),
    ('stock.default_low_threshold',  '5',                                               'int',     'stock',    'Varsayılan kritik stok eşiği', 0),
    ('mail.from_name',               'Yöresel Market',                                  'string',  'mail',     'Gönderen adı', 0),
    ('seo.default_title',            'Yöresel Market | Doğal Yöresel Ürünler',          'string',  'seo',      'Varsayılan sayfa başlığı', 1),
    ('seo.default_description',      'Türkiye''nin dört bir yanından yöresel gıda ürünleri kapınızda.', 'string', 'seo', 'Varsayılan meta açıklama', 1),
    ('legal.company_title',          'Örnek Gıda Ltd. Şti.',                            'string',  'legal',    'Şirket unvanı', 1),
    ('legal.tax_office',             'Örnek Vergi Dairesi',                             'string',  'legal',    'Vergi dairesi', 1),
    ('legal.tax_number',             '0000000000',                                      'string',  'legal',    'Vergi numarası', 1),
    ('legal.mersis',                 '0000000000000000',                                'string',  'legal',    'MERSİS numarası', 1),
    ('tax.prices_include_vat',       '1',                                               'bool',    'tax',      'Fiyatlar KDV dahil', 1);

-- ---------------------------------------------------------------------
-- B) shipping_rates ÖRNEK tarife (gerçek tarifeyle değiştir). Tekrar çalışınca çoğalmaz.
-- ---------------------------------------------------------------------
INSERT INTO shipping_rates (min_weight_grams, max_weight_grams, price, is_active)
SELECT t.mn, t.mx, t.pr, 1
FROM (
    SELECT 0 AS mn, 2000 AS mx, 64.90 AS pr
    UNION ALL SELECT 2001, 5000, 84.90
    UNION ALL SELECT 5001, 10000, 119.90
    UNION ALL SELECT 10001, 99999999, 169.90
) t
WHERE NOT EXISTS (
    SELECT 1 FROM shipping_rates s
    WHERE s.min_weight_grams = t.mn AND s.max_weight_grams = t.mx
);

-- ---------------------------------------------------------------------
-- C) regions
-- ---------------------------------------------------------------------
INSERT IGNORE INTO regions (name, slug, sort_order, is_active) VALUES
    ('Karadeniz',            'karadeniz',          1, 1),
    ('Ege',                  'ege',                2, 1),
    ('Akdeniz',              'akdeniz',            3, 1),
    ('Güneydoğu Anadolu',    'guneydogu-anadolu',  4, 1),
    ('İç Anadolu',           'ic-anadolu',         5, 1),
    ('Marmara',              'marmara',            6, 1),
    ('Doğu Anadolu',         'dogu-anadolu',       7, 1);

-- ---------------------------------------------------------------------
-- D) categories (7 üst + 2 alt örnek)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO categories (parent_id, name, slug, sort_order, is_active) VALUES
    (NULL, 'Bal ve Arı Ürünleri',       'bal-ve-ari-urunleri',      1, 1),
    (NULL, 'Zeytin ve Zeytinyağı',      'zeytin-ve-zeytinyagi',     2, 1),
    (NULL, 'Peynir ve Süt Ürünleri',    'peynir-ve-sut-urunleri',   3, 1),
    (NULL, 'Reçel ve Pekmez',           'recel-ve-pekmez',          4, 1),
    (NULL, 'Baharat ve Kuruyemiş',      'baharat-ve-kuruyemis',     5, 1),
    (NULL, 'Makarna-Tarhana-Erişte',    'makarna-tarhana-eriste',   6, 1),
    (NULL, 'Çay ve Bitki Çayları',      'cay-ve-bitki-caylari',     7, 1);

INSERT IGNORE INTO categories (parent_id, name, slug, sort_order, is_active)
SELECT id, 'Çiçek Balı', 'cicek-bali', 1, 1 FROM categories WHERE slug = 'bal-ve-ari-urunleri';

INSERT IGNORE INTO categories (parent_id, name, slug, sort_order, is_active)
SELECT id, 'Sızma Zeytinyağı', 'sizma-zeytinyagi', 1, 1 FROM categories WHERE slug = 'zeytin-ve-zeytinyagi';

-- ---------------------------------------------------------------------
-- E) menus + menu_items
-- ---------------------------------------------------------------------
INSERT IGNORE INTO menus (name, location, is_active) VALUES
    ('Ana Menü',  'header',   1),
    ('Kurumsal',  'footer_1', 1),
    ('Yasal',     'footer_2', 1);

-- header: üst seviye
INSERT INTO menu_items (menu_id, parent_id, label, link_type, link_value, badge_text, is_mega, sort_order)
SELECT m.id, NULL, t.label, t.link_type, t.link_value, t.badge_text, t.is_mega, t.sort_order
FROM menus m
JOIN (
    SELECT 'Tüm Ürünler' AS label, 'products' AS link_type, NULL AS link_value, NULL AS badge_text, 0 AS is_mega, 1 AS sort_order
    UNION ALL SELECT 'Kategoriler', 'products', NULL, NULL, 1, 2
    UNION ALL SELECT 'Yöreler', 'url', NULL, NULL, 0, 3
    UNION ALL SELECT 'Kampanyalar', 'url', '/urunler?indirimli=1', 'İndirim', 0, 4
) t
WHERE m.location = 'header'
  AND NOT EXISTS (
      SELECT 1 FROM menu_items x
      WHERE x.menu_id = m.id AND x.parent_id IS NULL AND x.label = t.label
  );

-- header > Kategoriler (mega menü): 7 üst kategori
INSERT INTO menu_items (menu_id, parent_id, label, link_type, link_value, sort_order)
SELECT m.id, p.id, c.name, 'category', c.slug, c.sort_order
FROM menus m
JOIN menu_items p ON p.menu_id = m.id AND p.parent_id IS NULL AND p.label = 'Kategoriler'
JOIN categories c ON c.parent_id IS NULL AND c.deleted_at IS NULL
 AND c.slug IN ('bal-ve-ari-urunleri','zeytin-ve-zeytinyagi','peynir-ve-sut-urunleri',
                'recel-ve-pekmez','baharat-ve-kuruyemis','makarna-tarhana-eriste','cay-ve-bitki-caylari')
WHERE m.location = 'header'
  AND NOT EXISTS (
      SELECT 1 FROM menu_items x
      WHERE x.parent_id = p.id AND x.link_type = 'category' AND x.link_value = c.slug
  );

-- header > Kategoriler > alt kategoriler (3. seviye)
INSERT INTO menu_items (menu_id, parent_id, label, link_type, link_value, sort_order)
SELECT m.id, p.id, c.name, 'category', c.slug, c.sort_order
FROM menus m
JOIN categories c ON c.slug IN ('cicek-bali','sizma-zeytinyagi')
JOIN categories pc ON pc.id = c.parent_id
JOIN menu_items p ON p.menu_id = m.id AND p.link_type = 'category' AND p.link_value = pc.slug
WHERE m.location = 'header'
  AND NOT EXISTS (
      SELECT 1 FROM menu_items x
      WHERE x.parent_id = p.id AND x.link_value = c.slug
  );

-- header > Yöreler: 7 yöre
INSERT INTO menu_items (menu_id, parent_id, label, link_type, link_value, sort_order)
SELECT m.id, p.id, r.name, 'region', r.slug, r.sort_order
FROM menus m
JOIN menu_items p ON p.menu_id = m.id AND p.parent_id IS NULL AND p.label = 'Yöreler'
JOIN regions r ON r.slug IN ('karadeniz','ege','akdeniz','guneydogu-anadolu','ic-anadolu','marmara','dogu-anadolu')
WHERE m.location = 'header'
  AND NOT EXISTS (
      SELECT 1 FROM menu_items x
      WHERE x.parent_id = p.id AND x.link_type = 'region' AND x.link_value = r.slug
  );

-- footer_1 (Kurumsal) ve footer_2 (Yasal)
INSERT INTO menu_items (menu_id, parent_id, label, link_type, link_value, sort_order)
SELECT m.id, NULL, t.label, t.link_type, t.link_value, t.sort_order
FROM (
    SELECT 'footer_1' AS location, 'Hakkımızda' AS label, 'page' AS link_type, 'hakkimizda' AS link_value, 1 AS sort_order
    UNION ALL SELECT 'footer_1', 'İletişim',         'url',  '/iletisim',      2
    UNION ALL SELECT 'footer_2', 'KVKK Aydınlatma',  'page', 'kvkk',           1
    UNION ALL SELECT 'footer_2', 'Mesafeli Satış Sözleşmesi', 'page', 'mesafeli-satis', 2
    UNION ALL SELECT 'footer_2', 'İade ve İptal',    'page', 'iade-iptal',     3
    UNION ALL SELECT 'footer_2', 'Gizlilik Politikası', 'page', 'gizlilik',    4
    UNION ALL SELECT 'footer_2', 'Çerez Politikası', 'page', 'cerez',          5
) t
JOIN menus m ON m.location = t.location
WHERE NOT EXISTS (
    SELECT 1 FROM menu_items x
    WHERE x.menu_id = m.id AND x.parent_id IS NULL AND x.label = t.label
);

-- ---------------------------------------------------------------------
-- F) pages (system_key ile). İçerik TASLAKTIR: yayından önce hukuk danışmanına onaylat.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO pages (title, slug, system_key, content, is_active, show_in_footer, meta_title, meta_description) VALUES
    ('Hakkımızda', 'hakkimizda', 'about',
     '<h2>TASLAK METİN: yayından önce hukuk danışmanına onaylat</h2><p>Yöresel Market, Türkiye''nin farklı yörelerindeki üreticilerden doğal ve yöresel gıda ürünlerini sizlere ulaştırır. Bu metin örnektir; kendi hikayenizle değiştiriniz.</p>',
     1, 1, 'Hakkımızda', 'Yöresel Market hakkında'),
    ('KVKK Aydınlatma Metni', 'kvkk', 'kvkk',
     '<h2>TASLAK METİN: yayından önce hukuk danışmanına onaylat</h2><p>6698 sayılı Kişisel Verilerin Korunması Kanunu kapsamında veri sorumlusu olarak kişisel verileriniz; sipariş, teslimat, fatura, müşteri hizmetleri ve yasal yükümlülüklerin yerine getirilmesi amaçlarıyla işlenir. Veri sorumlusu bilgileri, işleme amaçları, aktarılan taraflar, saklama süreleri ve haklarınız bu bölümde ayrıntılandırılmalıdır.</p>',
     1, 1, 'KVKK Aydınlatma Metni', 'Kişisel verilerin korunması aydınlatma metni'),
    ('Mesafeli Satış Sözleşmesi', 'mesafeli-satis', 'distance_sales',
     '<h2>TASLAK METİN: yayından önce hukuk danışmanına onaylat</h2><p>Bu sözleşme; satıcı ve alıcının tarafları, sözleşme konusu ürünler, toplam bedel, ödeme şekli, teslimat koşulları, cayma hakkı ve uyuşmazlık çözümü hükümlerini içermelidir.</p>',
     1, 1, 'Mesafeli Satış Sözleşmesi', 'Mesafeli satış sözleşmesi'),
    ('İade ve İptal Koşulları', 'iade-iptal', 'returns',
     '<h2>TASLAK METİN: yayından önce hukuk danışmanına onaylat</h2><p>Siparişinizi kargoya verilmeden önce iptal edebilirsiniz. Hasarlı veya hatalı teslim edilen ürünler için teslimattan itibaren makul süre içinde bizimle iletişime geçiniz.</p><h3>Cayma hakkı istisnası (gıda)</h3><p>Mesafeli Sözleşmeler Yönetmeliği kapsamında çabuk bozulabilen veya son kullanma tarihi geçebilecek mallar ile ambalajı açılmış, sağlık ve hijyen açısından iadesi uygun olmayan gıda ürünlerinde cayma hakkı kullanılamaz. Bu paragraf taslaktır; kapsam hukuk danışmanıyla netleştirilmelidir.</p>',
     1, 1, 'İade ve İptal Koşulları', 'İade, iptal ve cayma hakkı istisnaları'),
    ('Gizlilik Politikası', 'gizlilik', 'privacy',
     '<h2>TASLAK METİN: yayından önce hukuk danışmanına onaylat</h2><p>Bu bölümde toplanan veriler, kullanım amaçları, üçüncü taraf paylaşımları (ödeme kuruluşu, kargo firması) ve güvenlik önlemleri açıklanmalıdır. Kart bilgileriniz sitemizde saklanmaz.</p>',
     1, 1, 'Gizlilik Politikası', 'Gizlilik politikası'),
    ('Çerez Politikası', 'cerez', 'cookies',
     '<h2>TASLAK METİN: yayından önce hukuk danışmanına onaylat</h2><p>Sitemiz oturum yönetimi ve güvenlik için zorunlu çerezler kullanır. Zorunlu olmayan çerezler yalnızca onayınızla kullanılır. Kullanılan çerezlerin listesi ve süreleri burada belirtilmelidir.</p>',
     1, 1, 'Çerez Politikası', 'Çerez politikası');

-- ---------------------------------------------------------------------
-- G) announcements (1 örnek)
-- ---------------------------------------------------------------------
INSERT INTO announcements (message, link_url, sort_order, is_active)
SELECT '750 TL ve üzeri siparişlerde kargo bedava!', '/urunler', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM announcements WHERE message = '750 TL ve üzeri siparişlerde kargo bedava!');

-- ---------------------------------------------------------------------
-- H) banners (2 home_hero örneği; görseller placeholder)
-- ---------------------------------------------------------------------
INSERT INTO banners (title, subtitle, button_text, link_url, image_desktop, image_mobile, alt_text, position, sort_order, is_active)
SELECT t.title, t.subtitle, t.button_text, t.link_url, t.image_desktop, t.image_mobile, t.alt_text, 'home_hero', t.sort_order, 1
FROM (
    SELECT 'Yöresel Lezzetler Kapınızda' AS title, 'Türkiye''nin dört bir yanından doğal ürünler' AS subtitle,
           'Alışverişe Başla' AS button_text, '/urunler' AS link_url,
           '/assets/img/banner-1.jpg' AS image_desktop, '/assets/img/banner-1-mobile.jpg' AS image_mobile,
           'Yöresel ürünler banner görseli' AS alt_text, 1 AS sort_order
    UNION ALL
    SELECT 'Yeni Sezon Bal ve Zeytinyağı', 'Taze hasat ürünler stokta',
           'İncele', '/kategori/bal-ve-ari-urunleri',
           '/assets/img/banner-2.jpg', '/assets/img/banner-2-mobile.jpg',
           'Bal ve zeytinyağı banner görseli', 2
) t
WHERE NOT EXISTS (SELECT 1 FROM banners b WHERE b.title = t.title);

-- ---------------------------------------------------------------------
-- I) DEMO ürünler (SKU öneki DEMO-). 6 ürün: 1 indirimli (zeytinyağı 1 L),
-- 1 kritik stok (peynir 500 g, stok 3 <= eşik 5), 1 stoksuz (tarhana).
-- KDV oranı (tax_rate) varsayılan; muhasebeciyle netleştirilecek.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO products (category_id, region_id, name, slug, short_description, description, origin_text, producer_name, ingredients, allergen_info, storage_conditions, shelf_life_info, is_active, is_featured)
SELECT c.id, r.id, 'Karadeniz Çiçek Balı', 'karadeniz-cicek-bali',
       'Yayla çiçeklerinden süzme, katkısız çiçek balı.',
       'Karadeniz yaylalarındaki çiçeklerden elde edilen, ısıtılmamış ve süzme çiçek balı.',
       'Karadeniz Yaylaları', 'Örnek Arıcılık', '%100 çiçek balı',
       'Alerjen içermez. Bal, 1 yaşından küçük bebeklere verilmemelidir.',
       'Serin ve kuru yerde, doğrudan güneş ışığından uzakta saklayınız.', 'Üretimden itibaren 24 ay', 1, 1
FROM categories c JOIN regions r ON r.slug = 'karadeniz' WHERE c.slug = 'bal-ve-ari-urunleri';

INSERT IGNORE INTO products (category_id, region_id, name, slug, short_description, description, origin_text, producer_name, ingredients, allergen_info, storage_conditions, shelf_life_info, is_active, is_featured)
SELECT c.id, r.id, 'Ayvalık Sızma Zeytinyağı', 'ayvalik-sizma-zeytinyagi',
       'Soğuk sıkım, düşük asitli erken hasat zeytinyağı.',
       'Ayvalık zeytinlerinden soğuk sıkım yöntemiyle elde edilen natürel sızma zeytinyağı.',
       'Ayvalık / Balıkesir', 'Örnek Zeytincilik', '%100 natürel sızma zeytinyağı',
       'Alerjen içermez.', 'Işıktan ve sıcaktan uzak, serin yerde saklayınız.', 'Üretimden itibaren 18 ay', 1, 1
FROM categories c JOIN regions r ON r.slug = 'ege' WHERE c.slug = 'sizma-zeytinyagi';

INSERT IGNORE INTO products (category_id, region_id, name, slug, short_description, description, origin_text, producer_name, ingredients, allergen_info, storage_conditions, shelf_life_info, is_active, is_featured)
SELECT c.id, r.id, 'Erzincan Tulum Peyniri', 'erzincan-tulum-peyniri',
       'Deri tulumda olgunlaştırılmış koyun sütü peyniri.',
       'Erzincan yaylalarında koyun sütünden yapılan, geleneksel yöntemle olgunlaştırılmış tulum peyniri.',
       'Erzincan', 'Örnek Mandıra', 'Koyun sütü, tuz, peynir mayası',
       'Süt ürünü içerir (süt).', '+4 °C buzdolabında saklayınız.', 'Üretimden itibaren 6 ay', 1, 0
FROM categories c JOIN regions r ON r.slug = 'dogu-anadolu' WHERE c.slug = 'peynir-ve-sut-urunleri';

INSERT IGNORE INTO products (category_id, region_id, name, slug, short_description, description, origin_text, producer_name, ingredients, allergen_info, storage_conditions, shelf_life_info, is_active, is_featured)
SELECT c.id, r.id, 'Maraş Pul Biber', 'maras-pul-biber',
       'Güneşte kurutulmuş, yağlı ve tatlı acı pul biber.',
       'Kahramanmaraş biberinden güneşte kurutulup öğütülen, hafif yağlı pul biber.',
       'Kahramanmaraş', 'Örnek Baharat', 'Kırmızı biber, tuz, bitkisel yağ',
       'Alerjen içermez.', 'Kapalı kapta, serin ve kuru yerde saklayınız.', 'Üretimden itibaren 12 ay', 1, 1
FROM categories c JOIN regions r ON r.slug = 'guneydogu-anadolu' WHERE c.slug = 'baharat-ve-kuruyemis';

INSERT IGNORE INTO products (category_id, region_id, name, slug, short_description, description, origin_text, producer_name, ingredients, allergen_info, storage_conditions, shelf_life_info, is_active, is_featured)
SELECT c.id, r.id, 'Üzüm Pekmezi', 'uzum-pekmezi',
       'Geleneksel kazan usulü, katkısız üzüm pekmezi.',
       'Ege üzümlerinden toprak ile arıtılarak kazanda kaynatılan geleneksel üzüm pekmezi.',
       'Manisa', 'Örnek Pekmezcilik', '%100 üzüm şırası',
       'Alerjen içermez.', 'Serin ve kuru yerde saklayınız; açıldıktan sonra buzdolabında saklayınız.', 'Üretimden itibaren 24 ay', 1, 0
FROM categories c JOIN regions r ON r.slug = 'ege' WHERE c.slug = 'recel-ve-pekmez';

INSERT IGNORE INTO products (category_id, region_id, name, slug, short_description, description, origin_text, producer_name, ingredients, allergen_info, storage_conditions, shelf_life_info, is_active, is_featured)
SELECT c.id, r.id, 'Ev Yapımı Tarhana', 'ev-yapimi-tarhana',
       'Yoğurt ve sebzelerle mayalanmış geleneksel tarhana.',
       'Geleneksel yöntemle yoğurt, domates ve biberle mayalanıp kurutulan ev yapımı tarhana.',
       'İç Anadolu', 'Örnek Ev Lezzetleri', 'Buğday unu, yoğurt, domates, biber, soğan, tuz, nane',
       'Gluten (buğday) ve süt ürünü içerir.', 'Kuru ve serin yerde, kapalı kapta saklayınız.', 'Üretimden itibaren 12 ay', 1, 0
FROM categories c JOIN regions r ON r.slug = 'ic-anadolu' WHERE c.slug = 'makarna-tarhana-eriste';

-- Varyantlar (SKU öneki DEMO-)
INSERT IGNORE INTO product_variants (product_id, sku, name, weight_grams, price, sale_price, stock_quantity, low_stock_threshold, is_default, sort_order)
SELECT p.id, v.sku, v.vname, v.weight_grams, v.price, v.sale_price, v.stock_quantity, v.low_stock_threshold, v.is_default, v.sort_order
FROM (
    SELECT 'karadeniz-cicek-bali' AS slug, 'DEMO-BAL-250' AS sku, '250 g' AS vname, 250 AS weight_grams, 189.90 AS price, CAST(NULL AS DECIMAL(12,2)) AS sale_price, 40 AS stock_quantity, 5 AS low_stock_threshold, 1 AS is_default, 1 AS sort_order
    UNION ALL SELECT 'karadeniz-cicek-bali', 'DEMO-BAL-450', '450 g', 450, 329.90, NULL, 25, 5, 0, 2
    UNION ALL SELECT 'karadeniz-cicek-bali', 'DEMO-BAL-850', '850 g', 850, 589.90, NULL, 12, 5, 0, 3
    UNION ALL SELECT 'ayvalik-sizma-zeytinyagi', 'DEMO-ZYG-500', '500 ml', 460, 249.90, NULL, 30, 5, 1, 1
    UNION ALL SELECT 'ayvalik-sizma-zeytinyagi', 'DEMO-ZYG-1000', '1 L', 920, 459.90, 399.90, 18, 5, 0, 2
    UNION ALL SELECT 'erzincan-tulum-peyniri', 'DEMO-PEY-500', '500 g', 500, 289.90, NULL, 3, 5, 1, 1
    UNION ALL SELECT 'erzincan-tulum-peyniri', 'DEMO-PEY-1000', '1 kg', 1000, 549.90, NULL, 14, 5, 0, 2
    UNION ALL SELECT 'maras-pul-biber', 'DEMO-BIB-100', '100 g', 100, 59.90, NULL, 60, 5, 1, 1
    UNION ALL SELECT 'maras-pul-biber', 'DEMO-BIB-250', '250 g', 250, 129.90, NULL, 45, 5, 0, 2
    UNION ALL SELECT 'maras-pul-biber', 'DEMO-BIB-500', '500 g', 500, 239.90, NULL, 22, 5, 0, 3
    UNION ALL SELECT 'uzum-pekmezi', 'DEMO-PEK-400', '400 g', 400, 119.90, NULL, 35, 5, 1, 1
    UNION ALL SELECT 'uzum-pekmezi', 'DEMO-PEK-800', '800 g', 800, 209.90, NULL, 20, 5, 0, 2
    UNION ALL SELECT 'ev-yapimi-tarhana', 'DEMO-TAR-500', '500 g', 500, 149.90, NULL, 0, 5, 1, 1
    UNION ALL SELECT 'ev-yapimi-tarhana', 'DEMO-TAR-1000', '1 kg', 1000, 269.90, NULL, 0, 5, 0, 2
) v
JOIN products p ON p.slug = v.slug;

-- Görseller (placeholder yollar)
INSERT INTO product_images (product_id, path, alt_text, sort_order, is_primary)
SELECT p.id, i.path, i.alt_text, i.sort_order, i.is_primary
FROM (
    SELECT 'karadeniz-cicek-bali' AS slug, '/assets/img/demo/karadeniz-cicek-bali-1.jpg' AS path, 'Karadeniz çiçek balı kavanoz' AS alt_text, 1 AS sort_order, 1 AS is_primary
    UNION ALL SELECT 'karadeniz-cicek-bali', '/assets/img/demo/karadeniz-cicek-bali-2.jpg', 'Karadeniz çiçek balı petek', 2, 0
    UNION ALL SELECT 'ayvalik-sizma-zeytinyagi', '/assets/img/demo/ayvalik-sizma-zeytinyagi-1.jpg', 'Ayvalık sızma zeytinyağı şişe', 1, 1
    UNION ALL SELECT 'erzincan-tulum-peyniri', '/assets/img/demo/erzincan-tulum-peyniri-1.jpg', 'Erzincan tulum peyniri', 1, 1
    UNION ALL SELECT 'maras-pul-biber', '/assets/img/demo/maras-pul-biber-1.jpg', 'Maraş pul biber', 1, 1
    UNION ALL SELECT 'uzum-pekmezi', '/assets/img/demo/uzum-pekmezi-1.jpg', 'Üzüm pekmezi kavanoz', 1, 1
    UNION ALL SELECT 'ev-yapimi-tarhana', '/assets/img/demo/ev-yapimi-tarhana-1.jpg', 'Ev yapımı tarhana', 1, 1
) i
JOIN products p ON p.slug = i.slug
WHERE NOT EXISTS (
    SELECT 1 FROM product_images x WHERE x.product_id = p.id AND x.path = i.path
);

-- Başlangıç stok hareketi (stok defteri tutarlılığı için)
INSERT INTO stock_movements (variant_id, user_id, type, quantity_change, quantity_after, reference_type, note)
SELECT v.id, NULL, 'initial', v.stock_quantity, v.stock_quantity, 'seed', 'Demo başlangıç stoğu'
FROM product_variants v
WHERE v.sku LIKE 'DEMO-%'
  AND NOT EXISTS (
      SELECT 1 FROM stock_movements s WHERE s.variant_id = v.id AND s.type = 'initial'
  );

-- ---------------------------------------------------------------------
-- DEMO VERİYİ TEMİZLE (YORUM SATIRI; canlıya çıkmadan önce yorumu kaldırıp çalıştır)
-- Sıra önemli: stok hareketleri variant FK'sini RESTRICT ile tutar, önce onlar silinir.
-- ---------------------------------------------------------------------
-- DELETE sm FROM stock_movements sm JOIN product_variants v ON v.id = sm.variant_id WHERE v.sku LIKE 'DEMO-%';
-- DELETE FROM products WHERE slug IN ('karadeniz-cicek-bali','ayvalik-sizma-zeytinyagi','erzincan-tulum-peyniri','maras-pul-biber','uzum-pekmezi','ev-yapimi-tarhana');

-- =====================================================================
-- TEST BLOĞU (yorum satırı; TEST komutuyla çalıştırılır)
-- =====================================================================
-- #TEST
-- SELECT (SELECT COUNT(*) FROM settings) AS settings, (SELECT COUNT(*) FROM shipping_rates) AS rates, (SELECT COUNT(*) FROM regions) AS regions, (SELECT COUNT(*) FROM categories) AS categories, (SELECT COUNT(*) FROM menus) AS menus, (SELECT COUNT(*) FROM menu_items) AS menu_items, (SELECT COUNT(*) FROM pages) AS pages, (SELECT COUNT(*) FROM products) AS products, (SELECT COUNT(*) FROM product_variants) AS variants;
-- SELECT * FROM v_low_stock;
-- SELECT m.location, p.label AS parent_label, c.label AS child_label, c.sort_order FROM menus m JOIN menu_items c ON c.menu_id = m.id LEFT JOIN menu_items p ON p.id = c.parent_id ORDER BY m.location, c.parent_id, c.sort_order;
-- SELECT p.name, v.sku, v.name AS variant, v.price, v.sale_price, v.stock_quantity FROM products p JOIN product_variants v ON v.product_id = p.id WHERE v.sku LIKE 'DEMO-%' ORDER BY p.id, v.sort_order;