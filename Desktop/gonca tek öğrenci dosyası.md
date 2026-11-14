Sen; Excel otomasyonu, öğrenci koçluğu süreçleri, veri analizi ve kullanıcı deneyimi konusunda uzman bir Excel geliştiricisisin.

Gonca Lök Öğrenci ve Kariyer Koçluğu için, HER ÖĞRENCİYE AYRI KOPYALANARAK kullanılacak profesyonel, modern, Türkçe ve otomatik hesaplamalı bir Excel takip dosyası oluştur.

ÖNEMLİ:
- Sadece açıklama, tablo taslağı veya kod önerisi verme.
- Python kullanarak openpyxl veya xlsxwriter kütüphanesiyle gerçek, çalışır, indirilebilir `.xlsx` dosyası oluştur.
- Dosyayı oluşturduktan sonra kullanıcıya dosyayı sun.
- Dosya adı şu şekilde olsun:
  `Gonca_Lok_Ogrenci_Kariyer_Kocluk_Takip_Sablonu.xlsx`
- Dosyada makro/VBA kullanma.
- Dosya Microsoft Excel’de açıldığında çalışmalıdır.
- Formülleri Excel uyumluluğu için İngilizce Excel formül isimleriyle yaz. Örneğin: `SUMIFS`, `IFERROR`, `TODAY`, `COUNTIF`, `COUNTIFS`, `AVERAGEIFS`.
- Kullanıcı arayüzü, sekme adları, sütun adları, açıklamalar ve uyarılar tamamen Türkçe olsun.
- Dosya hem masaüstü Excel hem de mümkün olduğunca Google Sheets uyumlu olacak şekilde tasarlansın.
- Her sayfada filtreler, sabit üst satır, okunabilir sütun genişlikleri ve profesyonel biçimlendirme olsun.
- Gereksiz karmaşıklık oluşturma; koçun günlük kullanabileceği pratik bir yapı tasarla.
- Dosyada gerçek öğrenci kişisel verisi kullanma. Örnek veriler gerekiyorsa “ÖRNEK” ibaresi kullan ve kolayca silinebilir yap.
- Sayfalarda öğrenci adı, telefon numarası, okul vb. alanlar boş bırakılmalı.
- Dosya bir şablon olmalıdır; her öğrenci için çoğaltılarak kullanılacaktır.

TASARIM DİLİ:
- Marka başlığı: “Gonca Lök | Öğrenci ve Kariyer Koçluğu”
- Ana renkler:
  - Koyu lacivert: #17365D
  - Turkuaz/mavi: #1F9EAF
  - Açık mavi: #DDEBF7
  - Açık yeşil: #E2F0D9
  - Açık sarı: #FFF2CC
  - Açık kırmızı: #F4CCCC
  - Açık gri: #F2F2F2
- Başlık satırları lacivert zemin ve beyaz yazı olsun.
- Girdi yapılacak hücreler açık sarı veya açık mavi olsun.
- Otomatik formül hücreleri açık gri olsun ve hücre koruması mümkünse etkinleştirilsin.
- Riskli veya kritik alanlar kırmızı/turuncu, başarılı alanlar yeşil görünsün.
- Tarih biçimi `dd.mm.yyyy`, saat biçimi `hh:mm` olsun.
- Yüzde alanları `%0` veya `%0,0` biçiminde gösterilsin.
- Net alanlarında iki ondalık gösterilebilsin.

DOSYADA OLUŞTURULACAK SAYFALAR:
1. 00_Dashboard
2. 01_Ogrenci_Bilgileri
3. 02_Hedef_Yol_Haritasi
4. 03_Seans_Randevu_Takibi
5. 04_Haftalik_Calisma_Plani
6. 05_Gunluk_Calisma_Kaydi
7. 06_Soru_Takibi
8. 07_Deneme_Analizi
9. 08_Ders_Turkce
10. 09_Ders_Matematik
11. 10_Ders_Geometri
12. 11_Ders_Fizik
13. 12_Ders_Kimya
14. 13_Ders_Biyoloji
15. 14_Ders_Edebiyat
16. 15_Ders_Tarih
17. 16_Ders_Cografya
18. 17_Ders_Felsefe_Din
19. 18_Kariyer_Yol_Haritasi
20. 19_Veli_Takibi
21. 20_Seans_Sonu_Notu
22. 21_Konu_Kaynak_Genel
23. 22_Ayarlar_Listeler
24. 23_Kullanim_Rehberi

GENEL TEKNİK KURALLAR:
- Her veri giriş alanını Excel Tablosu olarak oluştur.
- Tablo adlarında Türkçe karakter kullanma.
- Örnek tablo adları:
  - tblOgrenci
  - tblHedefler
  - tblSeanslar
  - tblHaftalikPlan
  - tblGunlukCalisma
  - tblSoruTakip
  - tblDeneme
  - tblVeli
  - tblSeansNot
  - tblKariyer
  - tblKaynak
