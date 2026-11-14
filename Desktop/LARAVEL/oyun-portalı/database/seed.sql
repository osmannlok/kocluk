SET NAMES utf8mb4;

-- ---------------------------
-- eksik alan tamamlama: one campain icin featured flag
-- ---------------------------
ALTER TABLE games ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER is_premium;

-- ---------------------------
-- kategoriler
-- ---------------------------
INSERT INTO categories (name, slug, description) VALUES
('Matematik', 'matematik', 'Sayılar, işlemler ve problem çözme becerisi kazandıran oyunlar'),
('Kelime', 'kelime', 'Okuma, yazma ve kelime dağarcığı geliştiren oyunlar'),
('Mantık', 'mantik', 'Mantıksal düşünme ve problem çözme oyunları'),
('Hafıza', 'hafiza', 'Görsel ve işitsel hafızayı güçlendiren oyunlar'),
('Dikkat', 'dikkat', 'Dikkat ve odaklanma becerisi geliştiren oyunlar');

-- ---------------------------
-- oyunlar
-- ---------------------------
INSERT INTO games (category_id, title, slug, description, age_group, target_skill, cover_image, is_premium, is_featured, is_active) VALUES
(
    (SELECT id FROM categories WHERE slug = 'matematik'),
    'Sayı Avcısı',
    'sayi-avcisi',
    'Doğru toplama ve çıkarma işlemlerini bularak puan topladığın hızlı tempolu bir matematik oyunu. Çocuklar ekranda beliren sayı denklemlerinden doğru sonucu seçerek seviye atlar.',
    '6-9',
    'Temel toplama ve çıkarma işlemlerinde hız ve doğruluk',
    '/assets/images/games/sayi-avcisi.webp',
    0,
    1,
    1
),
(
    (SELECT id FROM categories WHERE slug = 'kelime'),
    'Kelime Bulmaca',
    'kelime-bulmaca',
    'Karışık harflerden anlamlı kelimeler türeterek kelime dağarcığını geliştiren bir oyun. Her doğru kelimede yeni bir harf seti açılır ve zorluk kademeli olarak artar.',
    '7-10',
    'Kelime dağarcığı ve okuma-yazma becerisi',
    '/assets/images/games/kelime-bulmaca.webp',
    0,
    1,
    1
),
(
    (SELECT id FROM categories WHERE slug = 'hafiza'),
    'Hafıza Kartları',
    'hafiza-kartlari',
    'Eşleşen kart çiftlerini bularak görsel hafızayı güçlendiren klasik bir eşleştirme oyunu. Hayvan, meyve ve şekil temalı kart setleriyle oynanır.',
    '6-8',
    'Görsel hafıza ve dikkat',
    '/assets/images/games/hafiza-kartlari.webp',
    0,
    1,
    1
),
(
    (SELECT id FROM categories WHERE slug = 'mantik'),
    'Şekil Bulmacası',
    'sekil-bulmacasi',
    'Verilen örüntüyü tamamlayacak doğru şekli seçerek mantıksal çıkarım becerisini geliştiren bir bulmaca oyunu. Her bölümde örüntüler biraz daha karmaşıklaşır.',
    '8-11',
    'Örüntü tanıma ve mantıksal çıkarım',
    '/assets/images/games/sekil-bulmacasi.webp',
    0,
    0,
    1
),
(
    (SELECT id FROM categories WHERE slug = 'dikkat'),
    'Fark Bul',
    'fark-bul',
    'İki görsel arasındaki farkları süre dolmadan bularak dikkat ve gözlem becerisini geliştiren bir oyun. Her seviyede fark sayısı ve görsel karmaşıklığı artar.',
    '6-10',
    'Görsel dikkat ve odaklanma',
    '/assets/images/games/fark-bul.webp',
    1,
    0,
    1
);

