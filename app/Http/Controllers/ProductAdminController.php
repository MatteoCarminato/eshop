<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductAdminController extends Controller
{
    /**
     * Colunas de preço do produto, na ordem em que aparecem na grade.
     *
     * Esta lista inteira é a whitelist do endpoint de edição inline: o nome da
     * coluna vem do cliente, então nunca é usado numa query sem passar por
     * aqui.
     *
     * `grade` controla quem aparece na tela — `false` esconde a coluna sem
     * tirá-la do whitelist nem do sync, então voltar atrás é trocar um
     * booleano. `tom` é a classe de cor da célula; `pri` é a coluna principal
     * (texto escuro e negrito).
     */
    public const PRICE_COLUMNS = [
        'wholesale_price' => ['label' => 'Atacado', 'tom' => 'pri', 'grade' => true],
        'web_price' => ['label' => 'Web', 'tom' => '', 'grade' => true],
        'sale_price' => ['label' => 'Promo', 'tom' => 'sale', 'grade' => true],
        'min_price' => ['label' => 'Mínimo', 'tom' => 'min', 'grade' => true],
        // Varejo (PRECO3) continua sendo sincronizado do ERP e aceito pelo
        // endpoint, mas fora da grade: o preço que o negócio usa é o atacado.
        'price' => ['label' => 'Varejo', 'tom' => '', 'grade' => false],
    ];

    /**
     * Só as colunas que a grade exibe, na ordem declarada acima.
     *
     * @return array<string, array{label: string, tom: string, grade: bool}>
     */
    public static function gridColumns(): array
    {
        return array_filter(self::PRICE_COLUMNS, fn (array $col) => $col['grade']);
    }

    /**
     * Grade densa de produtos × preços.
     *
     * Diferente de products.index (catálogo completo, com imagem, estoque e
     * ações), esta tela carrega só nome e preços de todos os produtos de uma
     * vez — a ideia é varrer e corrigir preços de cima a baixo pelo teclado,
     * sem paginação e sem precisar abrir cada produto.
     */
    public function index(Request $request): View
    {
        $brand = $request->get('brand');
        $active = $request->get('active', '1');
        // '1' = só com preço de atacado (padrão), '0' = só sem, '' = todos.
        // Padrão esconde os sem atacado: é o preço que o negócio usa, e boa
        // parte do catálogo vem do ERP com PREATAC zerado.
        $atacado = $request->get('atacado', '1');

        $cols = self::gridColumns();

        // Filtros compartilhados pela listagem e pela contagem do chip, pra as
        // duas não divergirem.
        $base = fn () => Product::query()
            ->when($brand !== null && $brand !== '', fn ($q) => $q->where('brand_id', (int) $brand))
            ->when($active === '1', fn ($q) => $q->where('active', true))
            ->when($active === '0', fn ($q) => $q->where('active', false));

        $products = $base()
            ->select(array_merge(
                ['id', 'brand_id', 'name', 'short_name', 'active'],
                array_keys($cols),
            ))
            ->with('brand:id,name')
            // `> 0` já descarta NULL (NULL > 0 não é verdadeiro), então o
            // "com atacado" cobre nulo e zero de uma vez.
            ->when($atacado === '1', fn ($q) => $q->where('wholesale_price', '>', 0))
            ->when($atacado === '0', fn ($q) => $q->where(
                fn ($q) => $q->whereNull('wholesale_price')->orWhere('wholesale_price', '<=', 0)
            ))
            ->orderBy('name')
            ->get();

        $brands = Brand::orderBy('name')->get(['id', 'name']);

        // Quantos ficariam de fora pelo filtro de atacado, dentro dos mesmos
        // filtros de marca/situação — mostrado no chip "Sem atacado".
        $semAtacado = $base()
            ->where(fn ($q) => $q->whereNull('wholesale_price')->orWhere('wholesale_price', '<=', 0))
            ->count();

        return view('admin.products.admin-index', compact(
            'products', 'brands', 'brand', 'active', 'atacado', 'cols', 'semAtacado',
        ));
    }

    /**
     * Tela de exportação: produtos COM ESTOQUE, agrupados por subgrupo, no
     * formato pronto pra colar no WhatsApp:
     *
     *     17 256GB LL
     *     BLUE U$ 885,00
     *
     *     (duas linhas em branco)
     *
     *     18 PROMAX 256GB LL
     *     BLACK U$ 1.430,00
     *
     * O rótulo de cada item é o NOMPRO do ERP (`short_name`) — nesses
     * subgrupos ele já é só a cor ("BLUE", "BLACK"), porque o subgrupo
     * carrega modelo e capacidade.
     *
     * O preço é o de ATACADO — PREATAC no ERP, `wholesale_price` aqui — e NÃO
     * o PRECO3/`price` que a grade de preços edita. É o preço que vai pro
     * cliente nessa lista. Valores em USD.
     *
     * Aqui só monta os dados: "com preço"/"sem preço" e quais subgrupos entram
     * são decididos na própria tela, pra alternar sem recarregar.
     */
    public function export(Request $request): View
    {
        $products = Product::query()
            ->select(['id', 'name', 'short_name', 'wholesale_price', 'stock', 'product_subgroup_id'])
            ->with('productSubgroup:id,name,code')
            ->where('stock', '>', 0)
            ->get();

        // Agrupa em PHP (não no banco) pra manter a ordenação dos itens e
        // jogar os sem subgrupo num bloco próprio no fim.
        $grupos = $products
            ->groupBy(fn (Product $p) => $p->productSubgroup?->name ?? '')
            ->map(fn ($itens, $nome) => [
                'nome' => $nome === '' ? 'SEM SUBGRUPO' : $nome,
                'sem_subgrupo' => $nome === '',
                'produtos' => $itens
                    ->map(fn (Product $p) => [
                        // Cai pro nome longo se algum produto vier sem NOMPRO.
                        'nome' => $p->short_name ?: $p->name,
                        // PREATAC. Fica 0 se o ERP não tiver preço de atacado
                        // cadastrado — a tela sinaliza esses casos em vez de
                        // inventar um fallback para o PRECO3.
                        'preco' => round((float) $p->wholesale_price, 2),
                        'estoque' => (float) $p->stock,
                    ])
                    ->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all(),
            ])
            // Subgrupos em ordem alfabética; "SEM SUBGRUPO" sempre por último.
            ->sortBy(fn (array $g) => ($g['sem_subgrupo'] ? '1' : '0') . mb_strtolower($g['nome']))
            ->values();

        return view('admin.products.export', [
            'grupos' => $grupos,
            'totalProdutos' => $products->count(),
        ]);
    }

    /**
     * Grava UM preço de UM produto — é o que a grade chama ao sair da célula.
     *
     * Uma coluna por request de propósito: a edição é célula a célula pelo
     * teclado, então salvar só o que mudou evita sobrescrever uma coluna
     * vizinha com valor obsoleto caso duas abas estejam abertas.
     *
     * Os valores são em USD (o catálogo do ERP é todo em dólar).
     */
    public function updatePrices(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'column' => ['required', 'string', Rule::in(array_keys(self::PRICE_COLUMNS))],
            // Teto casa com a coluna no banco: decimal(18,8).
            'value' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $column = $data['column'];
        $value = round((float) $data['value'], 2);

        $product->update([$column => $value]);

        return response()->json([
            'success' => true,
            'column' => $column,
            'label' => self::PRICE_COLUMNS[$column]['label'],
            // Devolve o valor já normalizado pelo banco para a grade confirmar
            // o que ficou gravado, em vez de confiar no que digitou.
            'value' => number_format((float) $product->fresh()->{$column}, 2, '.', ''),
        ]);
    }
}
