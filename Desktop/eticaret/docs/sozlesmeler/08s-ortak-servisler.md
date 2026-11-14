# 08s Ortak Servisler (GERÇEK koddan doğrulandı, bağlayıcı)

## AuditService (app/Services/AuditService.php): STATİK
AuditService::log(string $action, ?string $entityType = null, ?int $entityId = null, ?array $old = null, ?array $new = null): void
  - userId parametresi YOK. Kullanıcı, IP ve user-agent içeride otomatik alınır.
  - Hata ana işlemi düşürmez (içeride try/catch).
  - CLI ve PayTR bildirimi gibi oturumsuz bağlamda kullanıcı boş kalır ("sistem işlemi").
AuditService::security(string $type, string $severity, array $detail = []): void

## MailService (app/Services/MailService.php): ÖRNEK metodu, constructor YOK
(new MailService())->send(string $to, string $subject, string $template, array $data = []): bool
  - $template 'emails.' öneki OLMADAN: 'verify', 'password-reset', 'notice'.
  - İstisna fırlatmaz; hata = false. Çağıran false'u Logger::warning ile kaydeder, iş akışını bozmaz.
  - MAIL_DRIVER=log ise e-posta storage/mail/ altına .html yazılır.
  - Şablon dosya adı, şablon adıyla BİREBİR aynı olmalı (resources/views/emails/{ad}.php).

## UserSession (app/Models/UserSession.php): ÖRNEK metodu, constructor YOK
(new UserSession())->deleteByUser(int $userId, ?string $exceptSessionId = null): int
(new UserSession())->countByUser(int $userId): int
  - sessions.id, oturum kimliğinin SHA-256 özetidir (ham kimlik DEĞİL).

## E-posta şablonları (resources/views/emails/, layout: layouts.email)
- verify         : $name, $url, $hours
- password-reset : $name, $url, $minutes, $ip
- notice         : GENEL bildirim; $name, $heading, $paragraphs (dizi) [, $buttonText, $buttonUrl]
                   ("parola değişti" ve "zaten hesabınız var" e-postaları bunu kullanır)
- Eklenecek (her biri AYRI dosya): order-created (Oturum 8), order-paid (Oturum 9),
  order-shipped ve order-cancelled (Oturum 13)
- Basit bir bilgilendirme gerekiyorsa yeni dosya açmak yerine 'notice' kullanılabilir.
