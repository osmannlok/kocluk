<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductBarcodeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test için minimum alanlarla ürün oluşturur.
     */
    private function makeProduct(array $overrides = []): Product
    {
        return Product::forceCreate(array_merge([
            'name' => 'Bol Yoğurtlu Tarhana',
            'slug' => 'bol-yogurtlu-tarhana-' . uniqid(),
            'unit' => 'kg',
            'purchase_price' => 250,
            'sale_price' => 350,
            'stock' => 100,
            'critical_stock' => 10,
            'is_active' => true,
        ], $overrides));
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_barkodla_aktif_urun_bulunur(): void
    {
        Sanctum::actingAs($this->makeUser('cashier'));

        $product = $this->makeProduct();
        $product->barcodes()->create(['barcode' => '8690000000011', 'is_primary' => true]);

        $this->getJson('/api/products/barcode/8690000000011')
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'Bol Yoğurtlu Tarhana');
    }

    public function test_olmayan_barkod_404_doner(): void
    {
        Sanctum::actingAs($this->makeUser('cashier'));

        $this->getJson('/api/products/barcode/0000000000000')
            ->assertNotFound();
    }

    public function test_pasif_urun_barkodla_bulunamaz(): void
    {
        Sanctum::actingAs($this->makeUser('cashier'));

        $product = $this->makeProduct(['is_active' => false]);
        $product->barcodes()->create(['barcode' => '8690000000028', 'is_primary' => true]);

        $this->getJson('/api/products/barcode/8690000000028')
            ->assertNotFound();
    }

    public function test_token_olmadan_barkod_aramasi_401_doner(): void
    {
        $this->getJson('/api/products/barcode/8690000000011')
            ->assertUnauthorized();
    }

    public function test_kasiyer_barkod_ekleyemez(): void
    {
        Sanctum::actingAs($this->makeUser('cashier'));

        $product = $this->makeProduct();

        $this->postJson("/api/products/{$product->id}/barcodes", ['barcode' => '8690000000035'])
            ->assertForbidden();
    }

    public function test_depocu_barkod_ekleyebilir_ilk_barkod_ana_olur(): void
    {
        Sanctum::actingAs($this->makeUser('warehouse'));

        $product = $this->makeProduct();

        $this->postJson("/api/products/{$product->id}/barcodes", ['barcode' => '8690000000035'])
            ->assertCreated()
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('product_barcodes', [
            'product_id' => $product->id,
            'barcode' => '8690000000035',
            'is_primary' => true,
        ]);
    }

    public function test_ayni_barkod_ikinci_kez_eklenemez(): void
    {
        Sanctum::actingAs($this->makeUser('admin'));

        $first = $this->makeProduct();
        $second = $this->makeProduct(['name' => 'Kırmızı Pul Biber']);

        $this->postJson("/api/products/{$first->id}/barcodes", ['barcode' => '8690000000042'])
            ->assertCreated();

        $this->postJson("/api/products/{$second->id}/barcodes", ['barcode' => '8690000000042'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('barcode');

        $this->assertSame(1, ProductBarcode::where('barcode', '8690000000042')->count());
    }

    public function test_urune_birden_fazla_barkod_eklenir_ve_ana_barkod_degisir(): void
    {
        Sanctum::actingAs($this->makeUser('admin'));

        $product = $this->makeProduct();

        $this->postJson("/api/products/{$product->id}/barcodes", ['barcode' => '1111111111111'])->assertCreated();
        $this->postJson("/api/products/{$product->id}/barcodes", ['barcode' => '2222222222222'])->assertCreated();

        $second = ProductBarcode::where('barcode', '2222222222222')->firstOrFail();
        $this->assertFalse($second->is_primary);

        $this->patchJson("/api/barcodes/{$second->id}/primary")->assertOk();

        $this->assertSame(1, $product->barcodes()->where('is_primary', true)->count());
        $this->assertTrue($second->refresh()->is_primary);
    }

    public function test_ana_barkod_silinince_kalan_barkod_ana_olur(): void
    {
        Sanctum::actingAs($this->makeUser('admin'));

        $product = $this->makeProduct();
        $primary = $product->barcodes()->create(['barcode' => '3333333333333', 'is_primary' => true]);
        $other = $product->barcodes()->create(['barcode' => '4444444444444', 'is_primary' => false]);

        $this->deleteJson("/api/barcodes/{$primary->id}")->assertOk();

        $this->assertDatabaseMissing('product_barcodes', ['id' => $primary->id]);
        $this->assertTrue($other->refresh()->is_primary);
    }

    public function test_barkod_duzenlenebilir(): void
    {
        Sanctum::actingAs($this->makeUser('admin'));

        $product = $this->makeProduct();
        $barcode = $product->barcodes()->create(['barcode' => '5555555555555', 'is_primary' => true]);

        $this->putJson("/api/barcodes/{$barcode->id}", ['barcode' => '6666666666666'])
            ->assertOk();

        $this->assertDatabaseHas('product_barcodes', [
            'id' => $barcode->id,
            'barcode' => '6666666666666',
        ]);
    }
}