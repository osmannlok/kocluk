# E-Ticaret Sitesi İçin Gerekli Bilgi ve Belgeler Raporu

**Proje:** Yöresel Market — tek satıcılı yöresel gıda e-ticaret sitesi
**Kapsam:** Teknik altyapı, işlevsel modüller, veri modeli, güvenlik, yasal/operasyonel belgeler ve yayın kontrol listesi
**Rapor tarihi:** 12 Kasım 2026
**Not:** Bu rapor, mevcut `eticaret` projesindeki gerçek kod, şema ve dokümanlardan üretilmiştir. Yasal bölümler genel bilgilendirmedir; yayından önce hukuk danışmanı ve mali müşavir onayı gerekir.

---

## 1. Yönetici Özeti

Proje, PHP 8.2+ tabanlı hafif MVC mimarisiyle geliştirilen, MySQL/MariaDB üzerinde çalışan, mobil öncelikli bir e-ticaret uygulamasıdır. Sistem; katalog, varyant/stok, sepet, kupon, sipariş, PayTR ile kart ödemesi, havale/EFT, kapıda ödeme, kargo takibi, CMS, admin paneli ve rol tabanlı yetkilendirmeyi içerir.

**Mevcut durum özeti:**

| Alan | Durum |
|---|---|
| Çekirdek (router, DB, oturum, CSRF, hata yönetimi) | Hazır |
| Katalog, sepet, sipariş, ödeme, admin | Hazır |
| Veritabanı şeması | 36 tablo + 1 view, seed verisi mevcut |
| Rol/yetki | 3 rol, 23 izin kodu |
| Yasal sayfa altyapısı | 5 sayfa tanımlı (metinler **taslak**) |
| Yasal/ticari belgeler | **Eksik** — başvuru ve onay süreçleri tamamlanmalı |
| Yayın hazırlığı | Kısmi — aşağıdaki kontrol listesi kapatılmalı |

---

## 2. Proje Künyesi

| Başlık | Değer |
|---|---|
| Uygulama adı | Yöresel Market |
| İş modeli | Tek satıcılı, Türkiye'ye satış, fiziksel yöresel gıda |
| Para birimi / dil | TL / Türkçe |
| Sunucu | Apache / XAMPP uyumlu, PHP 8.2+ |
| Veritabanı | MySQL 8+ / MariaDB, `utf8mb4_unicode_ci` |
| Framework | Yok (native PHP, hafif MVC) |
| Bağımlılıklar | `phpmailer/phpmailer`, `ezyang/htmlpurifier` |
| Ödeme | PayTR iFrame, havale/EFT, kapıda ödeme |
| Kargo | Manuel takip (firma + takip no), ağırlık bazlı tarife |
| Ön yüz | HTML5, CSS3, Bootstrap 5, Vanilla JS |

---

## 3. Teknik Gereksinimler ve Mimari

### 3.1 Katmanlar

```
public/index.php  →  Router  →  Middleware  →  Controller
                                      ↓
                                   Service  →  Model (PDO)  →  MySQL
                                      ↓
                                    View (PHP şablon)
```

- **Core:** Router, Request, Response, Config, Database, Auth, Session, Csrf, Validator, RateLimiter, Logger, ErrorHandler.
- **Middleware:** Auth, Guest, Permission, Csrf, Throttle, SecurityHeaders.
- **Controller:** `Front` (vitrin) ve `Admin` (yönetim) olarak ikiye ayrılmıştır.
- **Service:** İş kuralları (fiyat, sepet, sipariş, stok, ödeme, kargo, kupon, rapor, e-posta).
- **Model:** PDO prepared statement ile veri erişimi.
- **View:** `resources/views` altında layout + partial + sayfa yapısı.

### 3.2 Sunucu ve Ortam Gereksinimleri

