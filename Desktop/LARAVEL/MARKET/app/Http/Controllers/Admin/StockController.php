<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    /**
     * Stok ekranı: kritik stok listesi, fire/sayım formları ve son hareketler.
     * GET /admin/stock
     */
    public function index(Request $request): View
    {
        $this->authorizeManage($request);

        $lowStockProducts = $this->stockService->getLowStockProducts();

        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'stock']);

        $movements = StockMovement::query()
            ->with(['product:id,name,unit', 'user:id,name'])
            ->latest('id')
            ->paginate(20);

        return view('admin.stock.index', compact('lowStockProducts', 'products', 'movements'));
    }

    /**
     * Fire / bozulma kaydı oluşturur.
     * POST /admin/stock/damage
     */
    public function storeDamage(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'note' => ['required', 'string', 'max:500'],
        ], [
            'product_id.required' => 'Ürün seçimi zorunludur.',
            'product_id.exists' => 'Seçilen ürün bulunamadı.',
            'quantity.required' => 'Miktar zorunludur.',
            'quantity.numeric' => 'Miktar sayı olmalıdır.',
            'quantity.gt' => 'Miktar sıfırdan büyük olmalıdır.',
            'note.required' => 'Fire gerekçesi zorunludur.',
            'note.max' => 'Gerekçe en fazla 500 karakter olabilir.',
        ]);

        $this->stockService->recordDamage(
            (int) $data['product_id'],
            (float) $data['quantity'],
            $data['note'],
            $request->user()->id
        );

        return back()->with('success', 'Fire kaydı oluşturuldu, stok güncellendi.');
    }

    /**
     * Stok sayımı kaydeder. Fark varsa düzeltme (correction) kaydı oluşur.
     * POST /admin/stock/count
     */
    public function storeCount(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'counted_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'note' => ['required', 'string', 'max:500'],
        ], [
            'product_id.required' => 'Ürün seçimi zorunludur.',
            'product_id.exists' => 'Seçilen ürün bulunamadı.',
            'counted_stock.required' => 'Sayılan stok zorunludur.',
            'counted_stock.numeric' => 'Sayılan stok sayı olmalıdır.',
            'counted_stock.min' => 'Sayılan stok eksi olamaz.',
            'note.required' => 'Sayım gerekçesi zorunludur.',
            'note.max' => 'Gerekçe en fazla 500 karakter olabilir.',
        ]);

        $movement = $this->stockService->recordCount(
            (int) $data['product_id'],
            (float) $data['counted_stock'],
            $data['note'],
            $request->user()->id
        );

        if ($movement === null) {
            return back()->with('info', 'Sayım sistemdeki stokla aynı, düzeltme gerekmedi.');
        }

        return back()->with('success', 'Sayım kaydedildi, stok düzeltildi.');
    }

    /**
     * Fire ve sayım yalnızca yönetici ve depocu tarafından yapılabilir.
     */
    private function authorizeManage(Request $request): void
    {
        abort_unless(
            in_array($request->user()?->role, ['admin', 'warehouse'], true),
            403,
            'Bu işlem için yetkiniz yok.'
        );
    }
}