# 14-admin-sozlesme.md

Oturum: [11: Admin altyapı + dashboard]. Kaynak: yazılan GERÇEK kod. routes/admin.php bu oturumda yazılmadı.

## (1) Dosya, sınıf, public/protected metot imzaları
- app/Controllers/Admin/AdminBaseController.php — abstract AdminBaseController extends BaseController. Görev: admin controller tabanı (yetki, denetim, sayfalama).
  - protected view(string $name, array $d=[], string $layout='layouts.admin'): Response
  - protected require(string $permission): void (can() yoksa SecurityEvent 'permission_denied' + HttpException 403)
  - protected audit(string $action, ?string $entityType=null, ?int $entityId=null, ?array $old=null, ?array $new=null): void (AuditLog::write; AuditService kullanılmadı)
  - protected currentPage(Request $request): int | perPage(): int (25)
  - protected pager(array $result, Request $request): array{total,page,per_page,pages,path,query}
- app/Services/ReportService.php — final ReportService (ctor: Order, OrderItem, ProductVariant, Payment, ContactMessage, User, Role; varsayılan new). Görev: dashboard/rapor verisi.
  - dashboardStats(): array{flags{report,orders,stock,payments,customers}, revenue?{today,yesterday,last7,last30,orders_30}, avg_basket?, orders?{pending_payment,to_prepare,shipped}, new_users?, pending_bank?, low_stock?, new_messages?} (yetkisiz alan null)
  - salesChart(int $days): array{labels: list<string>, revenue: list<string "1234.50">, orders: list<int>} (report.view yoksa boş diziler; days 1..90)
  - topProducts(int $days, int $limit): list<array{variant_id,sku,product_name,variant_name,total_quantity,total_revenue}> (report.view yoksa [])
  - lowStock(int $limit): list<array> (stok/ürün yetkisi yoksa [])
  - recentOrders(int $limit): list<array> (order.view yoksa [])
- app/Controllers/Admin/DashboardController.php — final DashboardController extends AdminBaseController. index(Request $request): Response. Görev: dashboard sayfası.
- resources/views/layouts/admin.php — admin iskeleti. Değişkenler: $content, $title, $pageScripts (asset yolları dizisi).
- resources/views/admin/dashboard.php — dashboard görünümü. Değişkenler: $stats, $chartJson, $topProducts, $lowStock, $recentOrders.
- resources/views/partials/admin-pagination.php — değişken $pager (AdminBaseController::pager çıktısı). Kullanım: View::partial('partials.admin-pagination', ['pager'=>$pager]).
- public/assets/css/admin.css — yönetim teması. Sınıflar: status-badge status-{durum}, kpi-card kpi-accent-{primary|warning|info|purple|danger|success}, table-cards (td data-label), filter-bar, admin-card/-header/-body(.flush), chart-box, empty-state, page-header.
- public/assets/js/admin.js — window.Admin: fetchJson(url,{method,body,headers}): Promise<data> (X-CSRF-Token, X-Requested-With; hata AdminError{status,code,errors}) | AdminError | confirmAction(message): bool | toast(message,type,timeout) | markClean() | dateRange{toISODate,preset,bind} | sidebar{apply}. Öznitelikler: [data-confirm], form[data-dirty-guard], [data-date-range][data-range-submit] + [data-range-from]/[data-range-to]/[data-range-preset=today|yesterday|7|30|month], [data-admin-sidebar-toggle]. Sidebar durumu localStorage 'admin.sidebar'.
- public/assets/js/admin-dashboard.js — canvas#salesChart[data-chart] ile Chart.js (bar ciro + line sipariş); boşsa #salesChartEmpty gösterilir.

## (2) Tablolar ve permission kodları
Tablolar (yalnızca model üzerinden): orders, order_items, users, roles, payments, contact_messages, v_low_stock, audit_logs (yazma), security_events (yazma).
Permission: dashboard.view (sayfa) | report.view (ciro, grafik, en çok satanlar) | order.view (son siparişler, sipariş sayıları) | stock.manage VEYA product.view (kritik stok) | payment.confirm (bekleyen havale) | customer.view (yeni üye, yeni mesaj).
Menü filtre kodları: Dashboard dashboard.view | Siparişler order.view | Müşteriler customer.view | Kuponlar coupon.manage | Ürünler product.view | Kategoriler category.manage | Stok stock.manage | Banner banner.manage | Menüler menu.manage | Sayfalar page.manage | Duyurular banner.manage (ayrı kod yok) | Mesajlar customer.view (ayrı kod yok) | Kullanıcılar user.manage | Roller role.manage | Denetim audit.view | Ayarlar setting.manage.

## (3) JSON uç noktaları
- Yok. Dashboard sunucu tarafı render; grafik verisi data-chart JSON: {"labels":["dd.mm"],"revenue":["1234.50"],"orders":[0]}.
- Admin AJAX sözleşmesi (admin.js): istek JSON/FormData + X-CSRF-Token; yanıt {"ok":true,"data":{}} veya {"ok":false,"error":{"code","message","errors"}}.

## (4) ROUTE SATIRLARI
Grup: prefix /yonetim | middleware [auth, perm:dashboard.view]
GET /yonetim -> App\Controllers\Admin\DashboardController@index | auth, perm:dashboard.view
Diğer admin controller'ları alt gruplarında kendi perm'lerini ekler; her metot ayrıca require() çağırır.
Dashboard'un verdiği bağlantılar (sonraki oturumlar uygulamalı): /yonetim/siparisler?status=pending_payment|paid|shipped; /yonetim/siparisler?status=pending_payment&payment_method=bank_transfer; /yonetim/stok?low=1; /yonetim/mesajlar?status=new; /yonetim/musteriler; /yonetim/siparisler/{orderNumber}.
Layout formu: POST /cikis (CSRF alanlı).

## (5) .env / settings anahtarları
- config('app.name') (site adı, başlıkta). .env/settings anahtarı eklenmedi.

## Sonraki oturumlar için notlar
- Vendor yolları: /assets/vendor/bootstrap/css/bootstrap.min.css, /assets/vendor/bootstrap/js/bootstrap.bundle.min.js, /assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css, /assets/vendor/chartjs/chart.umd.js.
- Varsayımlar: Order::salesByDay ödenmiş ve iptal/iade dışı döner; v_low_stock sütunları product_name, variant_name, sku, stock_quantity, low_stock_threshold; orders.grand_total; payment_method değeri bank_transfer; SecurityEvent severity 'warning' geçerli.
- Yeni admin görünümleri sayfalama için $pager'ı AdminBaseController::pager() ile üretmeli.
- flash_messages() [{type,message}] veya tip=>mesaj biçimini layout ikisini de okur.