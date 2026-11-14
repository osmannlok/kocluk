<?php
// resources/views/admin/announcements/index.php
/**
 * Girdi: $title, $announcements (Announcement::adminList items), $pager, $announcement (form değerleri),
 * $isEdit, $formAction, $now (Y-m-d H:i:s).
 * Güvenlik: tüm çıktı e() ile; ekleme/düzenleme/durum/silme POST + CSRF; satır içi betik/stil yok.
 * Sunucu tarafı doğrulama AnnouncementAdminController::collect() içindedir.
 *
 * @var array<int,array<string,mixed>> $announcements
 * @var array<string,mixed> $pager
 * @var array<string,mixed> $announcement
 * @var bool $isEdit
 * @var string $formAction
 * @var string $now
 */
$err = static function (string $key): string {
    $value = errors($key);

    return is_string($value) ? $value : '';
};
$val = static fn (string $key): string => (string) old($key, (string) ($announcement[$key] ?? ''));
$isActive = (string) old('is_active', (string) ((int) ($announcement['is_active'] ?? 1))) === '1';
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><?= e($title) ?></h1>
    <?php if ($isEdit): ?>
        <a href="<?= e(url('/yonetim/duyurular')) ?>" class="btn btn-outline-secondary">Yeni duyuru ekle</a>
    <?php endif; ?>
</div>

