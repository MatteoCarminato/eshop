<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_grade_mostra_so_medio_atacado_e_web(): void
    {
        $this->product();

        $response = $this->actingAs($this->admin())->get(route('products-admin.index'))->assertOk();

        $response->assertSee('Atacado')
            ->assertDontSee('Varejo')
            ->assertSee('data-k="wholesale_price"', false)
            ->assertSee('data-k="web_price"', false)
            // Varejo (PRECO3), Promo (PREVEN) e Mínimo (PREMIN) ficam fora da
            // grade — as duas últimas porque o atacado já as grava, então
            // repetiriam o mesmo número na tela.
            ->assertDontSee('data-k="price"', false)
            ->assertDontSee('data-k="sale_price"', false)
            ->assertDontSee('data-k="min_price"', false);

        // Ordem exata da grade: o Médio antes do atacado, e nada depois do Web.
        $cols = array_keys($response->viewData('cols'));
        $this->assertSame(['average_price', 'wholesale_price', 'web_price'], $cols);

        // O atacado é a primeira coluna NAVEGÁVEL (onde o Alt+Enter cai),
        // porque o Médio não é digitável.
        $navegaveis = array_values(array_intersect(
            array_keys(\App\Http\Controllers\ProductAdminController::editableColumns()),
            $cols,
        ));
        $this->assertSame(['wholesale_price', 'web_price'], $navegaveis);
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

    // ---- preço médio (UCBASE) ---------------------------------------------

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

    public function test_filtro_de_atacado_combina_com_situacao_e_marca(): void
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
}
