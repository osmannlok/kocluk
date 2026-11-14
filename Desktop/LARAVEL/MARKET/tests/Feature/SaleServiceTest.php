<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaleServiceTest extends TestCase
{
    use RefreshDatabase;

    private SaleService $sales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sales = app(SaleService::class);
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::forceCreate(array_merge([
            'name' => 'Bol Yoğurtlu Tarhana',
            'slug' => 'tarhana-' . uniqid(),
            'unit' => 'kg',
            'purchase_price' => 250,
            'sale_price' => 350,
            'vat_rate' => 0,
            'stock' => 20,
            'critical_stock' => 5,
            'is_active' => true,
        ], $overrides));
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function makeCustomer(): Customer
    {
        return Customer::forceCreate([
            'name' => 'Test Müşteri',
            'phone' => '05' . random_int(100000000, 999999999),
            'balance' => 0,
            'is_active' => true,
        ]);
    }

    public function test_satis_tum_kayitlari_olusturur_ve_stogu_azaltir(): void
    {
        $cashier = $this->makeUser('cashier');
        $product = $this->makeProduct(['stock' => 20]);

        $sale = $this->sales->createSale(
            [],
            [['product_id' => $product->id, 'quantity' => 2]],
            [['payment_type' => 'cash', 'amount' => 700]],
            $cashier
        );

        $this->assertSame(1, Sale::count());
        $this->assertSame(1, SaleItem::where('sale_id', $sale->id)->count());
        $this->assertSame(1, Payment::where('sale_id', $sale->id)->count());
        $this->assertSame(1, StockMovement::where('sale_id', $sale->id)->where('movement_type', 'sale')->count());

        $this->assertEquals(700, (float) $sale->total);
        $this->assertSame('completed', $sale->status);
        $this->assertEquals(18, (float) $product->fresh()->stock);

        $item = SaleItem::where('sale_id', $sale->id)->first();
        $this->assertEquals(250, (float) $item->purchase_price); // satış anı maliyeti
        $this->assertEquals(200, (float) $item->profit);         // 2 x (350 - 250)
    }

    public function test_para_ustu_hesaplanir_ve_odeme_kaydina_gercek_tutar_yazilir(): void
    {
        $product = $this->makeProduct(['sale_price' => 100, 'purchase_price' => 60]);

        $sale = $this->sales->createSale(
            [],
            [['product_id' => $product->id, 'quantity' => 1]],
            [['payment_type' => 'cash', 'amount' => 150]],
            $this->makeUser('cashier')
        );

        $this->assertEquals(100, (float) $sale->total);
        $this->assertEquals(150, (float) $sale->paid_total);
        $this->assertEquals(50, (float) $sale->change_amount);
        $this->assertEquals(100, (float) Payment::where('sale_id', $sale->id)->sum('amount'));
    }

    public function test_odeme_toplami_satis_toplamindan_az_olamaz(): void
    {
        $product = $this->makeProduct(['sale_price' => 100]);

        try {
            $this->sales->createSale(
                [],
                [['product_id' => $product->id, 'quantity' => 1]],
                [['payment_type' => 'cash', 'amount' => 50]],
                $this->makeUser('cashier')
            );
            $this->fail('Eksik ödemede hata beklenirdi.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payments', $e->errors());
        }

        $this->assertSame(0, Sale::count());
        $this->assertEquals(20, (float) $product->fresh()->stock);
    }

    public function test_kasiyer_yuzde_10dan_fazla_indirim_yapamaz(): void
    {
        $product = $this->makeProduct(['sale_price' => 100, 'purchase_price' => 60]);

        try {
            // Birim başına 15 TL = %15
            $this->sales->createSale(
                [],
                [['product_id' => $product->id, 'quantity' => 1, 'discount' => 15]],
                [['payment_type' => 'cash', 'amount' => 85]],
                $this->makeUser('cashier')
            );
            $this->fail('%15 indirimde hata beklenirdi.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.discount', $e->errors());
        }

        $this->assertSame(0, Sale::count());
        $this->assertEquals(20, (float) $product->fresh()->stock);
    }

    public function test_kasiyer_yuzde_10_indirim_yapabilir(): void
    {
        $product = $this->makeProduct(['sale_price' => 100, 'purchase_price' => 60]);

        $sale = $this->sales->createSale(
            [],
            [['product_id' => $product->id, 'quantity' => 1, 'discount' => 10]],
            [['payment_type' => 'cash', 'amount' => 90]],
            $this->makeUser('cashier')
        );

        $this->assertEquals(90, (float) $sale->total);
        $this->assertEquals(30, (float) SaleItem::where('sale_id', $sale->id)->first()->profit); // 90 - 60
    }

    public function test_yonetici_yuzde_10dan_fazla_indirim_yapabilir(): void
    {
        $product = $this->makeProduct(['sale_price' => 100, 'purchase_price' => 60]);

        $sale = $this->sales->createSale(
            [],
            [['product_id' => $product->id, 'quantity' => 1, 'discount' => 20]],
            [['payment_type' => 'cash', 'amount' => 80]],
            $this->makeUser('admin')
        );

        $this->assertEquals(80, (float) $sale->total);
    }

    public function test_genel_indirim_kari_dogru_dagitir(): void
    {
        $a = $this->makeProduct(['name' => 'A', 'sale_price' => 100, 'purchase_price' => 50]);
        $b = $this->makeProduct(['name' => 'B', 'sale_price' => 200, 'purchase_price' => 100]);

        // Kasiyer: toplam 300, genel indirim 30 = %10
        $sale = $this->sales->createSale(
            ['discount' => 30],
            [
                ['product_id' => $a->id, 'quantity' => 1],
                ['product_id' => $b->id, 'quantity' => 1],
            ],
            [['payment_type' => 'card', 'amount' => 270]],
            $this->makeUser('cashier')
        );

        $this->assertEquals(270, (float) $sale->total);

        // Toplam kâr = ciro - maliyet = 270 - 150
        $this->assertEquals(120, (float) SaleItem::where('sale_id', $sale->id)->sum('profit'));
        $this->assertEquals(270, (float) SaleItem::where('sale_id', $sale->id)->sum('total_price'));
    }

    public function test_veresiye_musterisiz_reddedilir(): void
    {
        $product = $this->makeProduct(['sale_price' => 100]);

        try {
            $this->sales->createSale(
                [],
                [['product_id' => $product->id, 'quantity' => 1]],
                [['payment_type' => 'credit', 'amount' => 100]],
                $this->makeUser('cashier')
            );
            $this->fail('Müşterisiz veresiyede hata beklenirdi.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('customer_id', $e->errors());
        }

        $this->assertSame(0, Sale::count());
    }

    public function test_veresiye_musteri_bakiyesini_artirir(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(['sale_price' => 100]);

        $sale = $this->sales->createSale(
            ['customer_id' => $customer->id],
            [['product_id' => $product->id, 'quantity' => 2]],
            [['payment_type' => 'credit', 'amount' => 200]],
            $this->makeUser('cashier')
        );

        $this->assertEquals(200, (float) $customer->fresh()->balance);
        $this->assertSame('credit', Payment::where('sale_id', $sale->id)->first()->payment_type);
    }

    public function test_karma_odemede_para_ustu_nakitten_dusulur(): void
    {
        $product = $this->makeProduct(['sale_price' => 100]);

        $sale = $this->sales->createSale(
            [],
            [['product_id' => $product->id, 'quantity' => 1]],
            [
                ['payment_type' => 'card', 'amount' => 40],
                ['payment_type' => 'cash', 'amount' => 80],
            ],
            $this->makeUser('cashier')
        );

        $this->assertEquals(20, (float) $sale->change_amount);
        $this->assertEquals(40, (float) Payment::where('sale_id', $sale->id)->where('payment_type', 'card')->sum('amount'));
        $this->assertEquals(60, (float) Payment::where('sale_id', $sale->id)->where('payment_type', 'cash')->sum('amount'));
    }

    public function test_para_ustu_kartla_verilemez(): void
    {
        $product = $this->makeProduct(['sale_price' => 100]);

        $this->expectException(ValidationException::class);

        $this->sales->createSale(
            [],
            [['product_id' => $product->id, 'quantity' => 1]],
            [['payment_type' => 'card', 'amount' => 150]],
            $this->makeUser('cashier')
        );
    }

    public function test_yetersiz_stokta_satis_tamamen_geri_alinir(): void
    {
        $customer = $this->makeCustomer();
        $ok = $this->makeProduct(['name' => 'Yeterli', 'stock' => 10, 'sale_price' => 100]);
        $low = $this->makeProduct(['name' => 'Az Stok', 'stock' => 1, 'sale_price' => 100]);

        try {
            $this->sales->createSale(
                ['customer_id' => $customer->id],
                [
                    ['product_id' => $ok->id, 'quantity' => 2],
                    ['product_id' => $low->id, 'quantity' => 5],
                ],
                [['payment_type' => 'credit', 'amount' => 700]],
                $this->makeUser('cashier')
            );
            $this->fail('Yetersiz stokta hata beklenirdi.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        $this->assertSame(0, Sale::withTrashed()->count());
        $this->assertSame(0, SaleItem::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertEquals(10, (float) $ok->fresh()->stock);
        $this->assertEquals(1, (float) $low->fresh()->stock);
        $this->assertEquals(0, (float) $customer->fresh()->balance);
    }

    public function test_pasif_urun_satilamaz(): void
    {
        $product = $this->makeProduct(['is_active' => false]);

        $this->expectException(ValidationException::class);

        $this->sales->createSale(
            [],
            [['product_id' => $product->id, 'quantity' => 1]],
            [['payment_type' => 'cash', 'amount' => 350]],
            $this->makeUser('cashier')
        );
    }

    public function test_stoku_sifir_olan_urun_satilamaz(): void
    {
        $product = $this->makeProduct(['stock' => 0]);

        $this->expectException(ValidationException::class);

        $this->sales->createSale(
            [],
            [['product_id' => $product->id, 'quantity' => 1]],
            [['payment_type' => 'cash', 'amount' => 350]],
            $this->makeUser('cashier')
        );
    }

    public function test_bos_sepet_reddedilir(): void
    {
        $this->expectException(ValidationException::class);

        $this->sales->createSale(
            [],
            [],
            [['payment_type' => 'cash', 'amount' => 100]],
            $this->makeUser('cashier')
        );
    }

    public function test_fis_numaralari_benzersizdir(): void
    {
        $product = $this->makeProduct(['stock' => 50, 'sale_price' => 10]);
        $user = $this->makeUser('cashier');

        $first = $this->sales->createSale([], [['product_id' => $product->id, 'quantity' => 1]], [['payment_type' => 'cash', 'amount' => 10]], $user);
        $second = $this->sales->createSale([], [['product_id' => $product->id, 'quantity' => 1]], [['payment_type' => 'cash', 'amount' => 10]], $user);

        $this->assertNotSame($first->sale_no, $second->sale_no);
        $this->assertStringStartsWith('S-' . now()->format('Y') . '-', $first->sale_no);
    }
}