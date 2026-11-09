<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Developer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// M2 CAT-01..04, PRD-01..04. Category/product write logic lives here,
// not in controllers: controllers only validate (FormRequest) and call
// one service method, then redirect.
class CatalogService
{
    public function __construct(private readonly StockService $stock) {}

    public function createCategory(array $data): Category
    {
        $name = trim((string) $data['name']);

        return Category::create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
        ]);
    }

    public function updateCategory(Category $category, array $data): Category
    {
        $name = trim((string) $data['name']);

        $category->update([
            'name' => $name,
            // CAT-02: renaming changes neither the id nor product links;
            // the slug is regenerated (categories are addressed by id in
            // URLs, never by slug, so this breaks no link).
            'slug' => $name !== $category->name
                ? $this->uniqueSlug($name, $category->id)
                : $category->slug,
        ]);

        return $category->fresh();
    }

    public function createProduct(array $data, User|Developer $actor): Product
    {
        return DB::transaction(function () use ($data, $actor) {
            $product = Product::create([
                'category_id' => $data['category_id'],
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?? null,
                'price_idr' => (int) $data['price_idr'],
                'stock_on_hand' => (int) $data['stock_on_hand'],
                'stock_reserved' => 0,
                'is_active' => $data['is_active'] ?? true,
                'release_date' => $data['release_date'] ?? null,
            ]);

            $this->stock->recordInitialStock($product, $actor);

            return $product;
        });
    }

    public function updateProduct(Product $product, array $data): Product
    {
        // PRD-03: a price change does NOT rewrite old orders, because
        // order items store a price snapshot (ORD-04). There are no
        // orders at all in M2, so this is recorded here as the rule
        // M3 must keep, not as code that touches orders.
        $product->update([
            'category_id' => $data['category_id'] ?? $product->category_id,
            'name' => isset($data['name']) ? trim((string) $data['name']) : $product->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $product->description,
            'price_idr' => isset($data['price_idr']) ? (int) $data['price_idr'] : $product->price_idr,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $product->is_active,
            // release_date hanya berubah bila kuncinya ada di data
            // tervalidasi: form admin tidak mengirimnya, jadi pembaruan
            // dari admin tidak menghapus tanggal yang diset developer.
            'release_date' => array_key_exists('release_date', $data) ? $data['release_date'] : $product->release_date,
        ]);

        return $product->fresh();
    }

    public function toggleProduct(Product $product): Product
    {
        // PRD-04: toggle is never a hard delete; a deactivated product
        // simply stops appearing in the buyer catalog/search (SHP-04).
        $product->update(['is_active' => ! $product->is_active]);

        return $product->fresh();
    }

    private function uniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'kategori';
        }

        $slug = $base;
        $suffix = 2;

        while (Category::query()
            ->where('slug', $slug)
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
