<?php
// resources/views/emails/password-reset.php
?>
<h1 style="margin:0 0 16px;font-size:20px;color:#2b2b2b;">Merhaba <?= e($name) ?>,</h1>
<p style="margin:0 0 16px;">Hesabınız için parola sıfırlama talebi aldık. Yeni parola belirlemek için aşağıdaki düğmeyi kullanın. Bağlantı <strong><?= e((string) $minutes) ?> dakika</strong> geçerlidir ve yalnızca bir kez kullanılabilir.</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
  <tr>
    <td style="background-color:#3f6b2e;border-radius:6px;">
      <a href="<?= e($url) ?>" style="display:inline-block;padding:12px 28px;color:#ffffff;font-weight:bold;text-decoration:none;">Parolamı Sıfırla</a>
    </td>
  </tr>
</table>
<p style="margin:0 0 8px;font-size:13px;color:#555555;">Düğme çalışmazsa aşağıdaki bağlantıyı tarayıcınıza yapıştırın:</p>
<p style="margin:0 0 16px;font-size:13px;word-break:break-all;"><a href="<?= e($url) ?>" style="color:#3f6b2e;"><?= e($url) ?></a></p>
<p style="margin:0 0 8px;font-size:13px;color:#555555;">Talep IP adresi: <?= e($ip) ?></p>
<p style="margin:0;font-size:13px;color:#555555;"><strong>Bu talebi siz yapmadıysanız bu e-postayı yok sayın;</strong> parolanız değişmeyecektir.</p>