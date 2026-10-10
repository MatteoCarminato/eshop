<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductSubgroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tela de exportação da lista de produtos com estoque, agrupada por subgrupo
 * (GET products-admin/export).
 */
class ProductExportListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Catálogo Export', 'is_admin' => false]);
        $role->syncModules(['products.view']);

        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
    }

    protected function subgroup(string $name, int $code): ProductSubgroup
    {
        return ProductSubgroup::create([
            'recno' => $code,
            'code' => $code,
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name) . '-' . $code,
            'active' => true,
        ]);
    }

    /**
     * `$atacado` é o PREATAC (wholesale_price) — é ele que a exportação usa.
     * O `price` (PRECO3) é gravado de propósito com outro valor, pra o teste
     * falhar se a tela voltar a ler a coluna errada.
     */
    protected function product(string $short, float $atacado, float $stock, ?ProductSubgroup $sg): Product
    {
        static $n = 0;
        $n++;

        return Product::create([
            'name' => "PRODUTO LONGO {$n}",
            'short_name' => $short,
            'slug' => "produto-{$n}",
            'price' => $atacado + 100,
            'wholesale_price' => $atacado,
            'stock' => $stock,
            'active' => true,
            'product_subgroup_id' => $sg?->id,
        ]);
    }

    public function test_lista_so_produtos_com_estoque(): void
    {
        $sg = $this->subgroup('17 256GB LL', 1);
        $this->product('BLUE', 885, 1, $sg);
        $this->product('BLACK', 815, 0, $sg);   // sem estoque: fica fora

        $response = $this->get(route('products-admin.export'))->assertOk();

        $grupos = $response->viewData('grupos');
        $this->assertCount(1, $grupos);
        $this->assertSame('17 256GB LL', $grupos[0]['nome']);
        $this->assertCount(1, $grupos[0]['produtos']);
        $this->assertSame('BLUE', $grupos[0]['produtos'][0]['nome']);
        $this->assertSame(885.0, $grupos[0]['produtos'][0]['preco']);
        $this->assertSame(1, $response->viewData('totalProdutos'));
    }

    public function test_agrupa_por_subgrupo_em_ordem_alfabetica(): void
    {
        $promax = $this->subgroup('18 PROMAX 256GB LL', 2);
        $pro = $this->subgroup('18 PRO 256GB LL', 3);
        $dezessete = $this->subgroup('17 256GB LL', 4);

        $this->product('BLACK', 1430, 2, $promax);
        $this->product('SILVER', 1290, 1, $pro);
        $this->product('BLUE', 885, 1, $dezessete);

        $grupos = $this->get(route('products-admin.export'))->assertOk()->viewData('grupos');

        $this->assertSame(
            ['17 256GB LL', '18 PRO 256GB LL', '18 PROMAX 256GB LL'],
            $grupos->pluck('nome')->all(),
        );
    }

    public function test_ordena_produtos_por_nome_dentro_do_subgrupo(): void
    {
        $sg = $this->subgroup('18 PROMAX 256GB LL', 5);
        $this->product('SILVER', 1440, 1, $sg);
        $this->product('BLACK', 1430, 1, $sg);
        $this->product('GLACIER', 1460, 1, $sg);
        $this->product('BURGUNDY', 1800, 1, $sg);

        $grupos = $this->get(route('products-admin.export'))->assertOk()->viewData('grupos');

        $this->assertSame(
            ['BLACK', 'BURGUNDY', 'GLACIER', 'SILVER'],
            array_column($grupos[0]['produtos'], 'nome'),
        );
    }

    public function test_produtos_sem_subgrupo_vao_para_um_bloco_no_fim(): void
    {
        $sg = $this->subgroup('ZZZ ULTIMO ALFABETICAMENTE', 6);
        $this->product('ALGO', 100, 1, $sg);
        $this->product('ORFAO', 50, 1, null);

        $grupos = $this->get(route('products-admin.export'))->assertOk()->viewData('grupos');

        $this->assertCount(2, $grupos);
        $this->assertSame('ZZZ ULTIMO ALFABETICAMENTE', $grupos[0]['nome']);
        $this->assertSame('SEM SUBGRUPO', $grupos[1]['nome']);
        $this->assertTrue($grupos[1]['sem_subgrupo']);
    }

    public function test_cai_para_o_nome_longo_quando_nao_tem_nompro(): void
    {
        $sg = $this->subgroup('GERAL', 7);
        $p = $this->product('TEMP', 10, 1, $sg);
        $p->update(['short_name' => null]);

        $grupos = $this->get(route('products-admin.export'))->assertOk()->viewData('grupos');

        $this->assertSame($p->fresh()->name, $grupos[0]['produtos'][0]['nome']);
    }

    public function test_tela_vazia_quando_nada_tem_estoque(): void
    {
        $sg = $this->subgroup('GERAL', 8);
        $this->product('BLUE', 885, 0, $sg);

        $this->get(route('products-admin.export'))
            ->assertOk()
            ->assertSee('não há o que exportar', false);
    }

    public function test_a_grade_tem_o_botao_de_exportar(): void
    {
        $this->get(route('products-admin.index'))
            ->assertOk()
            ->assertSee(route('products-admin.export'), false)
            ->assertSee('Exportar');
    }

    public function test_usa_preatac_e_nao_preco3(): void
    {
        $sg = $this->subgroup('17 256GB LL', 20);

        $p = $this->product('BLUE', 885, 1, $sg);
        $this->assertSame(985.0, (float) $p->price, 'o helper grava PRECO3 diferente de propósito');

        $grupos = $this->get(route('products-admin.export'))->assertOk()->viewData('grupos');

        $this->assertSame(885.0, $grupos[0]['produtos'][0]['preco']);
    }

    public function test_sinaliza_produto_sem_preco_de_atacado(): void
    {
        $sg = $this->subgroup('GERAL', 21);
        $this->product('SEM ATACADO', 0, 1, $sg);

        $this->get(route('products-admin.export'))
            ->assertOk()
            ->assertSee('sem atacado');
    }

    public function test_exige_o_modulo_products_view(): void
    {
        $role = Role::create(['name' => 'Sem Catálogo', 'is_admin' => false]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)->get(route('products-admin.export'))->assertForbidden();
    }
}
