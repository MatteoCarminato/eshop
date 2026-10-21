<?php

namespace App\Http\Controllers;

use App\Exceptions\ErpWriteException;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\Erp\ErpPriceWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductAdminController extends Controller
{
    /**
     * Colunas de preço do produto, na ordem em que aparecem na grade.
     *
     * As colunas com `edit => true` formam a whitelist do endpoint de edição
     * inline: o nome da coluna vem do cliente, então nunca é usado numa query
     * sem passar por aqui.
     *
     * - `grade`: quem aparece na tela. `false` esconde a coluna sem tirá-la do
     *   whitelist nem do sync, então voltar atrás é trocar um booleano.
     * - `edit`: quem é digitável. `false` sai do whitelist e vira texto na
     *   grade, fora do caminho do Tab.
     * - `tom`: classe de cor da célula; `pri` é a coluna principal (texto
     *   escuro e negrito).
     * - `espelha`: outras colunas que recebem o mesmo valor quando esta é
     *   gravada (ver updatePrices()).
     * - `erp`: coluna correspondente no PRODUTO_PRO. Quem tem essa chave é
     *   gravado TAMBÉM no ERP ao ser salvo (ver ErpPriceWriter); quem não tem
     *   fica só na base do site.
     */
    public const PRICE_COLUMNS = [
        // Preço médio (CUSMED): custo médio do estoque, vindo do ERP. Fica
        // antes do atacado de propósito — é contra ele que se confere a
        // margem ao digitar o preço. Só leitura: não há API de escrita no ERP
        // e custo médio não é número que se digita, sai do movimento de compra.
        'average_price' => ['label' => 'Médio', 'tom' => 'medio', 'grade' => true, 'edit' => false],
        // Atacado é o "A C Atacado" do ERP, e lá ele não é um campo só:
        // PREATAC, PREVEN e PREMIN andam sempre com o mesmo valor (conferido no
        // ERP: as três colunas são idênticas em todo o catálogo). Por isso
        // gravar o atacado aqui espelha Promo e Mínimo.
        // É a ÚNICA coluna que sai da grade pro ERP: salvar o atacado grava
        // PREATAC, PREVEN e PREMIN no PRODUTO_PRO na hora, sem passar pelo
        // painel nem esperar o autoprice.
        'wholesale_price' => [
            'label' => 'Atacado', 'tom' => 'pri', 'grade' => true, 'edit' => true,
            'espelha' => ['sale_price', 'min_price'], 'erp' => 'PREATAC',
        ],
        // Diferença = Atacado − Médio: a margem em dólar do item, que é o que se
        // confere ao varrer a grade. NÃO existe no banco — `calc` tira a coluna
        // do select() e do endpoint de edição; o valor é montado na renderização
        // e refeito no cliente a cada atacado digitado.
        'diff' => ['label' => 'Diferença', 'tom' => 'diff', 'grade' => true, 'edit' => false, 'calc' => true],
        // Web (PRECOWEB) é independente: sem `espelha` e, de propósito, sem
        // `erp` — o preço web é do site e não volta pro ERP.
        'web_price' => ['label' => 'Web', 'tom' => '', 'grade' => true, 'edit' => true],
        // Promo (PREVEN) e Mínimo (PREMIN) ficam fora da grade: no ERP elas são
        // sempre iguais ao atacado, e agora o atacado as grava junto — na tela
        // seriam três colunas repetindo o mesmo número. Seguem sincronizadas,
        // espelhadas e aceitas pelo endpoint.
        'sale_price' => ['label' => 'Promo', 'tom' => 'sale', 'grade' => false, 'edit' => true, 'erp' => 'PREVEN'],
        'min_price' => ['label' => 'Mínimo', 'tom' => 'min', 'grade' => false, 'edit' => true, 'erp' => 'PREMIN'],
        // Varejo (PRECO3) continua sendo sincronizado do ERP e aceito pelo
        // endpoint, mas fora da grade: o preço que o negócio usa é o atacado.
        // Sem `erp`: o usuário de escrita não tem grant no PRECO3, então esta
        // coluna não teria como ir pro ERP nem se voltasse pra tela.
        'price' => ['label' => 'Varejo', 'tom' => '', 'grade' => false, 'edit' => true],
    ];

    /**
     * O que cada linha da exportação carrega. Mesmos quatro modos da tela
     * (ver o JS de admin/products/export.blade.php), repetidos aqui porque o
     * PDF é montado no servidor e precisa obedecer ao modo escolhido.
     */
    public const MODOS_EXPORT = [
        'preco' => ['preco' => true, 'estoque' => false],
        'produto' => ['preco' => false, 'estoque' => false],
        'estoque' => ['preco' => false, 'estoque' => true],
        'ambos' => ['preco' => true, 'estoque' => true],
    ];

    /**
     * Só as colunas que a grade exibe, na ordem declarada acima.
     *
     * @return array<string, array{label: string, tom: string, grade: bool, edit: bool}>
     */
    public static function gridColumns(): array
    {
        return array_filter(self::PRICE_COLUMNS, fn (array $col) => $col['grade']);
    }

    /**
     * Só as colunas da grade que são coluna de verdade no banco — é isso que
     * pode ir pro select(). Tira a Diferença, que é calculada.
     *
     * @return array<string, array{label: string, tom: string, grade: bool, edit: bool}>
     */
    public static function storedGridColumns(): array
    {
        return array_filter(self::gridColumns(), fn (array $col) => empty($col['calc']));
    }

    /**
     * Colunas que o endpoint de edição aceita — inclui as que estão fora da
     * grade (`price`) e exclui as de leitura (`average_price`).
     *
     * @return array<string, array{label: string, tom: string, grade: bool, edit: bool}>
     */
    public static function editableColumns(): array
    {
        return array_filter(self::PRICE_COLUMNS, fn (array $col) => $col['edit']);
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
        $group = $request->get('group');
        $active = $request->get('active', '1');
        // '1' = só com preço de atacado (padrão), '0' = só sem, '' = todos.
        // Padrão esconde os sem atacado: é o preço que o negócio usa, e boa
        // parte do catálogo vem do ERP com PREATAC zerado.
        $atacado = $request->get('atacado', '1');
        // '1' = só com estoque (padrão), '0' = só os zerados, '' = todos.
        // Padrão esconde os zerados: a grade serve pra conferir o preço do que
        // está à venda, e o catálogo do ERP arrasta muito item sem saldo.
        $estoque = $request->get('estoque', '1');

        $cols = self::gridColumns();

        // Filtros compartilhados pela listagem e pelas contagens dos chips, pra
        // as três não divergirem. Atacado e estoque ficam em closures separadas
        // porque cada contagem precisa aplicar um sem aplicar o outro.
        $base = fn () => Product::query()
            ->when($group !== null && $group !== '', fn ($q) => $q->where('product_group_id', (int) $group))
            ->when($active === '1', fn ($q) => $q->where('active', true))
            ->when($active === '0', fn ($q) => $q->where('active', false));

        $filtroAtacado = function ($q) use ($atacado) {
            // `> 0` já descarta NULL (NULL > 0 não é verdadeiro), então o
            // "com atacado" cobre nulo e zero de uma vez.
            if ($atacado === '1') {
                $q->where('wholesale_price', '>', 0);
            }
            if ($atacado === '0') {
                $q->where(fn ($q) => $q->whereNull('wholesale_price')->orWhere('wholesale_price', '<=', 0));
            }
        };

        $filtroEstoque = function ($q) use ($estoque) {
            if ($estoque === '1') {
                $q->where('stock', '>', 0);
            }
            if ($estoque === '0') {
                $q->where('stock', '<=', 0);
            }
        };

        $products = $base()
            ->select(array_merge(
                // `stock` vem sempre: a coluna de estoque da grade é só um
                // liga/desliga visual, não vale recarregar a tela pra isso.
                ['id', 'product_group_id', 'name', 'short_name', 'active', 'stock'],
                // Sem as calculadas (Diferença): no banco elas não existem.
                array_keys(self::storedGridColumns()),
            ))
            ->with('productGroup:id,name')
            ->where($filtroAtacado)
            ->where($filtroEstoque)
            ->orderBy('name')
            ->get();

        $groups = ProductGroup::orderBy('name')->get(['id', 'name']);

        // Quantos ficariam de fora por cada filtro, dentro dos mesmos filtros de
        // grupo/situação — mostrado nos chips "Sem preço" e "Zerados".
        $semAtacado = $base()
            ->where($filtroEstoque)
            ->where(fn ($q) => $q->whereNull('wholesale_price')->orWhere('wholesale_price', '<=', 0))
            ->count();

        $semEstoque = $base()
            ->where($filtroAtacado)
            ->where('stock', '<=', 0)
            ->count();

        return view('admin.products.admin-index', compact(
            'products', 'groups', 'group', 'active', 'atacado', 'estoque', 'cols',
            'semAtacado', 'semEstoque',
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
                        // O id vai junto porque a tela devolve a seleção pro
                        // PDF (exportPdf) — lá os produtos são relidos do banco
                        // por id, não pelo que o cliente mandou.
                        'id' => $p->id,
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
     * Catálogo em PDF (A4 retrato, papel branco com hero escuro e detalhes
     * azuis) dos produtos marcados na tela de exportação: grade de 4 cards
     * por linha com subgrupo, marca, nome, preço de atacado, código do ERP e
     * foto quando o produto tem uma. O layout vem do template
     * `catalogo_eshop.html` — ver resources/views/admin/products/export-pdf.blade.php.
     *
     * A tela manda só os ids marcados — nome, preço e estoque são relidos do
     * banco aqui, nunca aproveitados do que veio no request. O que a tela
     * manda além dos ids é a *apresentação*: o modo da lista (se mostra preço
     * e/ou estoque) e o ajuste de valor, que é o mesmo da pré-visualização e
     * vale só neste PDF — não grava no produto.
     */
    public function exportPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'modo' => ['required', Rule::in(array_keys(self::MODOS_EXPORT))],
            'ajuste_tipo' => ['required', Rule::in(['pct', 'fixo'])],
            'ajuste_valor' => ['required', 'numeric', 'between:-100000,100000'],
        ]);

        $modo = self::MODOS_EXPORT[$dados['modo']];

        $products = Product::query()
            ->select(['id', 'name', 'short_name', 'erp_code', 'recno', 'wholesale_price', 'stock', 'image_url', 'brand_id', 'product_subgroup_id'])
            ->with(['productSubgroup:id,name', 'brand:id,name'])
            ->whereIn('id', $dados['ids'])
            ->where('stock', '>', 0)
            ->get();

        if ($products->isEmpty()) {
            return back()->with('error', 'Nenhum produto válido na seleção — nada a exportar.');
        }

        // Mesma ordem da tela: subgrupo em ordem alfabética, "SEM SUBGRUPO" no
        // fim, produtos por nome dentro de cada bloco. Diferente da tela, aqui
        // a saída é uma lista achatada: os cards correm de ponta a ponta, 4 por
        // linha, e o subgrupo vira o rótulo de cada card (é ele que diz o
        // modelo/capacidade — o nome do produto costuma ser só a cor).
        $itens = $products
            ->map(fn (Product $p) => [
                'nome' => $p->short_name ?: $p->name,
                // Subgrupo primeiro: é o rótulo mais informativo do card. A
                // marca entra quando o produto não tem subgrupo.
                // Cortado em 22: o cabeçalho do card cabe em duas linhas, e um
                // subgrupo que vazasse pra terceira deixaria o card mais alto
                // que os vizinhos da mesma linha.
                'rotulo' => Str::limit($p->productSubgroup?->name ?: ($p->brand?->name ?: 'eShop'), 22, '…'),
                // Etiqueta à direita do cabeçalho do card: a marca — a menos
                // que o rótulo já a carregue ("ACESSORIO APPLE" + "APPLE"
                // seria repetição) ou que ela seja o próprio rótulo.
                'etiqueta' => $this->etiquetaDaMarca($p),
                'ordem' => $p->productSubgroup?->name ?? 'zzzz',
                // CODNNN é o código que o cliente usa pra pedir; sem ele, o
                // RECNO ainda identifica a linha do ERP.
                'codigo' => $p->erp_code ?: ($p->recno ? (string) $p->recno : null),
                'preco' => $this->ajustarPreco((float) $p->wholesale_price, $dados['ajuste_tipo'], (float) $dados['ajuste_valor']),
                'estoque' => (float) $p->stock,
                'foto' => $p->image_url,
            ])
            ->sortBy(fn (array $i) => mb_strtolower($i['ordem'] . '|' . $i['nome']), SORT_NATURAL)
            ->values();

        $html = view('admin.products.export-pdf', [
            'itens' => $itens,
            'total' => $products->count(),
            'mostraPreco' => $modo['preco'],
            'mostraEstoque' => $modo['estoque'],
            'gerado' => now()->displayTz(),
            // Versão sem o fundo preto do PNG original: o hero do catálogo é
            // azul-escuro, e o logo com fundo deixaria um retângulo preto nele.
            'logo' => public_path('assets/images/logo-eshop-transparente.png'),
            'wppIcone' => public_path('assets/images/whatsapp.svg'),
            // Contato do vendedor impresso no catálogo. Sai do config
            // (services.whatsapp.catalogo) pra trocar sem mexer na view.
            'whatsapp' => config('services.whatsapp.catalogo'),
        ])->render();

        // O mPDF exige que o tempDir exista; a pasta não vem do git (vazia),
        // então é criada aqui em vez de quebrar no primeiro PDF do deploy.
        $temp = storage_path('app/mpdf');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($temp);

        $pdf = new \Mpdf\Mpdf([
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            // Espaço pro hero e pro rodapé fixos, que são desenhados fora da
            // margem (margin_header/footer = 0).
            'margin_top' => 33,
            'margin_bottom' => 20,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => $temp,
            // Foto de produto mora no S3/Spaces: sem timeout curto, um link
            // quebrado seguraria a geração inteira.
            'curlTimeout' => 5,
        ]);
        $pdf->SetTitle('Catálogo eShop');
        $pdf->SetDefaultBodyCSS('background-color', '#ffffff');
        $pdf->WriteHTML($html);

        $nome = 'catalogo-eshop-' . now()->displayTz()->format('Y-m-d') . '.pdf';

        return response($pdf->Output($nome, \Mpdf\Output\Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $nome . '"',
        ]);
    }

    /**
     * Marca a ser impressa como etiqueta do card, ou string vazia quando ela
     * não acrescenta nada ao rótulo (subgrupo) que já está ao lado.
     */
    private function etiquetaDaMarca(Product $product): string
    {
        $marca = $product->brand?->name;
        $rotulo = $product->productSubgroup?->name;

        if (!$marca || !$rotulo) {
            return '';
        }

        return str_contains(mb_strtolower($rotulo), mb_strtolower($marca)) ? '' : $marca;
    }

    /**
     * Ajuste de valor da tela de exportação: percentual ou valor fixo em
     * dólar, nunca negativo (100% de desconto zera o preço, não inverte).
     * Espelha `ajustar()` do JS da tela — os dois precisam dar o mesmo número.
     */
    private function ajustarPreco(float $preco, string $tipo, float $valor): float
    {
        if ($valor == 0.0) {
            return round($preco, 2);
        }

        $novo = $tipo === 'pct' ? $preco * (1 + $valor / 100) : $preco + $valor;

        return max(0, round($novo, 2));
    }

    /**
     * Grava UMA célula de preço de UM produto — é o que a grade chama ao sair
     * da célula.
     *
     * Uma célula por request de propósito: a edição é célula a célula pelo
     * teclado, então salvar só o que mudou evita sobrescrever uma coluna
     * vizinha com valor obsoleto caso duas abas estejam abertas.
     *
     * A exceção é o atacado, que arrasta Promo e Mínimo junto (`espelha` em
     * PRICE_COLUMNS): no ERP, o "A C Atacado" grava PREATAC, PREVEN e PREMIN
     * com o mesmo valor, e é assim que os três chegam aqui em todo sync. Sem
     * espelhar, digitar o atacado deixaria a própria grade incoerente — as
     * três colunas mostram o mesmo número no resto do catálogo. O preco web é
     * o contrário e continua sozinho: no ERP mexe só no PRECOWEB.
     *
     * O ATACADO vai pro ERP no mesmo request (ErpPriceWriter): grava PREATAC,
     * PREVEN e PREMIN no PRODUTO_PRO pelo CODNNN do produto. É a única coluna
     * que sai daqui — Web e Varejo ficam só na base do site, e o Médio é só
     * leitura.
     *
     * Se o ERP recusar, a base local NÃO é tocada e a célula volta em erro: as
     * duas bases nunca ficam diferentes por causa de uma gravação que só
     * funcionou de um lado.
     *
     * Os valores são em USD (o catálogo do ERP é todo em dólar).
     */
    public function updatePrices(Request $request, Product $product, ErpPriceWriter $erp): JsonResponse
    {
        $data = $request->validate([
            'column' => ['required', 'string', Rule::in(array_keys(self::editableColumns()))],
            // Teto casa com a coluna no banco: decimal(18,8).
            'value' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $column = $data['column'];
        $value = round((float) $data['value'], 2);
        $espelhos = self::PRICE_COLUMNS[$column]['espelha'] ?? [];
        $gravadas = array_merge([$column], $espelhos);

        // Colunas do ERP correspondentes às que serão gravadas aqui. Fica vazio
        // quando nenhuma delas tem par no ERP (Web, Varejo) — e aí a gravação é
        // só local, como era antes.
        $colunasErp = array_values(array_filter(array_map(
            fn (string $col) => self::PRICE_COLUMNS[$col]['erp'] ?? null,
            $gravadas,
        )));

        try {
            // Primeiro o ERP: ele é a fonte do preço e é o que pode recusar.
            // Dando erro, o update local nem acontece.
            $erp->write($product, $colunasErp, $value);
        } catch (ErpWriteException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $product->update(array_fill_keys($gravadas, $value));

        $fresh = $product->fresh();
        // Devolve os valores já normalizados pelo banco para a grade confirmar
        // o que ficou gravado, em vez de confiar no que digitou.
        $normalizado = fn (string $col) => number_format((float) $fresh->{$col}, 2, '.', '');

        return response()->json([
            'success' => true,
            // Diz se o preço saiu daqui pro ERP — a grade não usa hoje, mas é o
            // que diferencia "gravado no site" de "gravado no site e no ERP"
            // pra quem for depurar pelo network.
            'erp' => $colunasErp !== [] && $erp->enabled(),
            'column' => $column,
            'label' => self::PRICE_COLUMNS[$column]['label'],
            'value' => $normalizado($column),
            // Só as colunas vizinhas que foram gravadas de carona — a grade
            // repinta essas células sem recarregar.
            'espelhos' => array_combine($espelhos, array_map($normalizado, $espelhos)),
        ]);
    }
}
