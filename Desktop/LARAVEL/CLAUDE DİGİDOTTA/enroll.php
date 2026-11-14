<?php
/**
 * DigiDotta Akademi — Kayıt Formu Mail Gönderici
 * Alıcı: digidotta@gmail.com
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

function clean(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}

// Honeypot
if (!empty($_POST['website'])) {
    echo json_encode(['success' => true, 'message' => 'Başvurunuz alındı.']);
    exit;
}

$name   = clean($_POST['name']   ?? '');
$email  = clean($_POST['email']  ?? '');
$phone  = clean($_POST['phone']  ?? '');
$age    = clean($_POST['age']    ?? '');
$course = clean($_POST['course'] ?? '');
$level  = clean($_POST['level']  ?? '');
$job    = clean($_POST['job']    ?? '');
$source = clean($_POST['source'] ?? '');
$notes  = clean($_POST['notes']  ?? '');

// Doğrulama
$errors = [];
if (mb_strlen($name) < 2)                             $errors[] = 'Ad Soyad zorunludur.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))        $errors[] = 'Geçerli e-posta giriniz.';
if (empty($course))                                    $errors[] = 'Kurs seçimi zorunludur.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

$to      = 'digidotta@gmail.com';
$subject = '=?UTF-8?B?' . base64_encode('[DigiDotta Akademi] Yeni Kayıt Başvurusu — ' . $name) . '?=';
$date    = date('d.m.Y H:i') . ' UTC';

// ── HTML Mail ────────────────────────────────────────────────
$html = '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#060b18;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#060b18;padding:36px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0"
       style="background:#0a1528;border:1px solid rgba(0,212,255,.2);border-radius:8px;overflow:hidden;max-width:600px;">

  <!-- Header -->
  <tr><td style="background:linear-gradient(135deg,#00d4ff,#00ff9d);padding:26px 36px;">
    <h1 style="margin:0;font-size:20px;font-weight:900;letter-spacing:4px;color:#060b18;">DIGIDOTTA AKADEMİ</h1>
    <p style="margin:5px 0 0;font-size:10px;letter-spacing:3px;color:#060b18;opacity:.75;">YENİ KAYIT BAŞVURUSU</p>
  </td></tr>

  <!-- Kurs banner -->
  <tr><td style="padding:20px 36px 0;">
    <div style="background:rgba(0,255,157,.08);border:1px solid rgba(0,255,157,.25);border-radius:4px;padding:14px 20px;text-align:center;">
      <p style="margin:0;font-size:10px;letter-spacing:3px;color:#00d4ff;">BAŞVURULAN KURS</p>
      <p style="margin:6px 0 0;font-size:20px;font-weight:900;color:#00ff9d;letter-spacing:2px;">' . $course . '</p>
    </div>
  </td></tr>

  <!-- Body -->
  <tr><td style="padding:28px 36px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0 8px;">';

$rows = [
    ['Ad Soyad',      $name],
    ['E-posta',       '<a href="mailto:' . $email . '" style="color:#00d4ff;text-decoration:none;">' . $email . '</a>'],
    ['Telefon',       $phone  ?: '<em style="color:#2d5870">Belirtilmedi</em>'],
    ['Yaş',           $age    ?: '<em style="color:#2d5870">Belirtilmedi</em>'],
    ['Eğitim/Meslek', $job    ?: '<em style="color:#2d5870">Belirtilmedi</em>'],
    ['Deneyim',       $level  ?: '<em style="color:#2d5870">Belirtilmedi</em>'],
    ['Kaynak',        $source ?: '<em style="color:#2d5870">Belirtilmedi</em>'],
];

foreach ($rows as [$label, $val]) {
    $html .= '
    <tr>
      <td width="150" style="padding:9px 14px;background:rgba(0,212,255,.04);border:1px solid rgba(0,212,255,.1);
                             border-radius:4px 0 0 4px;font-size:10px;letter-spacing:1.5px;color:#6a9ab8;text-transform:uppercase;">'
             . $label . '</td>
      <td style="padding:9px 14px;background:rgba(0,212,255,.04);border:1px solid rgba(0,212,255,.1);
                 border-left:none;border-radius:0 4px 4px 0;font-size:13px;color:#a8cce8;">'
             . $val . '</td>
    </tr>';
}

if (!empty($notes)) {
    $html .= '
  </table>
  <p style="margin:18px 0 8px;font-size:10px;letter-spacing:2px;color:#6a9ab8;text-transform:uppercase;">Ek Notlar</p>
  <div style="padding:16px 18px;background:rgba(0,212,255,.04);border:1px solid rgba(0,212,255,.1);border-radius:4px;">
    <p style="margin:0;font-size:13px;line-height:1.85;color:#a8cce8;white-space:pre-wrap;">' . nl2br($notes) . '</p>
  </div>';
} else {
    $html .= '</table>';
}

$html .= '
  </td></tr>
  <tr><td style="padding:18px 36px;border-top:1px solid rgba(0,212,255,.1);">
    <p style="margin:0;font-size:10px;color:#1a3a50;text-align:center;">
      DigiDotta Akademi Kayıt Formu — Otomatik Bildirim — ' . $date . '
    </p>
  </td></tr>
</table></td></tr></table>
</body></html>';

// Düz metin
$txt  = "DigiDotta Akademi — Yeni Kayıt Başvurusu\n";
$txt .= str_repeat('=', 45) . "\n\n";
$txt .= "Kurs     : $course\n";
$txt .= "Ad Soyad : $name\n";
$txt .= "E-posta  : $email\n";
$txt .= "Telefon  : " . ($phone ?: '-') . "\n";
$txt .= "Yaş      : " . ($age   ?: '-') . "\n";
$txt .= "Meslek   : " . ($job   ?: '-') . "\n";
$txt .= "Deneyim  : " . ($level ?: '-') . "\n";
$txt .= "Kaynak   : " . ($source?: '-') . "\n";
if ($notes) $txt .= "\nNot:\n$notes\n";
$txt .= "\nGönderim: $date\n";

// ── Otomatik Yanıt (öğrenciye) ───────────────────────────────
$ar_html = '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#060b18;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#060b18;padding:36px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0"
       style="background:#0a1528;border:1px solid rgba(0,212,255,.2);border-radius:8px;max-width:560px;">
  <tr><td style="background:linear-gradient(135deg,#00d4ff,#00ff9d);padding:26px 36px;">
    <h1 style="margin:0;font-size:18px;font-weight:900;letter-spacing:4px;color:#060b18;">DIGIDOTTA AKADEMİ</h1>
  </td></tr>
  <tr><td style="padding:32px 36px;">
    <p style="font-size:15px;color:#e8f4ff;margin:0 0 14px;">Merhaba <strong>' . $name . '</strong>,</p>
    <p style="font-size:13px;line-height:1.85;color:#a8cce8;margin:0 0 16px;">
      <strong style="color:#00ff9d;">' . $course . '</strong> kursuna başvurunuz alındı.
    </p>
    <p style="font-size:13px;line-height:1.85;color:#a8cce8;margin:0 0 20px;">
      En kısa sürede — genellikle <strong style="color:#00d4ff;">24 saat</strong> içinde — ödeme seçenekleri
      ve ders takvimi hakkında sizi bilgilendireceğiz.
    </p>
    <p style="font-size:13px;color:#6a9ab8;margin:0;">
      Sorularınız için:<br>
      <a href="mailto:digidotta@gmail.com" style="color:#00d4ff;text-decoration:none;">digidotta@gmail.com</a><br>
      <a href="tel:+905467446722" style="color:#00d4ff;text-decoration:none;">0546 744 67 22</a>
    </p>
  </td></tr>
  <tr><td style="padding:16px 36px;border-top:1px solid rgba(0,212,255,.1);">
    <p style="margin:0;font-size:10px;color:#1a3a50;text-align:center;">© ' . date('Y') . ' DigiDotta Akademi</p>
  </td></tr>
</table></td></tr></table></body></html>';

// ── Gönder ───────────────────────────────────────────────────
$boundary = '----DigiDotta_' . md5(uniqid('', true));
$headers  = "From: DigiDotta Akademi <noreply@digidotta.com>\r\n"
          . "Reply-To: $name <$email>\r\n"
          . "MIME-Version: 1.0\r\n"
          . "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n"
          . "X-Priority: 1\r\n";

$body  = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
       . chunk_split(base64_encode($txt)) . "\r\n"
       . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
       . chunk_split(base64_encode($html)) . "\r\n"
       . "--$boundary--";

$sent = mail($to, $subject, $body, $headers);

if ($sent) {
    // Öğrenciye otomatik yanıt
    $ar_subj    = '=?UTF-8?B?' . base64_encode('DigiDotta Akademi — Başvurunuz Alındı') . '?=';
    $ar_headers = "From: DigiDotta Akademi <digidotta@gmail.com>\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n";
    mail($email, $ar_subj, chunk_split(base64_encode($ar_html)), $ar_headers);

    echo json_encode(['success' => true, 'message' => 'Başvurunuz alındı. 24 saat içinde dönüş yapacağız.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gönderim başarısız. Lütfen doğrudan e-posta ile ulaşın: digidotta@gmail.com']);
}

/*
 * ── Gmail SMTP (PHPMailer) ile göndermek istiyorsanız ────────
 * contact.php içindeki "SEÇENEK B" bölümünü buraya uygulayın.
 * $mail->Subject = '[DigiDotta Akademi] Yeni Kayıt — ' . $name;
 * $mail->Body    = $html;
 * $mail->AltBody = $txt;
 * ────────────────────────────────────────────────────────────
 */
