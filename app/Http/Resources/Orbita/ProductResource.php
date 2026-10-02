<?php

namespace App\Http\Resources\Orbita;

use App\Services\Orbita\OrbitaCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mapeia App\Models\Product pro contrato "Product" esperado pelo frontend
 * Orbita (ver orbita/web/src/types/product.ts). category/storage/colorName
 * vêm de App\Services\Catalog\ProductClassifierService (ver
 * docs/Product-Classification.md). Campos que o eshop ainda não modela
 * (promoção, variantes/model, rating, specs, SKU/GTIN reais) são omitidos —
 * ver docs/Orbita-Api.md.
 */
class ProductResource extends JsonResource
{
    /**
     * Inclui `brandLogo`, que o contrato só espera no detalhe (getProduct),
     * não na listagem. Setado pelo controller antes de retornar o resource
     * na action `show`.
     */
    public bool $includeBrandLogo = false;

    public function toArray(Request $request): array
    {
        /** @var \App\Models\Product $product */
        $product = $this->resource;

        /** @var OrbitaCatalogService $catalog */
        $catalog = app(OrbitaCatalogService::class);
        $usdRate = $catalog->usdRate();
        $priceUsd = $catalog->toUsd((float) $product->price, $usdRate);

        // Alt com nome do produto + marca da loja — texto alternativo real
        // em vez de só o nome, melhor pra SEO de busca de imagens.
        $altText = "{$product->name} - eShop Cell";

        $image = $product->image_url ? ['url' => $product->image_url, 'alt' => $altText] : null;

        // Galeria completa: capa primeiro (se existir), depois as fotos da
        // galeria (product_images), sem repetir a capa caso ela também
        // tenha sido adicionada lá.
        $images = collect();
        if ($image) {
            $images->push($image);
        }
        foreach ($product->images as $galleryImage) {
            if (!$image || $galleryImage->url !== $image['url']) {
                $images->push(['url' => $galleryImage->url, 'alt' => $altText]);
            }
        }

        return array_filter([
            'id' => (string) $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'brand' => $product->brand?->name ?? '',
            'brandLogo' => $this->includeBrandLogo ? $product->brand?->logo_url : null,
            'description' => $product->description ?? '',
            'price' => (float) $product->price,
            'priceUsd' => $priceUsd,
            'pricePyg' => $catalog->toPyg($priceUsd),
            'currency' => 'BRL',
            // Produto sem estoque/preço já é excluído antes de chegar aqui (ver
            // OrbitaCatalogService::sellableQuery), então availability é sempre InStock.
            'availability' => 'InStock',
            'sku' => $product->recno ? "ERP-{$product->recno}" : "SITE-{$product->id}",
            'category' => $product->category?->name ?? 'Outros',
            'categorySlug' => $product->category?->slug ?? 'outros',
            'storage' => $product->storage,
            'colorName' => $product->color_name,
            'images' => $images->values()->all(),
            // "art" (placeholder visual quando não há foto real) não tem equivalente
            // aqui — string vazia faz o componente ProductArt cair no placeholder neutro.
            'art' => '',
            'image' => $image,
            'updatedAt' => optional($product->updated_at)->toIso8601String(),
        ], fn ($value) => $value !== null);
    }
}
