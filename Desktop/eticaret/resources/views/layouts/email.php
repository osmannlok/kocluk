<?php
// resources/views/layouts/email.php
$brand = (string) config('app.name', 'Yöresel Market');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($brand) ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f4f1ea;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f1ea;">
  <tr>
    <td align="center" style="padding:24px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background-color:#ffffff;border-radius:8px;">
        <tr>
          <td style="padding:20px 28px;background-color:#3f6b2e;border-radius:8px 8px 0 0;color:#ffffff;font-size:20px;font-weight:bold;">
            <?= e($brand) ?>
          </td>
        </tr>
        <tr>
          <td style="padding:28px;color:#2b2b2b;font-size:15px;line-height:1.6;">
            <?= $content ?>
          </td>
        </tr>
        <tr>
          <td style="padding:16px 28px;border-top:1px solid #e6e1d6;color:#7a7a7a;font-size:12px;line-height:1.5;">
            Bu e-posta <?= e($brand) ?> tarafından otomatik olarak gönderilmiştir. Lütfen yanıtlamayınız.
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>