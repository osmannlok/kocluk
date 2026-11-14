<?php /* resources/views/admin/audit/index.php */ ?>
<?php
$fmt = static fn (?string $v): string => $v ? date('d.m.Y H:i:s', (int) strtotime($v)) : '—';
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Denetim günlüğü</h1>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/yonetim/guvenlik-olaylari')) ?>"><i class="bi bi-shield-exclamation"></i> Güvenlik olayları</a>
        <a class="btn btn-outline-primary"
           href="<?= e(url('/yonetim/denetim/disa-aktar') . '?' . http_build_query(array_filter($filters, static fn ($v) => $v !== ''))) ?>">
            <i class="bi bi-download"></i> CSV
        </a>
    </div>
</div>

<form class="filter-bar row g-2 mb-3" method="get" action="<?= e(url('/yonetim/denetim')) ?>">
    <div class="col-6 col-md-2">
        <label class="form-label small mb-0" for="f-user">Kullanıcı ID</label>
        <input class="form-control" id="f-user" name="user_id" inputmode="numeric" pattern="[0-9]*" value="<?= e($filters['user_id']) ?>">
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small mb-0" for="f-action">İşlem (önek)</label>
        <input class="form-control" id="f-action" name="action" maxlength="100" placeholder="user.update" value="<?= e($filters['action']) ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small mb-0" for="f-entity">Varlık türü</label>
        <input class="form-control" id="f-entity" name="entity_type" maxlength="60" placeholder="user" value="<?= e($filters['entity_type']) ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small mb-0" for="f-from">Başlangıç</label>
        <input class="form-control" id="f-from" type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small mb-0" for="f-to">Bitiş</label>
        <input class="form-control" id="f-to" type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
    </div>
    <div class="col-6 col-md-1 d-grid align-items-end">
        <button class="btn btn-outline-primary mt-md-4" type="submit"><i class="bi bi-funnel"></i></button>
    </div>
</form>

<div class="admin-card">
    <div class="admin-card-body flush">
        <?php if (empty($logs)): ?>
            <div class="empty-state p-4 text-center text-muted">Kayıt bulunamadı.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-cards">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Kullanıcı</th>
                            <th>İşlem</th>
                            <th>Varlık</th>
                            <th>IP</th>
                            <th class="text-end">Fark</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php $rowId = 'audit-diff-' . (int) $log['id']; ?>
                        <tr>
                            <td data-label="Tarih"><?= e($fmt($log['created_at'] ?? null)) ?></td>
                            <td data-label="Kullanıcı">
                                <?php if (!empty($log['user_email'])): ?>
                                    <?= e($log['user_name'] ?? '') ?>
                                    <span class="d-block small text-muted"><?= e($log['user_email']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Sistem</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="İşlem"><code><?= e($log['action'] ?? '') ?></code></td>
                            <td data-label="Varlık">
                                <?= e($log['entity_type'] ?? '—') ?>
                                <?php if (!empty($log['entity_id'])): ?>#<?= (int) $log['entity_id'] ?><?php endif; ?>
                            </td>
                            <td data-label="IP"><?= e(bin_to_ip($log['ip_address'] ?? null)) ?></td>
                            <td data-label="Fark" class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#<?= e($rowId) ?>"
                                        aria-expanded="false" aria-controls="<?= e($rowId) ?>">
                                    <i class="bi bi-chevron-expand"></i> Ayrıntı
                                </button>
                            </td>
                        </tr>
                        <tr class="collapse" id="<?= e($rowId) ?>">
                            <td colspan="6" class="bg-body-tertiary">
                                <?php if (!empty($log['diff'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-2">
                                            <thead><tr><th>Alan</th><th>Eski</th><th>Yeni</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($log['diff'] as $d): ?>
                                                <tr>
                                                    <td><code><?= e($d['field']) ?></code></td>
                                                    <td class="text-danger"><pre class="mb-0 small text-wrap"><?= e($d['old']) ?></pre></td>
                                                    <td class="text-success"><pre class="mb-0 small text-wrap"><?= e($d['new']) ?></pre></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php elseif (!empty($log['has_values'])): ?>
                                    <p class="text-muted small mb-2">Bu kayıtta eski/yeni değer farkı yok.</p>
                                <?php else: ?>
                                    <p class="text-muted small mb-2">Fark verisi bu listede yok; JSON ayrıntısını açın.</p>
                                <?php endif; ?>
                                <a class="small" href="<?= e(url('/yonetim/denetim/' . (int) $log['id'])) ?>">JSON ayrıntı</a>
                                <?php if (!empty($log['user_agent'])): ?>
                                    <span class="small text-muted ms-3"><?= e(mb_strimwidth((string) $log['user_agent'], 0, 160, '…')) ?></span>
                                <?php endif; ?>
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