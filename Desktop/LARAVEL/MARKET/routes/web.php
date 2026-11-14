<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Cashier\SaleController;
use App\Http\Controllers\Admin\ProductBarcodeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\ReportController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

});

/*
|--------------------------------------------------------------------------
| Yönetim Route'ları (Kategori ve Ürün Yönetimi)
|--------------------------------------------------------------------------
| Rol kontrolü (admin, warehouse) controller ve Form Request içinde yapılır.
| Route adları: admin.categories.*, admin.products.*
*/
Route::middleware('auth')
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Kategori CRUD (show sayfası yok)
        Route::resource('categories', CategoryController::class)
            ->except(['show']);

        // Ürünü pasife alma / yeniden aktif etme
        Route::patch('products/{product}/toggle-active', [ProductController::class, 'toggleActive'])
            ->name('products.toggle-active');

        // Ürün CRUD (show sayfası yok)
        Route::resource('products', ProductController::class)
            ->except(['show']);
    });

/*
|--------------------------------------------------------------------------
| Yönetim Route'ları
|--------------------------------------------------------------------------
| Rol kontrolü (admin, warehouse) controller içinde yapılır.
*/
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {

    // Ürün barkod yönetimi
    Route::get('/products/{product}/barcodes', [ProductBarcodeController::class, 'index'])
        ->name('products.barcodes.index');

    Route::post('/products/{product}/barcodes', [ProductBarcodeController::class, 'store'])
        ->name('products.barcodes.store');

    Route::put('/barcodes/{productBarcode}', [ProductBarcodeController::class, 'update'])
        ->name('barcodes.update');

    Route::patch('/barcodes/{productBarcode}/primary', [ProductBarcodeController::class, 'setPrimary'])
        ->name('barcodes.primary');

    Route::delete('/barcodes/{productBarcode}', [ProductBarcodeController::class, 'destroy'])
        ->name('barcodes.destroy');

        // Stok yönetimi: kritik stok, fire, sayım
    Route::get('/stock', [StockController::class, 'index'])
        ->name('stock.index');

    Route::post('/stock/damage', [StockController::class, 'storeDamage'])
        ->name('stock.damage');

    Route::post('/stock/count', [StockController::class, 'storeCount'])
        ->name('stock.count');

        // Raporlar (admin, accountant ve kasiyer için kendi satışları)
    Route::get('/reports', [ReportController::class, 'index'])
        ->name('reports.index');

    Route::get('/reports/export', [ReportController::class, 'export'])
        ->name('reports.export');
    

});
/*
|--------------------------------------------------------------------------
| Kasa Route'ları
|--------------------------------------------------------------------------
| Rol kontrolü (admin, cashier) controller ve Form Request içinde yapılır.
*/
Route::middleware('auth')->prefix('cashier')->name('cashier.')->group(function () {

    // Kasa ekranı
    Route::get('/sales/create', [SaleController::class, 'create'])
        ->name('sales.create');

    // Ürün arama (kasa ekranı bunları fetch ile çağırır)
    Route::get('/sales/product-by-barcode/{barcode}', [SaleController::class, 'findByBarcode'])
        ->name('sales.barcode');

    Route::get('/sales/search-products', [SaleController::class, 'searchProducts'])
        ->name('sales.search');

    // Satışı tamamla
    Route::post('/sales', [SaleController::class, 'store'])
        ->name('sales.store');
});

require __DIR__.'/auth.php';
