<?php

namespace App\Services;

use App\Models\Product;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * List all products.
     *
     * @param int|null $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function list(?int $perPage = null): Collection|LengthAwarePaginator
    {
        if ($perPage) {
            return Product::orderBy('created_at', 'desc')->paginate($perPage);
        }

        return Product::orderBy('created_at', 'desc')->get();
    }

    /**
     * Filter products by search terms (tags, AND'd together — each tag matches
     * name OR short_name), brand, category and active status. Products with
     * stock > 0 always sort before out-of-stock ones.
     *
     * @param array<int, string>|string|null $search Um termo único ou uma lista de termos/tags
     * @param int|null $brandId
     * @param int|null $categoryId
     * @param bool|null $active
     * @param int|null $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function filter(array|string|null $search, ?int $brandId, ?int $categoryId, ?bool $active, ?int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = Product::query()
            ->orderByRaw('CASE WHEN stock > 0 THEN 0 ELSE 1 END')
            ->orderBy('name');

        if ($brandId) {
            $query->where('brand_id', $brandId);
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($active !== null) {
            $query->where('active', $active);
        }

        foreach ($this->normalizeSearchTerms($search) as $term) {
            $term = strtolower($term);
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                  ->orWhereRaw('LOWER(short_name) LIKE ?', ["%{$term}%"]);
            });
        }

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Find a product by ID.
     *
     * @param int $id
     * @return Product
     */
    public function findById(int $id): Product
    {
        return Product::findOrFail($id);
    }

    /**
     * Create a new product.
     *
     * @param StoreProductRequest $request
     * @return Product
     */
    public function create(StoreProductRequest $request): Product
    {
        $data = $request->validated();

        $data['slug'] = $this->generateUniqueSlug($data['name']);

        return Product::create($data);
    }

    /**
     * Update an existing product.
     *
     * @param UpdateProductRequest $request
     * @param Product $product
     * @return Product
     */
    public function update(UpdateProductRequest $request, Product $product): Product
    {
        $data = $request->validated();

        if ($data['name'] !== $product->name) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $product->id);
        }

        $product->update($data);

        return $product->fresh();
    }

    /**
     * Delete a product.
     *
     * @param Product $product
     * @return bool
     * @throws \Exception
     */
    public function delete(Product $product): bool
    {
        return $product->delete();
    }

    /**
     * Generate a unique slug for the product based on its name.
     * Appends -2, -3, ... on collision (checking soft-deleted rows too,
     * since the `slug` column has a plain unique index).
     *
     * @param string $name
     * @param int|null $ignoreId
     * @return string
     */
    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'produto';
        $slug = $base;
        $i = 1;

        while (
            Product::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }

    /**
     * Normaliza o parâmetro de busca (um termo único ou uma lista de tags) em
     * uma lista de termos não vazios e sem duplicados.
     *
     * @param array<int, string>|string|null $search
     * @return array<int, string>
     */
    private function normalizeSearchTerms(array|string|null $search): array
    {
        $items = is_array($search) ? $search : ($search !== null && $search !== '' ? [$search] : []);

        return collect($items)
            ->map(fn ($term) => trim((string) $term))
            ->filter(fn ($term) => $term !== '')
            ->unique()
            ->values()
            ->all();
    }
}
