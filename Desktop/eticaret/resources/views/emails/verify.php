<?php
// resources/views/emails/verify.php
$brand = (string) config('app.name', 'Yöresel Market');
?>
<h1 style="margin:0 0 16px;font-size:20px;color:#2b2b2b;">Merhaba <?= e($name) ?>,</h1>
<p style="margin:0 0 16px;"><?= e($brand) ?> hesabınızı oluşturduğunuz için teşekkürler. Hesabınızı etkinleştirmek için e-posta adresinizi doğrulayın. Bağlantı <strong><?= e((string) $hours) ?> saat</strong> geçerlidir.</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
  <tr>
    <td style="background-color:#3f6b2e;border-radius:6px;">
      <a href="<?= e($url) ?>" style="display:inline-block;padding:12px 28px;color:#ffffff;font-weight:bold;text-decoration:none;">E-postamı Doğrula</a>
    </td>
  </tr>
</table>
<p style="margin:0 0 8px;font-size:13px;color:#555555;">Düğme çalışmazsa aşağıdaki bağlantıyı tarayıcınıza yapıştırın:</p>
<p style="margin:0 0 16px;font-size:13px;word-break:break-all;"><a href="<?= e($url) ?>" style="color:#3f6b2e;"><?= e($url) ?></a></p>
<p style="margin:0;font-size:13px;color:#555555;">Bu kaydı siz yapmadıysanız bu e-postayı yok sayabilirsiniz.</p>