<?php

namespace App\Services\Erp;

use App\Exceptions\ErpWriteException;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Grava preço no ERP (PRODUTO_PRO), pela conexão `erp_update`.
 *
 * É a única escrita que o site faz no ERP. O resto da integração é leitura
 * (ErpCatalogSyncService, conexão `erp`).
 *
 * Três coisas mandam no formato desta classe, e todas vêm do grant do usuário
 * `update1` no banco `novo_cec`:
 *
 *   GRANT SELECT (PREVEN, PREMIN, CODNNN, CODPRO, PRECOWEB, PREATAC, NOMPRO),
 *         UPDATE (PREVEN, PREMIN, PRECOWEB, PREATAC) ON novo_cec.PRODUTO_PRO
 *
 * 1. O WHERE é por CODNNN (`products.erp_code`): o usuário não tem SELECT nem
 *    na coluna RECNO, então casar por RECNO dá "SELECT command denied".
 * 2. Só PREATAC, PREVEN, PREMIN e PRECOWEB podem ser escritos. PRECO3 (o
 *    "Varejo" da whitelist local) não tem grant nenhum — por isso ele não tem
 *    coluna de ERP em PRICE_COLUMNS e fica só na base do site.
 * 3. Não há como conferir a gravação lendo de volta por aqui em outro banco: a
 *    leitura do site é no `cec_consulta`, que é outra conexão. Quem confirma é
 *    a contagem de linhas do próprio UPDATE.
 *
 * Isto NÃO passa pelo painel (`salvar_edicao_dinamica.php` + `crawler/
 * autoprice.php`): aquele caminho grava a string do preço em `tb_produtos_2` e
 * só chega no ERP quando o autoprice roda. Aqui o preço vai direto, no mesmo
 * request em que a célula é salva.
 */
class ErpPriceWriter
{
    /**
     * Grava as colunas do ERP de um produto, todas com o mesmo valor.
     *
     * @param  array<int, string>  $colunas  Colunas do PRODUTO_PRO (ex.: PREATAC)
     *
     * @throws ErpWriteException quando o ERP recusa — o chamador NÃO deve
     *         gravar na base do site nesse caso, pra as duas não divergirem.
     */
    public function write(Product $product, array $colunas, float $valor): void
    {
        if ($colunas === [] || ! $this->enabled()) {
            return;
        }

        $codigo = trim((string) $product->erp_code);

        if ($codigo === '') {
            throw new ErpWriteException(
                'Produto sem código do ERP (CODNNN) — rode o sync do catálogo antes de alterar o preço.'
            );
        }

        try {
            $afetadas = DB::connection('erp_update')
                ->table('PRODUTO_PRO')
                ->where('CODNNN', $codigo)
                ->update(array_fill_keys($colunas, $valor));
        } catch (Throwable $e) {
            Log::error('Falha ao gravar preço no ERP', [
                'product_id' => $product->id,
                'codnnn' => $codigo,
                'colunas' => $colunas,
                'valor' => $valor,
                'erro' => $e->getMessage(),
            ]);

            throw new ErpWriteException('O ERP não aceitou a gravação: ' . $e->getMessage(), 0, $e);
        }

        // A conexão usa MYSQL_ATTR_FOUND_ROWS, então zero aqui é linha não
        // encontrada de verdade — não "gravou o mesmo valor".
        if ($afetadas === 0) {
            throw new ErpWriteException("Nenhum produto no ERP com o código {$codigo}.");
        }
    }

    /**
     * Ambiente sem credencial de escrita (e os testes) grava só na base do
     * site — ver config/erp.php.
     */
    public function enabled(): bool
    {
        return (bool) config('erp.price_write');
    }
}
