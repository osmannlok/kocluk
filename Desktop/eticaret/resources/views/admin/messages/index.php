<?php
// resources/views/admin/messages/index.php
/**
 * @var list<array<string,mixed>> $messages
 * @var array<string,mixed> $pager
 * @var string $status
 * @var int $newCount
 */
$labels = ['new' => 'Yeni', 'read' => 'Okundu', 'replied' => 'Yanıtlandı'];
$badges = ['new' => 'text-bg-danger', 'read' => 'text-bg-secondary', 'replied' => 'text-bg-success'];
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0">Mesajlar
        <?php if ($newCount > 0): ?>
            <span class="badge text-bg-danger ms-1"><?= (int) $newCount ?> yeni</span>
        <?php endif; ?>
    </h1>
</div>

<form method="get" action="<?= e(url('/yonetim/mesajlar')) ?>" class="filter-bar mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-8 col-md-4">
            <label for="msgStatus" class="form-label">Durum</label>
            <select id="msgStatus" name="status" class="form-select">
                <option value="">Tümü</option>
                <?php foreach ($labels as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-4 col-md-2">
            <button type="submit" class="btn btn-outline-secondary w-100">Filtrele</button>
        </div>
    </div>
</form>

<div class="admin-card">
    <div class="admin-card-body flush">
        <?php if ($messages === []): ?>
            <div class="empty-state p-4 text-center text-muted">Mesaj bulunamadı.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-cards align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Gönderen</th>
                            <th scope="col">Konu</th>
                            <th scope="col">Tarih</th>
                            <th scope="col">Durum</th>
                            <th scope="col" class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($messages as $m): ?>
                        <?php
                        $id = (int) $m['id'];
                        $st = (string) $m['status'];
                        $isNew = $st === 'new';
                        $cls = $isNew ? 'fw-bold' : '';
                        ?>
                        <tr>
                            <td data-label="Gönderen" class="<?= e($cls) ?>">
                                <?= e($m['name']) ?><br>
                                <span class="small text-muted fw-normal"><?= e($m['email']) ?></span>
                            </td>
                            <td data-label="Konu" class="<?= e($cls) ?>">
                                <a href="<?= e(url('/yonetim/mesajlar/' . $id)) ?>" class="text-reset text-decoration-none">
                                    <?= e(mb_strimwidth((string) $m['subject'], 0, 80, '…', 'UTF-8')) ?>
                                </a>
                            </td>
                            <td data-label="Tarih" class="<?= e($cls) ?>"><?= e(date('d.m.Y H:i', (int) strtotime((string) $m['created_at']))) ?></td>
                            <td data-label="Durum">
                                <span class="badge <?= e($badges[$st] ?? 'text-bg-secondary') ?>"><?= e($labels[$st] ?? $st) ?></span>
                            </td>
                            <td data-label="İşlem" class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/yonetim/mesajlar/' . $id)) ?>">Görüntüle</a>
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