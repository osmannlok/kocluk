<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\CategoryService;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly CategoryService $categoryService,
    ) {
    }

    /**
     * Ürün listesi (arama, kategori, durum, kritik stok filtresi + sayfalama).
     */
    public function index(Request $request): View
    {
        $this->ensureCanManage($request);

        $filters = $request->only(['search', 'category_id', 'status', 'low_stock']);

        $products   = $this->productService->paginate($filters);
        $categories = $this->categoryService->activeList();

        return view('admin.products.index', compact('products', 'categories', 'filters'));
    }

    /**
     * Yeni ürün formu.
     */
    public function create(Request $request): View
    {
        $this->ensureCanManage($request);

        return view('admin.products.create', [
            'categories' => $this->categoryService->activeList(),
            'suppliers'  => $this->supplierList(),
            'units'      => $this->units(),
        ]);
    }

    /**
     * Yeni ürün kaydeder.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        $data          = $request->validated();
        $data['image'] = $request->file('image');

        $this->productService->create($data);

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Ürün başarıyla eklendi.');
    }

    /**
     * Ürün düzenleme formu.
     */
    public function edit(Request $request, Product $product): View
    {
        $this->ensureCanManage($request);

        $product->load('primaryBarcode');

        return view('admin.products.edit', [
            'product'    => $product,
            'categories' => $this->categoryService->activeList(),
            'suppliers'  => $this->supplierList(),
            'units'      => $this->units(),
        ]);
    }

    /**
     * Ürünü günceller.
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data          = $request->validated();
        $data['image'] = $request->file('image');

        $this->productService->update($product, $data);

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Ürün başarıyla güncellendi.');
    }

    /**
     * Ürünü pasife alır veya yeniden aktif eder.
     */
    public function toggleActive(Request $request, Product $product): RedirectResponse
    {
        $this->ensureCanManage($request);

        $product = $this->productService->toggleActive($product);

        return redirect()
            ->back()
            ->with('success', $product->is_active ? 'Ürün aktif edildi.' : 'Ürün pasife alındı.');
    }

    /**
     * Ürünü soft delete ile siler. Onay Blade tarafında istenir.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->ensureCanManage($request);

        $this->productService->delete($product);

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Ürün silindi.');
    }

    /**
     * Yalnızca admin ve warehouse kullanıcıları erişebilir.
     */
    private function ensureCanManage(Request $request): void
    {
        abort_unless(
            in_array($request->user()?->role, ['admin', 'warehouse'], true),
            403,
            'Bu işlem için yetkiniz yok.'
        );
    }

    /**
     * Form seçim listesi için aktif tedarikçiler.
     */
    private function supplierList()
    {
        return Supplier::active()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Geçerli ürün birimleri.
     */
    private function units(): array
    {
        return ['adet', 'kg', 'gram', 'litre', 'paket'];
    }
}