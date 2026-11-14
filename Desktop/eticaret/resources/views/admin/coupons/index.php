<?php
// resources/views/admin/coupons/index.php
/**
 * Girdi: $title, $coupons (Coupon::adminList items), $pager, $q, $status, $now (Y-m-d H:i:s).
 * Güvenlik: tüm çıktı e() ile; durum değiştirme/silme POST + CSRF; satır içi betik/stil yok.
 *
 * @var array<int,array<string,mixed>> $coupons
 * @var array<string,mixed> $pager
 * @var string $q
 * @var string $status
 * @var string $now
 */
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><?= e($title) ?></h1>
    <a href="<?= e(url('/yonetim/kuponlar/yeni')) ?>" class="btn btn-primary">Yeni kupon</a>
</div>

<form method="get" action="<?= e(url('/yonetim/kuponlar')) ?>" class="filter-bar admin-card mb-3">
    <div class="admin-card-body row g-2 align-items-end">
        <div class="col-12 col-md-5">
            <label for="couponSearch" class="form-label">Kupon kodu veya açıklama</label>
            <input type="search" id="couponSearch" name="q" value="<?= e($q) ?>" maxlength="100" class="form-control">
        </div>
        <div class="col-8 col-md-3">
            <label for="couponStatus" class="form-label">Durum</label>
            <select id="couponStatus" name="status" class="form-select">
                <option value=""<?= $status === '' ? ' selected' : '' ?>>Tümü</option>
                <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Etkin</option>
                <option value="passive"<?= $status === 'passive' ? ' selected' : '' ?>>Devre dışı</option>
            </select>
        </div>
        <div class="col-4 col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary">Filtrele</button>
            <?php if ($q !== '' || $status !== ''): ?>
                <a href="<?= e(url('/yonetim/kuponlar')) ?>" class="btn btn-link">Temizle</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="admin-card">
    <div class="admin-card-body flush">
        <?php if ($coupons === []): ?>
            <div class="empty-state p-4 text-center text-muted">Kupon bulunamadı.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-cards">
                    <thead>
                        <tr>
                            <th scope="col">Kod</th>
                            <th scope="col">Tür / Değer</th>
                            <th scope="col">Min. sepet</th>
                            <th scope="col">Kullanım</th>
                            <th scope="col">Geçerlilik</th>
                            <th scope="col">Durum</th>
                            <th scope="col" class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($coupons as $c): ?>
                        <?php
                        $id = (int) $c['id'];
                        $isPercent = ($c['discount_type'] ?? '') === 'percent';
                        $valueText = (string) $c['discount_value'];
                        $startsAt = is_string($c['starts_at'] ?? null) && $c['starts_at'] !== '' ? (string) $c['starts_at'] : null;
                        $endsAt = is_string($c['ends_at'] ?? null) && $c['ends_at'] !== '' ? (string) $c['ends_at'] : null;
                        $limit = $c['usage_limit'] ?? null;
                        $isActive = (int) $c['is_active'] === 1;

                        // Geçerlilik penceresi: planlı / süresi dolmuş / geçerli.
                        if (!$isActive) {
                            $badgeClass = 'text-bg-secondary';
                            $badgeText = 'Devre dışı';
                        } elseif ($startsAt !== null && $startsAt > $now) {
                            $badgeClass = 'text-bg-info';
                            $badgeText = 'Planlı';
                        } elseif ($endsAt !== null && $endsAt < $now) {
                            $badgeClass = 'text-bg-warning';
                            $badgeText = 'Süresi dolmuş';
                        } elseif ($limit !== null && (int) $c['used_count'] >= (int) $limit) {
                            $badgeClass = 'text-bg-warning';
                            $badgeText = 'Limit doldu';
                        } else {
                            $badgeClass = 'text-bg-success';
                            $badgeText = 'Geçerli';
                        }
                        ?>
                        <tr>
                            <td data-label="Kod">
                                <code class="fw-semibold"><?= e($c['code']) ?></code>
                                <?php if (!empty($c['description'])): ?>
                                    <div class="small text-muted"><?= e($c['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Tür / Değer">
                                <?php if ($isPercent): ?>
                                    %<?= e(rtrim(rtrim($valueText, '0'), '.')) ?>
                                    <?php if (!empty($c['max_discount_amount'])): ?>
                                        <div class="small text-muted">En çok <?= e(money((string) $c['max_discount_amount'])) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= e(money($valueText)) ?> sabit
                                <?php endif; ?>
                            </td>
                            <td data-label="Min. sepet"><?= e(money((string) $c['min_cart_total'])) ?></td>
                            <td data-label="Kullanım">
                                <?= (int) $c['used_count'] ?> / <?= $limit !== null ? (int) $limit : '∞' ?>
                                <?php if (!empty($c['usage_per_user'])): ?>
                                    <div class="small text-muted">Kişi başı <?= (int) $c['usage_per_user'] ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Geçerlilik">
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
                                    <a href="<?= e(url('/yonetim/kuponlar/' . $id . '/duzenle')) ?>" class="btn btn-sm btn-outline-primary">Düzenle</a>
                                    <form method="post" action="<?= e(url('/yonetim/kuponlar/' . $id . '/durum')) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <?= $isActive ? 'Devre dışı bırak' : 'Etkinleştir' ?>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= e(url('/yonetim/kuponlar/' . $id . '/sil')) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                data-confirm="Bu kupon silinsin mi? Geçmiş kullanım kayıtları korunur.">Sil</button>
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