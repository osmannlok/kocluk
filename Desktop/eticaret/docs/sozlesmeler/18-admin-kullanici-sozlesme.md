# 18-admin-kullanici-sozlesme.md

Oturum: [15-Admin kullanıcı/rol/log]. Kaynak: yazılan GERÇEK kod. routes/admin.php bu oturumda yazılmadı.

## (1) Dosya, sınıf, public metot imzaları
- app/Controllers/Admin/UserAdminController.php: final UserAdminController extends AdminBaseController. Görev: kullanıcı yönetimi (user.manage).
  - index(Request $request): Response | create(Request $request): Response | store(Request $request): Response
  - edit(Request $request, int $id): Response | update(Request $request, int $id): Response
  - suspend(Request $request, int $id): Response | activate(Request $request, int $id): Response
  - unlock(Request $request, int $id): Response | forceLogout(Request $request, int $id): Response | sendResetLink(Request $request, int $id): Response
- app/Controllers/Admin/RoleAdminController.php: final RoleAdminController extends AdminBaseController. Görev: rol yetki matrisi (role.manage).
  - index(Request $request): Response | edit(Request $request, int $id): Response | update(Request $request, int $id): Response
- app/Controllers/Admin/AuditLogController.php: final AuditLogController extends AdminBaseController. Görev: denetim + güvenlik olayı okuma/CSV (audit.view).
  - index(Request $request): Response | show(Request $request, int $id): Response (JSON) | securityEvents(Request $request): Response
  - export(Request $request): Response (CSV) | exportSecurity(Request $request): Response (CSV)
- resources/views/admin/users/index.php ($users,$pager,$filters,$roles,$statuses,$currentUserId) | users/form.php ($user ?array,$roles,$statuses,$isSelf,$isLastPrivileged,$isLocked)
- resources/views/admin/roles/index.php ($roles[id,name,label,description,user_count,permission_count,is_protected],$totalPermissions) | roles/edit.php ($role,$groups,$selectedCodes,$isLocked)
- resources/views/admin/audit/index.php ($logs[+diff,has_values],$pager,$filters) | audit/security.php ($events,$pager,$filters,$summary{critical,warning,total},$severities)
- Yeni model/servis yok. Mevcutlar kullanıldı: User, Role, Permission, UserSession, AuditLog, SecurityEvent, AuthService.

## (2) Tablolar ve permission kodları
Tablolar (model üzerinden): users, roles, role_permissions, permissions, sessions, audit_logs, security_events.
Permission: user.manage (kullanıcı ekranları) | role.manage (roller) | audit.view (denetim, güvenlik olayları, CSV).
- "Yetkili rol" = user.manage yetkisi olan rol (rol adı sabitlenmez). Korumalı rol: roles.name = admin (yalnızca RoleAdminController).
- Audit eylemleri: user.create, user.update, user.suspend, user.activate, user.unlock, user.force_logout, user.reset_link_sent, role.permissions_update, audit.export, audit.export_security.
- SecurityEvent türleri: user_role_changed (warning), privileged_user_created (warning), admin_guard_blocked (warning), role_permissions_changed (warning; hassas yetki eklenirse critical), role_invalid_permission (warning), admin_role_reduction_blocked (critical).
- Hassas yetkiler: user.manage, role.manage, setting.manage, audit.view, order.refund, product.delete.
- Korumalar: kendi rol/durum değişimi yok; son aktif yetkili hesap askıya alınamaz/rolü düşürülemez; rol/durum değişiminde hedef oturumları silinir; admin rolü azaltılamaz; permission id beyaz liste.
- Kullanıcı silme ekranı yok (soft delete UI kapsam dışı).

## (3) JSON uç noktaları
- GET /yonetim/denetim/{id} (audit.view): istek yok; yanıt {"ok":true,"data":{"id":int,"created_at":str,"user":{"id","name","email"}|null,"action":str,"entity_type":str|null,"entity_id":int|null,"ip":str,"user_agent":str|null,"diff":[{"field","old","new"}]}}; 404 {"ok":false,"error":{"code":"not_found",...}}.
- Diğer uçlar HTML/redirect (form POST + flash). Hata biçimi ErrorHandler standardı (422 validation, 403 forbidden, 419 csrf).
- CSV: GET .../disa-aktar -> text/csv (UTF-8 BOM, en çok 5000 satır, =,+,-,@,TAB,CR ile başlayan hücrelere ' öneki).

## (4) ROUTE SATIRLARI
GET /yonetim/kullanicilar -> UserAdminController@index | auth, perm:user.manage
GET /yonetim/kullanicilar/olustur -> UserAdminController@create | auth, perm:user.manage
POST /yonetim/kullanicilar -> UserAdminController@store | auth, perm:user.manage, throttle:10,60
GET /yonetim/kullanicilar/{id}/duzenle -> UserAdminController@edit | auth, perm:user.manage
POST /yonetim/kullanicilar/{id}/guncelle -> UserAdminController@update | auth, perm:user.manage
POST /yonetim/kullanicilar/{id}/askiya-al -> UserAdminController@suspend | auth, perm:user.manage
POST /yonetim/kullanicilar/{id}/aktif-et -> UserAdminController@activate | auth, perm:user.manage
POST /yonetim/kullanicilar/{id}/kilidi-ac -> UserAdminController@unlock | auth, perm:user.manage
POST /yonetim/kullanicilar/{id}/oturumlari-kapat -> UserAdminController@forceLogout | auth, perm:user.manage
POST /yonetim/kullanicilar/{id}/sifirlama-baglantisi -> UserAdminController@sendResetLink | auth, perm:user.manage, throttle:10,60
GET /yonetim/roller -> RoleAdminController@index | auth, perm:role.manage
GET /yonetim/roller/{id}/duzenle -> RoleAdminController@edit | auth, perm:role.manage
POST /yonetim/roller/{id}/guncelle -> RoleAdminController@update | auth, perm:role.manage
GET /yonetim/denetim -> AuditLogController@index | auth, perm:audit.view
GET /yonetim/denetim/disa-aktar -> AuditLogController@export | auth, perm:audit.view, throttle:5,60
GET /yonetim/denetim/{id} -> AuditLogController@show | auth, perm:audit.view
GET /yonetim/guvenlik-olaylari -> AuditLogController@securityEvents | auth, perm:audit.view
GET /yonetim/guvenlik-olaylari/disa-aktar -> AuditLogController@exportSecurity | auth, perm:audit.view, throttle:5,60
Not: Controller sınıfları App\Controllers\Admin\ altında; "disa-aktar" rotaları {id} rotasından ÖNCE tanımlanmalı.

## (5) .env / settings anahtarları
- Yok (settings/.env anahtarı eklenmedi). Oturum süresi/e-posta ayarları AuthService/MailService'ten gelir (mail.from_name, MAIL_*).

## Notlar (sonraki oturumlar)
- Varsayımlar: User::searchAdmin filtre anahtarları q, role_id, status; AuditLog::search satırları old_values/new_values/user_email/user_name taşır; SecurityEvent::search/AuditLog::search date_from/date_to "Y-m-d H:i:s" kabul eder; Role::allWithUserCounts satırları id,name,label,description içerir; Permission::allGroupedByModule satırları id,code,label,module içerir.
- Yeni kullanıcı status=pending açılır; parola bağlantısıyla belirleyince AuthService::resetPassword hesabı aktifleştirir (08 notu).
- Kullanıcı oluşturma/askıya alma yarış durumu (iki yönetici aynı anda son admini düşürürse) model kilidi gerektirir; şimdilik kontrol uygulama katmanındadır.