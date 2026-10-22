<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Feed\ComprasPyFeedService;
use DOMDocument;
use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /compraspy/feedseshop.xml — feed público consumido pelo ComprasPY.
 * Ver docs/ComprasPy-Feed.md.
 */
class ComprasPyFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'feeds.compraspy.store_url' => 'https://eshopcell.com',
            'feeds.compraspy.minimum_price' => 0.10,
            // Cache desligado nos testes: com TTL, o primeiro caso gravaria o
            // XML e os seguintes afirmariam sobre o catálogo do caso anterior.
            'feeds.compraspy.cache_ttl' => 0,
        ]);
    }

    /**
     * @return array<string, string> tags do item -> texto
     */
    private function firstItem(string $xml): array
    {
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'O feed deveria ser XML válido.');

        $items = $dom->getElementsByTagName('item');
        $this->assertGreaterThan(0, $items->length, 'O feed deveria ter pelo menos um item.');

        $out = [];
        foreach ($items->item(0)->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $out[$child->nodeName] = $child->textContent;
            }
        }

        return $out;
    }

    /**
     * @return list<string> títulos dos itens, na ordem do feed
     */
    private function titles(string $xml): array
    {
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml));

        $titles = [];
        foreach ($dom->getElementsByTagName('item') as $item) {
            $titles[] = $item->getElementsByTagName('title')->item(0)->textContent;
        }

        return $titles;
    }

    private function feedXml(): string
    {
        return app(ComprasPyFeedService::class)->xml();
    }

    public function test_rota_e_publica_e_responde_xml(): void
    {
        Product::factory()->create(['web_price' => 100, 'stock' => 3, 'active' => true]);

        $response = $this->get('/compraspy/feedseshop.xml');

        $response->assertOk();
        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));
        // Rota pública e cacheável: nada de cookie de sessão na resposta.
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_item_sai_no_formato_esperado_pelo_compraspy(): void
    {
        Product::factory()->create([
            'name' => 'IPHONE 17 256GB LL BLACK',
            'slug' => 'iphone-17-256gb-ll-black',
            'erp_code' => '1618',
            'web_code' => 'W1618',
            'web_price' => 915,
            'promo_price' => 0,
            'stock' => 49,
            'image_url' => 'https://cdn.exemplo.com/iphone-17.webp',
            'description' => "<p>Tela  de 6,1&quot;\n  Super Retina</p>",
            'active' => true,
        ]);

        $item = $this->firstItem($this->feedXml());

        $this->assertSame('IPHONE 17 256GB LL BLACK', $item['title']);
        $this->assertSame('https://eshopcell.com/produtos/iphone-17-256gb-ll-black', $item['link']);
        $this->assertSame('W1618', $item['codigo']);
        $this->assertSame('915.00 USD', $item['preco']);
        $this->assertSame('49', $item['estoque']);
        $this->assertSame('em estoque', $item['disponibilidade']);
        $this->assertSame('https://cdn.exemplo.com/iphone-17.webp', $item['link_imagem']);
        // HTML e espaços em excesso são achatados em texto puro.
        $this->assertSame('Tela de 6,1" Super Retina', $item['description']);
    }

    public function test_preco_do_feed_e_o_mesmo_da_loja_quando_ha_promocao(): void
    {
        Product::factory()->create([
            'name' => 'COM PROMO',
            'web_price' => 1000,
            'promo_price' => 850,
            'stock' => 2,
        ]);

        $this->assertSame('850.00 USD', $this->firstItem($this->feedXml())['preco']);
    }

    public function test_promocao_acima_do_preco_web_e_ignorada(): void
    {
        // Promo >= Preço Web é erro de digitação — mesma regra de
        // Product::hasSitePromo(), pro feed não anunciar mais caro que a loja.
        Product::factory()->create([
            'name' => 'PROMO INVALIDA',
            'web_price' => 500,
            'promo_price' => 700,
            'stock' => 2,
        ]);

        $this->assertSame('500.00 USD', $this->firstItem($this->feedXml())['preco']);
    }

    public function test_so_entra_no_feed_o_que_a_loja_vende(): void
    {
        Product::factory()->create(['name' => 'VENDAVEL', 'web_price' => 100, 'stock' => 5, 'active' => true]);
        Product::factory()->create(['name' => 'INATIVO', 'web_price' => 100, 'stock' => 5, 'active' => false]);
        Product::factory()->create(['name' => 'SEM ESTOQUE', 'web_price' => 100, 'stock' => 0, 'active' => true]);
        Product::factory()->create(['name' => 'SEM PRECO WEB', 'web_price' => 0, 'stock' => 5, 'active' => true]);
        Product::factory()->create(['name' => 'ABAIXO DO PISO', 'web_price' => 0.05, 'stock' => 5, 'active' => true]);

        $this->assertSame(['VENDAVEL'], $this->titles($this->feedXml()));
    }

    public function test_piso_de_preco_considera_a_promocao(): void
    {
        // Preço Web acima do piso, mas a promoção (que é o preço anunciado)
        // abaixo: não pode virar anúncio de 1 centavo.
        Product::factory()->create([
            'name' => 'PROMO ABAIXO DO PISO',
            'web_price' => 100,
            'promo_price' => 0.01,
            'stock' => 5,
        ]);

        $this->assertSame([], $this->titles($this->feedXml()));
    }

    public function test_codigo_cai_no_codnnn_no_recno_e_no_id_quando_nao_ha_codweb(): void
    {
        $product = Product::factory()->create([
            'name' => 'SEM CODWEB',
            'web_code' => null,
            'erp_code' => '1618',
            'recno' => 4321,
            'web_price' => 100,
            'stock' => 1,
        ]);

        $this->assertSame('1618', $this->firstItem($this->feedXml())['codigo']);

        $product->update(['erp_code' => null]);

        $this->assertSame('4321', $this->firstItem($this->feedXml())['codigo']);

        $product->update(['recno' => null]);

        $this->assertSame((string) $product->id, $this->firstItem($this->feedXml())['codigo']);
    }

    public function test_imagem_relativa_vira_url_absoluta_da_loja(): void
    {
        Product::factory()->create([
            'name' => 'IMAGEM RELATIVA',
            'image_url' => 'products/foto.webp',
            'web_price' => 100,
            'stock' => 1,
        ]);

        $this->assertSame(
            'https://eshopcell.com/products/foto.webp',
            $this->firstItem($this->feedXml())['link_imagem']
        );
    }

    public function test_estoque_fracionado_sai_arredondado_pra_baixo(): void
    {
        Product::factory()->create([
            'name' => 'ESTOQUE FRACIONADO',
            'web_price' => 100,
            'stock' => 3.7,
        ]);

        $this->assertSame('3', $this->firstItem($this->feedXml())['estoque']);
    }

    /**
     * Usuário com os módulos informados — permissão é por cargo, não por flag
     * no usuário (ver Role::hasModule()).
     */
    private function userComModulos(array $modules): User
    {
        $role = Role::create(['name' => 'Cargo Feed ' . implode('-', $modules), 'is_admin' => false]);
        $role->syncModules($modules);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_botao_atualizar_xml_republica_o_feed_na_hora(): void
    {
        config(['feeds.compraspy.cache_ttl' => 3600]);

        Product::factory()->create(['name' => 'PUBLICADO AGORA', 'web_price' => 100, 'stock' => 1]);

        $this->actingAs($this->userComModulos(['products.view', 'products.manage']))
            ->from(route('products-admin.index'))
            ->post(route('compraspy.feed.refresh'))
            ->assertRedirect(route('products-admin.index'))
            ->assertSessionHas('success');

        $this->assertStringContainsString(
            'PUBLICADO AGORA',
            (string) cache()->get(ComprasPyFeedService::CACHE_KEY)
        );

        // A grade tem layout próprio: o aviso de sucesso só aparece porque ela
        // renderiza o próprio bloco de flash.
        $this->actingAs($this->userComModulos(['products.view', 'products.manage']))
            ->withSession(['success' => 'Feed XML do ComprasPY atualizado: 1 produtos publicados.'])
            ->get(route('products-admin.index'))
            ->assertOk()
            ->assertSee('Feed XML do ComprasPY atualizado: 1 produtos publicados.');
    }

    public function test_grade_de_precos_mostra_o_botao_so_pra_quem_pode_gravar(): void
    {
        $this->actingAs($this->userComModulos(['products.view', 'products.manage']))
            ->get(route('products-admin.index'))
            ->assertOk()
            ->assertSee('Atualizar XML')
            ->assertSee(route('compraspy.feed.refresh'));

        $this->actingAs($this->userComModulos(['products.view']))
            ->get(route('products-admin.index'))
            ->assertOk()
            ->assertDontSee('Atualizar XML');
    }

    public function test_botao_atualizar_xml_exige_products_manage(): void
    {
        $this->actingAs($this->userComModulos(['products.view']))
            ->post(route('compraspy.feed.refresh'))
            ->assertForbidden();
    }

    public function test_botao_atualizar_xml_nao_e_acessivel_sem_login(): void
    {
        $this->post(route('compraspy.feed.refresh'))->assertRedirect(route('login'));
    }

    public function test_command_reaquece_o_cache(): void
    {
        config(['feeds.compraspy.cache_ttl' => 600]);

        Product::factory()->create(['name' => 'NO CACHE', 'web_price' => 100, 'stock' => 1]);

        $this->artisan('compraspy:feed')
            ->expectsOutputToContain('Produtos: 1')
            ->assertSuccessful();

        $cached = cache()->get(ComprasPyFeedService::CACHE_KEY);
        $this->assertIsString($cached);
        $this->assertStringContainsString('NO CACHE', $cached);
    }
}
