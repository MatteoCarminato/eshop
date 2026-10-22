<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Services\Feed\ComprasPyImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Envio de produtos pela API de importação do ComprasPY
 * (POST /api/products/import/). Ver docs/ComprasPy-Feed.md.
 */
class ComprasPyImportTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://products.comprasparaguai.com.br/api/products/import/';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'feeds.compraspy.store_url' => 'https://eshopcell.com',
            'feeds.compraspy.minimum_price' => 0.10,
            'feeds.compraspy.api.url' => self::URL,
            'feeds.compraspy.api.token' => 'token-da-loja',
            'feeds.compraspy.api.bearer' => null,
            'feeds.compraspy.api.timeout' => 15,
            'feeds.compraspy.api.fallback_image' => null,
            'feeds.compraspy.api.force_image_update' => false,
        ]);

        $this->fakeAceito();
    }

    /**
     * 207 é o sucesso documentado do endpoint (lote pode ser aceito em
     * parte). Fica num método porque o Http::fake não sobrepõe um stub já
     * registrado pra mesma URL — quem precisa de outra resposta (ver o caso de
     * erro) refaz o fake antes de qualquer envio.
     */
    private function fakeAceito(): void
    {
        Http::fake([self::URL => Http::response([
            'success' => true,
            'message' => 'Import completed.',
            'products_processed' => 1,
            'products_failed' => 0,
            'validation_errors' => [],
        ], 207)]);
    }

    private function product(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => 'IPHONE 17 256GB LL BLACK',
            'slug' => 'iphone-17-256gb-ll-black',
            'erp_code' => '1618',
            'web_code' => 'W1618',
            'web_price' => 915,
            'promo_price' => 0,
            'stock' => 49,
            'active' => true,
            'image_url' => 'https://cdn.exemplo.com/iphone-17.webp',
            'description' => null,
        ], $overrides));
    }

    private function service(): ComprasPyImportService
    {
        return app(ComprasPyImportService::class);
    }

    /**
     * @return array<string, mixed> o primeiro item do corpo enviado
     */
    private function itemEnviado(): array
    {
        $enviados = [];

        Http::assertSent(function ($request) use (&$enviados) {
            $enviados = $request->data();

            return true;
        });

        $this->assertNotEmpty($enviados, 'Nada foi enviado ao ComprasPY.');

        return $enviados[0];
    }

    public function test_produto_vai_no_formato_esperado_pelo_compraspy(): void
    {
        $brand = Brand::create(['name' => 'Apple', 'slug' => 'apple', 'active' => true]);
        $product = $this->product(['brand_id' => $brand->id]);

        $this->assertTrue($this->service()->pushProduct($product));

        $item = $this->itemEnviado();

        // Código do anúncio é o CODWEB, não o CODNNN.
        $this->assertSame('W1618', $item['code']);
        $this->assertSame(['pt' => 'IPHONE 17 256GB LL BLACK'], $item['name']);
        $this->assertSame('Apple', $item['brand']);
        $this->assertSame('https://cdn.exemplo.com/iphone-17.webp', $item['url_image']);
        $this->assertSame('https://eshopcell.com/produtos/iphone-17-256gb-ll-black', $item['link']);
        $this->assertSame('https://eshopcell.com/produtos/iphone-17-256gb-ll-black', $item['link_purchase']);
        $this->assertSame('https://eshopcell.com/produtos/iphone-17-256gb-ll-black', $item['link_ecommerce_br']);
        $this->assertFalse($item['force_image_update']);

        // Corpo é uma lista, e o token vai no header `token`.
        Http::assertSent(fn ($request) => $request->hasHeader('token', 'token-da-loja')
            && array_is_list($request->data()));
    }

    /**
     * Os três campos de preço levam o Preço Web, em dólar: é a coluna que o
     * anúncio acompanha e a moeda em que eles exigem o valor.
     */
    public function test_os_tres_precos_levam_o_preco_web(): void
    {
        $this->service()->pushProduct($this->product(['web_price' => 915.5]));

        $item = $this->itemEnviado();

        $this->assertSame(915.5, $item['price']);
        $this->assertSame(915.5, $item['price_iva']);
        $this->assertSame(915.5, $item['price_ecommerce_br']);
    }

    /**
     * Promoção do painel não entra aqui, diferente do feed XML: o gatilho do
     * envio é a edição do Preço Web, e é ele que vai no anúncio.
     */
    public function test_promocao_do_painel_nao_muda_o_preco_enviado(): void
    {
        $this->service()->pushProduct($this->product(['web_price' => 915, 'promo_price' => 799]));

        $this->assertSame(915.0, $this->itemEnviado()['price']);
    }

    public function test_estoque_vai_1_para_produto_vendavel(): void
    {
        $this->service()->pushProduct($this->product(['stock' => 49]));

        $this->assertSame(1, $this->itemEnviado()['stock']);
    }

    /**
     * `stock = 0` é como o ComprasPY derruba um anúncio — é o que mandamos
     * quando o produto deixou de ser vendável, em vez de deixar no ar um
     * anúncio de algo que a loja não vende.
     */
    public function test_estoque_vai_0_para_produto_que_nao_e_mais_vendavel(): void
    {
        $casos = [
            'pausado no painel' => ['active' => false],
            'sem estoque no ERP' => ['stock' => 0],
            'preço abaixo do piso do feed' => ['web_price' => 0.05],
        ];

        foreach ($casos as $caso => $overrides) {
            $product = $this->product(array_merge(['slug' => 'nao-vendavel-' . md5($caso)], $overrides));

            $this->assertTrue($this->service()->pushProduct($product), $caso);
            $this->assertSame(0, $this->itemEnviado()['stock'], $caso);
        }
    }

    /**
     * `description.pt` é obrigatório lá e quase nenhum produto nosso tem
     * descrição — vai o nome, que é a informação que existe, em vez de o
     * produto ser recusado.
     */
    public function test_descricao_vazia_cai_no_nome_do_produto(): void
    {
        $this->service()->pushProduct($this->product(['description' => null]));

        $this->assertSame(['pt' => 'IPHONE 17 256GB LL BLACK'], $this->itemEnviado()['description']);
    }

    public function test_descricao_vai_sem_html_e_sem_quebras(): void
    {
        $this->service()->pushProduct($this->product([
            'description' => "<p>Tela  de 6,1&quot;\n  Super Retina</p>",
        ]));

        $this->assertSame(['pt' => 'Tela de 6,1" Super Retina'], $this->itemEnviado()['description']);
    }

    /**
     * `url_image` é obrigatória e eles checam a extensão: sem foto usável o
     * produto é pulado aqui, em vez de recusado lá. O feed XML continua
     * publicando ele (lá a imagem é opcional).
     */
    public function test_produto_sem_imagem_usavel_nao_e_enviado(): void
    {
        $casos = [
            'sem foto' => null,
            'string vazia' => '',
            'formato que eles não baixam' => 'https://cdn.exemplo.com/iphone-17.gif',
        ];

        foreach ($casos as $caso => $imageUrl) {
            $product = $this->product(['slug' => 'sem-imagem-' . md5($caso), 'image_url' => $imageUrl]);

            $this->assertFalse($this->service()->pushProduct($product), $caso);
        }

        Http::assertNothingSent();
    }

    /**
     * Com a imagem genérica configurada, produto sem foto passa a ser
     * anunciado com ela em vez de ficar de fora.
     */
    public function test_imagem_generica_cobre_produto_sem_foto(): void
    {
        config(['feeds.compraspy.api.fallback_image' => '/img/sem-foto.png']);

        $this->assertTrue($this->service()->pushProduct($this->product(['image_url' => null])));

        $this->assertSame('https://eshopcell.com/img/sem-foto.png', $this->itemEnviado()['url_image']);
    }

    /**
     * Caminho relativo no banco vira URL absoluta: eles baixam a imagem de
     * fora, não têm como resolver caminho nosso.
     */
    public function test_caminho_relativo_de_imagem_vira_url_absoluta(): void
    {
        $this->service()->pushProduct($this->product(['image_url' => 'storage/products/iphone.jpg']));

        $this->assertSame('https://eshopcell.com/storage/products/iphone.jpg', $this->itemEnviado()['url_image']);
    }

    public function test_sem_codweb_o_codigo_cai_no_codnnn(): void
    {
        $this->assertTrue($this->service()->pushProduct($this->product(['web_code' => null])));

        $this->assertSame('1618', $this->itemEnviado()['code']);
    }

    public function test_produto_sem_preco_web_nao_e_enviado(): void
    {
        $this->assertFalse($this->service()->pushProduct($this->product(['web_price' => 0])));

        Http::assertNothingSent();
    }

    public function test_sem_credencial_configurada_nao_envia(): void
    {
        config(['feeds.compraspy.api.token' => null, 'feeds.compraspy.api.bearer' => null]);

        $this->assertFalse($this->service()->pushProduct($this->product()));

        Http::assertNothingSent();
    }

    public function test_bearer_configurado_vai_no_authorization(): void
    {
        config(['feeds.compraspy.api.token' => null, 'feeds.compraspy.api.bearer' => 'jwt-aqui']);

        $this->service()->pushProduct($this->product());

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer jwt-aqui'));
    }

    /**
     * Com as duas credenciais preenchidas vai só o header `token`: o UUID da
     * loja mandado também como Bearer é recusado pelo middleware de JWT deles
     * com 401, antes de o header `token` ser olhado (conferido na API de
     * produção).
     */
    public function test_com_as_duas_credenciais_manda_so_o_token(): void
    {
        config([
            'feeds.compraspy.api.token' => 'token-da-loja',
            'feeds.compraspy.api.bearer' => 'token-da-loja',
        ]);

        $this->service()->pushProduct($this->product());

        Http::assertSent(fn ($request) => $request->hasHeader('token', 'token-da-loja')
            && ! $request->hasHeader('Authorization'));
    }

    /**
     * Vários produtos saem num único request — eles aceitam até 500 por lote,
     * e um request por produto seria desperdício.
     */
    public function test_envio_em_lote_manda_todos_no_mesmo_request(): void
    {
        $produtos = collect([
            $this->product(['slug' => 'um', 'web_code' => '1']),
            $this->product(['slug' => 'dois', 'web_code' => '2']),
            // Esse não tem foto: é pulado sem derrubar o lote.
            $this->product(['slug' => 'tres', 'web_code' => '3', 'image_url' => null]),
        ]);

        $this->assertSame(2, $this->service()->pushMany($produtos));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => array_column($request->data(), 'code') === ['1', '2']);
    }

    /**
     * ComprasPY fora do ar não pode quebrar nada de quem chamou — quem chama
     * está no meio de salvar um preço.
     */
    public function test_erro_do_compraspy_nao_lanca_excecao(): void
    {
        // Http::fake() não substitui um stub já registrado pra mesma URL (o
        // primeiro que casa é o que responde), então o do setUp sai de cena
        // trocando a fábrica inteira antes de registrar o de erro.
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([self::URL => Http::response('bad gateway', 502)]);

        $this->assertFalse($this->service()->pushProduct($this->product()));
    }
}
