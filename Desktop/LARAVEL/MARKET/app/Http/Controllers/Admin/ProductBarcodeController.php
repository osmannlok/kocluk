<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Services\BarcodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductBarcodeController extends Controller
{
    public function __construct(private readonly BarcodeService $barcodeService)
    {
    }

    /**
     * Ürünün barkod yönetimi ekranı.
     * GET /admin/products/{product}/barcodes
     */
    public function index(Request $request, Product $product): View
    {
        $this->authorizeManage($request);

        $barcodes = $product->barcodes()
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        return view('admin.products.barcodes', compact('product', 'barcodes'));
    }

    /**
     * Ürüne barkod ekler.
     * POST /admin/products/{product}/barcodes
     */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate($this->rules(), $this->messages());

        $this->barcodeService->addBarcode(
            $product,
            $data['barcode'],
            $request->boolean('is_primary')
        );

        return back()->with('success', 'Barkod başarıyla eklendi.');
    }

    /**
     * Barkodu düzenler.
     * PUT /admin/barcodes/{productBarcode}
     */
    public function update(Request $request, ProductBarcode $productBarcode): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate($this->rules(), $this->messages());

        $this->barcodeService->updateBarcode(
            $productBarcode,
            $data['barcode'],
            $request->has('is_primary') ? $request->boolean('is_primary') : null
        );

        return back()->with('success', 'Barkod başarıyla güncellendi.');
    }

    /**
     * Barkodu ana barkod yapar.
     * PATCH /admin/barcodes/{productBarcode}/primary
     */
    public function setPrimary(Request $request, ProductBarcode $productBarcode): RedirectResponse
    {
        $this->authorizeManage($request);

        $this->barcodeService->setPrimary($productBarcode);

        return back()->with('success', 'Ana barkod güncellendi.');
    }

    /**
     * Barkodu siler. Onay penceresi Blade tarafında gösterilir.
     * DELETE /admin/barcodes/{productBarcode}
     */
    public function destroy(Request $request, ProductBarcode $productBarcode): RedirectResponse
    {
        $this->authorizeManage($request);

        $this->barcodeService->deleteBarcode($productBarcode);

        return back()->with('success', 'Barkod silindi.');
    }

    /**
     * Yalnızca yönetici ve depocu barkod yönetebilir.
     */
    private function authorizeManage(Request $request): void
    {
        abort_unless(
            in_array($request->user()?->role, ['admin', 'warehouse'], true),
            403,
            'Bu işlem için yetkiniz yok.'
        );
    }

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