<?php
declare(strict_types=1);

// resources/views/partials/flash.php
// flash_messages() -> Bootstrap alert (aria-live) + errors() özeti.

$typeMap = [
    'success' => 'success',
    'error' => 'danger',
    'danger' => 'danger',
    'warning' => 'warning',
    'info' => 'info',
];

// flash_messages() biçimi iki şekilde de kabul edilir: [{type,message}] veya {type: mesaj|[mesajlar]}.
$messages = [];
foreach ((array) flash_messages() as $key => $value) {
    if (is_array($value) && isset($value['message'])) {
        $messages[] = [(string) ($value['type'] ?? 'info'), (string) $value['message']];
    } elseif (is_array($value)) {
        foreach ($value as $m) {
            if (is_string($m) && $m !== '') {
                $messages[] = [is_string($key) ? $key : 'info', $m];
            }
        }
    } elseif (is_string($value) && $value !== '') {
        $messages[] = [is_string($key) ? $key : 'info', $value];
    }
}

// errors() alan => mesaj|[mesajlar]; özet için düzleştirilir.
$errorList = [];
$walk = static function (mixed $v) use (&$walk, &$errorList): void {
    if (is_array($v)) {
        foreach ($v as $item) {
            $walk($item);
        }
    } elseif (is_string($v) && $v !== '') {
        $errorList[] = $v;
    }
};
$walk(errors());
$errorList = array_values(array_unique($errorList));

if ($messages === [] && $errorList === []) {
    return;
}
?>
<div class="container mt-3" aria-live="polite">
<?php foreach ($messages as [$type, $text]): $cls = $typeMap[$type] ?? 'info'; ?>
    <div class="alert alert-<?= e($cls) ?> alert-dismissible fade show" role="<?= $cls === 'danger' ? 'alert' : 'status' ?>">
        <?= e($text) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endforeach; ?>
<?php if ($errorList !== []): ?>
    <div class="alert alert-danger" role="alert">
        <strong>Lütfen aşağıdaki hataları düzeltin:</strong>
        <ul class="mb-0 mt-1">
<?php foreach ($errorList as $err): ?>
            <li><?= e($err) ?></li>
<?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
</div>