- Her tabloda filtre aktif olsun.
- Her sayfada ilk satırda marka başlığı, ikinci satırda kısa kullanım açıklaması, dördüncü satırdan itibaren tablo veya panel yer alsın.
- Sayfa görünümünde ilk satırlar sabitlenmiş olsun.
- Kullanıcı dolduracağı hücreler kilitsiz, formül hücreleri kilitli olarak ayarlansın.
- Sayfa koruması parola gerektirmeden aktif edilebilir; kullanıcı girdi hücrelerine yazabilsin.
- Formül sonuçları boş veri varken “0” veya hata göstermesin. Boş ya da “-” gösterilsin.
- `IFERROR` ve gerekli yerlerde `IF` ile boş/hata kontrolü yap.
- Sütun başlıkları kısa ama anlaşılır olsun.
- Her veri sayfasında en az 300 satırlık hazır tablo alanı oluştur.
- Dashboard için gerekli yardımcı hesaplama alanlarını görünmeyen sütunlarda veya ayrı yardımcı bir alanda tut; Dashboard temiz görünsün.

==================================================
1) 00_DASHBOARD SAYFASI
==================================================

Bu sayfa koçun öğrenciyle seans öncesinde 2-3 dakikada genel durumu görebileceği ana ekran olsun.

Üst bölümde şu bilgi kartları yer alsın:
- Öğrenci Adı Soyadı
- Başlangıç Tarihi
- Hedef Sınav / Okul
- Hedef Bölüm / Meslek
- Hedef Tarihi
- Hedefe Kalan Gün
- Paket Türü
- Toplam Seans
- Yapılan Seans
- Kalan Seans
- Sonraki Seans Tarihi ve Saati
- Risk Durumu

Aşağıdaki performans kartlarını oluştur:
- Bu haftaki planlanan çalışma süresi
- Bu haftaki gerçekleşen çalışma süresi
- Bu haftaki plan gerçekleşme oranı
- Bu haftaki hedef soru sayısı
- Bu haftaki çözülen soru sayısı
- Bu haftaki soru hedefi gerçekleşme oranı
- Son deneme toplam neti
- Önceki denemeye göre net değişimi
- Son 4 haftadaki toplam çalışma süresi
- Son 4 haftadaki toplam soru sayısı
- Tekrar bekleyen konu sayısı
- Kritik/zayıf konu sayısı
- Bu hafta tamamlanmamış öğrenci taahhüdü sayısı

Dashboard’da şu grafikler yer alsın:
1. Son 8 haftada planlanan çalışma süresi ve gerçekleşen çalışma süresi karşılaştırma grafiği.
2. Son 5 denemede toplam net değişimi çizgi grafiği.
3. Ders bazında son deneme netleri sütun grafiği.
4. Ders bazında doğruluk oranı grafiği.
5. Konu durumlarının dağılımı: Başlanmadı, Çalışılıyor, Pekişiyor, Tekrar Gerekli, Ustalaştı.
6. Yanlış türü dağılımı: Bilgi eksiği, dikkat, işlem, süre, yorum vb.

Dashboard’da ayrıca şu kutular olsun:
- En kritik 3 konu
- Bu haftanın odak konusu
- Bu haftanın öğrenci taahhüdü
- Bu haftanın koç aksiyonu
- Son veli görüşmesi özeti
- Yaklaşan seans bilgisi
- Son denemeden çıkan 3 aksiyon

Risk durumu için otomatik renk sistemi kur:
- Yeşil: Haftalık plan gerçekleşme oranı %80 veya üzeri.
- Sarı: Haftalık plan gerçekleşme oranı %50 ile %79 arası.
- Kırmızı: Haftalık plan gerçekleşme oranı %50’nin altında veya son 14 günde günlük çalışma kaydı yoksa.
- Risk durumu formülü boş veride hata göstermesin.

==================================================
2) 01_OGRENCI_BILGILERI SAYFASI
==================================================

Bu sayfa form görünümünde olsun. Sol tarafta alan adı, sağ tarafta giriş hücresi bulunsun.

Alanlar:
- Öğrenci Kodu
- Ad Soyad
- Başlangıç Tarihi
- Sınıf / Mezun Durumu
- Okul
- Alan
- Sınav Türü
- Hedef Bölüm
- Hedef Üniversite / Okul
- Hedef Meslek
- Hedef Sıralama
- Hedef Puan
- Hedef Tarihi
- Paket Türü
- Paket Toplam Seans Sayısı
- Seans Günü
- Seans Saati
- Öğrenci Telefonu
- Öğrenci E-posta
- Veli Ad Soyad
- Veli Telefonu
- Veli İletişim Tercihi
- İletişim Sıklığı
- Öğrencinin Güçlü Yönleri
- Gelişim Alanları
- Başlangıç Koçluk Notu
- Öğrencinin En Büyük Engeli
- Öğrencinin Motivasyon Kaynağı
- Veri Kullanım / Veli Bilgilendirme Notu

