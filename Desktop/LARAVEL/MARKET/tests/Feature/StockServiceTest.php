<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = app(StockService::class);
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::forceCreate(array_merge([
            'name' => 'Kırmızı Pul Biber',
            'slug' => 'kirmizi-pul-biber-' . uniqid(),
            'unit' => 'kg',
            'purchase_price' => 180,
            'sale_price' => 260,
            'stock' => 50,
            'critical_stock' => 5,
            'is_active' => true,
        ], $overrides));
    }

    private function stockOf(Product $product): float
    {
        return (float) $product->fresh()->stock;
    }

    public function test_urun_girisi_stogu_artirir_ve_hareket_kaydeder(): void
    {
        $product = $this->makeProduct(['stock' => 50]);
        $user = User::factory()->create(['role' => 'warehouse']);

        $movement = $this->stock->addPurchase($product, 10, $user->id, null, 'Tedarikçiden gelen ürün');

        $this->assertSame(60.0, $this->stockOf($product));
        $this->assertSame('purchase', $movement->movement_type);
        $this->assertEquals(50, (float) $movement->stock_before);
        $this->assertEquals(60, (float) $movement->stock_after);
        $this->assertSame($user->id, $movement->user_id);
    }

    public function test_fire_stogu_azaltir_ve_damage_kaydi_olusur(): void
    {
        $product = $this->makeProduct(['stock' => 50]);

        $movement = $this->stock->recordDamage($product, 4.5, 'Bozulma');

        $this->assertSame(45.5, $this->stockOf($product));
        $this->assertSame('damage', $movement->movement_type);
        $this->assertEquals(50, (float) $movement->stock_before);
        $this->assertEquals(45.5, (float) $movement->stock_after);
    }

    public function test_fire_icin_gerekce_zorunludur(): void
    {
        $product = $this->makeProduct();

        $this->expectException(ValidationException::class);

        try {
            $this->stock->recordDamage($product, 1, '   ');
        } finally {
            $this->assertSame(50.0, $this->stockOf($product));
            $this->assertSame(0, StockMovement::count());
        }
    }

    public function test_stok_eksiye_dusemez(): void
    {
        $product = $this->makeProduct(['stock' => 3]);

        try {
            $this->stock->recordDamage($product, 10, 'Kırıldı');
            $this->fail('Yetersiz stokta hata beklenirdi.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        $this->assertSame(3.0, $this->stockOf($product));
        $this->assertSame(0, StockMovement::count());
    }

    public function test_sifir_veya_eksi_miktar_reddedilir(): void
    {
        $product = $this->makeProduct();

        $this->expectException(ValidationException::class);

        $this->stock->addPurchase($product, 0);
    }

    public function test_satis_cikisi_ve_iade_girisi(): void
    {
        $product = $this->makeProduct(['stock' => 20]);

        $sale = $this->stock->removeForSale($product, 2, 1);
        $this->assertSame(18.0, $this->stockOf($product));
        $this->assertSame('sale', $sale->movement_type);

        $return = $this->stock->addReturn($product, 2, 1, null, 'Müşteri iadesi');
        $this->assertSame(20.0, $this->stockOf($product));
        $this->assertSame('return', $return->movement_type);
    }

    public function test_sayim_farki_varsa_correction_kaydi_olusur(): void
    {
        $product = $this->makeProduct(['stock' => 50]);

        $movement = $this->stock->recordCount($product, 47, 'Sayım farkı: dökülme');

        $this->assertNotNull($movement);
        $this->assertSame('correction', $movement->movement_type);
        $this->assertEquals(-3, (float) $movement->quantity);
        $this->assertEquals(50, (float) $movement->stock_before);
        $this->assertEquals(47, (float) $movement->stock_after);
        $this->assertSame(47.0, $this->stockOf($product));
    }

    public function test_sayim_farki_yoksa_kayit_olusmaz(): void
    {
        $product = $this->makeProduct(['stock' => 50]);

        $movement = $this->stock->recordCount($product, 50, 'Sayım tuttu');

        $this->assertNull($movement);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_sayim_gerekcesi_zorunludur(): void
    {
        $product = $this->makeProduct();

        $this->expectException(ValidationException::class);

        $this->stock->recordCount($product, 40, '');
    }

    public function test_kritik_stoktaki_urunler_listelenir(): void
    {
        $low = $this->makeProduct(['name' => 'Az Kalan', 'stock' => 2, 'critical_stock' => 10]);
        $this->makeProduct(['name' => 'Bol Stok', 'stock' => 100, 'critical_stock' => 10]);
        $this->makeProduct(['name' => 'Pasif Az Kalan', 'stock' => 1, 'critical_stock' => 10, 'is_active' => false]);

        $result = $this->stock->getLowStockProducts();

        $this->assertCount(1, $result);
        $this->assertSame($low->id, $result->first()->id);
        $this->assertTrue($this->stock->isLowStock($low));
    }
}