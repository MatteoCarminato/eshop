<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Lista de produtos com estoque, agrupada por subgrupo — a mesma da tela
 * products-admin/export. O #lista do WhatsApp manda o texto que o botão
 * "Copiar" gera com a tela no padrão (tudo marcado, produto e preço, U$, sem ajuste).
 */
class ProductExportListService
{
    public function grupos(): Collection
    {
        $products = Product::query()
            ->select(['id', 'name', 'short_name', 'wholesale_price', 'store_stock', 'product_subgroup_id'])
            ->with('productSubgroup:id,name,code')
            // Estoque da loja ARMANDO ATACADO (`store_stock`), o mesmo da grade
            // de produtos — `stock` soma todos os depósitos do ERP.
            ->where('store_stock', '>', 0)
            ->get();

        // Agrupa em PHP (não no banco) pra manter a ordenação dos itens e
        // jogar os sem subgrupo num bloco próprio no fim.
        return $products
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
                        'estoque' => (float) $p->store_stock,
                    ])
                    ->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all(),
            ])
            // Subgrupos em ordem alfabética; "SEM SUBGRUPO" sempre por último.
            ->sortBy(fn (array $g) => ($g['sem_subgrupo'] ? '1' : '0') . mb_strtolower($g['nome']))
            ->values();
    }

    /**
     * Nomes dos subgrupos com estoque, na ordem da lista — é o menu do #lista.
     *
     * @return list<string>
     */
    public function nomesDosGrupos(): array
    {
        return $this->grupos()->pluck('nome')->all();
    }

    /**
     * Menu numerado do #lista: um item por subgrupo e "TODOS" por último.
     * A resposta com o número cai em textoWhatsapp([nome]) — ou sem filtro, no TODOS.
     *
     * @param  list<string>  $nomes  resultado de nomesDosGrupos()
     */
    public function menuWhatsapp(array $nomes): string
    {
        $linhas = array_map(fn (string $n, int $i) => '[' . ($i + 1) . '] ' . $n, $nomes, array_keys($nomes));
        $linhas[] = '[' . (count($nomes) + 1) . '] TODOS';

        return "*# Lista*\nResponda com o número do grupo:\n\n" . implode("\n", $linhas);
    }

    /**
     * Mesmo formato do montarTexto() da tela: subgrupo em negrito, depois
     * "NOME *U$ 1.215,00*" por item; blocos separados por duas linhas em branco.
     *
     * @param  list<string>|null  $soGrupos  só esses subgrupos (pelo nome); null = todos
     */
    public function textoWhatsapp(?array $soGrupos = null): string
    {
        $negrito = fn (string $t) => '*' . trim($t) . '*';

        return $this->grupos()
            ->when($soGrupos !== null, fn (Collection $g) => $g->filter(fn (array $x) => in_array($x['nome'], $soGrupos, true)))
            ->map(fn (array $g) => implode("\n", [
                $negrito($g['nome']),
                ...array_map(
                    fn (array $p) => $p['nome'] . ' ' . $negrito('U$ ' . number_format($p['preco'], 2, ',', '.')),
                    $g['produtos']
                ),
            ]))
            ->implode("\n\n\n");
    }
}
