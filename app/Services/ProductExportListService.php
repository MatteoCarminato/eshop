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
            ->select(['id', 'name', 'short_name', 'wholesale_price', 'stock', 'product_subgroup_id'])
            ->with('productSubgroup:id,name,code')
            ->where('stock', '>', 0)
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
                        'estoque' => (float) $p->stock,
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
     * Mesmo formato do montarTexto() da tela: subgrupo em negrito, depois
     * "NOME *U$ 1.215,00*" por item; blocos separados por duas linhas em branco.
     */
    public function textoWhatsapp(): string
    {
        $negrito = fn (string $t) => '*' . trim($t) . '*';

        return $this->grupos()
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
