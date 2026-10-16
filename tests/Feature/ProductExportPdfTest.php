<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductSubgroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Catálogo em PDF da tela de exportação (POST products-admin/export/pdf).
 *
 * O layout não é assertável, mas o conteúdo é: os testes leem o texto do PDF
 * gerado (smalot/pdfparser) pra conferir quem entra no catálogo, de onde vêm
 * os dados e como o modo e o ajuste de valor da tela aparecem no card.
 */
class ProductExportPdfTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $modulos = ['products.view']): User
    {
        $role = Role::create(['name' => 'Catálogo PDF ' . Str::random(5), 'is_admin' => false]);
        $role->syncModules($modulos);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function product(string $short, float $atacado, float $stock = 5): Product
    {
        static $n = 0;
        $n++;

        return Product::create([
            'name' => "PRODUTO LONGO {$n}",
            'short_name' => $short,
            'slug' => "produto-pdf-{$n}",
            'erp_code' => (string) (1000 + $n),
            'price' => $atacado + 100,
            'wholesale_price' => $atacado,
            'stock' => $stock,
            'active' => true,
        ]);
    }

    /**
     * Texto do PDF devolvido, pra assertar o conteúdo de verdade — não há
     * view pra inspecionar (o controller renderiza o HTML e entrega bytes).
     */
    private function textoDo($response): string
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'cat') . '.pdf';
        file_put_contents($arquivo, $response->getContent());

        // Sem reter o conteúdo das imagens: o catálogo embute o logo e as
        // fotos, e decodificá-las estoura o limite de memória do PHPUnit — o
        // que interessa aqui é só o texto.
        $config = new \Smalot\PdfParser\Config();
        $config->setRetainImageContent(false);

        try {
            $texto = (new \Smalot\PdfParser\Parser([], $config))->parseFile($arquivo)->getText();
        } finally {
            @unlink($arquivo);
        }

        // Espaços normalizados: o texto sai quebrado pelas células do card, e
        // o que interessa é o conteúdo, não onde a linha virou.
        return trim(preg_replace('/\s+/u', ' ', $texto));
    }

    private function gerar(array $payload = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->usuario())
            ->post(route('products-admin.export-pdf'), array_merge([
                'modo' => 'preco',
                'ajuste_tipo' => 'pct',
                'ajuste_valor' => 0,
            ], $payload));
    }

    public function test_gera_um_pdf_com_a_selecao(): void
    {
        $p = $this->product('BLUE', 885);

        $response = $this->gerar(['ids' => [$p->id]])->assertOk();

        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('catalogo-eshop-', $response->headers->get('content-disposition'));
    }

    public function test_exige_o_modulo_de_produtos(): void
    {
        $this->gerar(['ids' => [$this->product('BLUE', 885)->id]], $this->usuario(['clients.view']))
            ->assertForbidden();
    }

    public function test_sem_ids_nao_gera(): void
    {
        $this->product('BLUE', 885);

        $this->gerar(['ids' => []])->assertSessionHasErrors('ids');
    }

    /**
     * Produto sem estoque não entra no catálogo nem se o id for mandado à mão
     * — a tela só lista quem tem estoque, e o servidor refaz esse filtro.
     */
    public function test_ignora_produto_sem_estoque_e_para_quando_sobra_nada(): void
    {
        $semEstoque = $this->product('BLACK', 815, 0);

        $this->gerar(['ids' => [$semEstoque->id]])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    /**
     * O que vai pro PDF é lido do banco: nome, preço e código saem do
     * produto, não do que o cliente mandou no request.
     */
    public function test_conteudo_vem_do_banco_e_respeita_o_ajuste_de_valor(): void
    {
        $sg = ProductSubgroup::create([
            'recno' => 1, 'code' => 1, 'name' => '17 256GB LL', 'slug' => '17-256gb-ll', 'active' => true,
        ]);
        $p = $this->product('BLUE', 1000);
        $p->update(['product_subgroup_id' => $sg->id]);
        $this->product('ORANGE', 1475);

        $texto = $this->textoDo($this->gerar([
            'ids' => [$p->id],
            'ajuste_tipo' => 'pct',
            'ajuste_valor' => -10,
            'nome' => 'MANDADO NA MARRA',
        ])->assertOk());

        $this->assertStringContainsString('BLUE', $texto);
        // -10% sobre o atacado, só neste PDF.
        $this->assertStringContainsString('US$ 900,00', $texto);
        // Rótulo do card é o subgrupo, que é quem diz modelo/capacidade.
        $this->assertStringContainsString('17 256GB LL', $texto);
        $this->assertStringContainsString('CÓDIGO ' . $p->erp_code, $texto);
        // Nem o produto não marcado nem o nome mandado à mão entram.
        $this->assertStringNotContainsString('ORANGE', $texto);
        $this->assertStringNotContainsString('MANDADO NA MARRA', $texto);
    }

    /**
     * Ajuste fixo em dólar. Desconto maior que o preço zera em vez de virar
     * negativo — e preço zerado vira "CONSULTE" no card, nunca "US$ 0,00".
     */
    public function test_ajuste_fixo_soma_em_dolar_e_nunca_fica_negativo(): void
    {
        $p = $this->product('BLUE', 50);

        $texto = $this->textoDo($this->gerar([
            'ids' => [$p->id], 'ajuste_tipo' => 'fixo', 'ajuste_valor' => -80,
        ])->assertOk());

        $this->assertStringContainsString('CONSULTE', $texto);
        $this->assertStringNotContainsString('US$', $texto);
    }

    /**
     * O contato do vendedor é impresso no cabeçalho de toda página — é por ele
     * que o cliente fecha o pedido.
     */
    public function test_cabecalho_traz_o_whatsapp_do_vendedor(): void
    {
        config(['services.whatsapp.catalogo' => '+595 976 883 793']);

        $texto = $this->textoDo($this->gerar(['ids' => [$this->product('BLUE', 885)->id]])->assertOk());

        $this->assertStringContainsString('+595 976 883 793', $texto);
        $this->assertStringContainsString('CATÁLOGO', $texto);
    }

    /**
     * Os quatro modos da tela valem no PDF: "Somente produto" tira o preço,
     * "Estoque e preço" traz os dois.
     */
    public function test_modo_da_tela_decide_o_que_aparece_no_card(): void
    {
        $p = $this->product('BLUE', 885, 7);

        $soProduto = $this->textoDo($this->gerar(['ids' => [$p->id], 'modo' => 'produto'])->assertOk());
        $this->assertStringContainsString('BLUE', $soProduto);
        $this->assertStringNotContainsString('US$', $soProduto);
        $this->assertStringNotContainsString('EST.', $soProduto);

        $ambos = $this->textoDo($this->gerar(['ids' => [$p->id], 'modo' => 'ambos'])->assertOk());
        $this->assertStringContainsString('US$ 885,00', $ambos);
        $this->assertStringContainsString('EST. 7', $ambos);
    }
}