- PHP ≥ 8.2; `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`/`imagick` eklentileri.
- MySQL 8+ veya MariaDB; `utf8mb4` karakter seti.
- Apache `mod_rewrite`, HTTPS sertifikası (Let's Encrypt önerilir).
- Yazma izni: `storage/` (log, oturum, e-posta) ve `public/uploads/`.
- Cron: süresi dolan siparişlerin iptali için 5 dakikada bir çalışan görev.

### 3.3 Ortam Değişkenleri (`.env`)

| Grup | Anahtarlar |
|---|---|
| Uygulama | `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_KEY`, `TRUSTED_PROXIES` |
| Veritabanı | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` |
| Oturum | `SESSION_COOKIE_NAME`, `SESSION_IDLE_MINUTES`, `SESSION_ABSOLUTE_MINUTES` |
| E-posta | `MAIL_DRIVER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS` |
| Yükleme | `UPLOAD_MAX_MB` |
| PayTR | `PAYTR_MERCHANT_ID`, `PAYTR_MERCHANT_KEY`, `PAYTR_MERCHANT_SALT`, `PAYTR_TEST_MODE`, `PAYTR_DEBUG_ON`, `PAYTR_TIMEOUT_LIMIT`, `PAYTR_MAX_INSTALLMENT`, `PAYTR_NO_INSTALLMENT`, `PAYTR_USER_IP` |

> Kural: Gizli anahtarlar yalnızca `.env` içinde tutulur; `settings` tablosuna ve git'e yazılmaz.

---

## 4. İşlevsel Modüller

| # | Modül | Kapsam |
|---|---|---|
| 1 | Üyelik ve kimlik doğrulama | Kayıt, giriş, çıkış, e-posta doğrulama, şifre sıfırlama, oturum yönetimi |
| 2 | Katalog | Kategori (üst/alt), ürün, varyant, çoklu görsel, yöre/menşei, alerjen, saklama, raf ömrü |
| 3 | Arama ve filtreleme | Metin araması, öneri ucu, kategori/yöre filtreleri, indirimli ürünler |
| 4 | Sepet | Misafir sepeti, girişte birleştirme, adet güncelleme, kupon uygulama |
| 5 | Fiyatlandırma | Sunucu taraflı tek fiyat kaynağı, KDV dahil fiyat, kupon, ücretsiz kargo eşiği |
| 6 | Stok | Varyant bazlı stok, rezervasyon/iade, kritik stok görünümü, hareket kaydı |
| 7 | Sipariş | Sipariş oluşturma, durum makinesi, iptal/iade, sipariş geçmişi |
| 8 | Ödeme | PayTR iFrame, havale/EFT dekont onayı, kapıda ödeme |
| 9 | Kargo | Ağırlık bazlı ücret, firma + takip numarası, takip bağlantısı |
| 10 | Hesap | Adres defteri, sipariş takibi, profil bilgileri |
| 11 | CMS | Sayfalar, menüler, banner, duyurular, iletişim mesajları |
| 12 | Admin panel | Dashboard, ürün/kategori/stok, sipariş, kupon, içerik, kullanıcı/rol, ayar, denetim |
| 13 | Raporlama | Ciro, sipariş, en çok satanlar, kritik stok, yeni üye/mesaj |
| 14 | Güvenlik | RBAC, CSRF, oran sınırlama, güvenlik olayları, denetim kaydı |

### 4.1 Sipariş Durum Akışı

```
pending_payment → paid → preparing → shipped → delivered
       │            │         │
       └→ cancelled └→ cancelled └→ cancelled
                    └→ refunded   delivered → refunded
```

- Her durum değişikliği `order_status_history` tablosuna yazılır.
- İptalde stok iadesi ve kupon sayacı geri alınır.
- Ödeme başarısız/iptalde ilgili `payments` kaydı güncellenir.

---

## 5. Veri Modeli

Toplam **36 tablo + 1 view**. Özet gruplama:

| Grup | Tablolar |
|---|---|
| Kimlik/RBAC | `roles`, `permissions`, `role_permissions`, `users`, `addresses`, `login_attempts`, `password_resets`, `email_verifications` |
| Katalog/Stok | `categories`, `regions`, `products`, `product_variants`, `product_images`, `stock_movements`, `v_low_stock` (view) |
| Sepet/Sipariş/Ödeme | `carts`, `cart_items`, `coupons`, `shipping_rates`, `orders`, `order_items`, `order_status_history`, `payments`, `payment_callbacks`, `shipments`, `coupon_usages` |
| CMS/Pazarlama | `banners`, `menus`, `menu_items`, `pages`, `settings`, `announcements`, `contact_messages` |
| Güvenlik/Log | `audit_logs`, `security_events`, `rate_limits`, `sessions` |

### 5.1 Kritik Kurallar

- Tüm fiyatlar `DECIMAL(12,2)`, KDV dahil; hesaplama kuruş (int) düzeyinde yapılır.
- `cart_items` fiyat saklamaz; fiyat her zaman sunucuda yeniden hesaplanır.
- `order_items` ürün adı/fiyat **anlık görüntüsü** (snapshot) tutar.
- IP alanları `VARBINARY(16)` (IPv4/IPv6 uyumlu).
- `audit_logs`, `security_events`, `payment_callbacks`, `stock_movements`, `order_status_history` yalnızca ekleme (INSERT) ağırlıklıdır.
- Soft delete: `users`, `addresses`, `categories`, `products`, `product_variants`, `coupons`, `banners`, `pages`.

### 5.2 Örnek Ayarlar (`settings`)

| Anahtar | Varsayılan | Açıklama |
|---|---|---|
| `site.name`, `site.tagline` | — | Site kimliği |
| `contact.*` | — | E-posta, telefon, WhatsApp, adres |
| `social.instagram`, `social.facebook` | — | Sosyal medya |
| `shipping.free_threshold` | 750.00 | Ücretsiz kargo eşiği |
| `cod.enabled`, `cod.fee` | true / 15.00 | Kapıda ödeme |
| `bank_transfer.*` | — | Banka, hesap sahibi, IBAN, not |
| `card.enabled` | true | Kart ödemesi aç/kapa |
| `order.card_expiry_minutes` | 30 | Kart siparişi zaman aşımı |
| `order.bank_expiry_hours` | 48 | Havale zaman aşımı |
| `order.min_amount` | 0 | Minimum sipariş tutarı |
| `tax.prices_include_vat` | 1 | Fiyatlar KDV dahil mi |
| `legal.company_title/tax_office/tax_number/mersis` | — | Yasal künye |

---

## 6. Roller ve Yetki Matrisi

**Roller:** `admin` (23 izin), `manager` (11 izin), `customer` (0 yönetim izni).

| İzin | admin | manager |
|---|---|---|
| `dashboard.view` | ✓ | ✓ |
| `product.view` | ✓ | ✓ |
| `product.create` / `product.update` | ✓ | ✓ |
| `product.delete` | ✓ | — |
| `category.manage` | ✓ | ✓ |
| `stock.manage` | ✓ | ✓ |
| `order.view` | ✓ | ✓ |
| `order.update_status` | ✓ | ✓ |
| `order.cancel` / `order.refund` | ✓ | — |
| `payment.confirm` | ✓ | — |
| `shipment.manage` | ✓ | ✓ |
| `customer.view` | ✓ | ✓ |
| `coupon`, `banner`, `menu`, `page` yönetimi | ✓ | — |
| `setting`, `user`, `role` yönetimi | ✓ | — |
| `audit.view` | ✓ | — |
| `report.view` | ✓ | ✓ |

---

## 7. Fiyat, Vergi, Kargo ve Ödeme Kuralları

### 7.1 Fiyatlandırma

- Fiyatlar KDV dahil; `tax_rate` ürün bazında (varsayılan %10 — **mali müşavir onayı gerekli**).
- İndirimli fiyat, kupon (yüzde/sabit), minimum sepet tutarı, kullanım limiti, tarih aralığı desteklenir.
- Kargo ücreti ağırlık bandına göre hesaplanır; eşik üzeri ücretsiz.
- Kapıda ödeme ücreti ayarlardan eklenir.
- Tüm hesaplamalar `PricingService` üzerinden sunucuda yapılır.

### 7.2 Ödeme Yöntemleri

| Yöntem | Davranış |
|---|---|
| Kredi/banka kartı | PayTR iFrame; sunucudan sunucuya hash doğrulamalı bildirim |
| Havale/EFT | Sipariş `pending_payment`; admin dekont kontrolüyle onaylar |
| Kapıda ödeme | Sipariş `preparing`; teslimatta tahsilat, admin işaretler |

- Kart verisi hiçbir zaman sistemde saklanmaz.
- Tutar uyuşmazlığında (`amount_mismatch`) ödeme başarısız işaretlenir ve güvenlik olayı kaydedilir.
- Bildirim rotası (`/odeme/paytr/bildirim`) CSRF istisnasıdır; yalnızca hash doğrulamasıyla çalışır.

### 7.3 Kargo

- Kargo firmaları `config/carriers.php` içinde tanımlıdır.
- Takip URL'si şablondan sunucuda üretilir; yönetici serbest URL giremez.
- **Dikkat:** Mevcut takip URL şablonları `null` (doğrulanmadı); yayından önce doldurulmalı.

---

## 8. Güvenlik ve Uyum Gereksinimleri

| Konu | Uygulama |
|---|---|
| SQL enjeksiyonu | PDO prepared statement |
| XSS | Çıktıda `htmlspecialchars`; CMS içeriğinde HTMLPurifier |
| CSRF | Durum değiştiren tüm isteklerde token |
| Parola | `password_hash` / `password_verify` (Argon2id önerilir) |
| Oturum | `session_regenerate_id(true)`, boşta/mutlak zaman aşımı |
| RBAC | Endpoint düzeyinde izin middleware'i |
| Oran sınırlama | Giriş, kayıt, iletişim, arama, kupon uçlarında |
| Başlıklar | CSP, HSTS (üretim), clickjacking koruması, `form-action 'self'` |
| Yükleme | MIME beyaz listesi, boyut sınırı, `.htaccess` ile çalıştırma engeli |
| Loglama | `audit_logs`, `security_events`, `login_attempts` |
| Gizli veri | `.env` erişilemez; loglarda parola/kart verisi yok |

---

## 9. Yasal ve Ticari Belgeler (Türkiye)

> Bu bölüm, e-ticaret faaliyeti için tipik olarak gereken belgeleri listeler. Uygulanabilirlik; şirket türü, cirosu, ürün grubu ve satış kanalına göre değişir. **Hukuk danışmanı ve mali müşavir onayı zorunludur.**

### 9.1 Sitede Yayımlanması Gereken Metinler

| Belge | Durum (projede) | Not |
|---|---|---|
| KVKK Aydınlatma Metni | Taslak var (`pages.system_key = kvkk`) | Veri sorumlusu, işleme amacı, hukuki sebep, aktarım, saklama, haklar |
| KVKK Açık Rıza Metni | Eksik | Pazarlama/çerez onayları için ayrı |
| Çerez Politikası | Taslak var (`cookies`) | Çerez türleri, amaç, süre, yönetim |
| Mesafeli Satış Sözleşmesi | Taslak var (`distance_sales`) | Sipariş öncesi onay + kayıt |
| Ön Bilgilendirme Formu | Eksik (sözleşme içinde olabilir) | Mesafeli satış mevzuatı gereği |
| İade ve İptal Koşulları | Taslak var (`returns`) | Cayma hakkı, istisnalar, süreç |
| Gıda Cayma Hakkı İstisnası | Metin içinde | Çabuk bozulan/ambalajı açılan ürünler |
| Gizlilik Politikası | Taslak var (`privacy`) | Veri akışı ve güvenlik |
| Kullanım Koşulları | Eksik | Site kullanım kuralları |
| Ticari Künye / Hakkımızda | Kısmi (`legal.*` ayarları) | Unvan, MERSİS, vergi, adres, iletişim |
| Teslimat ve Kargo Koşulları | Kısmi | Süre, bölge, ücret, hasar süreci |
| Fiyat ve Ödeme Koşulları | Kısmi | Para birimi, taksit, fatura |

**Yasal sayfa slug'ları (kodda beklenen):** `kvkk`, `mesafeli-satis`, `iade-iptal`, `gizlilik`, `cerez`.

### 9.2 İşletme ve Resmî Belgeler

| Belge | Açıklama |
|---|---|
| Vergi levhası | Faaliyet konusu e-ticaret/perakende ile uyumlu |
| Ticaret sicil gazetesi / faaliyet belgesi | Şirket türüne göre |
| MERSİS numarası | Ticari künyede yayımlanır |
| İmza sirküleri | Banka/PayTR başvurularında |
| NACE / faaliyet kodu | Gıda perakende/uzaktan satış kodu |
| İşyeri açma ve çalışma ruhsatı | Fiziksel depo/mağaza varsa |
| Gıda İşletme Kayıt Belgesi | Gıda satışı için temel şart |
| Hijyen/İSG belgeleri | Personel ve depo koşulları |
| Üretici/tedarikçi sözleşmeleri | Ürün temin ve sorumluluk |
| Ürün etiket/alerjen uyumu | Türk Gıda Kodeksi |
| Banka ticari hesap | Tahsilat ve mutabakat |
| PayTR üye işyeri sözleşmesi | Kart ödemesi için zorunlu |
| Kargo firma sözleşmeleri | Gönderim, hasar, iade |
| Hosting/domain/SSL faturaları | Altyapı ve KVKK sorumluluğu |
| E-fatura / e-arşiv mükellefiyeti | Ciro eşiğine göre |
| ETBİS kaydı | E-ticaret mevzuatı kapsamında |
| Dijital hizmet sağlayıcı bildirimi | İlgili mevzuat kapsamında |
| Mesleki sorumluluk/siber sigorta | Opsiyonel risk yönetimi |
| Tüketici hakem heyeti bilgilendirmesi | İtiraz yolları |
| Aydınlatma/onay kayıtları | Onay zamanı, IP, user-agent saklanır |

### 9.3 Kişisel Veri İşleme Envanteri (KVKK)

| Veri | Amaç | Hukuki Sebep | Saklama |
|---|---|---|---|
| Ad, e-posta, telefon | Üyelik, iletişim | Sözleşme | Üyelik süresi + yasal süre |
| Adres, TC/VKN | Teslimat, fatura | Sözleşme/yasal yükümlülük | Yasal saklama süresi |
| Sipariş/ödeme kaydı | İşlem, muhasebe | Sözleşme/yasal | 10 yıl (muhasebe) |
| IP, user-agent | Güvenlik, onay ispatı | Meşru menfaat/yasal | Makul süre |
| Çerez verisi | Deneyim, analitik | Açık rıza (zorunlu olmayan) | Politika süresi |
| İletişim mesajları | Destek | Meşru menfaat | Makul süre |

---

## 10. Operasyon ve Finans Belgeleri

- Sipariş, iade, iptal ve mutabakat prosedürleri.
- Günlük/kasa ve banka mutabakatı; PayTR hakediş raporları.
- Fatura/e-arşiv akışı ve muhasebe entegrasyonu.
- Stok sayım, fire ve kritik stok prosedürü.
- Kargo hasar/kayıp süreci ve tazmin prosedürü.
- Müşteri destek SLA ve şikayet yönetimi.
- Yedekleme (DB + dosya) ve geri dönüş (restore) testi.
- Olay müdahale (veri ihlali) planı ve KVKK bildirim süreci (72 saat).

---

## 11. Pazarlama, SEO ve Analitik

- `robots.txt`, `sitemap.xml` (kodda rota **yorumlu**, açılmalı).
- Meta title/description, canonical, Open Graph.
- Yapısal veri (Product, Offer, BreadcrumbList).
- Google Search Console / Analytics, çerez onayına bağlı etiket yönetimi.
- E-posta şablonları: sipariş oluştu, ödendi, kargolandı, iptal.
- Terk edilmiş sepet, yorum/puan, favoriler → v2 kapsamı.

---

## 12. Mevcut Dokümantasyon Envanteri

`eticaret/docs/sozlesmeler/` altında:

| Dosya | İçerik |
|---|---|
| `00-gereksinimler.md` | MVP kapsamı, roller, katalog, ödeme, kargo, yasal |
| `01-kodlama-standartlari.md` | Kod ve isimlendirme kuralları |
| `02-guvenlik-kurallari.md` | Güvenlik gereksinimleri |
| `03-klasor-yapisi.md` | Dizin yapısı |
| `04-sema-özeti.md` | Tablo/kolon/FK/ENUM özeti |
| `05-sozlesme-ozeti.md`, `05a`, `05b` | Veritabanı ve çekirdek sözleşmeleri |
| `06-model`, `06a`, `06b` | Model katmanı sözleşmeleri |
| `07-on-yuz-sozlesme.md` | Ön yüz iskeleti ve JS API |
| `08-uyelik`, `08s-ortak-servisler` | Üyelik ve ortak servisler |
| `09-katalog`, `10-sepet`, `11-siparis`, `12-odeme`, `13-hesap` | Modül sözleşmeleri |
| `14-admin`, `16-admin-siparis`, `17b-admin-icerik`, `18-admin-kullanici` | Yönetim paneli sözleşmeleri |
| `docs/apache-vhost.conf` | Apache sanal sunucu örneği |
| `database/01..06` | Şema ve seed SQL dosyaları |
| `scripts/*` | Admin oluşturma, süre dolan sipariş iptali, PayTR test callback, smoke test |

---

## 13. Tespit Edilen Eksikler ve Riskler

| # | Eksik / Risk | Etki | Öneri |
|---|---|---|---|
| 1 | Yasal metinler taslak | Yayın/uyum riski | Hukuk onaylı metinlerle değiştir |
| 2 | `routes/admin.php` yok; admin rotaları tanımlı değil | Yönetim paneli erişilemez | Rotaları tamamla ve test et |
| 3 | Sitemap rotası yorumlu | SEO eksik | Metot doğrulanıp açılmalı |
| 4 | Kargo takip URL şablonları `null` | Takip bağlantısı çalışmaz | Firmalarla doğrula, doldur |
| 5 | `tax_rate` varsayılan %10 geçici | Vergi hatası riski | Ürün bazında netleştir |
| 6 | Seed banka/IBAN/vergi/MERSİS örnek | Yanlış künye | Gerçek verilerle güncelle |
| 7 | E-posta şablonlarının bir kısmı eksik olabilir | Bildirim gitmez | Şablonları tamamla, test et |
| 8 | PayTR canlı anahtarlar ve test senaryosu | Ödeme çalışmaz | Test → canlı geçiş planı |
| 9 | ETBİS, e-fatura, Gıda Kayıt belgeleri | Yasal engel | Başvuruları tamamla |
| 10 | Yedekleme/izleme planı | Veri kaybı | Otomatik yedek + alarm |
| 11 | KVKK açık rıza ve çerez yönetimi metni | Uyum riski | Onay akışını belgeleyin |
| 12 | Erişilebilirlik testi | Kullanım kalitesi | Klavye/kontrast denetimi |

---

## 14. Yayın Öncesi Kontrol Listesi

**Teknik**
- [ ] `.env` üretim değerleri (`APP_ENV=production`, `APP_DEBUG=false`, güçlü `APP_KEY`)
- [ ] HTTPS + HSTS, yönlendirmeler
- [ ] Veritabanı kullanıcısı `ym_app` (kısıtlı yetki), yedek planı
- [ ] Admin kullanıcısı `scripts/create_admin.php` ile oluşturuldu
- [ ] Cron: `cancel_expired_orders.php` çalışıyor
- [ ] PayTR canlı mod ve bildirim URL'si doğrulandı
- [ ] Yükleme dizini izinleri ve `.htaccess` koruması
- [ ] `routes/admin.php` ve tüm admin uçları çalışıyor
- [ ] Sitemap/robots, e-posta şablonları, kargo URL'leri
- [ ] Smoke testler ve kritik akış testleri (kayıt → sepet → ödeme → kargo)
- [ ] Log/izleme, hata bütçesi, yedek geri dönüş testi

**Ticari / Yasal**
- [ ] Şirket künyesi, MERSİS, vergi, adres, iletişim doğru
- [ ] KVKK aydınlatma + açık rıza + çerez politikası yayında
- [ ] Mesafeli satış sözleşmesi + ön bilgilendirme onayı
- [ ] İade/iptal ve gıda cayma istisnası metni
- [ ] ETBİS, e-fatura/e-arşiv, Gıda İşletme Kayıt Belgesi
- [ ] PayTR, kargo, tedarikçi, banka sözleşmeleri
- [ ] Fatura ve muhasebe akışı
- [ ] Tüketici hakem heyeti bilgilendirmesi

**Operasyon**
- [ ] Sipariş/iade/kargo prosedürleri yazılı
- [ ] Müşteri destek kanalları ve SLA
- [ ] Stok sayım ve kritik stok süreci
- [ ] Veri ihlali müdahale planı

---

## 15. Öneri Yol Haritası

1. **Hafta 1:** Eksik admin rotaları, sitemap, kargo URL'leri, e-posta şablonları.
2. **Hafta 2:** Yasal metinlerin hukuk onayı; künye ve ayarların gerçek verilerle doldurulması.
3. **Hafta 3:** PayTR test → canlı geçiş; uçtan uca sipariş/ödeme/kargo testi.
4. **Hafta 4:** ETBİS/e-fatura/Gıda Kayıt başvuruları; yedekleme ve izleme.
5. **Yayın:** Kontrol listesi kapanışı, KVKK/çerez onay akışı doğrulaması, canlı izleme.

---

**Sonuç:** Teknik altyapı büyük ölçüde hazırdır. Yayın için kritik yol; admin rotalarının tamamlanması, yasal metin/ticari belgelerin onaylanması, PayTR canlı geçişi ve operasyon prosedürlerinin yazılı hale getirilmesidir.
