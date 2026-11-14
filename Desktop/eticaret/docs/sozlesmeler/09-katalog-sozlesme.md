# 09-katalog-sozlesme.md
Oturum 6: Katalog (vitrin). Kaynak: yazılan GERÇEK kod. Modeller instance çağrılır; SQL yok.

## (1) Dosya, sınıf, public metot imzaları
- app/Services/CatalogService.php: final CatalogService | __construct(Product $products=new Product(), Category $categories=new Category())
  - listProducts(array $query, ?int $categoryId=null, ?int $regionId=null): array{products,filters,sort} | Görev: GET sorgusunu beyaz listeyle normalize edip Product::listActive çağırır (perPage 12).
  - productDetail(string $slug): ?array{product,images,variants,default_variant_id,related,category_trail} | Görev: Product::findActiveBySlug + related(4); varyant JSON dizisi (id,name,sku,final_price,price,on_sale,in_stock,low,weight_grams); varyantsız ürün null.
  - suggest(string $q): array (list{name,slug,image}) | Görev: Product::search ile en çok 6 öneri.
  - categoryTrail(array $category): array (list{id,name,slug}) | Görev: Category::breadcrumb + geçerli kategori garantisi. [EK]
  - static buildQuery(array $filters, string $sort): array | Görev: filtrelerden URL parametreleri üretir. [EK]
  - static normalizeQuery(mixed $raw): string | Görev: arama metni temizliği, <2 karakterde ''. [EK]
- app/Controllers/Front/ProductController.php: final | index(Request): Response | show(Request, string $slug): Response | byRegion(Request, string $slug): Response | Görev: ürün liste/detay/yöre.
- app/Controllers/Front/CategoryController.php: final | show(Request, string $slug): Response | Görev: kategori sayfası (alt kategori çipleri, breadcrumb, category_top banner, liste).
- app/Controllers/Front/SearchController.php: final | index(Request): Response | suggest(Request): Response | Görev: arama sayfası ve öneri JSON'u.
- app/Controllers/Front/SitemapController.php: final | index(Request): Response | Görev: text/xml site haritası.
- resources/views/front/products/index.php: liste (ürün, kategori, yöre ortak). Girdi: heading, description, category, subcategories, banners, crumbs, products, pager, filters, sort, queryParams, baseUrl, categoryTree, regions, activeCategoryId, activeRegionSlug, regionLocked.
- resources/views/front/products/_filters.php: ortak filtre paneli. Ek girdi: idPrefix (desk|mob).
- resources/views/front/products/show.php: detay. Girdi: product, images, variants, defaultVariantId, related, crumbs, freeThreshold.
- resources/views/front/search.php: girdi: q, rawQ, tooShort, products, pager, filters, sort, queryParams, suggestCategories, crumbs.
- public/assets/js/catalog.js: window'a global eklemez. [data-product-page][data-variants][data-default-variant], [data-variant-button], [data-qty-input|minus|plus], [data-price-final|old|badge], [data-sku], [data-weight], [data-stock-text], [data-add-label], [data-main-add], [data-sticky-bar], [data-gallery*]; liste: [data-catalog-list], [data-sort-form|select|submit], [data-filter-form], [data-price-input], [data-price-error], [data-skip-empty].
- public/assets/js/search.js: #ymSearch (veya form[data-search-autocomplete]) içindeki input[name=q] için combobox; global yok.
- public/robots.txt: statik.

## (2) Tablolar ve permission kodları
- Yalnızca model üzerinden: products, product_variants, product_images, categories, regions, banners (category_top), pages, settings.
- Permission kodu kullanılmaz (hepsi herkese açık vitrin).

## (3) JSON uç noktaları
- GET /ara/oneri?q=metin | CSRF gerekmez (GET) | q <2 karakter ise boş liste.
  Yanıt 200: {"ok":true,"data":{"items":[{"name":"","slug":"","image":null|"/yol","url":"/urun/slug"}]}}
  Hata biçimi çekirdek sözleşmesi gibi; throttle aşımı 429 + Retry-After.
- GET /sitemap.xml: XML (urlset); JSON değil.

## (4) ROUTE SATIRLARI
GET /urunler -> ProductController@index | global
GET /urun/{slug} -> ProductController@show | global
GET /kategori/{slug} -> CategoryController@show | global
GET /yore/{slug} -> ProductController@byRegion | global
GET /ara -> SearchController@index | throttle:60,60
GET /ara/oneri -> SearchController@suggest | throttle:60,60
GET /sitemap.xml -> SitemapController@index | global
(Not: /ara'ya throttle güvenlik kuralı 13 gereği eklendi.)

## (5) .env / settings anahtarları
- settings: site.name (başlık eki), shipping.free_threshold (ürün detay güven noktası ve teslimat sekmesi).
- config: app.url (SitemapController, Config::get).
- .env: yeni anahtar yok.

## Sonraki oturumlar için notlar
- Filtre sorgu parametreleri: sort, page, q, min_price, max_price, in_stock, indirimli, yore (slug). Kategori ve yöre sayfaları yol ile gelir.
- Layout'a $scripts ile ['catalog.js','search.js'] verilir. search.js header aramasının her sayfada çalışması için layout'a global eklenmeli (şimdilik yalnızca katalog sayfalarında yüklenir).
- Sepet oturumu POST /sepet/ekle'de {"variant_id":int,"quantity":int} bekler (app.js [data-add-to-cart]); catalog.js bu butonların data-variant-id ve data-quantity değerlerini günceller.
- Ürün açıklaması ham metin kabul edilir (nl2br(e())); HTML alanı değildir.
- Sitemap Product::all(['is_active'=>1], null, 50000) kullanır; pasif kategorideki ürünler de listelenebilir.
- robots.txt'deki Sitemap satırı üretimde alan adıyla değiştirilmeli.
- Varsayımlar: Category::breadcrumb satırları id/name/slug içerir; activeTree düğümleri 'children' taşır; BaseController'ın yapıcı parametresi yoktur.