-- ---------------------------
-- blog yazilari
-- ---------------------------
INSERT INTO blog_posts (user_id, title, slug, excerpt, content, cover_image, is_published, published_at) VALUES
(
    NULL,
    'Çocuklar İçin Eğitici Oyunların Faydaları',
    'cocuklar-icin-egitici-oyunlarin-faydalari',
    'Eğitici oyunların çocukların bilişsel gelişimine katkılarını inceliyoruz.',
    'Eğitici oyunlar, çocukların matematik, dil ve problem çözme becerilerini eğlenceli bir şekilde geliştirmelerine yardımcı olur. Kısa ve tekrar oynanabilir oyun formatları, çocukların dikkatini uzun süre canlı tutar ve öğrenmeyi bir alışkanlığa dönüştürür.\n\nÖrneğin Sayı Avcısı gibi hızlı tempolu bir matematik oyunu, işlem becerisini oyun içinde tekrar ettirerek kalıcı öğrenmeyi destekler. Ara sıra kısa molalarla oynanan bu tür oyunlar, ebeveynler için de çocuklarının gelişimini takip etmenin pratik bir yolunu sunar.',
    '/assets/images/blog/egitici-oyunlarin-faydalari.webp',
    1,
    '2026-06-02 09:00:00'
),
(
    NULL,
    'Oyunlaştırma ile Öğrenmeyi Eğlenceli Hale Getirmek',
    'oyunlastirma-ile-ogrenmeyi-eglenceli-hale-getirmek',
    'Oyunlaştırma tekniklerinin sınıf ve ev ortamında öğrenmeye katkısı.',
    'Oyunlaştırma (gamification), puan, rozet ve seviye sistemleri gibi oyun mekaniklerini eğitim süreçlerine entegre eden bir yaklaşımdır. Bu yaklaşım, çocukların motivasyonunu artırır ve öğrenme sürecini daha sürdürülebilir kılar.\n\nPlatformumuzdaki rozet sistemi tam olarak bu amaçla tasarlandı: bir çocuk "İlk Adım" rozetini kazandığında küçük bir başarı hissi yaşar ve bir sonraki oyuna daha istekli başlar. Öğretmenler ve ebeveynler, bu mekanikleri kullanarak çocukların ilerlemesini somut şekilde takip edebilir.',
    '/assets/images/blog/oyunlastirma.webp',
    1,
    '2026-06-09 09:00:00'
),
(
    NULL,
    'Ebeveynler İçin Ekran Süresi Rehberi',
    'ebeveynler-icin-ekran-suresi-rehberi',
    'Çocuğunuzun ekran süresini dengeli ve verimli hale getirmek için pratik öneriler.',
    'Ekran süresi yönetimi, dijital çağda ebeveynlerin en çok merak ettiği konulardan biridir. Eğitici içeriklere ayrılan süre, pasif izleme sürelerinden ayrı planlanmalıdır.\n\nGünlük 15-20 dakikalık kısa ve hedefli oyun seansları, çocuğun hem dijital okuryazarlığını hem de akademik becerilerini destekler. Hafıza Kartları gibi kısa süren ama tekrar oynanabilir oyunlar, bu tarz planlı kullanım için idealdir.',
    '/assets/images/blog/ekran-suresi-rehberi.webp',
    1,
    '2026-06-16 09:00:00'
),
(
    NULL,
    'Öğretmenler İçin Sınıf İçi Kullanım Notları',
    'ogretmenler-icin-sinif-ici-kullanim-notlari',
    'Eğitim Oyun Portalı\'nı sınıf etkinliklerine nasıl entegre edebileceğinize dair pratik notlar.',
    'Öğretmenler, ders arası kısa etkinliklerde veya erken bitiren öğrenciler için tamamlayıcı görev olarak platformdaki oyunları kullanabilir. Özellikle Kelime Bulmaca ve Şekil Bulmacası, sınıf içi grup çalışmalarında rekabetçi ama sağlıklı bir öğrenme ortamı oluşturur.\n\nSkor ve rozet sistemi, öğretmenlere öğrencilerin hangi alanlarda daha fazla pratiğe ihtiyaç duyduğunu gözlemleme fırsatı da sunar.',
    '/assets/images/blog/ogretmen-kullanim-notlari.webp',
    1,
    '2026-06-23 09:00:00'
),
(
    NULL,
    'Python ve JavaScript ile Basit Bir Eğitim Oyunu Nasıl Geliştirilir',
    'python-ve-javascript-ile-basit-bir-egitim-oyunu-nasil-gelistirilir',
    'Geliştirme sürecimizden yola çıkarak basit bir eğitim oyununun temel adımlarını anlatıyoruz.',
    'Basit bir eğitim oyunu geliştirirken önce hedef yaş grubu ve kazanım net şekilde tanımlanmalıdır. Sayı Avcısı örneğinde olduğu gibi, oyun mekaniği tek bir beceriye odaklanınca hem geliştirme hem de öğrenme etkisi daha net olur.\n\nPrototip aşamasında Python ile oyun mantığı test edilebilir, ardından tarayıcıda çalışacak sürüm HTML5 ve JavaScript ile geliştirilir. Kısa döngülü test ve geri bildirim süreci, oyunun hem eğlenceli hem öğretici kalmasını sağlar.',
    '/assets/images/blog/python-js-oyun-gelistirme.webp',
    1,
    '2026-06-30 09:00:00'
);

