<?php
declare(strict_types=1);

// resources/views/partials/announcement.php
// Duyuru şeridi: Announcement::activeList dönüşümlü; yoksa shipping.free_threshold'tan otomatik cümle.

use App\Models\Announcement;

$settings = isset($settings) && is_array($settings) ? $settings : [];
$items = [];

foreach ((new Announcement())->activeList() as $row) {
    $msg = trim((string) ($row['message'] ?? ''));
    if ($msg === '') {
        continue;
    }
    $link = trim((string) ($row['link_url'] ?? ''));
    // Açık yönlendirme/javascript: şemalarına karşı yalnızca site içi yol veya http(s) kabul edilir.
    if ($link !== '' && !preg_match('#^(/(?!/)|https?://)#i', $link)) {
        $link = '';
    }
    $items[] = ['message' => $msg, 'link' => $link];
}

if ($items === []) {
    $threshold = (string) ($settings['shipping.free_threshold'] ?? '0');
    if (is_numeric($threshold) && (float) $threshold > 0) {
        $items[] = ['message' => money($threshold) . ' ve üzeri alışverişlerde kargo ücretsiz!', 'link' => ''];
    }
}

if ($items === []) {
    return;
}
?>
<div id="ymAnnouncement" class="ym-announcement" role="region" aria-label="Duyurular">
    <div class="container ym-announcement__inner">
<?php foreach ($items as $i => $item): ?>
        <p class="ym-announcement__item"<?= $i > 0 ? ' hidden' : '' ?>>
<?php if ($item['link'] !== ''): ?>
            <a href="<?= e(str_starts_with($item['link'], '/') ? url($item['link']) : $item['link']) ?>"><?= e($item['message']) ?></a>
<?php else: ?>
            <?= e($item['message']) ?>
<?php endif; ?>
        </p>
<?php endforeach; ?>
        <button type="button" class="ym-announcement__close" data-announcement-dismiss aria-label="Duyuruyu kapat">
            <svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-x"></use></svg>
        </button>
    </div>
</div>
