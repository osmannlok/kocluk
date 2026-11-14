<?php
declare(strict_types=1);

// resources/views/partials/product-card.php
// Girdi: $product = LİSTE SATIRI {id,name,slug,short_description,region_name,image_path,price,final_price,on_sale,in_stock,default_variant_id}.

use App\Helpers\Money;

if (!isset($product) || !is_array($product)) {
    return;
}

$name = (string) ($product['name'] ?? '');
$slug = (string) ($product['slug'] ?? '');
$price = (string) ($product['price'] ?? '0');
$finalPrice = (string) ($product['final_price'] ?? $price);
$inStock = !empty($product['in_stock']);
$variantId = (int) ($product['default_variant_id'] ?? 0);
$region = trim((string) ($product['region_name'] ?? ''));

// İndirim yüzdesi tamsayı kuruş hesabıyla (float yok), yarım yukarı yuvarlama.
$discountPct = 0;
$onSale = !empty($product['on_sale']);
if ($onSale) {
    $oldCents = Money::toCents($price);
    $newCents = Money::toCents($finalPrice);
    if ($oldCents > 0 && $newCents < $oldCents) {
        $discountPct = intdiv(($oldCents - $newCents) * 100 + intdiv($oldCents, 2), $oldCents);
        if ($discountPct < 1) {
            $onSale = false;
        }
    } else {
        $onSale = false;
    }
}

$imagePath = trim((string) ($product['image_path'] ?? ''));
$imageSrc = '';
if ($imagePath !== '') {
    $imageSrc = preg_match('#^https?://#i', $imagePath) === 1 ? $imagePath : url('/' . ltrim($imagePath, '/'));
}

$canBuy = $inStock && $variantId > 0;
?>
<article class="ym-card">
    <a class="ym-card__link" href="<?= e(url('/urun/' . $slug)) ?>">
        <div class="ym-card__media">
<?php if ($imageSrc !== ''): ?>
            <img class="ym-card__img" src="<?= e($imageSrc) ?>" alt="" width="400" height="400" loading="lazy" decoding="async">
<?php else: ?>
            <img class="ym-card__img ym-card__img--empty" src="<?= e(asset('img/logo.svg')) ?>" alt="" width="400" height="400" loading="lazy" decoding="async">
<?php endif; ?>
            <div class="ym-card__badges">
<?php if ($region !== ''): ?>
                <span class="ym-badge ym-badge--region"><?= e($region) ?></span>
<?php else: ?>
                <span></span>
<?php endif; ?>
<?php if (!$inStock): ?>
                <span class="ym-badge ym-badge--oos">Stokta yok</span>
<?php elseif ($onSale): ?>
                <span class="ym-badge ym-badge--sale"><span aria-hidden="true">-%<?= e((string) $discountPct) ?></span><span class="visually-hidden">Yüzde <?= e((string) $discountPct) ?> indirim</span></span>
<?php endif; ?>
            </div>
        </div>
        <div class="ym-card__body">
            <h3 class="ym-card__title"><?= e($name) ?></h3>
        </div>
    </a>
    <div class="ym-card__foot">
        <p class="ym-card__price">
<?php if ($onSale): ?>
            <del class="ym-card__old"><span class="visually-hidden">Eski fiyat: </span><?= e(money($price)) ?></del>
            <ins class="ym-card__now text-decoration-none"><span class="visually-hidden">İndirimli fiyat: </span><?= e(money($finalPrice)) ?></ins>
<?php else: ?>
            <span class="ym-card__now"><?= e(money($finalPrice)) ?></span>
<?php endif; ?>
        </p>
        <button type="button" class="btn btn-primary btn-sm w-100"<?= $canBuy ? ' data-add-to-cart data-variant-id="' . e((string) $variantId) . '"' : ' disabled' ?> aria-label="<?= e($canBuy ? $name . ' ürününü sepete ekle' : $name . ' stokta yok') ?>">
            <svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-cart"></use></svg>
            <?= $canBuy ? 'Sepete ekle' : 'Stokta yok' ?>
        </button>
    </div>
</article>
