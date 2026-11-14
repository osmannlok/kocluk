16-admin-siparis-sozlesme.md

Oturum 13: Admin sipariş/kargo/havale + müşteri (salt okunur). Kaynak: yazılan GERÇEK kod. Bu oturumda YAZILMAYANLAR (sonraki mesajlarda gelecek): admin/orders/show.php, admin/orders/print.php, public/assets/js/admin-orders.js, emails/order-cancelled.php, admin/customers/index.php, admin/customers/show.php. routes/admin.php bilerek yazılmadı (Oturum 16'da birleşecek).

(1) Dosya, sınıf, public metot imzaları
config/carriers.php: dizi döndürür. kod => ['name'=>string,'tracking_url'=>?string]. Kodlar: yurtici, aras, mng, ptt, surat, ups, hepsijet, other. Tüm tracking_url değerleri null (“DOĞRULANMADI”); {tracking} yer tutucusu kullanıcı doğrulayınca doldurulacak.
app/Services/ShipmentService.php: final ShipmentService
__construct(?OrderStatusService $statusService = null, ?OrderNotificationService $notifier = null)
addShipment(int $orderId, string $carrierKey, string $trackingNumber, ?string $note, int $adminId): int
updateShipment(int $shipmentId, string $carrierKey, string $trackingNumber, ?string $note, int $adminId): bool
markDelivered(int $orderId, int $adminId): void
Görev: kargo kaydı + durum geçişi. addShipment tek transaction (sipariş FOR UPDATE, yalnızca preparing); e-posta transaction dışı. Takip no ^[A-Za-z0-9-]{6,40}$. shipments.carrier = görünen ad. tracking_url yalnızca config şablonundan, rawurlencode ile üretilir; https:// değilse veya şablon null ise null. Hatalar: ValidationException (carrier, tracking_number, note), DomainException, HttpException 404.
app/Controllers/Admin/OrderAdminController.php: final OrderAdminController extends AdminBaseController
index(Request $request): Response
showByNumber(Request $request, string $orderNumber): Response (sayısal id’ye 302)
show(Request $request, int $id): Response
updateStatus(Request $request, int $id): Response
addShipment(Request $request, int $id): Response
updateShipment(Request $request, int $id, int $shipmentId): Response
markDelivered(Request $request, int $id): Response
confirmBank(Request $request, int $id): Response
markCodPaid(Request $request, int $id): Response
print(Request $request, int $id): Response
exportCsv(Request $request): Response
Görev: sipariş listesi/detay/durum/kargo/ödeme onayı/yazdır/CSV.
app/Controllers/Admin/CustomerAdminController.php: final CustomerAdminController extends AdminBaseController
index(Request $request): Response
show(Request $request, int $id): Response
Görev: salt okunur müşteri listesi/detayı.
resources/views/admin/orders/index.php: girdi $orders,$pager,$filters,$tab,$statusCounts,$bankPending,$statuses,$methods,$paymentStates,$title. Sekmeler, filtre çubuğu, mobil kart tablo, CSV düğmesi (can('report.view')).
resources/views/emails/order-shipped.php: girdi $name,$order,$shipment. Takip düğmesi yalnızca https:// ise. Satır içi stil yalnızca e-postada.

View girdileri, henüz yazılmamış sayfalar için controller’ın GERÇEKTEN geçirdikleri:

admin.orders.show: $title,$order,$customer,$items,$history,$payments,$shipments,$allowedNext,$statusTargets,$carriers.
admin.orders.print (layoutsuz): $title,$order,$customer,$items,$shipments,$siteName.
admin.customers.index: $title,$customers,$pager,$filters{q,status},$statuses.
admin.customers.show: $title,$customer,$addresses,$orders,$pager.

$order içinde shipping/billing çözülmüş dizi (TC maskeli, son 2 hane), consent_ip/ip_address metin; ham adres JSON’u yok. Müşteri sayfasında tc_identity_no controller’da maskelenir (3+****+2); password_hash/last_login_ip çıkarılır.

(2) Tablolar ve permission kodları
Tablolar (yalnızca model/servis üzerinden): orders, order_items, order_status_history, payments, shipments, users, roles, addresses, audit_logs.
Permission: order.view, report.view, order.update_status, order.cancel, order.refund, shipment.manage, payment.confirm, customer.view.
Metot başına:
index/showByNumber/show/print → order.view
exportCsv → order.view VE report.view (ikisi de require edilir)
updateStatus → order.update_status; hedef cancelled için ek order.cancel, refunded için ek order.refund
addShipment/updateShipment/markDelivered → shipment.manage
confirmBank/markCodPaid → payment.confirm
CustomerAdminController tüm metotlar → customer.view
AuditService::log eylemleri: order.status_change, order.export_csv, shipment.create, shipment.update, shipment.delivered. Havale/kapıda onay denetimi PaymentService içinde (‘payment.confirm_bank_transfer’, ‘payment.cod_collected’).
(3) JSON uç noktaları

JSON ucu YOK. Tüm yazma uçları klasik POST form (CSRF _token) olup işlem sonunda /yonetim/siparisler/{id} adresine 302 yapar. Mesajlar flash ile: success, error (DomainException/PaymentException), warning (kart iadesi hatırlatması).

POST durum: to (zorunlu), note (en çok 255; cancelled/refunded için min 5). Formdan yalnızca preparing|cancelled|refunded hedefleri ve allowedNext kesişimi kabul edilir; paid → havale onayı/PayTR, shipped → kargo formu, delivered → teslim ucu. Geçersiz → 422 ValidationException (to/note + old).
POST kargo ekle/güncelle: carrier (config kodu), tracking_number, note?. Yönetici URL gönderemez.
POST havale-onay: note?. POST teslim ve kapıda-odeme-alindi: gövde yok.
GET disa-aktar yanıtı: text/csv; charset=UTF-8, BOM, Cache-Control: no-store, en çok 5000 satır. Sütunlar: Sipariş No, Tarih, Durum, Ödeme Yöntemi, Ödeme Durumu, Müşteri, E-posta, Ara Toplam, İndirim, Kargo, Kapıda Ödeme Ücreti, Genel Toplam, Kupon, Ödeme Tarihi. İlk karakteri = + - @ TAB CR olan hücrenin başına ' eklenir. TC/IBAN/adres yok.
Liste sorguları (GET): q,status,payment_method,payment_status,date_from,date_to,tab(all|bank_pending|to_prepare|shipped),page. Sekme filtreleri elle girilenin üzerine yazar. Müşteri listesi: q,status,page.
(4) ROUTE SATIRLARI

Grup prefix /yonetim; hepsi auth altında. Controller: App\Controllers\Admin\*.
GET /yonetim/siparisler -> OrderAdminController@index | auth, perm:order.view
GET /yonetim/siparisler/disa-aktar -> OrderAdminController@exportCsv | auth, perm:order.view, perm:report.view
GET /yonetim/siparisler/{id}/yazdir -> OrderAdminController@print | auth, perm:order.view
GET /yonetim/siparisler/{id} -> OrderAdminController@show | auth, perm:order.view
GET /yonetim/siparisler/{orderNumber} -> OrderAdminController@showByNumber | auth, perm:order.view
POST /yonetim/siparisler/{id}/durum -> OrderAdminController@updateStatus | auth, perm:order.update_status
POST /yonetim/siparisler/{id}/kargo -> OrderAdminController@addShipment | auth, perm:shipment.manage
POST /yonetim/siparisler/{id}/kargo/{shipmentId}/guncelle -> OrderAdminController@updateShipment | auth, perm:shipment.manage
POST /yonetim/siparisler/{id}/teslim -> OrderAdminController@markDelivered | auth, perm:shipment.manage
POST /yonetim/siparisler/{id}/havale-onay -> OrderAdminController@confirmBank | auth, perm:payment.confirm
POST /yonetim/siparisler/{id}/kapida-odeme-alindi -> OrderAdminController@markCodPaid | auth, perm:payment.confirm
GET /yonetim/musteriler -> CustomerAdminController@index | auth, perm:customer.view
GET /yonetim/musteriler/{id} -> CustomerAdminController@show | auth, perm:customer.view

Sıralama notları (routes/admin.php birleştirilirken):

disa-aktar rotası {orderNumber} ve {id} rotalarından ÖNCE tanımlanmalı.
{id} (\d+) {orderNumber}’dan önce gelmeli; {orderNumber} için sayısal olmayan kısıt (örn. {orderNumber:[A-Za-z0-9-]+}) verilmeli.
Dashboard bağlantıları (/yonetim/siparisler?status=...&payment_method=..., /yonetim/musteriler, /yonetim/siparisler/{orderNumber}) bu rotalarla karşılanır.
perm:a,b “herhangi biri” demektir; exportCsv için iki ayrı perm: girdisi (AND) kullanıldı.
(5) .env / settings anahtarları
.env: yok. settings: yok.
config: carriers (config/carriers.php), app.name (yazdırma sayfası $siteName).
Oturum/flash: flash türleri success, error, warning.
Sapmalar ve açık konular
grep çıktısında OrderNotificationService boş döndü: sendShipped/sendCancelled servisin şablonlara GERÇEKTEN ne geçirdiği DOĞRULANMADI. order-shipped.php onaylanan $name,$order,$shipment kümesine göre yazıldı. Servis farklı değişken geçiriyorsa servis değil şablon uyarlanmalı. order-cancelled.php ve order-shipped/cancelled e-posta gönderimleri bu doğrulamaya bağlı.
carriers.php’deki tüm tracking_url değerleri null: doğrulanana kadar e-postada düğme yerine takip numarası metni gösterilir.
updateStatus formu paid/shipped/delivered hedeflerini bilerek kapatır; bu geçişler yalnızca PaymentService / ShipmentService uçlarından yapılır.
Müşteri listesi User::searchAdmin ile role_id (Role::findByName(‘customer’)) ve status filtresi kullanır; model değişikliği gerekmedi. SAFE_COLUMNS içeriği ve password_hash’in SELECT’e girmediği doğrulanmadı; controller savunma amaçlı unset eder.
Kart ödemesinin gerçek iadesi PayTR panelinden yapılır (iptal/iade sonrası warning flash).
index view’ı admin.css’te status-badge status-{durum}, table-cards, filter-bar sınıflarını varsayar; print sınıfları henüz admin.css’e eklenmedi.