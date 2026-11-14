<?php
// scripts/model_smoke.php

declare(strict_types=1);

// Yalnızca CLI: web üzerinden çalıştırılamaz.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$base = dirname(__DIR__);
$autoload = $base . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class) use ($base): void {
        if (str_starts_with($class, 'App\\')) {
            $file = $base . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}

try {
    \App\Core\Config::load($base);
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA Config::load: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}

use App\Models\Address;
use App\Models\BaseModel;
use App\Models\Category;
use App\Models\EmailVerification;
use App\Models\LoginAttempt;
use App\Models\PasswordReset;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Region;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;

$failed = 0;

/** Okuma çağrısını dener; "OK Model::metot" veya hata satırı yazar. */
$check = static function (string $label, callable $fn) use (&$failed): void {
    try {
        $fn();
        echo 'OK ' . $label . PHP_EOL;
    } catch (Throwable $e) {
        $failed++;
        echo 'HATA ' . $label . ': ' . $e->getMessage() . PHP_EOL;
    }
};

/** Eklemeli modelde yazma metodunun LogicException fırlattığını doğrular (veri yazılmaz). */
$expectLogic = static function (string $label, callable $fn) use ($check): void {
    $check($label, static function () use ($fn): void {
        try {
            $fn();
        } catch (LogicException) {
            return;
        }
        throw new RuntimeException('LogicException bekleniyordu, fırlatılmadı.');
    });
};

$testIp = (string) inet_pton('127.0.0.1');
$testHash = str_repeat('0', 64);

// Örnek kayıt kimlikleri (varsa).
$firstId = static function (BaseModel $model): int {
    $rows = $model->all([], 'id ASC', 1);

    return $rows === [] ? 0 : (int) $rows[0]['id'];
};

$userModel = new User();
$roleModel = new Role();
$categoryModel = new Category();
$productModel = new Product();
$variantModel = new ProductVariant();

$userId = 0;
$roleId = 0;
$categoryId = 0;
$productId = 0;
$variantId = 0;
$productSlug = '';
$categorySlug = '';
$regionSlug = '';

$check('BaseModel::all/count/paginate/find/findBy (Role)', static function () use ($roleModel, &$roleId, $firstId): void {
    $roleModel->count();
    $roleModel->all(['name' => ['IN', ['admin', 'manager']]], 'id ASC', 5);
    $page = $roleModel->paginate(1, 10, [], 'id ASC');
    foreach (['items', 'total', 'page', 'per_page', 'pages'] as $key) {
        if (!array_key_exists($key, $page)) {
            throw new RuntimeException('paginate anahtarı eksik: ' . $key);
        }
    }
    $roleId = $firstId($roleModel);
    if ($roleId > 0) {
        $roleModel->find($roleId);
        $roleModel->findBy('name', 'admin');
    }
});

$check('BaseModel::where doğrulaması (geçersiz kolon reddi)', static function () use ($roleModel): void {
    try {
        $roleModel->all(['name; DROP TABLE roles' => 'x']);
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Geçersiz kolon kabul edildi.');
});

$check('User::count/all', static function () use ($userModel, &$userId, $firstId): void {
    $userModel->count();
    $userId = $firstId($userModel);
});
$check('User::findByEmail', static fn () => $userModel->findByEmail('smoke-test@example.invalid'));
$check('User::emailExists', static fn () => $userModel->emailExists('smoke-test@example.invalid'));
$check('User::withRole', static fn () => $userModel->withRole($userId));
$check('User::searchAdmin', static fn () => $userModel->searchAdmin(['q' => 'a', 'status' => 'active'], 1, 5));

$check('Role::findByName', static fn () => $roleModel->findByName('admin'));
$check('Role::permissionCodes', static fn () => $roleModel->permissionCodes($roleId));
$check('Role::allWithUserCounts', static fn () => $roleModel->allWithUserCounts());

$permissionModel = new Permission();
$check('Permission::allGroupedByModule', static fn () => $permissionModel->allGroupedByModule());
$check('Permission::idsByCodes', static fn () => $permissionModel->idsByCodes(['dashboard.view', 'product.view']));

$addressModel = new Address();
$check('Address::forUser', static fn () => $addressModel->forUser($userId));
$check('Address::findForUser', static fn () => $addressModel->findForUser(1, $userId));
$check('Address::countForUser', static fn () => $addressModel->countForUser($userId));

$loginAttemptModel = new LoginAttempt();
$check('LoginAttempt::failedCountByEmail', static fn () => $loginAttemptModel->failedCountByEmail('smoke-test@example.invalid', 15));
$check('LoginAttempt::failedCountByIp', static fn () => $loginAttemptModel->failedCountByIp($testIp, 15));
$expectLogic('LoginAttempt::update (eklemeli: LogicException)', static fn () => $loginAttemptModel->update(1, ['email' => 'x']));

$check('PasswordReset::findValidByHash', static fn () => (new PasswordReset())->findValidByHash($testHash));
$check('EmailVerification::findValidByHash', static fn () => (new EmailVerification())->findValidByHash($testHash));

$check('Category::activeTree', static function () use ($categoryModel, &$categoryId, &$categorySlug): void {
    $categoryModel->activeTree();
    $rows = $categoryModel->all(['is_active' => 1], 'id ASC', 1);
    if ($rows !== []) {
        $categoryId = (int) $rows[0]['id'];
        $categorySlug = (string) $rows[0]['slug'];
    }
});
$check('Category::allFlat', static fn () => $categoryModel->allFlat());
$check('Category::findBySlug', static fn () => $categoryModel->findBySlug($categorySlug !== '' ? $categorySlug : 'yok'));
$check('Category::childrenOf', static fn () => $categoryModel->childrenOf($categoryId));
$check('Category::breadcrumb', static fn () => $categoryModel->breadcrumb($categoryId));
$check('Category::slugExists', static fn () => $categoryModel->slugExists($categorySlug !== '' ? $categorySlug : 'yok'));
$check('Category::descendantIds', static fn () => $categoryModel->descendantIds($categoryId));

$regionModel = new Region();
$check('Region::activeAll', static function () use ($regionModel, &$regionSlug): void {
    $rows = $regionModel->activeAll();
    $regionSlug = $rows === [] ? '' : (string) $rows[0]['slug'];
});
$check('Region::findBySlug', static fn () => $regionModel->findBySlug($regionSlug !== '' ? $regionSlug : 'yok'));

$check('Product::count/all', static function () use ($productModel, &$productId, &$productSlug, $firstId): void {
    $productModel->count();
    $rows = $productModel->all(['is_active' => 1], 'id ASC', 1);
    if ($rows !== []) {
        $productId = (int) $rows[0]['id'];
        $productSlug = (string) $rows[0]['slug'];
    }
});
$check('Product::findActiveBySlug', static function () use ($productModel, $productSlug): void {
    $row = $productModel->findActiveBySlug($productSlug !== '' ? $productSlug : 'yok');
    if ($row !== null) {
        foreach (['region_name', 'category', 'variants', 'images'] as $key) {
            if (!array_key_exists($key, $row)) {
                throw new RuntimeException('Anahtar eksik: ' . $key);
            }
        }
    }
});
$check('Product::listActive', static fn () => $productModel->listActive(
    ['category_id' => $categoryId, 'in_stock' => 1, 'min_price' => '0', 'max_price' => '100000', 'q' => 'bal'],
    'price_asc',
    1,
    12
));
$check('Product::search', static fn () => $productModel->search('bal', 5));
$check('Product::featured', static fn () => $productModel->featured(4));
$check('Product::related', static fn () => $productModel->related($productId, 4));
$check('Product::slugExists', static fn () => $productModel->slugExists($productSlug !== '' ? $productSlug : 'yok'));
$check('Product::adminList', static fn () => $productModel->adminList(['q' => 'a'], 1, 10));

$check('ProductVariant::count/all', static function () use ($variantModel, &$variantId, $firstId): void {
    $variantModel->count();
    $variantId = $firstId($variantModel);
});
$check('ProductVariant::forProduct', static fn () => $variantModel->forProduct($productId, false));
$check('ProductVariant::findActiveMany', static fn () => $variantModel->findActiveMany([$variantId]));
$check('ProductVariant::lowStock', static fn () => $variantModel->lowStock());
$check('ProductVariant::skuExists', static fn () => $variantModel->skuExists('SMOKE-YOK'));
$expectLogic('ProductVariant::lockManyForUpdate (transaction dışı: LogicException)', static fn () => $variantModel->lockManyForUpdate([$variantId]));

$check('ProductImage::forProduct', static fn () => (new ProductImage())->forProduct($productId));

$stockMovementModel = new StockMovement();
$check('StockMovement::forVariant', static fn () => $stockMovementModel->forVariant($variantId, 1, 10));
$check('StockMovement::recent', static fn () => $stockMovementModel->recent(5));
$expectLogic('StockMovement::forceDelete (eklemeli: LogicException)', static fn () => $stockMovementModel->forceDelete(1));

echo PHP_EOL . ($failed === 0 ? 'TAMAM: tüm kontroller geçti.' : 'BAŞARISIZ: ' . $failed . ' kontrol hatalı.') . PHP_EOL;
exit($failed === 0 ? 0 : 1);