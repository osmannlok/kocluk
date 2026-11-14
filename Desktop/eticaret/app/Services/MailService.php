<?php
// app/Services/MailService.php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Core\View;
use App\Models\Setting;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 * PHPMailer sarmalayıcı. Hata fırlatmaz; başarısızlıkta false döner.
 */
final class MailService
{
    public function send(string $to, string $subject, string $template, array $data = []): bool
    {
        try {
            // Neden temizlik: başlık enjeksiyonunu (CR-LF) kaynağında keser.
            $to = $this->cleanHeader($to);
            $subject = $this->cleanHeader($subject);

            if (filter_var($to, FILTER_VALIDATE_EMAIL) === false || $subject === '') {
                Logger::warning('Geçersiz e-posta alıcısı veya konusu', ['template' => $template]);
                return false;
            }

            if (preg_match('/^[a-z0-9_-]+$/', $template) !== 1) {
                Logger::warning('Geçersiz e-posta şablon adı', ['template' => $template]);
                return false;
            }

            $html = View::render('emails.' . $template, $data, 'layouts.email');
            $driver = strtolower((string) Config::get('app.mail.driver', 'log'));

            return $driver === 'smtp'
                ? $this->sendSmtp($to, $subject, $html)
                : $this->writeToLog($to, $subject, $html);
        } catch (Throwable $e) {
            Logger::error('E-posta gönderilemedi', ['template' => $template, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function sendSmtp(string $to, string $subject, string $html): bool
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) Config::get('app.mail.host', '');
        $mail->Port = (int) Config::get('app.mail.port', 587);
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 10;

        $user = (string) Config::get('app.mail.user', '');
        $mail->SMTPAuth = $user !== '';
        if ($user !== '') {
            $mail->Username = $user;
            $mail->Password = (string) Config::get('app.mail.pass', '');
        }

        $encryption = strtolower((string) Config::get('app.mail.encryption', 'tls'));
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        // Neden açık doğrulama: ortadaki adam (MITM) saldırısına karşı sertifika kontrolü şart.
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ];

        $mail->setFrom(
            $this->cleanHeader((string) Config::get('app.mail.from_address', '')),
            $this->fromName()
        );
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $this->toText($html);

        return $mail->send();
    }

    /** Geliştirme sürücüsü: e-postayı storage/mail altına .html olarak yazar (storage web'e kapalı). */
    private function writeToLog(string $to, string $subject, string $html): bool
    {
        $dir = Config::basePath('storage/mail');
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return false;
        }

        $file = $dir . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.html';
        $header = '<!-- To: ' . str_replace('--', '- -', $to)
            . ' | Subject: ' . str_replace('--', '- -', $subject) . ' -->' . "\n";

        return file_put_contents($file, $header . $html, LOCK_EX) !== false;
    }

    private function fromName(): string
    {
        try {
            $name = (string) Setting::get('mail.from_name', (string) Config::get('app.name', 'Yöresel Market'));
        } catch (Throwable) {
            $name = (string) Config::get('app.name', 'Yöresel Market');
        }

        $name = $this->cleanHeader($name);

        return $name !== '' ? $name : 'Yöresel Market';
    }

    private function cleanHeader(string $value): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);

        return trim($clean ?? '');
    }

    private function toText(string $html): string
    {
        $text = preg_replace('#<(style|head)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $text = preg_replace('/<a\s[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', $text) ?? $text;
        $text = preg_replace('#<(br|/p|/tr|/h1|/h2)[^>]*>#i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}