Açılır listeler oluştur:
- Alan: Sayısal, Eşit Ağırlık, Sözel, Dil, LGS, Kariyer Koçluğu, Diğer
- Sınav Türü: TYT, AYT, YDT, LGS, KPSS, DGS, Üniversite Hazırlık, Kariyer Planlama, Diğer
- Paket Türü: 4 Seans, 8 Seans, 12 Seans, Aylık Takip, Deneme Paketi, Kariyer Koçluğu, Diğer
- İletişim Sıklığı: Haftalık, 2 Haftada Bir, Aylık, Gerektiğinde
- Veli İletişim Tercihi: Telefon, WhatsApp, E-posta, Yüz Yüze, Online Görüşme

Dashboard’daki öğrenci bilgileri bu sayfadan otomatik çekilsin.

==================================================
3) 02_HEDEF_YOL_HARITASI SAYFASI
==================================================

Tablo sütunları:
- Hedef No
- Hedef Alanı
- Nihai Hedef
- Başlangıç Durumu
- Hedef Değer
- Ölçüm Birimi
- Hedef Tarihi
- Ara Kontrol Tarihi
- Başarı Kanıtı
- Öğrenci Aksiyonu
- Koç Aksiyonu
- Durum
- Gerçekleşme Oranı
- Revizyon Nedeni
- Son Güncelleme Tarihi
- Not

Hedef alanı açılır listesi:
- Akademik
- Sınav
- Çalışma Alışkanlığı
- Zaman Yönetimi
- Motivasyon
- Kariyer
- Üniversite Tercihi
- Sosyal / Kişisel Gelişim
- Diğer

Durum açılır listesi:
- Başlanmadı
- Devam Ediyor
- Riskte
- Tamamlandı
- Revize Edildi
- Ertelendi

Gerçekleşme oranı yüzde biçiminde olsun.
Hedef tarihi geçen ve tamamlanmayan satırlar kırmızı koşullu biçimlendirme ile vurgulansın.

==================================================
4) 03_SEANS_RANDEVU_TAKIBI SAYFASI
==================================================

Tablo sütunları:
- Seans No
- Tarih
- Saat
- Seans Türü
- Planlanan Süre (dk)
- Gerçekleşen Süre (dk)
- Seans Durumu
- İptal / Erteleme Nedeni
- Ana Gündem
- Görüşülen Konular
- Öğrenci Taahhüdü
- Koç Aksiyonu
- Sonraki Seans Tarihi
- Sonraki Seans Saati
- Not

Seans türü açılır listesi:
- Öğrenci Koçluğu
- Kariyer Koçluğu
- Veli Görüşmesi
- Deneme Analizi
- Planlama
- Takip Görüşmesi
- Online Görüşme
- Yüz Yüze Görüşme

Seans durumu açılır listesi:
- Planlandı
- Yapıldı
- İptal
- Ertelendi
- Öğrenci Katılmadı
- Koç Tarafından Ertelendi

Otomatik hesaplamalar:
- Yapılan seans sayısı = “Yapıldı” durumundaki kayıtların sayısı.
- Kalan seans sayısı = öğrenci bilgilerindeki paket toplam seans sayısı - yapılan seans.
- En yakın gelecekteki “Planlandı” durumundaki seansı Dashboard’a getir.
- İptal, ertelenen veya öğrenci katılmadı olan satırlar turuncu/kırmızı biçimde vurgulansın.

==================================================
5) 04_HAFTALIK_CALISMA_PLANI SAYFASI
==================================================

Tablo sütunları:
- Hafta No
- Hafta Başlangıç Tarihi
- Hafta Bitiş Tarihi
- Tarih
- Gün
- Ders
- Konu
- Çalışma Türü
- Planlanan Süre (dk)
- Hedef Soru Sayısı
- Hedef Çıktı
- Gerçekleşen Süre (dk)
- Çözülen Soru
- Süre Gerçekleşme Oranı
- Soru Gerçekleşme Oranı
- Genel Durum
- Öğrenci Notu
- Koç Notu
- Telafi Tarihi

Çalışma türü açılır listesi:
- Konu Anlatımı
- Soru Çözümü
- Deneme
- Yanlış Analizi
- Tekrar
- Video Ders
- Ödev
- Okuma
- Kariyer Araştırması
- CV / Portföy
- Diğer

