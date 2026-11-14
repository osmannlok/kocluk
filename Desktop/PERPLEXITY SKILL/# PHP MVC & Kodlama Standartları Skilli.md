# PHP MVC & Kodlama Standartları Skilli

Projede üretilecek tüm PHP kodları aşağıdaki kurallara kesinlikle uymalıdır:
1. **Mimari:** Custom MVC (Model-View-Controller) yapısı kullanılacaktır. Sınıflar OOP (Nesne Yönelimli Programlama) prensiplerine uygun yazılmalıdır.
2. **Veritabanı Güvenliği:** Doğrudan SQL sorguları asla yazılmayacak; veritabanı bağlantısı için Singleton PDO ve **Prepared Statements** (Güvenli Parametre Bağlama) kesinlikle kullanılacaktır.
3. **Güvenlik Önlemleri:** 
   - Kullanıcıdan gelen tüm girdiler (POST/GET) `htmlspecialchars` veya filtreleme süzgeçlerinden geçirilmelidir (XSS koruması).
   - Şifreler kesinlikle düz metin saklanmayacak; `password_hash()` ile hash'lenecek ve `password_verify()` ile doğrulanacaktır.
4. **Hata Yönetimi:** Veritabanı ve kritik işlemlerde `try-catch` blokları kullanılmalıdır.