<?php
/**
 * contact_send.php
 * Bahar Yöresel - İletişim Formu İşleme (mail() ile e-posta gönderimi)
 */

require_once __DIR__ . '/config/session.php';

// --- AYARLAR (gerçek bilgilerle değiştir) ---
define('CONTACT_MAIL_TO', 'info@baharyoresel.com');        // TODO: Mesajların gideceği adres
define('CONTACT_MAIL_FROM', 'no-reply@baharyoresel.com');  // TODO: Sitenin kendi alan adından bir adres
define('CONTACT_MIN_INTERVAL', 30);                         // Aynı kullanıcı iki mesaj arası minimum saniye

/**
 * contact.php'ye yönlendirir ve script'i sonlandırır.
 */
function contactYonlendir(string $query = ''): void
{
    header('Location: /contact.php' . ($query !== '' ? '?' . $query : ''));
    exit;
}

/**
 * Tek satırlık alanlardaki satır sonu/tab karakterlerini temizler (e-posta başlığı enjeksiyonuna karşı).
 */
function tekSatir(string $metin): string
{
    return trim(preg_replace('/[\r\n\t]+/', ' ', $metin));
}

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    contactYonlendir();
}

// --- CSRF DOĞRULAMA ---
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['contact_error'] = 'Geçersiz istek (CSRF doğrulaması başarısız). Lütfen sayfayı yenileyip tekrar deneyin.';
    contactYonlendir('error=1');
}

// --- HONEYPOT: bot tuzağı doluysa mesajı gönderme, ama bota başarılıymış gibi davran ---
if (!empty($_POST['website'])) {
    $_SESSION['contact_success'] = 'Mesajınız başarıyla gönderildi. En kısa sürede size dönüş yapacağız.';
    contactYonlendir('success=1');
}

// --- HIZ SINIRI (spam/çift gönderim koruması) ---
$sonGonderim = $_SESSION['contact_last_sent'] ?? 0;
if ((time() - (int) $sonGonderim) < CONTACT_MIN_INTERVAL) {
    $_SESSION['contact_error'] = 'Çok sık mesaj gönderiyorsunuz. Lütfen birkaç saniye bekleyip tekrar deneyin.';
    contactYonlendir('error=1');
}

// --- FORM VERİLERİNİ AL ---
$name    = tekSatir((string) ($_POST['name'] ?? ''));
$email   = tekSatir((string) ($_POST['email'] ?? ''));
$subject = tekSatir((string) ($_POST['subject'] ?? ''));
$message = trim(str_replace(["\r\n", "\r"], "\n", (string) ($_POST['message'] ?? '')));

// Hata durumunda formu geri doldurmak için sakla
$_SESSION['contact_old'] = [
    'name'    => $name,
    'email'   => $email,
    'subject' => $subject,
    'message' => $message,
];

// --- VALIDATION ---
$hatalar = [];

if ($name === '') {
    $hatalar[] = 'Ad soyad boş bırakılamaz.';
} elseif (mb_strlen($name, 'UTF-8') > 100) {
    $hatalar[] = 'Ad soyad en fazla 100 karakter olabilir.';
}

if ($email === '') {
    $hatalar[] = 'E-posta adresi boş bırakılamaz.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email, 'UTF-8') > 150) {
    $hatalar[] = 'Geçerli bir e-posta adresi giriniz.';
}

if ($subject === '') {
    $hatalar[] = 'Konu boş bırakılamaz.';
} elseif (mb_strlen($subject, 'UTF-8') > 200) {
    $hatalar[] = 'Konu en fazla 200 karakter olabilir.';
}

if ($message === '') {
    $hatalar[] = 'Mesaj boş bırakılamaz.';
} elseif (mb_strlen($message, 'UTF-8') < 10) {
    $hatalar[] = 'Mesajınız en az 10 karakter olmalıdır.';
} elseif (mb_strlen($message, 'UTF-8') > 3000) {
    $hatalar[] = 'Mesajınız en fazla 3000 karakter olabilir.';
}

if (!empty($hatalar)) {
    $_SESSION['contact_error'] = implode(' ', $hatalar);
    contactYonlendir('error=1');
}

// --- E-POSTAYI HAZIRLA ---
$postaKonusu = mb_encode_mimeheader('[Bahar Yöresel İletişim] ' . $subject, 'UTF-8', 'B', "\r\n");

$govde  = "Web sitesi iletişim formundan yeni bir mesaj alındı.\n\n";
$govde .= "Ad Soyad : {$name}\n";
$govde .= "E-posta  : {$email}\n";
$govde .= "Konu     : {$subject}\n";
$govde .= "Tarih    : " . date('d.m.Y H:i') . "\n";
$govde .= "IP       : " . ($_SERVER['REMOTE_ADDR'] ?? '-') . "\n";
$govde .= "\n---------- Mesaj ----------\n\n";
$govde .= wordwrap($message, 70, "\n", false) . "\n";

// From: sitenin kendi adresi (kullanıcı adresi değil; spam filtrelerine takılmamak için).
// Reply-To: ziyaretçinin e-postası (yanıtla denince ona gider).
$basliklar = [
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
    'From'         => mb_encode_mimeheader('Bahar Yöresel', 'UTF-8', 'B') . ' <' . CONTACT_MAIL_FROM . '>',
    'Reply-To'     => $email,
    'X-Mailer'     => 'PHP/' . phpversion(),
];

// --- GÖNDER ---
$gonderildi = @mail(CONTACT_MAIL_TO, $postaKonusu, $govde, $basliklar);

if ($gonderildi) {
    $_SESSION['contact_last_sent'] = time();
    unset($_SESSION['contact_old']); // Başarılı gönderimde formu temiz aç
    $_SESSION['contact_success'] = 'Mesajınız başarıyla gönderildi. En kısa sürede size dönüş yapacağız.';
    contactYonlendir('success=1');
}

error_log('İletişim formu e-postası gönderilemedi. Gönderen: ' . $email);
$_SESSION['contact_error'] = 'Mesajınız gönderilirken bir sorun oluştu. Lütfen daha sonra tekrar deneyin veya bizi telefonla arayın.';
contactYonlendir('error=1');