<?php
// resources/views/admin/settings/index.php
/**
 * @var list<array{id:string,label:string,fields:list<array<string,mixed>>}> $tabs
 */
$oldSettings = old('settings', null);
$oldSettings = is_array($oldSettings) ? $oldSettings : null;

$allErrors = errors();
$allErrors = is_array($allErrors) ? $allErrors : [];

// Hatası olan ilk sekme açılır; yoksa ilk sekme.
$activeTab = $tabs[0]['id'] ?? 'general';
foreach ($tabs as $t) {
    foreach ($t['fields'] as $f) {
        if (isset($allErrors[$f['key']])) {
            $activeTab = $t['id'];
            break 2;
        }
    }
}
$fieldId = static fn (string $key): string => 'f-' . str_replace('.', '-', $key);
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0">Site ayarları</h1>
</div>

<?php if ($allErrors !== []): ?>
    <div class="alert alert-danger" role="alert">
        <p class="fw-semibold mb-1">Ayarlar kaydedilemedi:</p>
        <ul class="mb-0">
            <?php foreach ($allErrors as $k => $msg): ?>
                <li><?= e(is_array($msg) ? (string) reset($msg) : (string) $msg) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/yonetim/ayarlar')) ?>" data-dirty-guard novalidate>
    <?= csrf_field() ?>

    <ul class="nav nav-tabs flex-nowrap overflow-x-auto mb-3" role="tablist">
        <?php foreach ($tabs as $t): ?>
            <li class="nav-item" role="presentation">
                <button type="button"
                        class="nav-link text-nowrap<?= $t['id'] === $activeTab ? ' active' : '' ?>"
                        id="tab-btn-<?= e($t['id']) ?>"
                        data-bs-toggle="tab"
                        data-bs-target="#tab-<?= e($t['id']) ?>"
                        role="tab"
                        aria-controls="tab-<?= e($t['id']) ?>"
                        aria-selected="<?= $t['id'] === $activeTab ? 'true' : 'false' ?>"><?= e($t['label']) ?></button>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php foreach ($tabs as $t): ?>
            <div class="tab-pane fade<?= $t['id'] === $activeTab ? ' show active' : '' ?>"
                 id="tab-<?= e($t['id']) ?>" role="tabpanel" aria-labelledby="tab-btn-<?= e($t['id']) ?>" tabindex="0">
                <div class="admin-card mb-3">
                    <div class="admin-card-body">

                        <?php if ($t['id'] === 'payment'): ?>
                            <div class="alert alert-warning" role="alert">
                                <strong>IBAN uyarısı:</strong> Bu bilgiler ödeme ekranında ve sipariş e-postalarında müşterilere gösterilir.
                                Hatalı IBAN, müşteri ödemesinin yanlış hesaba gitmesine yol açar. Kaydetmeden önce banka kayıtlarıyla karşılaştırın.
                            </div>
                        <?php endif; ?>
                        <?php if ($t['id'] === 'shipping'): ?>
                            <p class="text-muted">Ücretsiz kargo eşiği vitrindeki duyuru şeridinde ve sepet özetinde kullanılır.</p>
                        <?php endif; ?>

                        <div class="row g-3">
                            <?php foreach ($t['fields'] as $f): ?>
                                <?php
                                $key = (string) $f['key'];
                                $id = $fieldId($key);
                                $value = ($oldSettings !== null && array_key_exists($key, $oldSettings)) ? $oldSettings[$key] : $f['value'];
                                $error = $allErrors[$key] ?? null;
                                $error = is_array($error) ? (string) reset($error) : $error;
                                $helpId = $id . '-help';
                                $name = 'settings[' . $key . ']';
                                $isWide = in_array($f['input'], ['textarea'], true);
                                ?>
                                <div class="<?= $isWide ? 'col-12' : 'col-md-6' ?>">
                                    <?php if ($f['input'] === 'checkbox'): ?>
                                        <?php $checked = ($value === true || (string) $value === '1'); ?>
                                        <input type="hidden" name="<?= e($name) ?>" value="0">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input<?= $error ? ' is-invalid' : '' ?>" type="checkbox" role="switch"
                                                   id="<?= e($id) ?>" name="<?= e($name) ?>" value="1"<?= $checked ? ' checked' : '' ?>
                                                   <?= $f['help'] !== '' ? 'aria-describedby="' . e($helpId) . '"' : '' ?>>
                                            <label class="form-check-label" for="<?= e($id) ?>"><?= e($f['label']) ?></label>
                                        </div>
                                    <?php else: ?>
                                        <label for="<?= e($id) ?>" class="form-label">
                                            <?= e($f['label']) ?><?php if ($f['required']): ?> <span class="text-danger">*</span><?php endif; ?>
                                        </label>

                                        <?php if ($f['input'] === 'textarea'): ?>
                                            <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="3" maxlength="<?= (int) $f['max'] ?>"
                                                      class="form-control<?= $error ? ' is-invalid' : '' ?>"
                                                      <?= $f['help'] !== '' ? 'aria-describedby="' . e($helpId) . '"' : '' ?>><?= e((string) $value) ?></textarea>
                                        <?php elseif ($f['input'] === 'number'): ?>
                                            <input type="number" id="<?= e($id) ?>" name="<?= e($name) ?>" inputmode="numeric" step="1"
                                                   <?= $f['min'] !== null ? 'min="' . (int) $f['min'] . '"' : '' ?>
                                                   <?= $f['maxv'] !== null ? 'max="' . (int) $f['maxv'] . '"' : '' ?>
                                                   class="form-control<?= $error ? ' is-invalid' : '' ?>"
                                                   value="<?= e((string) $value) ?>"
                                                   <?= $f['help'] !== '' ? 'aria-describedby="' . e($helpId) . '"' : '' ?>>
                                        <?php elseif ($f['input'] === 'decimal'): ?>
                                            <div class="input-group">
                                                <input type="text" id="<?= e($id) ?>" name="<?= e($name) ?>" inputmode="decimal" maxlength="12"
                                                       class="form-control<?= $error ? ' is-invalid' : '' ?>"
                                                       value="<?= e((string) $value) ?>"
                                                       <?= $f['help'] !== '' ? 'aria-describedby="' . e($helpId) . '"' : '' ?>>
                                                <span class="input-group-text">TL</span>
                                            </div>
                                        <?php else: ?>
                                            <?php
                                            $htmlType = match ($f['input']) {
                                                'email' => 'email',
                                                'url' => 'url',
                                                'tel' => 'tel',
                                                default => 'text',
                                            };
                                            ?>
                                            <input type="<?= e($htmlType) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"
                                                   maxlength="<?= (int) $f['max'] ?>"
                                                   <?= $f['input'] === 'iban' ? 'autocomplete="off" spellcheck="false" placeholder="TR00 0000 0000 0000 0000 0000 00"' : '' ?>
                                                   class="form-control<?= $f['input'] === 'iban' ? ' font-monospace' : '' ?><?= $error ? ' is-invalid' : '' ?>"
                                                   value="<?= e((string) $value) ?>"
                                                   <?= $f['help'] !== '' ? 'aria-describedby="' . e($helpId) . '"' : '' ?>>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($error): ?>
                                        <div class="invalid-feedback d-block"><?= e((string) $error) ?></div>
                                    <?php endif; ?>
                                    <?php if ($f['help'] !== ''): ?>
                                        <div id="<?= e($helpId) ?>" class="form-text"><?= e($f['help']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="d-grid d-md-flex justify-content-md-end">
        <button type="submit" class="btn btn-primary">Ayarları kaydet</button>
    </div>
</form>