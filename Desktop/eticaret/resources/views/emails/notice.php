<?php
// resources/views/emails/notice.php  (5.5b: ek genel bildirim şablonu; girdi $name,$heading,$paragraphs[,$buttonText,$buttonUrl])
$paragraphs = is_array($paragraphs ?? null) ? $paragraphs : [];
$buttonText = isset($buttonText) && is_string($buttonText) ? $buttonText : null;
$buttonUrl = isset($buttonUrl) && is_string($buttonUrl) ? $buttonUrl : null;
?>
<h1 style="margin:0 0 16px;font-size:20px;color:#2b2b2b;"><?= e($heading) ?></h1>
<p style="margin:0 0 16px;">Merhaba <?= e($name) ?>,</p>
<?php foreach ($paragraphs as $paragraph): ?>
<p style="margin:0 0 16px;"><?= e((string) $paragraph) ?></p>
<?php endforeach; ?>
<?php if ($buttonText !== null && $buttonUrl !== null): ?>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
  <tr>
    <td style="background-color:#3f6b2e;border-radius:6px;">
      <a href="<?= e($buttonUrl) ?>" style="display:inline-block;padding:12px 28px;color:#ffffff;font-weight:bold;text-decoration:none;"><?= e($buttonText) ?></a>
    </td>
  </tr>
</table>
<?php endif; ?>