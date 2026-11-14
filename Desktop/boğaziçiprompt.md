Sen kıdemli (senior) bir full-stack web geliştiricisisin. Görevin, modern, güvenli, responsive ve production seviyesinde bir web uygulaması geliştirmektir.

## 🎯 PROJE TANIMI

Bir eğitim kurumu için “Soru Çözüm Merkezi” web sitesi geliştireceksin. Sistem LGS öğrencilerine yönelik soru çözüm ve etüt hizmeti sunan profesyonel bir kurum sitesidir.

Kurum adı:
👉 [BOĞAZİÇİVIP EĞİTİM KURUMU] (kolayca değiştirilebilir olmalı)

---

## 🧱 KULLANILACAK TEKNOLOJİLER

* Frontend: HTML5, CSS3, Bootstrap 5.3
* Backend: PHP 8.2 (PDO ile)
* Veritabanı: MySQL 8
* JavaScript: Vanilla JS
* E-posta: PHPMailer (SMTP)
* UI: SweetAlert2, AOS animasyonları

---

## 🎨 TASARIM

* Modern, kurumsal ve temiz tasarım
* Ana renkler:

  * #812c8f
  * #b4228d
  * Beyaz (#ffffff)
* Responsive (mobil + tablet + desktop uyumlu)
* SEO uyumlu meta tag yapısı
* Header’da logo alanı:

  * `/assets/images/logo.png` dosyasından yüklenecek

---

## 📁 PROJE YAPISI (ZORUNLU)

Profesyonel klasör yapısı oluştur:

/project-root
│
├── index.php
├── hakkimizda.php
├── rehberlik.php
├── galeri.php
├── duyurular.php
├── randevu.php
├── iletisim.php
│
├── /admin
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── duyurular.php
│   ├── galeri.php
│
├── /includes
│   ├── config.php
│   ├── db.php
│   ├── header.php
│   ├── footer.php
│   ├── auth.php
│   ├── csrf.php
│
├── /assets
│   ├── /css
│   ├── /js
│   ├── /images
│
├── /uploads
│
└── /vendor (PHPMailer)

---

## 🗄️ VERİTABANI

Aşağıdaki tabloları oluştur:

* users
* appointments
* announcements
* gallery
* articles
* contact_messages
* admin_users

Tüm tablolar için:

* PRIMARY KEY
* ilişkiler (foreign key)
* created_at alanları

Ayrıca tam bir SQL dump dosyası üret.

---

## 🔐 GÜVENLİK

* PDO prepared statements
* password_hash() ve password_verify()
* CSRF token sistemi
* Session yönetimi
* XSS ve input sanitization
* Admin paneli giriş koruması

---

## 📄 SAYFALAR

### 1. index.php

* Hero slider (3-4 slide, otomatik geçişli)
* “Randevu Al” butonu
* “Neden Bizi Seçmelisiniz?” kartları
* “Biz Kimiz?”
* “Başarılarımız” (counter animasyonlu)
* Son 3 duyuru
* Galeri önizleme
* Mini iletişim formu

---

### 2. hakkimizda.php

* Kurum tanıtımı
* Misyon & vizyon

---

### 3. rehberlik.php

* 4 adet blog kartı:

  * Başlık
  * Görsel
  * Özet
  * “Devamını Oku” butonu

---

### 4. galeri.php

* Bootstrap grid
* Kategori filtreleme
* Lightbox görüntüleme

---

### 5. duyurular.php

* Liste görünümü
* Başlık, tarih, görsel, açıklama
* Admin panelinden yönetilebilir

---

### 6. randevu.php (EN KRİTİK)

* Giriş sistemi:

  * TC Kimlik No + Şifre
* Kayıt:

  * TC No, Ad Soyad, E-posta, Telefon
  * Rastgele güçlü şifre üret
  * Şifreyi PHPMailer ile mail gönder
* Giriş sonrası:

  * Takvimden randevu seçimi
  * Branş seçimi (Matematik, Fizik, Kimya vb.)
  * Randevu oluşturma
* Veritabanına kayıt

---

### 7. iletisim.php

* Form:

  * Ad, Soyad, Email, Konu, Mesaj
* PHPMailer ile mail gönder
* Google Maps embed

---

## ⚙️ ADMIN PANELİ

/admin altında:

* Giriş sistemi
* Duyuru CRUD
* Galeri CRUD (görsel upload dahil)

---

## ✨ EKSTRA ÖZELLİKLER

* SweetAlert2 bildirimler
* Loading spinner
* AOS animasyonlar
* Footer:

  * iletişim bilgileri
  adres:Tekerek Mahallesi Karasu Bulvarı 15B Beykent Sitesi Altı Yeşilkent Sitesi Karşısı
Onikişubat/Kahramanmaraş
telefonlar:+90 538 446 92 00
+90 536 471  32 00

  * sosyal medya ikonları
  FACEBOOK,instagram,x,youtube adresi: bogazicivipkurs

---

## 📦 ÇIKTI FORMATI (ÇOK ÖNEMLİ)

Kodları PARÇA PARÇA ve TAM VER:

1. Klasör yapısını (tree)
2. SQL tabloları (CREATE TABLE)
3. config.php
4. Tüm PHP sayfaları (tam kod)
5. CSS ve JS dosyaları
6. Admin paneli dosyaları
7. Kurulum talimatları

---

## ⚠️ ÖNEMLİ KURALLAR

* Tüm metinler TÜRKÇE olacak
* Kodlar çalışır durumda olacak
* Eksik dosya olmayacak
* Temiz, okunabilir ve profesyonel kod yaz
* Tekrar eden kodları include ile yönet
* Bootstrap bileşenlerini doğru kullan

---

Şimdi yukarıdaki tüm gereksinimleri eksiksiz karşılayan, tamamen çalışan, production seviyesinde projeyi oluşturmaya başla.
