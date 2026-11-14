<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $aliases = [
            'Product' => Product::class,
            'ActivityLog' => ActivityLog::class,
            'Category' => Category::class,
            'Customer' => Customer::class,
            'Payment' => Payment::class,
            'ProductBarcode' => ProductBarcode::class,
            'Purchase' => Purchase::class,
            'PurchaseItem' => PurchaseItem::class,
            'Sale' => Sale::class,
            'SaleItem' => SaleItem::class,
            'Setting' => Setting::class,
            'StockMovement' => StockMovement::class,
            'Supplier' => Supplier::class,
        ];

        foreach ($aliases as $alias => $class) {
            // Testlerde uygulama her testte yeniden başlar ve boot() tekrar çalışır;
            // takma ad zaten tanımlıysa tekrar tanımlama.
            if (! class_exists($alias, false)) {
                class_alias($class, $alias);
            }
        }
    }
}