Süre gerçekleşme oranı formülü:
`=IFERROR([@[Gerçekleşen Süre (dk)]]/[@[Planlanan Süre (dk)]],"")`

Soru gerçekleşme oranı formülü:
`=IFERROR([@[Çözülen Soru]]/[@[Hedef Soru Sayısı]],"")`

Genel durum formülü:
- Hem süre hem soru oranı %80 ve üzeriyse “Tamamlandı”
- %50-%79 arasındaysa “Kısmen Tamamlandı”
- %50 altındaysa “Eksik”
- Veri girilmemişse boş bırak

Koşullu biçimlendirme:
- Tamamlandı = yeşil
- Kısmen Tamamlandı = sarı
- Eksik = kırmızı

==================================================
6) 05_GUNLUK_CALISMA_KAYDI SAYFASI
==================================================

Tablo sütunları:
- Tarih
- Hafta No
- Gün
- Ders
- Konu
- Kaynak
- Çalışma Türü
- Planlanan Süre (dk)
- Gerçekleşen Süre (dk)
- Çözülen Soru
- Odak Puanı (1-5)
- Çalışma Ortamı
- En Büyük Engel
- Öğrencinin Kısa Notu
- Telafi Gerekli Mi?
- Telafi Tarihi
- Koç Değerlendirmesi

Çalışma ortamı açılır listesi:
- Ev
- Kütüphane
- Etüt Merkezi
- Okul
- Kurs
- Online
- Diğer

En büyük engel açılır listesi:
- Telefon / Sosyal Medya
- Erteleme
- Yorgunluk
- Konu Eksiği
- Süre Yönetimi
- Motivasyon
- Kaynak Eksikliği
- Dikkat Dağınıklığı
- Plan Dışı Durum
- Diğer

Odak puanında 1-5 arası veri doğrulama olsun.
1-2 kırmızı, 3 sarı, 4-5 yeşil koşullu biçimlendirme ile gösterilsin.

==================================================
7) 06_SORU_TAKIBI SAYFASI
==================================================

Bu sayfa merkezi veri giriş sayfasıdır.
Ders sayfalarındaki konu bazlı doğru, yanlış, boş, net ve soru sayıları buradan otomatik çekilecektir.

Tablo sütunları:
- Tarih
- Hafta No
- Sınav Türü
- Ders
- Konu
- Alt Konu
- Kaynak
- Test / Fasikül Adı
- Test No
- Toplam Soru
- Doğru
- Yanlış
- Boş
- Net
- Süre (dk)
- Soru Başına Süre
- Yanlış Türü
- Yanlış Analizi Yapıldı Mı?
- Tekrar Tarihi
- Tekrar Sonucu
- Not

Net formülü varsayılan olarak:
`=IFERROR([@Doğru]-([@Yanlış]/Ayarlar_Listeler!$B$2),"")`

Ayarlar_Listeler sayfasında B2 hücresinde “Yanlış Cevap Ceza Katsayısı” yer alsın ve varsayılan değeri 4 olsun.
Böylece net formülü sınav türüne göre istenirse kolayca değiştirilebilsin.

Toplam soru formülü:
`=IF(COUNTA([@Doğru],[@Yanlış],[@Boş])=0,"",[@Doğru]+[@Yanlış]+[@Boş])`

Soru başına süre formülü:
`=IFERROR([@[Süre (dk)]]/[@[Toplam Soru]],"")`

Yanlış türü açılır listesi:
- Bilgi Eksiği
- Kavram Yanılgısı
- Dikkat Hatası
- İşlem Hatası
- Süre Yönetimi
- Soruyu Yanlış Okuma
- Yorumlama Problemi
- Strateji Hatası
- Tahmin / Emin Olmadan İşaretleme
- Diğer

Yanlış analizi yapıldı mı? açılır listesi:
- Evet
- Hayır
- Kısmen

==================================================
8) 07_DENEME_ANALIZI SAYFASI
==================================================

Bu sayfa deneme verilerinin ders/test bazında girileceği merkezi alan olsun.
Aynı deneme için her ders veya test ayrı satırda yer alabilir.

Tablo sütunları:
- Deneme ID
- Deneme Tarihi
- Sınav Türü
- Deneme Adı
- Yayın / Kaynak
- Test / Ders
- Doğru
- Yanlış
- Boş
- Net
- Süre (dk)
- Süre Yeterli Mi?
- Önceki Denemeye Göre Net Farkı
- En Çok Zorlanılan Konu
- Baskın Hata Türü
- Denemeden Çıkan Ders
- Gelecek Hafta Odağı
- Somut Aksiyon
- Koç Notu

Net formülü:
`=IFERROR([@Doğru]-([@Yanlış]/Ayarlar_Listeler!$B$2),"")`

