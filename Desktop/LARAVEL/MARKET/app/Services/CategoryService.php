<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CategoryService
{
    /**
     * Kategorileri arama ve sayfalama ile listeler.
     */
    public function paginate(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return Category::query()
            ->withCount('products')
            ->when(filled($search), function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Ürün formlarındaki filtre ve seçim listeleri için aktif kategoriler.
     */
    public function activeList()
    {
        return Category::active()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Yeni kategori oluşturur.
     */
    public function create(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            return Category::create($data);
        });
    }

    /**
     * Kategoriyi günceller.
     */
    public function update(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $category->update($data);

            return $category->refresh();
        });
    }

    /**
     * Kategoriyi soft delete ile siler.
     * İçinde ürün bulunan kategori silinemez; önce pasife alınmalıdır.
     */
    public function delete(Category $category): void
    {
        if ($category->products()->exists()) {
            throw new RuntimeException(
                'Bu kategoriye bağlı ürünler var. Silmek yerine kategoriyi pasife alın.'
            );
        }

        DB::transaction(function () use ($category) {
            $category->delete();
        });
    }
}