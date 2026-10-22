<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Edição inline de preço na grade Produtos × Preços
 * (PATCH products-admin/{product}/prices).
 */
class ProductPriceInlineUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function product(array $overrides = []): Product
    {
        static $n = 0;
        $n++;

        return Product::create(array_merge([
            'name' => 'IPHONE 16 PRO MAX 256GB',
            'slug' => 'iphone-16-pro-max-256gb-' . $n,
            'price' => 1200,
            'wholesale_price' => 1100,
            'web_price' => 1200,
            'sale_price' => 1150,
            'min_price' => 1000,
            'average_price' => 950,
            'stock' => 5,
            'active' => true,
        ], $overrides));
    }

    /**
     * Usuário com os módulos informados (permissão é por cargo, não por
     * flag no usuário — ver Role::hasModule()).
     */
    protected function userComModulos(array $modules): User
    {
        $role = Role::create(['name' => 'Cargo Teste ' . implode('-', $modules), 'is_admin' => false]);
        $role->syncModules($modules);

        return User::factory()->create(['role_id' => $role->id]);
    }

    protected function admin(): User
    {
        return $this->userComModulos(['products.view', 'products.manage']);
    }

    public function test_grava_um_preco_e_devolve_o_valor_normalizado(): void
    {
        $product = $this->product();

        $response = $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), [
                'column' => 'price',
                'value' => '2500.00',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'column' => 'price', 'value' => '2500.00']);

        $this->assertSame('2500.00', number_format((float) $product->fresh()->price, 2, '.', ''));
    }

    public function test_grava_cada_coluna_de_preco(): void
    {
        $product = $this->product();
        $admin = $this->admin();

        // Inclui `price` (Varejo), que está oculto na grade mas segue aceito
        // pelo endpoint — esconder a coluna não é tirá-la do whitelist. Já
        // `average_price` fica fora: é coluna de leitura (ver test abaixo).
        foreach (array_keys(\App\Http\Controllers\ProductAdminController::editableColumns()) as $i => $column) {
            $valor = 100 + $i;

            $this->actingAs($admin)
                ->patchJson(route('products-admin.prices', $product), ['column' => $column, 'value' => $valor])
                ->assertOk();

            $this->assertSame((float) $valor, (float) $product->fresh()->{$column}, "coluna {$column}");
        }
    }

    public function test_nao_toca_nas_outras_colunas(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), ['column' => 'min_price', 'value' => 999])
            ->assertOk();

        $fresh = $product->fresh();
        $this->assertSame(999.0, (float) $fresh->min_price);
        $this->assertSame(1200.0, (float) $fresh->price);
        $this->assertSame(1100.0, (float) $fresh->wholesale_price);
        $this->assertSame('IPHONE 16 PRO MAX 256GB', $fresh->name);
    }

    public function test_recusa_coluna_fora_da_whitelist(): void
    {
        $product = $this->product();

        // `name` e `stock` são colunas reais do produto, mas não são preço —
        // a whitelist tem que barrar antes de chegar no update.
        foreach (['name', 'stock', 'active', 'recno', 'id'] as $column) {
            $this->actingAs($this->admin())
                ->patchJson(route('products-admin.prices', $product), ['column' => $column, 'value' => 1])
                ->assertStatus(422)
                ->assertJsonValidationErrors('column');
        }

        $this->assertSame('IPHONE 16 PRO MAX 256GB', $product->fresh()->name);
    }

    public function test_recusa_valor_invalido(): void
    {
        $product = $this->product();
        $admin = $this->admin();

        foreach ([-1, 'abc', '', 1e12] as $valor) {
            $this->actingAs($admin)
                ->patchJson(route('products-admin.prices', $product), ['column' => 'price', 'value' => $valor])
                ->assertStatus(422)
                ->assertJsonValidationErrors('value');
        }

        $this->assertSame(1200.0, (float) $product->fresh()->price);
    }

    public function test_aceita_zero(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), ['column' => 'sale_price', 'value' => 0])
            ->assertOk()
            ->assertJson(['value' => '0.00']);

        $this->assertSame(0.0, (float) $product->fresh()->sale_price);
    }

    public function test_exige_autenticacao(): void
    {
        $product = $this->product();

        $this->patchJson(route('products-admin.prices', $product), ['column' => 'price', 'value' => 1])
            ->assertStatus(401);

        $this->assertSame(1200.0, (float) $product->fresh()->price);
    }

    public function test_quem_so_tem_products_view_abre_a_grade_mas_nao_grava(): void
    {
        $product = $this->product();
        $user = $this->userComModulos(['products.view']);

        // vê a tela...
        $this->actingAs($user)
            ->get(route('products-admin.index'))
            ->assertOk()
            ->assertSee('somente leitura');

        // ...mas o endpoint de gravação barra
        $this->actingAs($user)
            ->patchJson(route('products-admin.prices', $product), ['column' => 'price', 'value' => 9999])
            ->assertStatus(403);

        $this->assertSame(1200.0, (float) $product->fresh()->price);
    }

    public function test_inputs_ficam_readonly_para_quem_nao_pode_gravar(): void
    {
        $this->product();

        $this->actingAs($this->userComModulos(['products.view']))
            ->get(route('products-admin.index'))
            ->assertOk()
            ->assertSee('readonly', false);
    }

    /**
     * A grade filtra e rotula por grupo de produto do ERP (GRUPO), não por
     * marca.
     */
    public function test_grade_filtra_e_rotula_por_grupo_de_produto(): void
    {
        $celular = ProductGroup::create(['code' => 6, 'name' => 'CELULAR', 'slug' => 'celular', 'active' => true]);
        $tablet = ProductGroup::create(['code' => 3, 'name' => 'TABLET', 'slug' => 'tablet', 'active' => true]);

        $this->product(['name' => 'IPHONE 16 PRO', 'product_group_id' => $celular->id]);
        $this->product(['name' => 'IPAD AIR', 'product_group_id' => $tablet->id]);

        $this->actingAs($this->admin())
            ->get(route('products-admin.index'))
            ->assertOk()
            ->assertSee('IPHONE 16 PRO')
            ->assertSee('IPAD AIR')
            // O grupo aparece ao lado do nome, no lugar onde a marca aparecia.
            ->assertSee('CELULAR')
            ->assertSee('TABLET')
            ->assertSee('Todos os grupos')
            ->assertDontSee('Todas as marcas');

        $this->actingAs($this->admin())
            ->get(route('products-admin.index', ['group' => $celular->id]))
            ->assertOk()
            ->assertSee('IPHONE 16 PRO')
            ->assertDontSee('IPAD AIR');
    }

    /**
     * Trocar de situação/estoque/atacado não pode perder o grupo escolhido —
     * os chips carregam o filtro atual na própria URL.
     */
    public function test_chips_preservam_o_filtro_de_grupo(): void
    {
        $grupo = ProductGroup::create(['code' => 6, 'name' => 'CELULAR', 'slug' => 'celular', 'active' => true]);

        $this->actingAs($this->admin())
            ->get(route('products-admin.index', ['group' => $grupo->id]))
            ->assertOk()
            ->assertSee('group=' . $grupo->id, false)
            ->assertDontSee('brand=', false);
    }

    public function test_grade_mostra_medio_atacado_diferenca_e_web(): void
    {
        $this->product();

        $response = $this->actingAs($this->admin())->get(route('products-admin.index'))->assertOk();

        $response->assertSee('Atacado')
            ->assertDontSee('Varejo')
            ->assertSee('data-k="wholesale_price"', false)
            ->assertSee('data-k="diff"', false)
            ->assertSee('data-k="web_price"', false)
            // Varejo (PRECO3), Promo (PREVEN) e Mínimo (PREMIN) ficam fora da
            // grade — as duas últimas porque o atacado já as grava, então
            // repetiriam o mesmo número na tela.
            ->assertDontSee('data-k="price"', false)
            ->assertDontSee('data-k="sale_price"', false)
            ->assertDontSee('data-k="min_price"', false);

        // Ordem exata da grade: o Médio antes do atacado, a Diferença logo
        // depois dele (é a conta entre os dois) e nada depois do Web.
        $cols = array_keys($response->viewData('cols'));
        $this->assertSame(['average_price', 'wholesale_price', 'diff', 'web_price'], $cols);

        // O atacado é a primeira coluna NAVEGÁVEL (onde o Alt+Enter cai),
        // porque o Médio não é digitável.
        $navegaveis = array_values(array_intersect(
            array_keys(\App\Http\Controllers\ProductAdminController::editableColumns()),
            $cols,
        ));
        $this->assertSame(['wholesale_price', 'web_price'], $navegaveis);
    }

    /**
     * A Diferença é coluna da grade mas não existe no banco: ela não pode nem
     * ir pro select() (quebraria a query) nem ser aceita pelo endpoint.
     */
    public function test_diferenca_e_calculada_e_nao_e_gravavel(): void
    {
        $product = $this->product(['wholesale_price' => 1100, 'average_price' => 950]);

        $this->assertArrayNotHasKey(
            'diff',
            \App\Http\Controllers\ProductAdminController::storedGridColumns(),
            'coluna calculada não pode entrar no select()',
        );
        $this->assertArrayNotHasKey(
            'diff',
            \App\Http\Controllers\ProductAdminController::editableColumns(),
        );

        $this->actingAs($this->admin())
            ->get(route('products-admin.index'))
            ->assertOk()
            // 1100 − 950, calculado na renderização.
            ->assertSee('data-v="150"', false)
            ->assertSee('150.00');

        $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), ['column' => 'diff', 'value' => 10])
            ->assertStatus(422);
    }

    /**
     * Sem saldo o produto não está à venda, então não entra na grade de preços
     * — mas continua alcançável pelos chips de estoque.
     */
    public function test_esconde_produto_com_estoque_zerado_por_padrao(): void
    {
        $this->product(['name' => 'COM SALDO', 'stock' => 3]);
        $this->product(['name' => 'ZERADO', 'stock' => 0]);

        $admin = $this->admin();

        $padrao = $this->actingAs($admin)->get(route('products-admin.index'))->assertOk();
        $this->assertSame(['COM SALDO'], $padrao->viewData('products')->pluck('name')->all());
        // O chip "Zerados" conta quem ficou de fora.
        $this->assertSame(1, $padrao->viewData('semEstoque'));

        $zerados = $this->actingAs($admin)
            ->get(route('products-admin.index', ['estoque' => '0']))->assertOk();
        $this->assertSame(['ZERADO'], $zerados->viewData('products')->pluck('name')->all());

        $todos = $this->actingAs($admin)
            ->get(route('products-admin.index', ['estoque' => '']))->assertOk();
        $this->assertSame(['COM SALDO', 'ZERADO'], $todos->viewData('products')->pluck('name')->all());
    }

    /**
     * As duas contagens dos chips olham uma pelo filtro da outra: o "sem
     * atacado" só conta o que o filtro de estoque deixaria aparecer, e
     * vice-versa.
     */
    public function test_contagens_dos_chips_respeitam_o_outro_filtro(): void
    {
        $this->product(['name' => 'ZERADO SEM ATACADO', 'stock' => 0, 'wholesale_price' => 0]);
        $this->product(['name' => 'COM SALDO SEM ATACADO', 'stock' => 4, 'wholesale_price' => 0]);
        $this->product(['name' => 'COM SALDO E ATACADO', 'stock' => 4, 'wholesale_price' => 900]);

        $r = $this->actingAs($this->admin())->get(route('products-admin.index'))->assertOk();

        $this->assertSame(['COM SALDO E ATACADO'], $r->viewData('products')->pluck('name')->all());
        // Só o sem atacado que TEM saldo: o zerado já está fora por estoque.
        $this->assertSame(1, $r->viewData('semAtacado'));
        // Só o zerado que TEM atacado — nenhum.
        $this->assertSame(0, $r->viewData('semEstoque'));
    }

    // ---- gravação no ERP ----------------------------------------------------
    // Nenhum teste encosta no ERP de verdade: ERP_PRICE_WRITE=false no
    // phpunit.xml e, onde o comportamento importa, o ErpPriceWriter é trocado
    // por um dublê.

    public function test_atacado_vai_pro_erp_com_preatac_preven_e_premin(): void
    {
        $product = $this->product(['erp_code' => '2523']);

        $this->mock(\App\Services\Erp\ErpPriceWriter::class, function ($mock) use ($product) {
            $mock->shouldReceive('write')
                ->once()
                ->withArgs(fn ($p, $colunas, $valor) => $p->is($product)
                    && $colunas === ['PREATAC', 'PREVEN', 'PREMIN']
                    && $valor === 5.01)
                ->andReturnNull();
            $mock->shouldReceive('enabled')->andReturnTrue();
        });

        $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), ['column' => 'wholesale_price', 'value' => '5.01'])
            ->assertOk()
            ->assertJson(['success' => true, 'erp' => true]);

        $this->assertSame('5.01', (string) $product->fresh()->wholesale_price);
    }

    /**
     * O preço web é do site: não tem par no ERP e não pode disparar gravação
     * lá (o grant até permitiria o PRECOWEB — a decisão é de produto).
     */
    public function test_preco_web_nao_vai_pro_erp(): void
    {
        $product = $this->product(['erp_code' => '2523']);

        $this->mock(\App\Services\Erp\ErpPriceWriter::class, function ($mock) {
            // Chamado, sim, mas sem coluna nenhuma de ERP pra gravar.
            $mock->shouldReceive('write')
                ->once()
                ->withArgs(fn ($p, $colunas, $valor) => $colunas === [])
                ->andReturnNull();
            $mock->shouldReceive('enabled')->andReturnTrue();
        });

        $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), ['column' => 'web_price', 'value' => '1234.00'])
            ->assertOk()
            ->assertJson(['success' => true, 'erp' => false]);

        $this->assertSame('1234.00', (string) $product->fresh()->web_price);
    }

    /**
     * ERP recusando, a base do site não muda: é o que impede as duas ficarem
     * com preços diferentes.
     */
    public function test_erp_recusando_nao_grava_na_base_do_site(): void
    {
        $product = $this->product(['erp_code' => '2523', 'wholesale_price' => 1100]);

        $this->mock(\App\Services\Erp\ErpPriceWriter::class, function ($mock) {
            $mock->shouldReceive('write')
                ->once()
                ->andThrow(new \App\Exceptions\ErpWriteException('Nenhum produto no ERP com o código 2523.'));
        });

        $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), ['column' => 'wholesale_price', 'value' => '5.01'])
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'Nenhum produto no ERP com o código 2523.']);

        $product->refresh();
        $this->assertSame('1100.00', (string) $product->wholesale_price);
        // Os espelhos também não podem ter andado sozinhos.
        $this->assertSame('1150.00', (string) $product->sale_price);
        $this->assertSame('1000.00', (string) $product->min_price);
    }

    /**
     * Produto que nunca veio do ERP não tem CODNNN — e sem CODNNN não existe
     * UPDATE possível (o usuário de escrita não enxerga RECNO).
     */
    public function test_produto_sem_codnnn_nao_tenta_gravar_no_erp(): void
    {
        config(['erp.price_write' => true]);
        $product = $this->product(['erp_code' => null]);

        $this->expectException(\App\Exceptions\ErpWriteException::class);
        $this->expectExceptionMessage('Produto sem código do ERP (CODNNN)');

        // Sem conexão nenhuma: a checagem acontece antes de falar com o banco.
        (new \App\Services\Erp\ErpPriceWriter())->write($product, ['PREATAC'], 5.01);
    }

    /**
     * Com o flag desligado (ambiente sem credencial de escrita, e os testes) a
     * grade volta a gravar só na base do site.
     */
    public function test_flag_desligado_grava_so_no_site(): void
    {
        config(['erp.price_write' => false]);
        $product = $this->product(['erp_code' => null]);

        $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), ['column' => 'wholesale_price', 'value' => '5.01'])
            ->assertOk()
            ->assertJson(['success' => true, 'erp' => false]);

        $this->assertSame('5.01', (string) $product->fresh()->wholesale_price);
    }

    /**
     * Esconder Promo e Mínimo da grade não é tirá-las do whitelist nem do
     * espelhamento: o atacado continua gravando as duas.
     */
    public function test_promo_e_minimo_seguem_gravaveis_fora_da_grade(): void
    {
        $product = $this->product();
        $admin = $this->admin();

        foreach (['sale_price' => 321, 'min_price' => 322] as $column => $valor) {
            $this->actingAs($admin)
                ->patchJson(route('products-admin.prices', $product), ['column' => $column, 'value' => $valor])
                ->assertOk();

            $this->assertSame((float) $valor, (float) $product->fresh()->{$column}, "coluna {$column}");
        }
    }

    // ---- atacado espelha Promo e Mínimo ----------------------------------

    public function test_atacado_grava_promo_e_minimo_junto(): void
    {
        $product = $this->product();

        // No ERP, o "A C Atacado" grava PREATAC, PREVEN e PREMIN com o mesmo
        // valor — a grade espelha isso pra não ficar incoerente na tela.
        $response = $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), [
                'column' => 'wholesale_price',
                'value' => '1234.50',
            ])->assertOk();

        $response->assertJson([
            'success' => true,
            'column' => 'wholesale_price',
            'value' => '1234.50',
            'espelhos' => ['sale_price' => '1234.50', 'min_price' => '1234.50'],
        ]);

        $fresh = $product->fresh();
        $this->assertSame(1234.5, (float) $fresh->wholesale_price);
        $this->assertSame(1234.5, (float) $fresh->sale_price);
        $this->assertSame(1234.5, (float) $fresh->min_price);

        // Web, Varejo e Médio não entram na carona.
        $this->assertSame(1200.0, (float) $fresh->web_price);
        $this->assertSame(1200.0, (float) $fresh->price);
        $this->assertSame(950.0, (float) $fresh->average_price);
    }

    public function test_web_altera_so_o_web(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), ['column' => 'web_price', 'value' => 777])
            ->assertOk()
            ->assertJson(['espelhos' => []]);

        $fresh = $product->fresh();
        $this->assertSame(777.0, (float) $fresh->web_price);
        $this->assertSame(1100.0, (float) $fresh->wholesale_price);
        $this->assertSame(1150.0, (float) $fresh->sale_price);
        $this->assertSame(1000.0, (float) $fresh->min_price);
        $this->assertSame(1200.0, (float) $fresh->price);
    }

    // ---- preço médio (CUSMED) ---------------------------------------------

    public function test_preco_medio_nao_e_editavel(): void
    {
        $product = $this->product();

        // Vem do ERP e não tem gravação: tem que bater na whitelist, não
        // chegar no update.
        $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), ['column' => 'average_price', 'value' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('column');

        $this->assertSame(950.0, (float) $product->fresh()->average_price);
    }

    public function test_grade_mostra_o_preco_medio_como_texto_antes_do_atacado(): void
    {
        $this->product(['average_price' => 912.34]);

        $html = $this->actingAs($this->admin())
            ->get(route('products-admin.index'))
            ->assertOk()
            ->assertSee('Médio')
            ->assertSee('data-k="average_price"', false)
            // célula de leitura, não input
            ->assertSee('<span class="pa-ro medio">912.34</span>', false)
            ->getContent();

        $this->assertStringNotContainsString('data-col="average_price" data-orig', $html);
        // O cabeçalho do Médio vem antes do de Atacado na tela.
        $this->assertLessThan(
            strpos($html, 'data-k="wholesale_price"'),
            strpos($html, 'data-k="average_price"'),
        );
    }

    // ---- coluna de estoque (opcional) --------------------------------------

    public function test_grade_mostra_a_coluna_de_estoque_antes_do_medio(): void
    {
        $this->product(['stock' => 14]);

        $html = $this->actingAs($this->admin())
            ->get(route('products-admin.index'))
            ->assertOk()
            ->assertSee('Estoque')
            ->assertSee('data-k="stock"', false)
            ->assertSee('data-col="stock" data-v="14"', false)
            // Coluna fixa: não há chip nem classe pra esconder.
            ->assertDontSee('sem-estoque', false)
            ->assertDontSee('paEst', false)
            ->getContent();

        // Ordem na tela: Estoque, depois Médio, depois Atacado.
        $this->assertLessThan(strpos($html, 'data-k="average_price"'), strpos($html, 'data-k="stock"'));
        $this->assertLessThan(strpos($html, 'data-k="wholesale_price"'), strpos($html, 'data-k="average_price"'));
    }

    public function test_coluna_de_estoque_nao_mostra_centavos_de_quantidade_inteira(): void
    {
        $this->product(['stock' => 14]);

        $html = $this->actingAs($this->admin())->get(route('products-admin.index'))->getContent();

        $this->assertMatchesRegularExpression('/data-v="14">\s*14\s*</', $html);
    }

    // ---- filtro de atacado ------------------------------------------------

    /** Cria produtos com e sem PREATAC pra exercitar o filtro. */
    protected function trioDeAtacado(): array
    {
        return [
            'com' => $this->product(['name' => 'COM ATACADO', 'slug' => 'com-atacado', 'wholesale_price' => 500]),
            'zero' => $this->product(['name' => 'ATACADO ZERO', 'slug' => 'atacado-zero', 'wholesale_price' => 0]),
            'nulo' => $this->product(['name' => 'ATACADO NULO', 'slug' => 'atacado-nulo', 'wholesale_price' => null]),
        ];
    }

    public function test_por_padrao_esconde_produtos_sem_preco_de_atacado(): void
    {
        $this->trioDeAtacado();

        $response = $this->actingAs($this->admin())->get(route('products-admin.index'))->assertOk();

        $nomes = $response->viewData('products')->pluck('name')->all();
        $this->assertSame(['COM ATACADO'], $nomes);
        $this->assertSame('1', $response->viewData('atacado'));
        // zero e nulo contam como "sem atacado"
        $this->assertSame(2, $response->viewData('semAtacado'));
    }

    public function test_mostrar_todos_traz_tambem_os_sem_atacado(): void
    {
        $this->trioDeAtacado();

        $nomes = $this->actingAs($this->admin())
            ->get(route('products-admin.index', ['atacado' => '']))
            ->assertOk()
            ->viewData('products')->pluck('name')->sort()->values()->all();

        $this->assertSame(['ATACADO NULO', 'ATACADO ZERO', 'COM ATACADO'], $nomes);
    }

    public function test_filtro_sem_preco_isola_quem_precisa_de_atacado(): void
    {
        $this->trioDeAtacado();

        $nomes = $this->actingAs($this->admin())
            ->get(route('products-admin.index', ['atacado' => '0']))
            ->assertOk()
            ->viewData('products')->pluck('name')->sort()->values()->all();

        $this->assertSame(['ATACADO NULO', 'ATACADO ZERO'], $nomes);
    }

    public function test_filtro_de_atacado_combina_com_situacao(): void
    {
        $this->product(['name' => 'ATIVO COM', 'slug' => 'ativo-com', 'wholesale_price' => 100, 'active' => true]);
        $this->product(['name' => 'INATIVO COM', 'slug' => 'inativo-com', 'wholesale_price' => 100, 'active' => false]);
        $this->product(['name' => 'ATIVO SEM', 'slug' => 'ativo-sem', 'wholesale_price' => 0, 'active' => true]);

        // inativos + com atacado
        $nomes = $this->actingAs($this->admin())
            ->get(route('products-admin.index', ['active' => '0', 'atacado' => '1']))
            ->assertOk()
            ->viewData('products')->pluck('name')->all();

        $this->assertSame(['INATIVO COM'], $nomes);
    }

    public function test_chips_de_atacado_preservam_os_outros_filtros_no_link(): void
    {
        $this->product(['wholesale_price' => 10]);

        // Os links vão dentro de um atributo HTML, então o `&` sai como `&amp;`
        // — compara já escapado.
        $this->actingAs($this->admin())
            ->get(route('products-admin.index', ['active' => '0']))
            ->assertOk()
            // o chip de atacado tem que levar active=0 adiante
            ->assertSee(e(route('products-admin.index', ['active' => '0', 'atacado' => ''])), false)
            // e o chip de situação tem que levar o atacado atual
            ->assertSee(e(route('products-admin.index', ['active' => '1', 'atacado' => '1'])), false);
    }

    /**
     * A célula esvazia ao receber foco (a máscara é da direita pra esquerda,
     * então digitar por cima emendaria os dígitos). O valor gravado vai no
     * placeholder pra continuar à vista enquanto se digita.
     */
    public function test_celula_leva_o_valor_gravado_tambem_no_placeholder(): void
    {
        $this->product(['wholesale_price' => 1190]);

        $this->actingAs($this->admin())
            ->get(route('products-admin.index'))
            ->assertOk()
            ->assertSee('value="1,190.00"', false)
            ->assertSee('data-orig="1,190.00"', false)
            ->assertSee('placeholder="1,190.00"', false);
    }

    public function test_a_grade_abre_para_quem_tem_products_view(): void
    {
        $this->product();

        $this->actingAs($this->admin())
            ->get(route('products-admin.index'))
            ->assertOk()
            ->assertSee('Produtos × Preços', false)
            ->assertSee('data-orig', false);
    }

    /**
     * Configura a revalidação do Orbita e finge a chamada HTTP — sem isso o
     * OrbitaRevalidator se desliga sozinho (ver enabled()).
     */
    private function fingeOrbita(): void
    {
        config([
            'services.orbita.revalidate_url' => 'https://orbita.test/api/revalidate',
            'services.orbita.revalidate_secret' => 'segredo-de-teste',
        ]);

        Http::fake(['orbita.test/*' => Http::response(['revalidated' => true])]);
    }

    /**
     * O Preço Web é o preço que a loja cobra, e o Orbita cacheia as respostas
     * da API: salvar a célula tem de avisar o site pra derrubar o cache do
     * produto e das listagens na hora.
     */
    public function test_preco_web_avisa_o_orbita_pra_revalidar(): void
    {
        $product = $this->product(['slug' => 'iphone-16-pro-max-256gb-web']);
        $this->fingeOrbita();

        $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), ['column' => 'web_price', 'value' => 1499])
            ->assertOk();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://orbita.test/api/revalidate'
                && $request->hasHeader('X-Orbita-Revalidate', 'segredo-de-teste')
                && $request['tags'] === ['catalog', 'product:iphone-16-pro-max-256gb-web'];
        });
    }

    /**
     * Atacado, promo, mínimo e varejo são preços internos — não aparecem na
     * loja, então não há cache do Orbita pra invalidar.
     */
    public function test_atacado_nao_avisa_o_orbita(): void
    {
        $product = $this->product();
        $this->fingeOrbita();

        $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), ['column' => 'wholesale_price', 'value' => 1050])
            ->assertOk();

        Http::assertNothingSent();
    }

    /**
     * Configura a API de importação do ComprasPY e finge a chamada — sem
     * credencial o ComprasPyImportService se desliga sozinho.
     */
    private function fingeComprasPy(): void
    {
        config([
            'feeds.compraspy.store_url' => 'https://eshopcell.com',
            'feeds.compraspy.minimum_price' => 0.10,
            'feeds.compraspy.api.url' => 'https://products.comprasparaguai.test/api/products/import/',
            'feeds.compraspy.api.token' => 'token-da-loja',
            'feeds.compraspy.api.timeout' => 15,
        ]);

        Http::fake(['products.comprasparaguai.test/*' => Http::response(['products_failed' => 0], 207)]);
    }

    /**
     * O mesmo Preço Web que vai pra loja tem de chegar no ComprasPY na hora:
     * fora disso, o anúncio lá só mudaria quando eles lessem o feed XML de
     * novo, até uma hora depois.
     */
    public function test_preco_web_vai_para_o_compraspy(): void
    {
        $product = $this->product([
            'slug' => 'iphone-16-pro-max-256gb-cpy',
            'erp_code' => '1618',
            'image_url' => 'https://cdn.exemplo.com/iphone.webp',
            'stock' => 7,
        ]);
        $this->fingeComprasPy();

        $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), ['column' => 'web_price', 'value' => 1499])
            ->assertOk();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'comprasparaguai')) {
                return false;
            }

            $item = $request->data()[0];

            return $request->hasHeader('token', 'token-da-loja')
                && $item['code'] === '1618'
                && $item['price'] === 1499.0
                && $item['stock'] === 1
                && $item['link'] === 'https://eshopcell.com/produtos/iphone-16-pro-max-256gb-cpy';
        });
    }

    /**
     * Atacado é preço interno: não vai pra loja nem pro ComprasPY.
     */
    public function test_atacado_nao_vai_para_o_compraspy(): void
    {
        $product = $this->product(['erp_code' => '1618', 'image_url' => 'https://cdn.exemplo.com/i.webp']);
        $this->fingeComprasPy();

        $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), ['column' => 'wholesale_price', 'value' => 1050])
            ->assertOk();

        Http::assertNothingSent();
    }

    /**
     * ComprasPY fora do ar também não derruba o salvamento — e não impede o
     * aviso ao Orbita, que é o canal da própria loja.
     */
    public function test_falha_do_compraspy_nao_desfaz_a_gravacao_nem_impede_o_orbita(): void
    {
        $product = $this->product(['erp_code' => '1618', 'image_url' => 'https://cdn.exemplo.com/i.webp']);
        $this->fingeOrbita();
        config([
            'feeds.compraspy.api.url' => 'https://products.comprasparaguai.test/api/products/import/',
            'feeds.compraspy.api.token' => 'token-da-loja',
            'feeds.compraspy.api.timeout' => 15,
        ]);
        Http::fake(['products.comprasparaguai.test/*' => Http::response('erro', 500)]);

        $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), ['column' => 'web_price', 'value' => 1499])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(1499.0, (float) $product->fresh()->web_price);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'orbita.test'));
    }

    /**
     * Orbita fora do ar não pode derrubar o salvamento do preço: a base do
     * site é a fonte de verdade e o cache dele vence sozinho de qualquer jeito.
     */
    public function test_falha_do_orbita_nao_desfaz_a_gravacao(): void
    {
        $product = $this->product();

        config([
            'services.orbita.revalidate_url' => 'https://orbita.test/api/revalidate',
            'services.orbita.revalidate_secret' => 'segredo-de-teste',
        ]);
        Http::fake(['orbita.test/*' => Http::response('nginx: 502', 502)]);

        $this->actingAs($this->admin())
            ->patchJson(route('products-admin.prices', $product), ['column' => 'web_price', 'value' => 1499])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(1499.0, (float) $product->fresh()->web_price);
    }
}
