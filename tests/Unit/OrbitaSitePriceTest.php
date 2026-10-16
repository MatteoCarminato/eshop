<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Orbita\OrbitaCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Preço da loja online: é o Preço Web (`web_price`), e a promoção do painel
 * (`promo_price`, 0 = sem promoção) quando preenchida. O `price` do ERP
 * (PRECO3/varejo) não participa — ver docs/Orbita-Api.md.
 */
class OrbitaSitePriceTest extends TestCase
{
    use RefreshDatabase;

    private function catalog(): OrbitaCatalogService
    {
        return app(OrbitaCatalogService::class);
    }

    public function test_preco_da_loja_e_o_web_e_ignora_o_preco_do_erp(): void
    {
        $product = Product::factory()->create([
            'price' => 1000,
            'web_price' => 1200,
            'promo_price' => 0,
            'stock' => 3,
        ]);

        $this->assertSame(1200.0, $product->sitePrice());
        $this->assertNull($product->siteCompareAtPrice());
    }

    public function test_promocao_do_painel_vira_o_preco_e_risca_o_web(): void
    {
        $product = Product::factory()->create([
            'price' => 1000,
            'web_price' => 1200,
            'promo_price' => 999,
            'stock' => 3,
        ]);

        $this->assertSame(999.0, $product->sitePrice());
        $this->assertSame(1200.0, $product->siteCompareAtPrice());
    }

    public function test_produto_sem_preco_web_nao_aparece_na_loja(): void
    {
        $semWeb = Product::factory()->create([
            'price' => 1000,
            'web_price' => 0,
            'stock' => 5,
            'slug' => 'sem-web',
        ]);

        $comWeb = Product::factory()->create([
            'price' => 0,
            'web_price' => 500,
            'stock' => 5,
            'slug' => 'com-web',
        ]);

        $slugs = collect($this->catalog()->search([])['products'])->pluck('slug');

        $this->assertFalse($slugs->contains($semWeb->slug));
        $this->assertTrue($slugs->contains($comWeb->slug));
        $this->assertNull($this->catalog()->findSellableBySlug('sem-web'));
    }

    public function test_ordenacao_e_faixa_usam_o_preco_efetivo_da_loja(): void
    {
        // Web alto, mas em promoção: na loja é o mais barato dos dois.
        Product::factory()->create([
            'web_price' => 2000, 'promo_price' => 100, 'stock' => 1, 'slug' => 'em-promocao',
        ]);
        Product::factory()->create([
            'web_price' => 300, 'promo_price' => 0, 'stock' => 1, 'slug' => 'sem-promocao',
        ]);

        $asc = collect($this->catalog()->search(['sort' => 'price_asc'])['products'])->pluck('slug');
        $this->assertSame(['em-promocao', 'sem-promocao'], $asc->all());

        $faixa = $this->catalog()->search(['price_min' => 200]);
        $this->assertSame(['sem-promocao'], collect($faixa['products'])->pluck('slug')->all());
        $this->assertSame(100.0, $faixa['meta']['priceBounds']['min']);
        $this->assertSame(300.0, $faixa['meta']['priceBounds']['max']);
    }
}
