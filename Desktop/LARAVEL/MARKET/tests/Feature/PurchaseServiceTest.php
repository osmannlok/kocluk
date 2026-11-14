<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseService $purchases;

    protected function setUp(): void
    {
        parent::setUp();

        $this->purchases = app(PurchaseService::class);
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::forceCreate(array_merge([
            'name' => 'Sumak Eşkisi',
            'slug' => 'sumak-eskisi-' . uniqid(),
            'unit' => 'kg',
            'purchase_price' => 1200,
            'sale_price' => 1600,
            'stock' => 20,
            'critical_stock' => 3,
            'is_active' => true,
        ], $overrides));
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::forceCreate([
            'name' => 'Maraş Yöresel Toptan',
            'balance' => 0,
            'is_active' => true,
        ]);
    }

    public function test_alim_stogu_artirir_ve_kayitlari_olusturur(): void
    {
        $user = User::factory()->create(['role' => 'warehouse']);
        $supplier = $this->makeSupplier();
        $a = $this->makeProduct(['name' => 'Ürün A', 'stock' => 20]);
        $b = $this->makeProduct(['name' => 'Ürün B', 'stock' => 5]);

        $purchase = $this->purchases->createPurchase(
            ['supplier_id' => $supplier->id, 'paid_amount' => 3000],
            [
                ['product_id' => $a->id, 'quantity' => 10, 'unit_price' => 250],
                ['product_id' => $b->id, 'quantity' => 4, 'unit_price' => 500],
            ],
            $user->id
        );

        $this->assertSame(1, Purchase::count());
        $this->assertSame(2, $purchase->items()->count());
        $this->assertEquals(4500, (float) $purchase->total_amount);
        $this->assertEquals(1500, (float) $purchase->remaining_amount);

        $this->assertEquals(30, (float) $a->fresh()->stock);
        $this->assertEquals(9, (float) $b->fresh()->stock);

        $this->assertSame(2, StockMovement::where('purchase_id', $purchase->id)
            ->where('movement_type', 'purchase')->count());
    }

    public function test_kalan_tutar_tedarikci_bakiyesine_eklenir(): void
    {
        $supplier = $this->makeSupplier();
        $product = $this->makeProduct();

        $this->purchases->createPurchase(
            ['supplier_id' => $supplier->id, 'paid_amount' => 1000],
            [['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 250]]
        );

        $this->assertEquals(1500, (float) $supplier->fresh()->balance);
    }

    public function test_alim_fiyati_urune_yazilir(): void
    {
        $product = $this->makeProduct(['purchase_price' => 1200]);

        $this->purchases->createPurchase(
            ['paid_amount' => 1300],
            [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1300]]
        );

        $this->assertEquals(1300, (float) $product->fresh()->purchase_price);
    }

    public function test_gecersiz_urunde_hicbir_kayit_olusmaz(): void
    {
        $supplier = $this->makeSupplier();
        $product = $this->makeProduct(['stock' => 20]);

        try {
            $this->purchases->createPurchase(
                ['supplier_id' => $supplier->id],
                [
                    ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 100],
                    ['product_id' => 999999, 'quantity' => 1, 'unit_price' => 100],
                ]
            );
            $this->fail('Geçersiz ürün için hata beklenirdi.');
        } catch (\Throwable $e) {
            // Hata türü önemli değil; önemli olan rollback
        }

        $this->assertSame(0, Purchase::withTrashed()->count());
        $this->assertSame(0, StockMovement::count());
        $this->assertEquals(20, (float) $product->fresh()->stock);
        $this->assertEquals(0, (float) $supplier->fresh()->balance);
    }

    public function test_odenen_tutar_toplamdan_buyuk_olamaz(): void
    {
        $product = $this->makeProduct();

        try {
            $this->purchases->createPurchase(
                ['paid_amount' => 99999],
                [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]]
            );
            $this->fail('Hata beklenirdi.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('paid_amount', $e->errors());
        }

        $this->assertSame(0, Purchase::withTrashed()->count());
    }

    public function test_kalemsiz_alim_reddedilir(): void
    {
        $this->expectException(ValidationException::class);

        $this->purchases->createPurchase([], []);
    }
}