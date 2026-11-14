<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductBarcodeController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| API Route'ları
|--------------------------------------------------------------------------
| Tüm route'lar Laravel Sanctum ile korunur (Bearer Token).
| Base URL: /api
*/

Route::middleware('auth:sanctum')->group(function () {

    // Barkodla ürün arama (kasiyer dahil giriş yapmış tüm kullanıcılar)
    Route::get('/products/barcode/{barcode}', [ProductBarcodeController::class, 'findByBarcode'])
        ->name('api.products.barcode');

    // Barkod yönetimi (yalnızca admin ve warehouse; kontrol controller içinde yapılır)
    Route::get('/products/{product}/barcodes', [ProductBarcodeController::class, 'index'])
        ->name('api.products.barcodes.index');

    Route::post('/products/{product}/barcodes', [ProductBarcodeController::class, 'store'])
        ->name('api.products.barcodes.store');

    Route::put('/barcodes/{productBarcode}', [ProductBarcodeController::class, 'update'])
        ->name('api.barcodes.update');

    Route::patch('/barcodes/{productBarcode}/primary', [ProductBarcodeController::class, 'setPrimary'])
        ->name('api.barcodes.primary');

    Route::delete('/barcodes/{productBarcode}', [ProductBarcodeController::class, 'destroy'])
        ->name('api.barcodes.destroy');
});