-- ---------------------------
-- rozetler
-- ---------------------------
INSERT INTO badges (name, description, icon) VALUES
('İlk Adım', 'İlk oyununu tamamladın', '/assets/images/badges/ilk-adim.svg'),
('Matematik Ustası', '10 matematik oyunu tamamladın', '/assets/images/badges/matematik-ustasi.svg'),
('Kelime Kaşifi', '10 kelime oyunu tamamladın', '/assets/images/badges/kelime-kasifi.svg'),
('Hafıza Şampiyonu', 'Hafıza oyununda üst üste 5 kez kazandın', '/assets/images/badges/hafiza-sampiyonu.svg'),
('Dikkat Dedektifi', 'Fark Bul oyununda 20 farkı süresinde buldun', '/assets/images/badges/dikkat-dedektifi.svg');

-- ---------------------------
-- site ayarlari
-- ---------------------------
INSERT INTO settings (`key`, `value`) VALUES
('site_title', 'Eğitim Oyun Portalı'),
('site_description', 'Çocuklar için eğitici ve eğlenceli oyunlar'),
('featured_games_count', '6'),
('maintenance_mode', '0');

-- ---------------------------
-- reklam alanlari
-- ---------------------------
INSERT INTO ads_slots (name, placement, code, is_active) VALUES
('Ana Sayfa Üst Banner', 'home_top', NULL, 0),
('Oyun Detay Yan Alan', 'game_sidebar', NULL, 0),
('Blog İçi Reklam', 'blog_in_content', NULL, 0);

-- ---------------------------
-- statik sayfalar
-- ---------------------------
INSERT INTO site_pages (title, slug, content) VALUES
(
    'İletişim',
    'iletisim',
    'Bizimle iletişime geçmek için asagidaki e-posta adresini kullanabilirsiniz: info@egitimoyunportali.com\n\nSorularınızı ve önerilerinizi bize iletmekten çekinmeyin.'
),
(
    'Gizlilik Politikası',
    'gizlilik',
    'Eğitim Oyun Portalı olarak çocukların ve ailelerin gizliliğine büyük önem veriyoruz. Toplanan veriler yalnızca hizmet kalitesini artırmak amacıyla kullanılır ve üçüncü taraflarla paylaşılmaz.\n\nHesap bilgileriniz güvenli şekilde saklanır ve şifreleriniz hash algoritmalarıyla korunur.'
),
(
    'Kullanım Şartları',
    'kullanim-sartlari',
    'Bu platformu kullanarak aşağıdaki şartları kabul etmiş sayılırsınız: Hesabınızın güvenliğinden siz sorumlusunuz, içerikler yalnızca eğitim amaçlı kullanılmalıdır, platformun kötüye kullanımı hesabınızın askıya alınmasına neden olabilir.\n\nSorularınız için iletişim sayfamızdan bize ulaşabilirsiniz.'
);

-- ---------------------------
-- uyelik planlari
-- ---------------------------
INSERT INTO membership_plans (name, slug, description, price_cents, duration_days, is_active) VALUES
('Aylık Premium', 'aylik-premium', 'Tüm premium oyunlara ve reklamsız deneyime 1 aylık erişim.', 4900, 30, 1),
('Yıllık Premium', 'yillik-premium', 'Tüm premium oyunlara ve reklamsız deneyime 1 yıllık erişim, 2 ay bedava.', 49900, 365, 1);


SET NAMES utf8mb4;

