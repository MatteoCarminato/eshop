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
        // pelo endpoint — esconder a coluna não é tirá-la do whitelist.
        foreach (array_keys(\App\Http\Controllers\ProductAdminController::PRICE_COLUMNS) as $i => $column) {
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

    public function test_grade_mostra_atacado_como_principal_e_esconde_varejo(): void
    {
        $this->product();

        $response = $this->actingAs($this->admin())->get(route('products-admin.index'))->assertOk();

        $response->assertSee('Atacado')
            ->assertDontSee('Varejo')
            ->assertSee('data-k="wholesale_price"', false)
            ->assertDontSee('data-k="price"', false);

        // Atacado é a primeira coluna navegável (onde o Alt+Enter cai).
        $cols = array_keys($response->viewData('cols'));
        $this->assertSame('wholesale_price', $cols[0]);
        $this->assertNotContains('price', $cols);
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
