<?php
// resources/views/admin/messages/show.php
/**
 * @var array<string,mixed> $message
 * @var ?string $mailto
 * @var string $ip
 */
$labels = ['new' => 'Yeni', 'read' => 'Okundu', 'replied' => 'Yanıtlandı'];
$badges = ['new' => 'text-bg-danger', 'read' => 'text-bg-secondary', 'replied' => 'text-bg-success'];
$id = (int) $message['id'];
$st = (string) $message['status'];
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0">Mesaj #<?= $id ?></h1>
    <a class="btn btn-outline-secondary" href="<?= e(url('/yonetim/mesajlar')) ?>">Listeye dön</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="admin-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h2 class="h6 mb-0"><?= e($message['subject']) ?></h2>
                <span class="badge <?= e($badges[$st] ?? 'text-bg-secondary') ?>"><?= e($labels[$st] ?? $st) ?></span>
            </div>
            <div class="admin-card-body">
                <!-- Kullanıcı girdisi: önce e(), sonra nl2br (XSS'e karşı sıra önemli). -->
                <div class="text-break"><?= nl2br(e($message['message'])) ?></div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card mb-3">
            <div class="admin-card-header"><h2 class="h6 mb-0">Gönderen</h2></div>
            <div class="admin-card-body">
                <dl class="row mb-0">
                    <dt class="col-4">Ad</dt>
                    <dd class="col-8 text-break"><?= e($message['name']) ?></dd>
                    <dt class="col-4">E-posta</dt>
                    <dd class="col-8 text-break"><?= e($message['email']) ?></dd>
                    <dt class="col-4">Telefon</dt>
                    <dd class="col-8"><?= e(($message['phone'] ?? '') !== '' && $message['phone'] !== null ? $message['phone'] : '-') ?></dd>
                    <dt class="col-4">Tarih</dt>
                    <dd class="col-8"><?= e(date('d.m.Y H:i', (int) strtotime((string) $message['created_at']))) ?></dd>
                    <dt class="col-4">IP</dt>
                    <dd class="col-8"><?= e($ip) ?></dd>
                </dl>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header"><h2 class="h6 mb-0">İşlemler</h2></div>
            <div class="admin-card-body d-grid gap-2">
                <?php if ($mailto !== null): ?>
                    <a class="btn btn-primary" href="<?= e($mailto) ?>">E-posta ile yanıtla</a>
                <?php else: ?>
                    <span class="text-muted small">Geçerli bir e-posta adresi olmadığından yanıt bağlantısı oluşturulamadı.</span>
                <?php endif; ?>

                <?php if ($st !== 'replied'): ?>
                    <form method="post" action="<?= e(url('/yonetim/mesajlar/' . $id . '/durum')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="replied">
                        <button type="submit" class="btn btn-success w-100">Yanıtlandı olarak işaretle</button>
                    </form>
                <?php endif; ?>

                <?php if ($st !== 'new'): ?>
                    <form method="post" action="<?= e(url('/yonetim/mesajlar/' . $id . '/durum')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="new">
                        <button type="submit" class="btn btn-outline-secondary w-100">Okunmadı olarak işaretle</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>