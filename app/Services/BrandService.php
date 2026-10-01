<?php

namespace App\Services;

use App\Models\Brand;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class BrandService
{
    /**
     * List all brands.
     *
     * @param int|null $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function list(?int $perPage = null): Collection|LengthAwarePaginator
    {
        if ($perPage) {
            return Brand::orderBy('name')->paginate($perPage);
        }

        return Brand::orderBy('name')->get();
    }

    /**
     * Filter brands by search term.
     *
     * @param string|null $search
     * @param int|null $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function filter(?string $search, ?int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = Brand::orderBy('name');

        if ($search) {
            $query->search($search);
        }

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Find a brand by ID.
     *
     * @param int $id
     * @return Brand
     */
    public function findById(int $id): Brand
    {
        return Brand::findOrFail($id);
    }

    /**
     * Create a new brand.
     *
     * @param StoreBrandRequest $request
     * @return Brand
     */
    public function create(StoreBrandRequest $request): Brand
    {
        $data = $request->validated();
        $data['slug'] = $this->generateUniqueSlug($data['name']);

        return Brand::create($data);
    }

    /**
     * Update an existing brand.
     *
     * @param UpdateBrandRequest $request
     * @param Brand $brand
     * @return Brand
     */
    public function update(UpdateBrandRequest $request, Brand $brand): Brand
    {
        $data = $request->validated();

        if ($data['name'] !== $brand->name) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $brand->id);
        }

        $brand->update($data);

        return $brand->fresh();
    }

    /**
     * Delete a brand.
     *
     * @param Brand $brand
     * @return bool
     */
    public function delete(Brand $brand): bool
    {
        return $brand->delete();
    }

    /**
     * Gera um slug único a partir do nome, incrementando um sufixo numérico
     * (`-2`, `-3`, ...) enquanto já existir outra marca com o mesmo slug.
     *
     * @param string $name
     * @param int|null $exceptId
     * @return string
     */
    private function generateUniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = Str::slug($name) ?: 'marca';
        $slug = $base;
        $i = 1;

        while (
            Brand::withTrashed()
                ->where('slug', $slug)
                ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
                ->exists()
        ) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
