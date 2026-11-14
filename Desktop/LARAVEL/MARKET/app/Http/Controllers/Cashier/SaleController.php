<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Services\BarcodeService;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
        private readonly BarcodeService $barcodeService
    ) {
    }

    /**
     * Kasa ekranı.
     * GET /cashier/sales/create
     */
    public function create(Request $request): View
    {
        $this->authorizeSell($request);

        $customers = Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        return view('cashier.sales.create', compact('customers'));
    }

    /**
     * Barkodla ürün bulur (oturum ile çalışır, kasa ekranı bunu kullanır).
     * GET /cashier/sales/product-by-barcode/{barcode}
     */
    public function findByBarcode(Request $request, string $barcode): JsonResponse
    {
        $this->authorizeSell($request);

        $product = $this->barcodeService->findProductByBarcode($barcode);

        if (! $product) {
            return response()->json([
                'message' => 'Bu barkoda ait aktif ürün bulunamadı.',
            ], 404);
        }

        return response()->json(['data' => $this->formatProduct($product, trim($barcode))]);
    }

    /**
     * Barkod okunamadığında ürün adı, barkod veya SKU ile manuel arama.
     * GET /cashier/sales/search-products?q=tarhana
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $this->authorizeSell($request);

        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $products = Product::query()
            ->where('is_active', true)
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhereHas('barcodes', fn ($q) => $q->where('barcode', 'like', "%{$term}%"));
            })
            ->with(['barcodes' => fn ($q) => $q->orderByDesc('is_primary')])
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $products->map(fn (Product $p) => $this->formatProduct($p))->values(),
        ]);
    }

    /**
     * Satışı tamamlar. İş mantığı SaleService içindedir.
     * POST /cashier/sales
     */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $sale = $this->saleService->createSale(
            [
                'customer_id' => $data['customer_id'] ?? null,
                'discount' => $data['discount'] ?? 0,
                'note' => $data['note'] ?? null,
            ],
            $data['items'],
            $data['payments'],
            $request->user()
        );

        return response()->json([
            'message' => 'Satış başarıyla tamamlandı.',
            'sale_id' => $sale->id,
            'sale_no' => $sale->sale_no,
            'total' => $sale->total,
            'paid_total' => $sale->paid_total,
            'change_amount' => $sale->change_amount,
        ], 201);
    }

    /**
     * Satışı yalnızca yönetici ve kasiyer yapabilir.
     */
    private function authorizeSell(Request $request): void
    {
        abort_unless(
            in_array($request->user()?->role, ['admin', 'cashier'], true),
            403,
            'Bu işlem için yetkiniz yok.'
        );
    }

    /**
     * Kasa ekranının ihtiyaç duyduğu ürün alanları.
     */
    private function formatProduct(Product $product, ?string $barcode = null): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'barcode' => $barcode ?? $product->barcodes->first()?->barcode,
            'unit' => $product->unit,
            'sale_price' => $product->sale_price,
            'vat_rate' => $product->vat_rate,
            'stock' => $product->stock,
            'is_weighed' => (bool) $product->is_weighed,
            'in_stock' => (float) $product->stock > 0,
        ];
    }
}