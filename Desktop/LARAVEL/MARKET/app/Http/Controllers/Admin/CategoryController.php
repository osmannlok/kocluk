<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categoryService)
    {
    }

    /**
     * Kategori listesi (arama + sayfalama).
     */
    public function index(Request $request): View
    {
        $this->ensureCanManage($request);

        $categories = $this->categoryService->paginate($request->query('search'));

        return view('admin.categories.index', compact('categories'));
    }

    /**
     * Yeni kategori formu.
     */
    public function create(Request $request): View
    {
        $this->ensureCanManage($request);

        return view('admin.categories.create');
    }

    /**
     * Yeni kategori kaydeder.
     */
    public function store(CategoryRequest $request): RedirectResponse
    {
        $this->categoryService->create($request->validated());

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Kategori başarıyla eklendi.');
    }

    /**
     * Kategori düzenleme formu.
     */
    public function edit(Request $request, Category $category): View
    {
        $this->ensureCanManage($request);

        return view('admin.categories.edit', compact('category'));
    }

    /**
     * Kategoriyi günceller.
     */
    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->categoryService->update($category, $request->validated());

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Kategori başarıyla güncellendi.');
    }

    /**
     * Kategoriyi siler (soft delete). Onay Blade tarafında istenir.
     */
    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $this->categoryService->delete($category);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.categories.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Kategori silindi.');
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
}