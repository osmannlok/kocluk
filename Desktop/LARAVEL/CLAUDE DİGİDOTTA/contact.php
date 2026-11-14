<?php
/**
 * DigiDotta — İletişim Formu Mail Gönderici
 * Alıcı: digidotta@gmail.com
 *
 * Gereksinim: Sunucunuzda PHP mail() fonksiyonu veya PHPMailer (SMTP) aktif olmalı.
 * Gmail SMTP kullanımı için aşağıdaki PHPMailer bölümünü aktif edin.
 */

// ── Güvenlik: Sadece POST isteği kabul et ────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek yöntemi.']);
    exit;
}

// ── CORS / Referer koruması (isteğe bağlı, kendi domain'inize göre ayarlayın) ─
$allowed_origin = 'https://www.digidotta.com'; // Kendi domain'inizi yazın
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $allowed_origin);
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type');
}

header('Content-Type: application/json; charset=utf-8');

// ── Yardımcı: Güvenli temizleme fonksiyonu ──────────────────
function clean(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// ── Veri al ve temizle ───────────────────────────────────────
$name    = clean($_POST['name']    ?? '');
$email   = clean($_POST['email']   ?? '');
$company = clean($_POST['company'] ?? '');
$service = clean($_POST['service'] ?? '');
$message = clean($_POST['message'] ?? '');

// Honeypot spam koruması (gizli alan — botlar doldurur, insanlar doldurmaz)
$honeypot = $_POST['website'] ?? '';
if (!empty($honeypot)) {
    // Bot yakalandı — sessizce başarılı gibi göster
    echo json_encode(['success' => true, 'message' => 'Mesajınız iletildi.']);
    exit;
}

// ── Doğrulama ────────────────────────────────────────────────
$errors = [];

if (empty($name) || mb_strlen($name) < 2) {
    $errors[] = 'Ad Soyad zorunludur (en az 2 karakter).';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Geçerli bir e-posta adresi giriniz.';
}

if (empty($message) || mb_strlen($message) < 10) {
    $errors[] = 'Mesaj alanı zorunludur (en az 10 karakter).';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ── E-posta ayarları ─────────────────────────────────────────
$to      = 'digidotta@gmail.com';
$subject = '=?UTF-8?B?' . base64_encode('[DigiDotta] Yeni Proje Talebi — ' . $name) . '?=';

// ── Mail içeriği (HTML) ──────────────────────────────────────
$html_body = '<!DOCTYPE html>
<html lang="tr">
<head><meta charset="UTF-8"><title>DigiDotta — Yeni Mesaj</title></head>
<body style="margin:0;padding:0;background:#060b18;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#060b18;padding:40px 20px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0"
           style="background:#0a1528;border:1px solid rgba(0,212,255,.2);border-radius:8px;overflow:hidden;max-width:600px;">

      <!-- Header -->
      <tr>
        <td style="background:linear-gradient(135deg,#00d4ff,#00b8a9);padding:28px 36px;">
          <h1 style="margin:0;font-size:22px;font-weight:900;letter-spacing:4px;
                     color:#060b18;font-family:Arial,sans-serif;">
            DIGIDOTTA
          </h1>
          <p style="margin:6px 0 0;font-size:11px;letter-spacing:3px;color:#060b18;opacity:.8;">
            YENİ PROJE TALEBİ
          </p>
        </td>
      </tr>

      <!-- Body -->
      <tr>
        <td style="padding:32px 36px;">

          <!-- Gönderen bilgisi -->
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
            <tr>
              <td style="padding:12px 16px;background:rgba(0,212,255,.06);
                         border-left:3px solid #00d4ff;border-radius:0 4px 4px 0;">
                <p style="margin:0 0 4px;font-size:10px;letter-spacing:2px;
                           color:#00d4ff;text-transform:uppercase;">Gönderen</p>
                <p style="margin:0;font-size:16px;font-weight:700;color:#e8f4ff;">' . $name . '</p>
              </td>
            </tr>
          </table>

          <!-- Detay satırları -->
          <table width="100%" cellpadding="0" cellspacing="0"
                 style="border-collapse:separate;border-spacing:0 8px;margin-bottom:24px;">

            <tr>
              <td width="140" style="padding:10px 14px;background:rgba(0,212,255,.04);
                                     border:1px solid rgba(0,212,255,.1);border-radius:4px 0 0 4px;
                                     font-size:10px;letter-spacing:1.5px;color:#6a9ab8;
                                     text-transform:uppercase;">E-posta</td>
              <td style="padding:10px 14px;background:rgba(0,212,255,.04);
                         border:1px solid rgba(0,212,255,.1);border-left:none;border-radius:0 4px 4px 0;
                         font-size:13px;color:#a8cce8;">
                <a href="mailto:' . $email . '" style="color:#00d4ff;text-decoration:none;">' . $email . '</a>
              </td>
            </tr>';

if (!empty($company)) {
    $html_body .= '
            <tr>
              <td width="140" style="padding:10px 14px;background:rgba(0,212,255,.04);
                                     border:1px solid rgba(0,212,255,.1);border-radius:4px 0 0 4px;
                                     font-size:10px;letter-spacing:1.5px;color:#6a9ab8;
                                     text-transform:uppercase;">Şirket</td>
              <td style="padding:10px 14px;background:rgba(0,212,255,.04);
                         border:1px solid rgba(0,212,255,.1);border-left:none;border-radius:0 4px 4px 0;
                         font-size:13px;color:#a8cce8;">' . $company . '</td>
            </tr>';
}

if (!empty($service)) {
    $html_body .= '
            <tr>
              <td width="140" style="padding:10px 14px;background:rgba(0,212,255,.04);
                                     border:1px solid rgba(0,212,255,.1);border-radius:4px 0 0 4px;
                                     font-size:10px;letter-spacing:1.5px;color:#6a9ab8;
                                     text-transform:uppercase;">Hizmet</td>
              <td style="padding:10px 14px;background:rgba(0,212,255,.04);
                         border:1px solid rgba(0,212,255,.1);border-left:none;border-radius:0 4px 4px 0;
                         font-size:13px;color:#00d4ff;font-weight:700;">' . $service . '</td>
            </tr>';
}

$html_body .= '
          </table>

          <!-- Mesaj -->
          <p style="margin:0 0 10px;font-size:10px;letter-spacing:2px;
                    color:#6a9ab8;text-transform:uppercase;">Mesaj</p>
          <div style="padding:18px 20px;background:rgba(0,212,255,.04);
                      border:1px solid rgba(0,212,255,.1);border-radius:4px;">
            <p style="margin:0;font-size:14px;line-height:1.85;color:#a8cce8;
                      white-space:pre-wrap;">' . nl2br($message) . '</p>
          </div>

        </td>
      </tr>

      <!-- Footer -->
      <tr>
        <td style="padding:20px 36px;border-top:1px solid rgba(0,212,255,.1);">
          <p style="margin:0;font-size:10px;letter-spacing:1px;color:#2d5870;text-align:center;">
            Bu e-posta digidotta.com üzerindeki iletişim formundan otomatik olarak gönderilmiştir.<br>
            Gönderim zamanı: ' . date('d.m.Y H:i:s', time()) . ' UTC
          </p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';

// ── Düz metin yedek ─────────────────────────────────────────
$text_body = "DigiDotta — Yeni Proje Talebi\n";
$text_body .= str_repeat('=', 40) . "\n\n";
$text_body .= "Ad Soyad : $name\n";
$text_body .= "E-posta  : $email\n";
if (!empty($company)) $text_body .= "Şirket   : $company\n";
if (!empty($service)) $text_body .= "Hizmet   : $service\n";
$text_body .= "\nMesaj:\n" . str_repeat('-', 40) . "\n$message\n";
$text_body .= str_repeat('-', 40) . "\n";
$text_body .= "Gönderim: " . date('d.m.Y H:i:s') . " UTC\n";

// ── Otomatik yanıt (gönderene) ───────────────────────────────
$auto_reply_subject = '=?UTF-8?B?' . base64_encode('DigiDotta — Mesajınızı Aldık') . '?=';
$auto_reply_html = '<!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#060b18;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#060b18;padding:40px 20px;">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0"
           style="background:#0a1528;border:1px solid rgba(0,212,255,.2);border-radius:8px;max-width:560px;">
      <tr>
        <td style="background:linear-gradient(135deg,#00d4ff,#00b8a9);padding:28px 36px;">
          <h1 style="margin:0;font-size:20px;font-weight:900;letter-spacing:4px;color:#060b18;">DIGIDOTTA</h1>
        </td>
      </tr>
      <tr>
        <td style="padding:32px 36px;">
          <p style="font-size:15px;color:#e8f4ff;margin:0 0 16px;">Merhaba <strong>' . $name . '</strong>,</p>
          <p style="font-size:13px;line-height:1.85;color:#a8cce8;margin:0 0 20px;">
            Mesajınız başarıyla iletildi. En kısa sürede — genellikle <strong style="color:#00d4ff;">24 saat</strong> içinde — sizinle iletişime geçeceğiz.
          </p>
          <p style="font-size:13px;line-height:1.85;color:#6a9ab8;margin:0;">
            Acil bir konunuz varsa bize doğrudan ulaşabilirsiniz:<br>
            <a href="mailto:digidotta@gmail.com" style="color:#00d4ff;text-decoration:none;">digidotta@gmail.com</a><br>
            <a href="tel:+905467446722" style="color:#00d4ff;text-decoration:none;">0546 744 67 22</a>
          </p>
        </td>
      </tr>
      <tr>
        <td style="padding:16px 36px;border-top:1px solid rgba(0,212,255,.1);">
          <p style="margin:0;font-size:10px;color:#2d5870;text-align:center;">
            © ' . date('Y') . ' DigiDotta — digidotta.com
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body></html>';

// ── Mail gönder — PHP mail() ─────────────────────────────────
/*
 * SEÇENEK A: Sunucunuzda mail() aktifse aşağıdaki kodu kullanın.
 * SEÇENEK B: Gmail SMTP için aşağıdaki PHPMailer bölümüne bakın.
 */

$boundary = '----=_Part_' . md5(uniqid('', true));

$headers  = "From: DigiDotta Web <noreply@digidotta.com>\r\n";
$headers .= "Reply-To: $name <$email>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$headers .= "X-Priority: 1\r\n";

$body  = "--$boundary\r\n";
$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: base64\r\n\r\n";
$body .= chunk_split(base64_encode($text_body)) . "\r\n";
$body .= "--$boundary\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: base64\r\n\r\n";
$body .= chunk_split(base64_encode($html_body)) . "\r\n";
$body .= "--$boundary--";

$sent = mail($to, $subject, $body, $headers);

// Otomatik yanıt gönder (gönderene)
if ($sent) {
    $ar_headers  = "From: DigiDotta <digidotta@gmail.com>\r\n";
    $ar_headers .= "MIME-Version: 1.0\r\n";
    $ar_headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $ar_headers .= "Content-Transfer-Encoding: base64\r\n";
    mail($email, $auto_reply_subject, chunk_split(base64_encode($auto_reply_html)), $ar_headers);
}

if ($sent) {
    echo json_encode(['success' => true,  'message' => 'Mesajınız iletildi. En kısa sürede dönüş yapacağız.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Mesaj gönderilemedi. Lütfen tekrar deneyin veya doğrudan e-posta gönderin.']);
}

/*
 * ══════════════════════════════════════════════════════════════
 * SEÇENEK B — Gmail SMTP ile PHPMailer (Daha güvenilir)
 * ══════════════════════════════════════════════════════════════
 *
 * 1. Composer ile kur: composer require phpmailer/phpmailer
 *    VEYA phpmailer/src klasörünü manuel olarak projenize kopyalayın.
 *
 * 2. Gmail hesabınızda "Uygulama Şifresi" oluşturun:
 *    Google Hesabı → Güvenlik → 2 Adımlı Doğrulama → Uygulama şifreleri
 *
 * 3. Aşağıdaki kodu yukarıdaki mail() bloğunun yerine yapıştırın:
 *
 * ─────────────────────────────────────────────────────────────

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // veya: require 'phpmailer/src/PHPMailer.php'; vb.

try {
    $mail = new PHPMailer(true);

    // SMTP Ayarları
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'digidotta@gmail.com';   // Gmail adresiniz
    $mail->Password   = 'UYGULAMA_SIFRENIZ';     // Uygulama şifresi (Gmail şifresi değil!)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    // Gönderici & Alıcı
    $mail->setFrom('digidotta@gmail.com', 'DigiDotta Web');
    $mail->addAddress('digidotta@gmail.com', 'DigiDotta');
    $mail->addReplyTo($email, $name);

    // İçerik
    $mail->isHTML(true);
    $mail->Subject = '[DigiDotta] Yeni Proje Talebi — ' . $name;
    $mail->Body    = $html_body;
    $mail->AltBody = $text_body;

    $mail->send();

    // Otomatik yanıt
    $mail->clearAddresses();
    $mail->clearReplyTos();
    $mail->addAddress($email, $name);
    $mail->Subject = 'DigiDotta — Mesajınızı Aldık';
    $mail->Body    = $auto_reply_html;
    $mail->AltBody = "Merhaba $name, mesajınız alındı. 24 saat içinde dönüş yapacağız.";
    $mail->send();

    echo json_encode(['success' => true, 'message' => 'Mesajınız iletildi.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gönderim hatası: ' . $mail->ErrorInfo]);
}

 * ─────────────────────────────────────────────────────────────
 */