Süre yeterli mi? açılır listesi:
- Evet
- Hayır
- Kısmen

Deneme analizinde koşullu biçimlendirme uygula:
- Önceki denemeye göre net farkı pozitifse yeşil.
- Negatifse kırmızı.
- Sıfırsa sarı veya gri.

Dashboard’da son 5 denemenin toplam net trendini hesaplayacak yardımcı alanlar oluştur.
Her deneme için toplam net hesaplanabilsin.
Ders bazında son deneme netlerini Dashboard’a taşı.

==================================================
9) DERS SAYFALARI - GENEL YAPI
==================================================

Aşağıdaki ders sayfalarının her biri için aynı profesyonel yapı oluştur:
- 08_Ders_Turkce
- 09_Ders_Matematik
- 10_Ders_Geometri
- 11_Ders_Fizik
- 12_Ders_Kimya
- 13_Ders_Biyoloji
- 14_Ders_Edebiyat
- 15_Ders_Tarih
- 16_Ders_Cografya
- 17_Ders_Felsefe_Din

ÇOK ÖNEMLİ:
- Ders sayfaları günlük soru girişinin tekrar yapılacağı yerler değildir.
- Günlük doğru, yanlış, boş, net ve soru girişleri yalnızca `06_Soru_Takibi` sayfasında yapılmalıdır.
- Ders sayfaları, merkezi soru takip verilerini konu bazında otomatik özetleyen “konu hakimiyet haritası” olmalıdır.
- Her ders sayfasındaki konu listesi düzenlenebilir olmalıdır.
- Ders sayfalarında öğrenci için ilgili olmayan konular kolayca gizlenebilir veya satır silinebilir olmalıdır.

Her ders sayfasının üstünde şu özet kartlar olsun:
- Ders Adı
- Toplam Konu Sayısı
- Başlanmış Konu Sayısı
- Tamamlanan Konu Sayısı
- Tekrar Bekleyen Konu Sayısı
- Kritik / Zayıf Konu Sayısı
- Bu Derste Çözülen Toplam Soru
- Bu Dersteki Genel Doğruluk Oranı
- Son Deneme Neti
- Son Denemeye Göre Net Değişimi
- Son 4 Haftadaki Soru Sayısı

Her ders sayfasında ana konu tablosu şu sütunlardan oluşsun:
- Ünite
- Konu
- Alt Konu
- Öncelik
- Başlangıç Seviyesi
- Konu Durumu
- Konu Anlatımı Durumu
- Hedef Soru
- Çözülen Soru
- Doğru
- Yanlış
- Boş
- Net
- Doğruluk Oranı
- Son Çalışma Tarihi
- Son Tekrar Tarihi
- Sonraki Tekrar Tarihi
- Hakimiyet Seviyesi
- Baskın Hata Türü
- Kullanılan Ana Kaynak
- Sonraki Aksiyon
- Koç Notu

Öncelik açılır listesi:
- Çok Yüksek
- Yüksek
- Orta
- Düşük

Başlangıç seviyesi açılır listesi:
- 1/5
- 2/5
- 3/5
- 4/5
- 5/5

Konu durumu açılır listesi:
- Başlanmadı
- Konu Anlatımı Bekliyor
- Çalışılıyor
- İlk Tur Tamamlandı
- Pekişiyor
- Tekrar Gerekli
- Kritik / Zayıf
- Ustalaştı

Konu anlatımı durumu açılır listesi:
- Başlanmadı
- Devam Ediyor
- Tamamlandı
- Tekrar İzlenecek
- Gerekli Değil

Hakimiyet seviyesi açılır listesi:
- 1/5
- 2/5
- 3/5
- 4/5
- 5/5

Konu durumu için koşullu biçimlendirme:
- Başlanmadı = gri
- Konu Anlatımı Bekliyor = açık sarı
- Çalışılıyor = sarı
- İlk Tur Tamamlandı = açık mavi
- Pekişiyor = açık yeşil
- Tekrar Gerekli = turuncu
- Kritik / Zayıf = kırmızı
- Ustalaştı = koyu yeşil

DERS SAYFALARINDAKİ FORMÜLLER:
Her ders sayfasında B2 hücresinde o dersin adı sabit olarak yazsın.
Örneğin Matematik sayfasında B2 = “Matematik”.

Çözülen soru formülü:
`=IFERROR(SUMIFS(tblSoruTakip[Toplam Soru],tblSoruTakip[Ders],$B$2,tblSoruTakip[Konu],[@Konu]),"")`

Doğru formülü:
`=IFERROR(SUMIFS(tblSoruTakip[Doğru],tblSoruTakip[Ders],$B$2,tblSoruTakip[Konu],[@Konu]),"")`

