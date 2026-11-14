<?php
// resources/views/admin/banners/index.php
/**
 * Girdi: $title, $groups (konum => banner satırları, sıralı), $positions (konum => etiket), $now (Y-m-d H:i:s).
 * Güvenlik: tüm çıktı e() ile; durum/silme POST + CSRF; satır içi betik/stil yok.
 * Sıra düğmeleri [data-banner-move] ile işaretlidir; sıralama JSON'u BannerAdminController::reorder'a gider:
 * {"position":"home_hero","ids":[3,1,2]} (başlık X-CSRF-Token).
 *
 * @var array<string,array<int,array<string,mixed>>> $groups
 * @var array<string,string> $positions
 * @var string $now
 */
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><?= e($title) ?></h1>
    <a href="<?= e(url('/yonetim/bannerlar/yeni')) ?>" class="btn btn-primary">Yeni banner</a>
</div>

<?php foreach ($positions as $position => $label): ?>
    <?php $rows = $groups[$position] ?? []; $total = count($rows); ?>
    <section class="admin-card mb-4" aria-labelledby="bannerGroup-<?= e($position) ?>"
             data-banner-group data-position="<?= e($position) ?>"
             data-reorder-url="<?= e(url('/yonetim/bannerlar/sirala')) ?>">
        <div class="admin-card-header d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-0" id="bannerGroup-<?= e($position) ?>"><?= e($label) ?></h2>
            <span class="badge text-bg-light"><?= $total ?> banner</span>
        </div>
        <div class="admin-card-body flush">
            <?php if ($rows === []): ?>
                <div class="empty-state p-4 text-center text-muted">Bu konumda banner yok.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-cards">
                        <thead>
                            <tr>
                                <th scope="col">Sıra</th>
                                <th scope="col">Önizleme</th>
                                <th scope="col">Başlık</th>
                                <th scope="col">Zamanlama</th>
                                <th scope="col">Durum</th>
                                <th scope="col" class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody data-banner-list>
                        <?php foreach ($rows as $i => $b): ?>
                            <?php
                            $id = (int) $b['id'];
                            $isActive = (int) $b['is_active'] === 1;
                            $startsAt = is_string($b['starts_at'] ?? null) && $b['starts_at'] !== '' ? (string) $b['starts_at'] : null;
                            $endsAt = is_string($b['ends_at'] ?? null) && $b['ends_at'] !== '' ? (string) $b['ends_at'] : null;

                            // Zamanlama rozeti: planlı / süresi dolmuş / yayında.
                            if ($startsAt !== null && $startsAt > $now) {
                                $badgeClass = 'text-bg-info';
                                $badgeText = 'Planlı';
                            } elseif ($endsAt !== null && $endsAt < $now) {
                                $badgeClass = 'text-bg-warning';
                                $badgeText = 'Süresi dolmuş';
                            } else {
                                $badgeClass = 'text-bg-success';
                                $badgeText = 'Yayında';
                            }
                            $desktop = (string) ($b['image_desktop'] ?? '');
                            $mobile = (string) ($b['image_mobile'] ?? '');
                            ?>
                            <tr data-banner-id="<?= $id ?>">
                                <td data-label="Sıra">
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Sıralama">
                                        <button type="button" class="btn btn-outline-secondary" data-banner-move="up"
                                                aria-label="Yukarı taşı: <?= e($b['title']) ?>"<?= $i === 0 ? ' disabled' : '' ?>>&uarr;</button>
                                        <button type="button" class="btn btn-outline-secondary" data-banner-move="down"
                                                aria-label="Aşağı taşı: <?= e($b['title']) ?>"<?= $i === $total - 1 ? ' disabled' : '' ?>>&darr;</button>
                                    </div>
                                </td>
                                <td data-label="Önizleme">
                                    <div class="d-flex gap-2">
                                        <?php if ($desktop !== ''): ?>
                                            <div class="ratio ratio-21x9 col-6">
                                                <img src="<?= e(url($desktop)) ?>" alt="Masaüstü önizleme" loading="lazy" class="img-thumbnail object-fit-cover">
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($mobile !== ''): ?>
                                            <div class="ratio ratio-1x1 col-3">
                                                <img src="<?= e(url($mobile)) ?>" alt="Mobil önizleme" loading="lazy" class="img-thumbnail object-fit-cover">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Başlık">
                                    <div class="fw-semibold"><?= e($b['title']) ?></div>
                                    <?php if (!empty($b['subtitle'])): ?>
                                        <div class="small text-muted"><?= e($b['subtitle']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($b['link_url'])): ?>
                                        <div class="small text-muted text-break"><?= e($b['link_url']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Zamanlama">
                                    <?php if ($startsAt === null && $endsAt === null): ?>
                                        <span class="text-muted">Süresiz</span>
                                    <?php else: ?>
                                        <?= $startsAt !== null ? e(date('d.m.Y H:i', (int) strtotime($startsAt))) : '…' ?>
                                        –
                                        <?= $endsAt !== null ? e(date('d.m.Y H:i', (int) strtotime($endsAt))) : '…' ?>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Durum">
                                    <?php if ($isActive): ?>
                                        <span class="badge <?= e($badgeClass) ?>"><?= e($badgeText) ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="İşlem" class="text-end">
                                    <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                        <a href="<?= e(url('/yonetim/bannerlar/' . $id . '/duzenle')) ?>" class="btn btn-sm btn-outline-primary">Düzenle</a>
                                        <form method="post" action="<?= e(url('/yonetim/bannerlar/' . $id . '/durum')) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                <?= $isActive ? 'Yayından kaldır' : 'Yayına al' ?>
                                            </button>
                                        </form>
                                        <form method="post" action="<?= e(url('/yonetim/bannerlar/' . $id . '/sil')) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    data-confirm="Bu banner silinsin mi? Görsel dosyaları korunur.">Sil</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>