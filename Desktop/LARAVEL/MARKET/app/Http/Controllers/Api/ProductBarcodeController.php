<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Services\BarcodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductBarcodeController extends Controller
{
    public function __construct(private readonly BarcodeService $barcodeService)
    {
    }

    /**
     * Barkodla ürün arama. Kasiyer dahil giriş yapmış tüm kullanıcılar kullanabilir.
     * GET /api/products/barcode/{barcode}
     */
    public function findByBarcode(string $barcode): JsonResponse
    {
        $product = $this->barcodeService->findProductByBarcode($barcode);

        if (! $product) {
            return response()->json([
                'message' => 'Bu barkoda ait aktif ürün bulunamadı.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'barcode' => trim($barcode),
                'unit' => $product->unit,
                'sale_price' => $product->sale_price,
                'vat_rate' => $product->vat_rate,
                'stock' => $product->stock,
                'is_weighed' => (bool) $product->is_weighed,
            ],
        ]);
    }

    /**
     * Ürünün barkodlarını listeler.
     * GET /api/products/{product}/barcodes
     */
    public function index(Request $request, Product $product): JsonResponse
    {
        $this->authorizeManage($request);

        return response()->json([
            'data' => $product->barcodes()
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->get(['id', 'product_id', 'barcode', 'is_primary']),
        ]);
    }

    /**
     * Ürüne barkod ekler.
     * POST /api/products/{product}/barcodes
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate($this->rules(), $this->messages());

        $barcode = $this->barcodeService->addBarcode(
            $product,
            $data['barcode'],
            (bool) ($data['is_primary'] ?? false)
        );

        return response()->json([
            'message' => 'Barkod başarıyla eklendi.',
            'data' => $barcode,
        ], 201);
    }

    /**
     * Barkodu düzenler.
     * PUT /api/barcodes/{productBarcode}
     */
    public function update(Request $request, ProductBarcode $productBarcode): JsonResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate($this->rules(), $this->messages());

        $barcode = $this->barcodeService->updateBarcode(
            $productBarcode,
            $data['barcode'],
            array_key_exists('is_primary', $data) ? (bool) $data['is_primary'] : null
        );

        return response()->json([
            'message' => 'Barkod başarıyla güncellendi.',
            'data' => $barcode,
        ]);
    }

    /**
     * Barkodu ana barkod yapar.
     * PATCH /api/barcodes/{productBarcode}/primary
     */
    public function setPrimary(Request $request, ProductBarcode $productBarcode): JsonResponse
    {
        $this->authorizeManage($request);

        $barcode = $this->barcodeService->setPrimary($productBarcode);

        return response()->json([
            'message' => 'Ana barkod güncellendi.',
            'data' => $barcode,
        ]);
    }

    /**
     * Barkodu siler. Silme onayı arayüzde istenir.
     * DELETE /api/barcodes/{productBarcode}
     */
    public function destroy(Request $request, ProductBarcode $productBarcode): JsonResponse
    {
        $this->authorizeManage($request);

        $this->barcodeService->deleteBarcode($productBarcode);

        return response()->json([
            'message' => 'Barkod silindi.',
        ]);
    }

    /**
     * Barkod yönetimi yalnızca yönetici ve depocu tarafından yapılabilir.
     */
    private function authorizeManage(Request $request): void
    {
        abort_unless(
            in_array($request->user()?->role, ['admin', 'warehouse'], true),
            403,
            'Bu işlem için yetkiniz yok.'
        );
    }

    /**
     * Doğrulama kuralları. Benzersizlik kontrolü serviste yapılır.
     */
    private function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:100'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'barcode.required' => 'Barkod alanı zorunludur.',
            'barcode.string' => 'Barkod metin olmalıdır.',
            'barcode.max' => 'Barkod en fazla 100 karakter olabilir.',
            'is_primary.boolean' => 'Ana barkod değeri geçersiz.',
        ];
    }
}