Yanlış formülü:
`=IFERROR(SUMIFS(tblSoruTakip[Yanlış],tblSoruTakip[Ders],$B$2,tblSoruTakip[Konu],[@Konu]),"")`

Boş formülü:
`=IFERROR(SUMIFS(tblSoruTakip[Boş],tblSoruTakip[Ders],$B$2,tblSoruTakip[Konu],[@Konu]),"")`

Net formülü:
`=IFERROR(SUMIFS(tblSoruTakip[Net],tblSoruTakip[Ders],$B$2,tblSoruTakip[Konu],[@Konu]),"")`

Doğruluk oranı formülü:
`=IFERROR([@Doğru]/([@Doğru]+[@Yanlış]),"")`

Son çalışma tarihi mümkünse `MAXIFS` ile ilgili ders ve konu için son tarihi getirsin:
`=IFERROR(MAXIFS(tblSoruTakip[Tarih],tblSoruTakip[Ders],$B$2,tblSoruTakip[Konu],[@Konu]),"")`

Ders sayfalarının üst kısmında ayrıca şu mini grafikler olsun:
1. Konu bazlı doğruluk oranı grafiği.
2. Konu bazlı çözülen soru sayısı grafiği.
3. Konu durumu dağılımı grafiği.

HER DERS İÇİN BAŞLANGIÇ KONU LİSTESİ EKLE:
Konu listeleri düzenlenebilir olmalı. Genel YKS/TYT/AYT düzeyinde kullanılabilecek başlangıç konu başlıklarını aşağıdaki mantıkla yerleştir:

Türkçe:
- Sözcükte Anlam
- Cümlede Anlam
- Paragrafta Anlam
- Anlatım Biçimleri
- Düşünceyi Geliştirme Yolları
- Sözel Mantık
- Ses Bilgisi
- Yazım Kuralları
- Noktalama İşaretleri
- Sözcükte Yapı
- Sözcük Türleri
- Fiiller
- Fiilimsi
- Cümlenin Ögeleri
- Cümle Türleri
- Anlatım Bozukluğu

Matematik:
- Temel Kavramlar
- Sayı Basamakları
- Bölme ve Bölünebilme
- EBOB-EKOK
- Rasyonel Sayılar
- Basit Eşitsizlikler
- Mutlak Değer
- Üslü Sayılar
- Köklü Sayılar
- Çarpanlara Ayırma
- Oran Orantı
- Denklem Çözme
- Problemler
- Kümeler
- Mantık
- Fonksiyonlar
- Polinomlar
- Permütasyon Kombinasyon
- Olasılık
- İstatistik

Geometri:
- Doğruda Açılar
- Üçgenler
- Dörtgenler
- Çokgenler
- Çember ve Daire
- Analitik Geometri
- Katı Cisimler
- Trigonometri Temelleri

Fizik:
- Fizik Bilimine Giriş
- Madde ve Özellikleri
- Hareket ve Kuvvet
- İş Güç Enerji
- Isı Sıcaklık Genleşme
- Elektrostatik
- Elektrik ve Manyetizma
- Dalgalar
- Optik
- Basit Makineler
- Modern Fizik

Kimya:
- Kimya Bilimi
- Atom ve Periyodik Sistem
- Kimyasal Türler Arası Etkileşimler
- Maddenin Halleri
- Kimyanın Temel Kanunları
- Mol Kavramı
- Karışımlar
- Asit Baz Tuz
- Kimyasal Tepkimeler
- Kimya ve Elektrik
- Organik Kimya

Biyoloji:
- Canlıların Ortak Özellikleri
- Hücre
- Canlıların Sınıflandırılması
- Hücre Bölünmeleri
- Kalıtım
- Ekosistem Ekolojisi
- İnsan Fizyolojisi
- Bitki Biyolojisi
- Sistemler
- Fotosentez
- Solunum
- Protein Sentezi

Edebiyat:
- Güzel Sanatlar ve Edebiyat
- Şiir Bilgisi
- Söz Sanatları
- İslamiyet Öncesi Türk Edebiyatı
- Halk Edebiyatı
- Divan Edebiyatı
- Tanzimat Edebiyatı
- Servetifünun
- Fecriati
- Milli Edebiyat
- Cumhuriyet Dönemi Edebiyatı
- Yazar Eser Eşleştirme

Tarih:
- Tarih Bilimi
- İlk Uygarlıklar
- İlk Türk Devletleri
- İslam Tarihi
- Türk İslam Devletleri
- Osmanlı Kuruluş
- Osmanlı Yükselme
- Osmanlı Kültür Medeniyet
- Kurtuluş Savaşı
- Atatürk İlke ve İnkılapları
- Çağdaş Türk ve Dünya Tarihi