<section class="admin-card mb-4" aria-labelledby="announcementFormTitle">
    <div class="admin-card-header">
        <h2 class="h6 mb-0" id="announcementFormTitle"><?= $isEdit ? 'Duyuruyu düzenle' : 'Yeni duyuru' ?></h2>
    </div>
    <form method="post" action="<?= e($formAction) ?>" novalidate data-dirty-guard>
        <?= csrf_field() ?>
        <div class="admin-card-body">
            <div class="row g-3">
                <div class="col-12 col-lg-8">
                    <label for="annMessage" class="form-label">Duyuru metni <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="text" id="annMessage" name="message" value="<?= e($val('message')) ?>"
                           class="form-control<?= $err('message') !== '' ? ' is-invalid' : '' ?>" maxlength="255" required
                           aria-describedby="annMessageHelp">
                    <?php if ($err('message') !== ''): ?>
                        <div class="text-danger small mt-1" role="alert"><?= e($err('message')) ?></div>
                    <?php endif; ?>
                    <div id="annMessageHelp" class="form-text">Sitenin üstündeki şeritte görünür (örn. "750 TL üzeri siparişlerde ücretsiz kargo").</div>
                </div>

                <div class="col-12 col-lg-4">
                    <label for="annSort" class="form-label">Sıra</label>
                    <input type="number" id="annSort" name="sort_order" value="<?= e($val('sort_order')) ?>"
                           class="form-control<?= $err('sort_order') !== '' ? ' is-invalid' : '' ?>" min="0" max="9999" step="1"
                           aria-describedby="annSortHelp">
                    <?php if ($err('sort_order') !== ''): ?>
                        <div class="text-danger small mt-1" role="alert"><?= e($err('sort_order')) ?></div>
                    <?php endif; ?>
                    <div id="annSortHelp" class="form-text">Küçük sayı önce gösterilir.</div>
                </div>

                <div class="col-12">
                    <label for="annLink" class="form-label">Bağlantı</label>
                    <input type="text" id="annLink" name="link_url" value="<?= e($val('link_url')) ?>"
                           class="form-control<?= $err('link_url') !== '' ? ' is-invalid' : '' ?>" maxlength="255"
                           inputmode="url" autocomplete="off" aria-describedby="annLinkHelp">
                    <?php if ($err('link_url') !== ''): ?>
                        <div class="text-danger small mt-1" role="alert"><?= e($err('link_url')) ?></div>
                    <?php endif; ?>
                    <div id="annLinkHelp" class="form-text">İsteğe bağlı. Site içi yol ("/urunler") veya "https://" ile başlayan adres.</div>
                </div>

                <div class="col-12 col-md-6">
                    <label for="annStartsAt" class="form-label">Yayın başlangıcı</label>
                    <input type="datetime-local" id="annStartsAt" name="starts_at" value="<?= e($val('starts_at')) ?>"
                           class="form-control<?= $err('starts_at') !== '' ? ' is-invalid' : '' ?>">
                    <?php if ($err('starts_at') !== ''): ?>
                        <div class="text-danger small mt-1" role="alert"><?= e($err('starts_at')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 col-md-6">
                    <label for="annEndsAt" class="form-label">Yayın bitişi</label>
                    <input type="datetime-local" id="annEndsAt" name="ends_at" value="<?= e($val('ends_at')) ?>"
                           class="form-control<?= $err('ends_at') !== '' ? ' is-invalid' : '' ?>" aria-describedby="annDatesHelp">
                    <?php if ($err('ends_at') !== ''): ?>
                        <div class="text-danger small mt-1" role="alert"><?= e($err('ends_at')) ?></div>
                    <?php endif; ?>
                    <div id="annDatesHelp" class="form-text">Tarihler boşsa duyuru sürekli yayındadır (aktifse).</div>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" role="switch" id="annActive" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                        <label class="form-check-label" for="annActive">Duyuru aktif</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="admin-card-footer d-flex flex-wrap gap-2 p-3 border-top">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Değişiklikleri kaydet' : 'Duyuru ekle' ?></button>
            <?php if ($isEdit): ?>
                <a href="<?= e(url('/yonetim/duyurular')) ?>" class="btn btn-link">Vazgeç</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<div class="admin-card">
    <div class="admin-card-body flush">
        <?php if ($announcements === []): ?>
            <div class="empty-state p-4 text-center text-muted">Henüz duyuru yok.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-cards">
                    <thead>
                        <tr>
                            <th scope="col">Sıra</th>
                            <th scope="col">Duyuru</th>
                            <th scope="col">Zamanlama</th>
                            <th scope="col">Durum</th>
                            <th scope="col" class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($announcements as $a): ?>
                        <?php
                        $id = (int) $a['id'];
                        $active = (int) $a['is_active'] === 1;
                        $startsAt = is_string($a['starts_at'] ?? null) && $a['starts_at'] !== '' ? (string) $a['starts_at'] : null;
                        $endsAt = is_string($a['ends_at'] ?? null) && $a['ends_at'] !== '' ? (string) $a['ends_at'] : null;

                        if (!$active) {
                            $badgeClass = 'text-bg-secondary';
                            $badgeText = 'Pasif';
                        } elseif ($startsAt !== null && $startsAt > $now) {
                            $badgeClass = 'text-bg-info';
                            $badgeText = 'Planlı';
                        } elseif ($endsAt !== null && $endsAt < $now) {
                            $badgeClass = 'text-bg-warning';
                            $badgeText = 'Süresi dolmuş';
                        } else {
                            $badgeClass = 'text-bg-success';
                            $badgeText = 'Yayında';
                        }
                        ?>
                        <tr>
                            <td data-label="Sıra"><?= (int) $a['sort_order'] ?></td>
                            <td data-label="Duyuru">
                                <div class="fw-semibold"><?= e($a['message']) ?></div>
                                <?php if (!empty($a['link_url'])): ?>
                                    <div class="small text-muted text-break"><?= e($a['link_url']) ?></div>
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
                            <td data-label="Durum"><span class="badge <?= e($badgeClass) ?>"><?= e($badgeText) ?></span></td>
                            <td data-label="İşlem" class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                    <a href="<?= e(url('/yonetim/duyurular?duzenle=' . $id)) ?>" class="btn btn-sm btn-outline-primary">Düzenle</a>
                                    <form method="post" action="<?= e(url('/yonetim/duyurular/' . $id . '/durum')) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <?= $active ? 'Yayından kaldır' : 'Yayına al' ?>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= e(url('/yonetim/duyurular/' . $id . '/sil')) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                data-confirm="Bu duyuru kalıcı olarak silinsin mi?">Sil</button>
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
</div>

<?= \App\Core\View::partial('partials.admin-pagination', ['pager' => $pager]) ?>