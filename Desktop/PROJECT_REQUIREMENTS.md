# PROJECT_REQUIREMENTS.md

## 1. Proje Tanımı

**Uygulama Adı:** Gonca Lök | Öğrenci ve Kariyer Koçluğu

Bu uygulama, öğrenci ve kariyer koçluğu hizmeti veren bir kurum için geliştirilen; kullanıcı girişli, rol tabanlı ve mobil öncelikli bir web uygulamasıdır. Koçların öğrencilerini takip edebildiği, öğrencilerin kendi hedef, çalışma ve deneme süreçlerini yönetebildiği, velilerin ise izin verilen özet verileri görüntüleyebildiği merkezi bir yönetim platformu olarak tasarlanmıştır.

Uygulama, tek bir koç ile başlayıp ileride çoklu koç/kurum yapısına (birden fazla organizasyonun aynı sistemi kullanması) uygun şekilde genişleyebilecek bir mimariyle kurgulanır.

## 2. Amaç ve Kapsam

- Koçluk sürecini dijitalleştirmek: hedef belirleme, ders/konu takibi, çalışma planı, soru takibi, deneme analizi, seans yönetimi.
- Öğrenci-koç-veli arasında şeffaf ama yetki sınırlı bir bilgi akışı sağlamak.
- Koçların iş yükünü azaltacak dashboard ve raporlama araçları sunmak.
- Mobil cihazlardan rahatça kullanılabilecek, sade ve profesyonel bir arayüz sunmak.

## 3. Teknoloji Gereksinimleri

| Katman | Teknoloji |
|---|---|
| Sunucu tarafı dili | Native PHP 8.2+ (framework yok: Laravel, CodeIgniter, Symfony vb. kullanılmayacak) |
| Veritabanı | MySQL 8+ |
| Veritabanı erişimi | PDO, yalnızca prepared statement (mysqli kullanılmayacak) |
| Ön yüz | HTML5, CSS3, Bootstrap 5, Vanilla JavaScript |
| İzin verilen küçük JS kütüphaneleri | Chart.js, FullCalendar, SortableJS, Flatpickr, SweetAlert2, Lucide Icons |
| Yasaklı JS kütüphaneleri/frameworkler | React, Vue, Angular, jQuery |

### Kod Standartları

- Kod mimarisi hafif MVC: Router, Controller, Service, Repository, View, Middleware katmanları ayrı tutulur.
- Mümkün olan her PHP dosyasında `declare(strict_types=1);` kullanılır.
- Arayüzde kullanıcıya gösterilen tüm metinler Türkçe olur.
- Kod, sınıf, değişken, fonksiyon, tablo ve dosya isimleri İngilizce olur.
- CSS, JavaScript ve PHP dosyaları birbirinden ayrı dosyalarda tutulur; büyük tek dosya üretilmez.
- Her kaynak (route/endpoint) erişiminde kullanıcının rolü ve yetkisi kontrol edilir.

## 4. Güvenlik Gereksinimleri

- Tüm SQL sorguları PDO prepared statement ile yazılır.
- Tüm kullanıcı girdileri backend tarafında doğrulanır (validation); yalnızca frontend doğrulamasına güvenilmez.
- XSS önlemi olarak tüm HTML çıktılarında `htmlspecialchars` kullanılır.
- Durum değiştiren tüm POST, PUT, PATCH, DELETE isteklerinde CSRF token doğrulaması yapılır.
- Parolalar `password_hash` ile saklanır, `password_verify` ile doğrulanır. Parolalar hiçbir zaman düz metin olarak tutulmaz veya loglanmaz.
- Başarılı girişten sonra `session_regenerate_id(true)` uygulanarak session sabitleme (session fixation) saldırıları engellenir.
- Rol bazlı erişim kontrolü (RBAC) her kaynak/endpoint seviyesinde middleware ile uygulanır.
- Öğrenci yalnızca kendi verisine, koç yalnızca kendisine atanmış öğrencilerin verisine, veli yalnızca kendisine izin verilmiş özet verilere erişebilir.
- Gerçek öğrenci kişisel verisi veya gerçek isim örnekleri hiçbir dokümanda, testte veya seed veride kullanılmaz; tüm örnekler kurgusal olur.

## 5. Çok Kurumlu / Çoklu Koç Mimarisi

- Uygulama başlangıçta tek koç/kurum için kullanılsa da veri modeli `organization_id` alanı üzerinden çoklu kurum yapısına uygun tasarlanır.
- İlgili tüm ana tablolarda (kullanıcılar, öğrenciler, hedefler, planlar, seanslar vb.) `organization_id` alanı bulunur.
- Sorgular her zaman oturum açan kullanıcının `organization_id` değeriyle filtrelenir; böylece ileride farklı kurumların verileri birbirinden izole tutulabilir.

## 6. Kullanıcı Rolleri (Özet)

- Yönetici
- Baş Koç
- Koç
- Öğrenci
- Veli

(Detaylı yetki matrisi için bkz. `USER_ROLES_AND_PERMISSIONS.md`)

## 7. Ana Modüller

1. Kullanıcı girişi (kimlik doğrulama, oturum yönetimi)
2. Öğrenci yönetimi
3. Hedef yol haritası
4. Ders ve konu takibi
5. Haftalık çalışma planı
6. Günlük çalışma kaydı
7. Soru takibi
8. Deneme analizi
9. Seans/randevu
10. Seans sonu notu
11. Veli takibi
12. Kariyer yol haritası
13. Bildirimler
14. Dashboard
15. Raporlar

## 8. Fonksiyonel Olmayan Gereksinimler

- **Mobil öncelik:** Arayüz öncelikle mobil ekranlar için tasarlanır, ardından tablet ve masaüstüne uyarlanır (responsive, Bootstrap 5 grid sistemi ile).
- **Performans:** Sayfa yüklenme sürelerini kısaltmak için gereksiz sorgulardan kaçınılır, sayfalama (pagination) kullanılır.
- **Erişilebilirlik:** Formlarda uygun `label`, kontrast oranı yeterli renk paleti, klavye ile gezilebilirlik hedeflenir.
- **Sürdürülebilirlik:** Katmanlı mimari sayesinde yeni modüllerin eklenmesi mevcut kodu bozmadan yapılabilir.
- **Ölçeklenebilirlik:** `organization_id` mantığı sayesinde ileride yeni koç/kurum eklemek şema değişikliği gerektirmez.
- **Veri bütünlüğü:** Veritabanında yabancı anahtar (foreign key) kısıtlamaları ve uygun indeksler kullanılır.
- **Loglama:** Kritik işlemler (giriş denemeleri, veri değişiklikleri) için temel bir log mekanizması öngörülür (ileride detaylandırılabilir).

## 9. Varsayımlar ve Kısıtlar

- Uygulama tek bir sunucuda native PHP ile çalışacak, ek bir framework kurulumu yapılmayacaktır.
- E-posta/SMS gibi dış servis entegrasyonları MVP kapsamında değildir (bkz. `FUTURE_FEATURES.md`).
- Uygulama başlangıçta Türkçe dil desteğiyle sınırlıdır; çoklu dil desteği kapsam dışıdır.
- Gerçek kullanıcı verisiyle test edilmeyecek, geliştirme ve doküman sürecinde yalnızca kurgusal örnekler kullanılacaktır.