Coğrafya:
- Doğa ve İnsan
- Harita Bilgisi
- Dünya’nın Şekli ve Hareketleri
- İklim Bilgisi
- Yer Şekilleri
- Nüfus ve Yerleşme
- Ekonomik Faaliyetler
- Türkiye’nin Fiziki Coğrafyası
- Türkiye’nin Beşeri Coğrafyası
- Bölgeler Coğrafyası
- Çevre ve Toplum

Felsefe Din:
- Felsefenin Konusu
- Bilgi Felsefesi
- Varlık Felsefesi
- Ahlak Felsefesi
- Sanat Felsefesi
- Din Felsefesi
- Mantık
- İslam ve İnanç
- İbadet
- Ahlak ve Değerler
- Hz. Muhammed’in Hayatı
- Din Kültürü Temel Kavramları

Bu konu listelerinin başlangıç listesi olduğunu, koçun öğrenciye ve güncel müfredata göre değiştirebileceğini `23_Kullanim_Rehberi` sayfasında belirt.

==================================================
10) 18_KARIYER_YOL_HARITASI SAYFASI
==================================================

Tablo sütunları:
- Kayıt No
- Tarih
- İlgi Alanı
- Güçlü Yön
- Gelişim Alanı
- Mesleki Değer
- Hedef Meslek
- Araştırılan Bölüm
- Araştırılan Üniversite / Kurum
- Yapılan Araştırma
- Görüşülen Kişi / Kurum
- Portföy / CV Görevi
- Son Tarih
- Durum
- Öğrenci Notu
- Koç Notu
- Sonraki Aksiyon

Mesleki değer açılır listesi:
- Gelir
- Sosyal Etki
- Yaratıcılık
- Güvence
- Özgürlük
- Prestij
- Akademik Gelişim
- İnsanlarla Çalışma
- Teknoloji
- Girişimcilik
- Diğer

Durum açılır listesi:
- Araştırılacak
- Araştırılıyor
- Tamamlandı
- Beklemede
- Revize Edildi

==================================================
11) 19_VELI_TAKIBI SAYFASI
==================================================

Tablo sütunları:
- Görüşme No
- Görüşme Tarihi
- Görüşme Saati
- Görüşme Kanalı
- Görüşen Kişi
- Görüşme Nedeni
- Paylaşılan Olumlu Gelişme
- Paylaşılan Somut Veri
- Konuşulan Konu
- Belirlenen Zorluk
- Alınan Ortak Karar
- Velinin Aksiyonu
- Koçun Aksiyonu
- Sonraki Görüşme Tarihi
- Not

Görüşme kanalı açılır listesi:
- Telefon
- WhatsApp
- E-posta
- Yüz Yüze
- Online Görüşme
- Diğer

Görüşme nedeni açılır listesi:
- Düzenli Bilgilendirme
- Akademik Performans
- Çalışma Disiplini
- Motivasyon
- Seans Planlama
- Deneme Sonucu
- Risk Durumu
- Kariyer Planlama
- Diğer

Not alanı geniş olsun.
Veli görüşmelerinde “somut veri” alanı vurgulansın.

==================================================
12) 20_SEANS_SONU_NOTU SAYFASI
==================================================

Tablo sütunları:
- Seans No
- Tarih
- Seans Gündemi
- Geçen Haftanın Verisi
- Bu Haftanın Kazanımı
- Zorlanılan Alan
- Öğrencinin Fark Ettiği Durum
- Alınan Karar
- Öğrenci Taahhüdü
- Taahhüt Son Tarihi
- Koç Aksiyonu
- Koç Aksiyon Son Tarihi
- Sonraki Seans Gündemi
- Takip Durumu
- Not

Takip durumu açılır listesi:
- Takip Bekliyor
- Öğrenci Tamamladı
- Kısmen Tamamladı
- Tamamlamadı
- Koç Takibi Gerekli
- Sonraki Seansa Aktarıldı

Taahhüt son tarihi geçmiş ve tamamlanmamış satırlar kırmızı görünsün.

==================================================
13) 21_KONU_KAYNAK_GENEL SAYFASI
==================================================

Bu sayfa tüm derslerde kullanılan kaynakların genel takip alanı olsun.

Tablo sütunları:
- Ders
- Konu
- Kaynak Adı
- Kaynak Türü
- Seviye
- Başlama Tarihi
- Hedef Bitiş Tarihi
- Gerçek Bitiş Tarihi
- Toplam Test
- Tamamlanan Test
- Tamamlanma Oranı
- Kaynak Verimi
- Öğrenciye Uygunluk
- Sonraki Kullanım Kararı
- Not

Kaynak türü açılır listesi:
- Soru Bankası
- Fasikül
- Branş Denemesi
- Genel Deneme
- Video Ders
- Konu Anlatımı
- Mobil Uygulama
- PDF
- Diğer

