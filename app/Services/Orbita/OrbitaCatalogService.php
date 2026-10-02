<?php

namespace App\Services\Orbita;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\ExchangeRateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Catálogo público consumido pelo frontend Orbita (via Api\OrbitaProductController).
 *
 * Regra de negócio (documentada no próprio front, src/lib/api.ts): produto com
 * preço 0 ou sem estoque nunca pode aparecer na loja, nem em listagem/busca,
 * nem no detalhe. Por isso toda query aqui já filtra active + price > 0 +
 * stock > 0 — o front reaplica o mesmo filtro como cinto e suspensório, mas a
 * fonte de verdade é aqui.
 *
 * category/storage/colorName/model vêm de App\Services\Catalog\ProductClassifierService
 * (ver docs/Product-Classification.md). "model" agrupa produtos irmãos (mesmo
 * modelo, cor/armazenamento diferente) — ver variantsFor(), usado pelo
 * seletor de variante do Orbita no detalhe do produto. Orbita ainda não tem o
 * conceito de promoção (compareAtPrice) do lado do eshop — esse campo
 * simplesmente não é incluído na resposta.
 */
class OrbitaCatalogService
{
    private const FALLBACK_USD_RATE = 5.20;
    private const PYG_PER_USD = 6400; // Sem fonte real de câmbio Gs. ainda — mesma aproximação usada no mock do Orbita.

    public function __construct(private ExchangeRateService $exchangeRateService)
    {
    }

    private function sellableQuery(): Builder
    {
        return Product::query()
            ->with(['brand', 'category', 'images'])
            ->where('active', true)
            ->where('price', '>', 0)
            ->where('stock', '>', 0);
    }

    public function findSellableBySlug(string $slug): ?Product
    {
        return $this->sellableQuery()->where('slug', $slug)->first();
    }

    /**
     * Produtos "irmãos" de $product: mesmo `model` + mesma marca, usados pelo
     * seletor de variante do Orbita (cor/armazenamento) no detalhe. Inclui o
     * próprio $product (o front espera achar `variants.find(v => v.slug ===
     * product.slug)`) e variantes sem estoque/inativas — o seletor precisa
     * delas pra mostrar a opção desabilitada em vez de simplesmente sumir.
     * Só filtra `active`: um produto pausado manualmente no admin não deveria
     * nem aparecer como opção desabilitada.
     *
     * @return Collection<int, Product>
     */
    public function variantsFor(Product $product): Collection
    {
        if (!$product->model) {
            return collect([$product]);
        }

        return Product::query()
            ->where('active', true)
            ->where('model', $product->model)
            ->when(
                $product->brand_id,
                fn (Builder $q) => $q->where('brand_id', $product->brand_id),
                fn (Builder $q) => $q->whereNull('brand_id')
            )
            ->orderBy('storage')
            ->orderBy('color_name')
            ->get();
    }

