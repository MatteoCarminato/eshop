<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/orbita/promotions: produtos com promoção digitada no painel.
 * Lista vazia é o sinal que o front usa pra esconder o link "Ofertas" do
 * header e a seção "Ofertas do dia" da home.
 */
class OrbitaPromotionsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.orbita.token' => 'token-de-teste']);
    }

    private function promotions(array $query = [])
    {
        return $this->withHeader('X-Orbita-Token', 'token-de-teste')
            ->getJson('/api/orbita/promotions' . ($query ? '?' . http_build_query($query) : ''));
    }

    public function test_sem_promocao_responde_lista_vazia(): void
    {
        Product::factory()->create(['web_price' => 500, 'promo_price' => 0, 'stock' => 2]);

        $this->promotions()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_lista_so_os_promocionados_do_maior_desconto_pro_menor(): void
    {
        Product::factory()->create([
            'web_price' => 1000, 'promo_price' => 900, 'stock' => 1, 'slug' => 'desconto-10',
        ]);
        Product::factory()->create([
            'web_price' => 1000, 'promo_price' => 500, 'stock' => 1, 'slug' => 'desconto-50',
        ]);
        Product::factory()->create([
            'web_price' => 1000, 'promo_price' => 0, 'stock' => 1, 'slug' => 'sem-promo',
        ]);

        $data = $this->promotions(['limit' => 10])->assertOk()->json('data');

        $this->assertSame(['desconto-50', 'desconto-10'], array_column($data, 'slug'));
        // Promoção vira o preço e o Preço Web vai como "de" riscado. A asserção
        // é no par em dólar porque é nele que o valor do painel sai intacto:
        // `price`/`compareAtPrice` são a conversão pra real e variam com o
        // câmbio do dia.
        $this->assertEquals(500, $data[0]['priceUsd']);
        $this->assertEquals(1000, $data[0]['compareAtPriceUsd']);
    }

    public function test_promo_acima_do_preco_web_e_ignorada(): void
    {
        // Erro de digitação: "promoção" mais caro que o Preço Web não é oferta
        // nem muda o preço da loja.
        $product = Product::factory()->create([
            'web_price' => 500, 'promo_price' => 800, 'stock' => 1, 'slug' => 'promo-invalida',
        ]);

        $this->promotions()->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(500.0, $product->sitePrice());
        $this->assertNull($product->siteCompareAtPrice());
    }

    public function test_respeita_o_limite_pedido(): void
    {
        Product::factory()->count(5)->create(['web_price' => 1000, 'promo_price' => 700, 'stock' => 1]);

        $this->promotions(['limit' => 2])->assertOk()->assertJsonCount(2, 'data');
        // Sem `limit`, o padrão do contrato é 4.
        $this->promotions()->assertOk()->assertJsonCount(4, 'data');
    }
}
