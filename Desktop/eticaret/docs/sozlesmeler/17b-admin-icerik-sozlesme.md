# 17b-admin-icerik-sozlesme.md
Oturum 14b: Admin sayfa/ayar/mesaj. Kaynak: yazılan GERÇEK kod. Controller'lar final, AdminBaseController'dan türer; modeller instance çağrılır (new Page() vb., lazy).

## (1) Dosya, sınıf, public metot imzaları
- app/Services/ContentSanitizer.php: final ContentSanitizer | clean(string $html): string. Görev: HTMLPurifier + DOM ikinci geçiş (img src yalnızca /uploads/ veya https, "//" href düşer, harici bağlantıya rel="noopener noreferrer"); önbellek storage/cache/purifier. HTMLPurifier yoksa RuntimeException (loglanır).
- app/Controllers/Admin/PageAdminController.php: final | index(Request): Response | create(Request): Response | store(Request): Response | edit(Request, int $id): Response | update(Request, int $id): Response | toggle(Request, int $id): Response | destroy(Request, int $id): Response | preview(Request): Response. Görev: CMS sayfa yönetimi; system_key'li sayfa silinemez, slug'ı değişmez; içerik kayıttan önce clean().
- app/Controllers/Admin/SettingAdminController.php: final | index(Request): Response | update(Request): Response. Görev: gruplu ayar formu; tip/biçim doğrulaması, tanımsız anahtar reddi (+SecurityEvent 'setting_unknown_key' warning), en az bir ödeme yöntemi açık, havale açıkken banka/hesap sahibi/IBAN zorunlu, tek transaction, değişen anahtarlar audit.
- app/Controllers/Admin/ContactMessageAdminController.php: final | index(Request): Response | show(Request, int $id): Response | setStatus(Request, int $id): Response. Görev: mesaj listesi, detay (new ise otomatik read), durum güncelleme (new|read|replied).
- resources/views/admin/pages/index.php: girdi $pages,$pager,$status. Yasal sayfa rozeti, yayın/sil formları.
- resources/views/admin/pages/form.php: girdi $page,$isEdit,$isSystem,$formAction (+ controller $pageScripts). Araç çubuklu textarea + önizleme paneli.
- resources/views/admin/settings/index.php: girdi $tabs[{id,label,fields[]}]. Sekmeli form, yardım metinleri, IBAN uyarısı. Alan adı settings[anahtar].
- resources/views/admin/messages/index.php: girdi $messages,$pager,$status,$newCount (okunmamış kalın).
- resources/views/admin/messages/show.php: girdi $message,$mailto,$ip. İçerik nl2br(e()).
- public/assets/js/admin-page-editor.js: IIFE, global yok. [data-page-editor][data-preview-url][data-csrf], [data-editor-cmd=bold|italic|h2|h3|ul|ol|quote|link], [data-editor-input], [data-editor-preview], [data-editor-panel], [data-editor-status]. Önizleme HTML'i innerHTML değil DOMParser ile aktarılır; kendi fetch'ini kullanır (Admin.fetchJson'a bağımlı değil).

## (2) Tablolar ve permission kodları
Tablolar (yalnızca model üzerinden): pages, settings, contact_messages, audit_logs (AdminBaseController::audit), security_events (setting_unknown_key, permission_denied).
Permission: page.manage (sayfa uçları + önizleme) | setting.manage (ayarlar; yalnızca admin) | customer.view (mesajlar). Her metot require() çağırır; route'larda perm: middleware ayrıca vardır.
Audit eylemleri: page.create, page.update, page.toggle, page.delete, setting.update (old/new yalnızca değişen anahtarlar), contact_message.status. Sayfa audit'inde içerik yerine content_length yazılır.

## (3) JSON uç noktaları
- POST /yonetim/sayfalar/onizle (başlık X-CSRF-Token, gövde JSON) istek: {"content":"<html>"} | yanıt 200: {"ok":true,"data":{"html":"<temizlenmiş html>"}} | 422: {"ok":false,"error":{"code":"validation","message":"İçerik çok uzun.","errors":{"content":"..."}}} (200000 bayt sınırı) | 403/419/429 çekirdek biçimi.
- Diğer uçlar form/redirect akışıdır; doğrulama hatasında ValidationException (flash errors+old, geri yönlendirme). Ayar hataları errors anahtarı = ayar anahtarı (örn. "bank_transfer.iban"), genel hata "form"; old = {"settings":{anahtar:değer}}.

## (4) ROUTE SATIRLARI
Grup: prefix /yonetim | middleware [auth, perm:dashboard.view]
GET /yonetim/sayfalar -> App\Controllers\Admin\PageAdminController@index | auth, perm:page.manage
GET /yonetim/sayfalar/yeni -> App\Controllers\Admin\PageAdminController@create | auth, perm:page.manage
POST /yonetim/sayfalar -> App\Controllers\Admin\PageAdminController@store | auth, perm:page.manage
POST /yonetim/sayfalar/onizle -> App\Controllers\Admin\PageAdminController@preview | auth, perm:page.manage, throttle:60,60
GET /yonetim/sayfalar/{id}/duzenle -> App\Controllers\Admin\PageAdminController@edit | auth, perm:page.manage
POST /yonetim/sayfalar/{id}/guncelle -> App\Controllers\Admin\PageAdminController@update | auth, perm:page.manage
POST /yonetim/sayfalar/{id}/durum -> App\Controllers\Admin\PageAdminController@toggle | auth, perm:page.manage
POST /yonetim/sayfalar/{id}/sil -> App\Controllers\Admin\PageAdminController@destroy | auth, perm:page.manage
GET /yonetim/ayarlar -> App\Controllers\Admin\SettingAdminController@index | auth, perm:setting.manage
POST /yonetim/ayarlar -> App\Controllers\Admin\SettingAdminController@update | auth, perm:setting.manage, throttle:20,60
GET /yonetim/mesajlar -> App\Controllers\Admin\ContactMessageAdminController@index | auth, perm:customer.view
GET /yonetim/mesajlar/{id} -> App\Controllers\Admin\ContactMessageAdminController@show | auth, perm:customer.view
POST /yonetim/mesajlar/{id}/durum -> App\Controllers\Admin\ContactMessageAdminController@setStatus | auth, perm:customer.view
(Rota sırası: /sayfalar/yeni ve /sayfalar/onizle, {id} rotalarından önce. Bu dosyadaki rotalar routes/admin.php'ye eklenmeli.)

## (5) .env / settings anahtarları
- settings (okunur/yazılır, tümü tablodaki mevcut satırlar): site.name, site.tagline, mail.from_name, contact.email/phone/whatsapp/address, social.instagram/facebook, shipping.free_threshold, card.enabled, cod.enabled, cod.fee, bank_transfer.enabled/bank_name/account_holder/iban/note, order.card_expiry_minutes, order.bank_expiry_hours, order.min_amount, stock.default_low_threshold, seo.default_title/default_description, legal.company_title/tax_office/tax_number/mersis, tax.prices_include_vat. Sekme eşlemesi anahtar önekine göredir (Genel: site.,mail. | İletişim: contact. | Sosyal: social. | Kargo: shipping. | Ödeme ve Havale: card.,cod.,bank_transfer. | Sipariş: order.,stock. | SEO: seo. | Yasal: legal.,tax.).
- Doğrulama sınırları: decimal 0–9999999.99; order.card_expiry_minutes 5–1440; order.bank_expiry_hours 1–720; IBAN ^TR\d{24}$ (boşluksuz, büyük harf saklanır); whatsapp 10–15 hane; legal.tax_number 10/11 hane; legal.mersis 16 hane.
- .env: PAYTR_MERCHANT_ID yalnızca okunur (kart açılırken boşsa uyarı flash'ı). Config: composer paketi ezyang/htmlpurifier gerekli.

## SAPMA / VARSAYIMLAR
- Sayfa/ayar/mesaj controller'ları Validator kullanmaz; elle doğrulayıp ValidationException(errors, old) fırlatır (Validator kural sözdizimi sözleşmede yoktu).
- Setting::allByGroup() dönüş şekli sözleşmede açık değildi: grup=>satırlar veya düz liste kabul edilir; satırlarda key_name, value, value_type, label beklenir.
- $pageScripts değerleri asset() altı göreli yol olarak verildi ('js/admin-page-editor.js'); layouts.admin tam URL bekliyorsa controller'daki iki dizi güncellenmeli.
- Eklenen rotalar: /sayfalar/yeni, /{id}/duzenle, /{id}/guncelle, /{id}/durum, /{id}/sil; mesajlarda /{id} ve /{id}/durum. Mesaj durumu 'new' geri alınabilir (Okunmadı işaretle).
- Sayfa listesinde arama yok (BaseModel::paginate LIKE kaçışı belirsiz); yalnızca durum filtresi. Sıralama id DESC.
- Pasif yapılan yasal sayfalar toggle ile kapatılabilir (kısıt yalnızca silme ve slug için).