Seviye açılır listesi:
- Temel
- Kolay
- Orta
- Zor
- İleri Seviye

Tamamlanma oranı formülü:
`=IFERROR([@[Tamamlanan Test]]/[@[Toplam Test]],"")`

Kaynak verimi açılır listesi:
- Çok Verimli
- Verimli
- Orta
- Uygun Değil
- Henüz Değerlendirilmedi

==================================================
14) 22_AYARLAR_LISTELER SAYFASI
==================================================

Bu sayfa düzenli, korumalı ve referans listelerinin bulunduğu teknik sayfa olsun.

Ayarlar:
- A1: Yanlış Cevap Ceza Katsayısı
- B2: 4
- A3: Kritik Konu Doğruluk Eşiği
- B3: %60
- A4: Başarılı Plan Gerçekleşme Eşiği
- B4: %80
- A5: Orta Risk Plan Eşiği
- B5: %50
- A6: Tekrar Uyarı Gün Sayısı
- B6: 14

Ayrıca aşağıdaki listeleri ayrı sütunlarda oluştur:
- Dersler
- Sınav türleri
- Alanlar
- Paket türleri
- Seans türleri
- Seans durumları
- Çalışma türleri
- Konu durumları
- Konu anlatımı durumları
- Hakimiyet seviyeleri
- Öncelik seviyeleri
- Yanlış türleri
- Çalışma ortamları
- Engeller
- Görüşme kanalları
- Veli görüşme nedenleri
- Kariyer durumları
- Kaynak türleri
- Kaynak seviyeleri
- Kaynak verimi seçenekleri

Bu listeleri adlandırılmış aralıklar veya veri doğrulama için güvenilir kaynaklar olarak kullan.

==================================================
15) 23_KULLANIM_REHBERI SAYFASI
==================================================

Bu sayfada kısa, anlaşılır ve görsel olarak düzenli bir kullanım rehberi oluştur.

Başlık:
“Gonca Lök Öğrenci ve Kariyer Koçluğu | Kullanım Rehberi”

Aşağıdaki açıklamalar yer alsın:
1. Bu dosya her öğrenci için ayrı kopyalanarak kullanılmalıdır.
2. İlk olarak `01_Ogrenci_Bilgileri` sayfası doldurulmalıdır.
3. Sonra hedefler `02_Hedef_Yol_Haritasi` sayfasına girilmelidir.
4. Seanslar `03_Seans_Randevu_Takibi` sayfasından takip edilmelidir.
5. Haftalık plan ve günlük çalışma kayıtları düzenli girilmelidir.
6. Günlük doğru-yanlış-boş bilgileri sadece `06_Soru_Takibi` sayfasına girilmelidir.
7. Ders sayfaları veri girişi için değil; konu bazlı otomatik performans özeti içindir.
8. Deneme sonrası mutlaka `07_Deneme_Analizi` sayfası doldurulmalıdır.
9. Her seans sonunda `20_Seans_Sonu_Notu` sayfası güncellenmelidir.
10. Veli görüşmelerinde yalnızca gerekli, somut ve ölçülebilir bilgiler paylaşılmalıdır.
11. Hassas sağlık, psikolojik tanı veya gereksiz özel veriler dosyada tutulmamalıdır.
12. Dosya parola koruması ve güvenli saklama ile korunmalıdır.
13. Ders konu listeleri öğrenciye, sınıf düzeyine ve güncel müfredata göre düzenlenebilir.
14. Dashboard’daki grafiklerin sağlıklı çalışması için günlük, haftalık, soru ve deneme kayıtları düzenli girilmelidir.

SON KALİTE KONTROLÜ:
Dosyayı oluşturmadan önce ve sonra şunları kontrol et:
- Tüm sayfalar oluşturulmuş mu?
- Tüm başlıklar Türkçe mi?
- Formüllerde `#REF!`, `#VALUE!`, `#DIV/0!` veya benzeri hata görünmüyor mu?
- Boş tablolar Dashboard’da hata üretmiyor mu?
- Açılır listeler çalışıyor mu?
- Koşullu biçimlendirmeler uygulanmış mı?
- Tarih, yüzde ve net biçimleri doğru mu?
- Ders sayfalarında konu listeleri bulunuyor mu?
- Merkezi soru takibi ile ders sayfaları arasında formül bağlantısı kurulmuş mu?
- Dashboard temiz, profesyonel ve okunabilir mi?
- Dosya gerçek bir `.xlsx` olarak kaydedilmiş mi?

İŞİN SONUNDA:
1. Excel dosyasını oluştur.
2. Dosyanın adını belirt.
3. Kısaca hangi sayfaların, formüllerin, grafiklerin ve otomasyonların eklendiğini özetle.
4. Kullanıcıya dosyayı indirilebilir şekilde sun.