-- ---------------------------
-- 10 yeni oyun (oynanabilir HTML5/JS versiyonlar)
-- ---------------------------
INSERT INTO games (category_id, title, slug, description, age_group, target_skill, cover_image, is_premium, is_featured, is_active) VALUES
(
    (SELECT id FROM categories WHERE slug = 'matematik'),
    'Sayı Eşleştirme',
    'sayi-eslestirme',
    'Aynı değere sahip sayı ve rakam kartlarını eşleştirerek sayı tanıma becerisini geliştiren bir oyun.',
    '6-8',
    'Sayı tanıma ve eşleştirme becerisi',
    '/assets/images/games/sayi-eslestirme.webp',
    0,
    1,
    1
),
(
    (SELECT id FROM categories WHERE slug = 'matematik'),
    'Toplama Hız Yarışı',
    'toplama-hiz-yarisi',
    'Süre dolmadan doğru toplama işlemlerini seçerek puan topladığın hızlı tempolu bir matematik yarışı.',
    '7-10',
    'Toplama işleminde hız ve doğruluk',
    '/assets/images/games/toplama-hiz-yarisi.webp',
    0,
    0,
    1
),
(
    (SELECT id FROM categories WHERE slug = 'matematik'),
    'Çarpım Tablosu Kartları',
    'carpim-tablosu-kartlari',
    'Kart çevirerek çarpım tablosu sorularını yanıtladığın, tekrar oynanabilir bir alıştırma oyunu.',
    '8-11',
    'Çarpım tablosu ezberi ve hız',
    '/assets/images/games/carpim-tablosu-kartlari.webp',
    0,
    0,
    1
),
(
    (SELECT id FROM categories WHERE slug = 'hafiza'),
    'Hafıza Kartları Pro',
    'hafiza-kartlari-pro',
    'Eşleşen kart çiftlerini en az hamlede bularak görsel hafızanı test ettiğin gelişmiş bir eşleştirme oyunu.',
    '6-9',
    'Görsel hafıza ve konsantrasyon',
    '/assets/images/games/hafiza-kartlari-pro.webp',
    0,
    1,
    1
),
(
    (SELECT id FROM categories WHERE slug = 'kelime'),
    'Kelime Bulmaca Plus',
    'kelime-bulmaca-plus',
    'Karışık harflerden anlamlı kelimeler türeterek kelime dağarcığını genişlettiğin bir bulmaca oyunu.',
    '7-11',
    'Kelime dağarcığı ve okuma-yazma',
    '/assets/images/games/kelime-bulmaca-plus.webp',
    0,
    0,
    1
),
(
    (SELECT id FROM categories WHERE slug = 'mantik'),
    'Mantık Dizisi Tamamlama',
    'mantik-dizisi-tamamlama',
    'Verilen sayı veya şekil dizisindeki kuralı bularak sırada gelecek doğru öğeyi seçtiğin bir mantık oyunu.',
    '8-12',
    'Örüntü tanıma ve mantıksal çıkarım',
    '/assets/images/games/mantik-dizisi-tamamlama.webp',
    0,
    1,
    1
),
(
    (SELECT id FROM categories WHERE slug = 'dikkat'),
    'Farkı Bul',
    'farki-bul',
    'İki görsel arasındaki farkları süre dolmadan bularak dikkat ve gözlem becerisini geliştirdiğin bir oyun.',
    '6-10',
    'Görsel dikkat ve gözlem',
    '/assets/images/games/farki-bul.webp',
    0,
    0,
    1
),
(
    (SELECT id FROM categories WHERE slug = 'mantik'),
    'Şekil Sıralama',
    'sekil-siralama',
    'Şekilleri büyükten küçüğe veya belirtilen kurala göre doğru sıraya dizdiğin bir mantık oyunu.',
    '6-9',
    'Sıralama becerisi ve mantıksal düşünme',
    '/assets/images/games/sekil-siralama.webp',
    0,
    0,
    1
),
(
    (SELECT id FROM categories WHERE slug = 'matematik'),
    'Hedef Sayıya Ulaş',
    'hedef-sayiya-ulas',
    'Verilen sayılarla toplama ve çıkarma işlemleri yaparak hedef sayıya ulaşmaya çalıştığın bir strateji oyunu.',
    '9-12',
    'İşlem stratejisi ve problem çözme',
    '/assets/images/games/hedef-sayiya-ulas.webp',
    1,
    0,
    1
),
(
    (SELECT id FROM categories WHERE slug = 'dikkat'),
    'Dikkat Süresi Oyunu',
    'dikkat-suresi-oyunu',
    'Ekranda beliren doğru sembolü, yanlışlar arasından süre dolmadan tıklayarak dikkat süreni test ettiğin bir oyun.',
    '6-10',
    'Sürdürülebilir dikkat ve tepki hızı',
    '/assets/images/games/dikkat-suresi-oyunu.webp',
    1,
    0,
    1
);