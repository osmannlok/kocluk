# Güvenlik Kuralları (her dosyada uygulanır)

## Girdi/çıktı
1. SQL parametreli (PDO). Dinamik kolon/sıralama adı yalnızca beyaz liste.
2. Tüm girdi sunucuda doğrulanır (tip, uzunluk, biçim). Mass assignment yok (fillable).
3. Çıktı e() ile (ENT_QUOTES|ENT_SUBSTITUTE, UTF-8). HTML kabul eden alan (CMS) yalnızca HTMLPurifier'dan geçer.
## Oturum ve kimlik
4. Parola Argon2id, min 8; giriş ve yetki değişiminde session_regenerate_id(true).
5. Cookie: HttpOnly, Secure (HTTPS'te), SameSite=Lax, strict mode; boşta + mutlak zaman aşımı; user-agent değişirse oturum düşer.
6. Giriş denemeleri login_attempts'e; IP+e-posta bazlı kilit (5 hata/15 dk). Hata mesajları kullanıcı varlığını sızdırmaz.
7. Sıfırlama/doğrulama token'ları random_bytes, DB'de yalnızca SHA-256 hash, tek kullanımlık, süreli.
## Yetki
8. Her yönetim ucu permission middleware + controller içi require(). Varsayılan: reddet.
9. IDOR: kullanıcıya ait kayıt (sipariş, adres, sepet) her sorguda user_id ile sınırlanır.
10. Yönetici işlemleri audit_logs'a (kim, ne, eski/yeni, IP). Silme = soft delete. Eklemeli tablolara UPDATE/DELETE yok.
## İstek bütünlüğü
11. CSRF: tüm POST/PUT/DELETE (form _token veya X-CSRF-Token). Tek istisna PayTR bildirimi (hash doğrulaması).
12. Başlıklar: CSP (satır içi yok), X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy; HTTPS'te HSTS.
13. Hız sınırı: giriş, kayıt, sıfırlama, kupon, arama, iletişim.
14. Yönlendirmelerde yalnızca aynı site göreli yolu (açık yönlendirme yok).
## Ödeme ve sipariş
15. Tutar/kargo/indirim/kupon HER ZAMAN sunucuda yeniden hesaplanır; istemci fiyatı yok sayılır.
16. PayTR bildirimi: hash doğrulanmadan işlem yok; merchant_oid ile eşleşme; İDEMPOTENT; tutar siparişle eşleşmezse reddet + logla; doğrulanınca düz "OK".
17. Kart verisi alınmaz/saklanmaz/loglanmaz. Gizli anahtarlar yalnızca .env.
## Yükleme
18. Uzantı + finfo MIME + boyut + getimagesize; GD ile yeniden encode; rastgele ad; uploads'ta betik çalıştırma kapalı.
## Genel
19. display_errors=0; loglarda parola/token/kart/anahtar YOK (maskele).
20. Yalnızca public/ dışarı açık; .env, storage, scripts web'den erişilemez; dizin listeleme kapalı.
21. TC kimlik no hassastır: loglanmaz, admin ekranlarında maskeli.
22. Dış istek yalnızca PayTR API (TLS doğrulama açık, zaman aşımı var).