    /**
     * @param array{q?:string,category?:string,brand?:string,model?:string,storage?:string,color?:string,price_min?:float,price_max?:float,sort?:string,page?:int,limit?:int} $params
     * @return array{products: Collection<int, Product>, meta: array}
     */
    public function search(array $params): array
    {
        $limit = max(1, min(60, (int) ($params['limit'] ?? 24)));
        $page = max(1, (int) ($params['page'] ?? 1));

        $base = $this->sellableQuery();
        $this->applyFilters($base, $params);

        $priceBounds = $this->sellableQuery();
        $this->applyFilters($priceBounds, $params, except: ['price']);
        $priceBounds = $priceBounds->without(['brand', 'category'])
            ->selectRaw('MIN(price) as min, MAX(price) as max')
            ->first();

        $facets = [
            'brands' => $this->brandFacets($this->filteredQuery($params, except: ['brand'])),
            'categories' => $this->categoryFacets($this->filteredQuery($params, except: ['category'])),
            'models' => $this->columnFacets($this->filteredQuery($params, except: ['model']), 'model'),
            'storage' => $this->columnFacets($this->filteredQuery($params, except: ['storage']), 'storage'),
            'colors' => $this->columnFacets($this->filteredQuery($params, except: ['color']), 'color_name'),
        ];

        $total = (clone $base)->count();

        $this->applySort($base, $params['sort'] ?? null);

        $products = $base->forPage($page, $limit)->get();

        return [
            'products' => $products,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'lastPage' => max(1, (int) ceil($total / $limit)),
                'facets' => $facets,
                'priceBounds' => [
                    'min' => (float) ($priceBounds->min ?? 0),
                    'max' => (float) ($priceBounds->max ?? 0),
                ],
            ],
        ];
    }

    private function filteredQuery(array $params, array $except = []): Builder
    {
        $query = $this->sellableQuery();
        $this->applyFilters($query, $params, $except);

        return $query;
    }

    /**
     * Aplica todos os filtros de busca à query, exceto os listados em $except
     * — usado tanto pra montar a listagem final (sem `except`) quanto pra
     * calcular cada facet considerando os outros filtros já ativos, mas não a
     * si mesma (mesma técnica do mock local do próprio front).
     *
     * @param array<int, "q"|"brand"|"category"|"model"|"storage"|"color"|"price"> $except
     */
    private function applyFilters(Builder $query, array $params, array $except = []): void
    {
        if (!in_array('q', $except, true)) {
            $this->applyTextSearch($query, $params['q'] ?? null);
        }

        if (!in_array('brand', $except, true) && !empty($params['brand'])) {
            $brand = $params['brand'];
            $query->whereHas('brand', fn (Builder $q) => $q->whereRaw('LOWER(name) = ?', [strtolower($brand)]));
        }

        if (!in_array('category', $except, true) && !empty($params['category'])) {
            $category = $params['category'];
            $query->whereHas('category', fn (Builder $q) => $q->where('slug', $category));
        }

        if (!in_array('model', $except, true) && !empty($params['model'])) {
            $query->where('model', $params['model']);
        }

        if (!in_array('storage', $except, true) && !empty($params['storage'])) {
            $query->where('storage', $params['storage']);
        }

        if (!in_array('color', $except, true) && !empty($params['color'])) {
            $query->where('color_name', $params['color']);
        }

        if (!in_array('price', $except, true)) {
            if (isset($params['price_min'])) {
                $query->where('price', '>=', (float) $params['price_min']);
            }
            if (isset($params['price_max'])) {
                $query->where('price', '<=', (float) $params['price_max']);
            }
        }
    }

    private function applyTextSearch(Builder $query, ?string $q): void
    {
        if (!$q) {
            return;
        }

        foreach (preg_split('/\s+/', trim($q)) as $term) {
            if ($term === '') {
                continue;
            }
            $term = strtolower($term);
            $query->where(function (Builder $sub) use ($term) {
                $sub->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(short_name) LIKE ?', ["%{$term}%"])
                    ->orWhereHas('brand', fn (Builder $b) => $b->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"]));
            });
        }
    }

    private function applySort(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name_asc' => $query->orderBy('name'),
            default => $query->orderByDesc('created_at'),
        };
    }

    /**
     * Conta produtos por marca dentro do resultado filtrado, sem usar JOIN
     * (evitaria ambiguidade de colunas com os `where`s já aplicados na query
     * base, já que `products` e `brands` compartilham nomes de coluna como
     * `name`/`active`/`created_at`).
     *
     * @return array<int, array{value: string, count: int}>
     */
    private function brandFacets(Builder $query): array
    {
        $counts = $query->whereNotNull('brand_id')
            ->selectRaw('brand_id, count(*) as count')
            ->groupBy('brand_id')
            ->pluck('count', 'brand_id');

        if ($counts->isEmpty()) {
            return [];
        }

        $names = Brand::whereIn('id', $counts->keys())->pluck('name', 'id');

        return $counts
            ->map(fn ($count, $brandId) => ['value' => $names[$brandId] ?? '', 'count' => (int) $count])
            ->filter(fn ($facet) => $facet['value'] !== '')
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Mesma técnica de brandFacets(), mas pra categoria (name/slug/count).
     *
     * @return array<int, array{name: string, slug: string, count: int}>
     */
    private function categoryFacets(Builder $query): array
    {
        $counts = $query->whereNotNull('category_id')
            ->selectRaw('category_id, count(*) as count')
            ->groupBy('category_id')
            ->pluck('count', 'category_id');

        if ($counts->isEmpty()) {
            return [];
        }

        $categories = Category::whereIn('id', $counts->keys())->get(['id', 'name', 'slug'])->keyBy('id');

        return $counts
            ->map(fn ($count, $categoryId) => isset($categories[$categoryId]) ? [
                'name' => $categories[$categoryId]->name,
                'slug' => $categories[$categoryId]->slug,
                'count' => (int) $count,
            ] : null)
            ->filter()
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Facet genérico pra uma coluna simples (storage/color_name): conta
     * produtos por valor distinto não-nulo dentro do resultado filtrado.
     *
     * @return array<int, array{value: string, count: int}>
     */
    private function columnFacets(Builder $query, string $column): array
    {
        return $query->whereNotNull($column)
            ->where($column, '!=', '')
            ->selectRaw("{$column} as value, count(*) as count")
            ->groupBy($column)
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['value' => $row->value, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * Categorias com contagem de produtos vendáveis — usado pelo
     * Api\OrbitaSiteController pra /categories/home e /categories/menu.
     *
     * @return Collection<int, array{id: string, name: string, slug: string, count: int}>
     */
    public function categoriesWithCounts(): Collection
    {
        // Filtra products_count > 0 na collection (não em HAVING): o Postgres não
        // aceita referenciar o alias de uma subquery correlata (withCount) direto
        // no HAVING nessa forma, diferente de MySQL/SQLite — e a tabela é pequena.
        return Category::withCount(['products' => function (Builder $q) {
                $q->where('active', true)->where('price', '>', 0)->where('stock', '>', 0);
            }])
            ->orderByDesc('products_count')
            ->get()
            ->filter(fn (Category $c) => $c->products_count > 0)
            ->map(fn (Category $c) => [
                'id' => (string) $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'count' => $c->products_count,
            ]);
    }

    public function usdRate(): float
    {
        return $this->exchangeRateService->getUsdBrlRate()['rate'] ?? self::FALLBACK_USD_RATE;
    }

    public function toUsd(float $brlAmount, float $usdRate): float
    {
        return $usdRate > 0 ? round($brlAmount / $usdRate, 2) : 0.0;
    }

    public function toPyg(float $usdAmount): float
    {
        return round($usdAmount * self::PYG_PER_USD);
    }
}
