<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TreasuryLot;
use App\Models\TreasurySale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Caixa próprio do dono em USD.
 *
 *  Origens dos lotes (treasury_lots.source):
 *   - 'owner'        → o dono aportou USD do próprio bolso (addLot).
 *   - 'pre_purchase' → o dono comprou USD usando R$ DO CLIENTE numa pré-compra
 *                      (criado automaticamente em WalletService::prePurchaseDollar).
 *
 *  Saídas (consumo FIFO):
 *   - sellToClient(): venda direta avulsa (modal "Vender" do dashboard de caixa).
 *     Cria 2 transações no cliente (USD entrada + BRL saída) e debita o caixa.
 *
 *   - sellPendingTransactions(): finaliza transações USD que estão em
 *     'aguardando_venda' (geradas pelo fechamento de dólar). Aqui o BRL JÁ foi
 *     consumido no fechamento; só é entregue o USD e calculado o PnL em R$.
 */
class TreasuryService
{
    public function __construct(protected WalletService $walletService) {}

    public function summary(): array
    {
        $open = TreasuryLot::query()
            ->whereIn('status', ['open', 'partial'])
            ->get();

        $usdEmCaixa = (float) $open->sum('usd_remaining');

        $custoPonderado = (float) $open->sum(fn ($l) => (float) $l->usd_remaining * (float) $l->cost_rate);
        $custoMedio = $usdEmCaixa > 0 ? round($custoPonderado / $usdEmCaixa, 6) : null;

        $pnlAcumulado = (float) TreasuryLot::query()->sum('realized_pnl_brl');
        $pnlAcumuladoUsd = (float) TreasurySale::query()->sum('realized_pnl_usd');

        $usdOwner       = (float) $open->where('source', 'owner')->sum('usd_remaining');
        $usdPrePurchase = (float) $open->where('source', 'pre_purchase')->sum('usd_remaining');
        $usdProfit      = (float) $open->where('source', 'profit')->sum('usd_remaining');

        return [
            'usd_em_caixa'         => round($usdEmCaixa, 4),
            'custo_medio'          => $custoMedio,
            'brl_investido_aberto' => round($custoPonderado, 2),
            'pnl_acumulado_brl'    => round($pnlAcumulado, 2),
            'pnl_acumulado_usd'    => round($pnlAcumuladoUsd, 4),
            'lotes_abertos'        => $open->count(),
            'usd_owner'            => round($usdOwner, 4),
            'usd_pre_purchase'     => round($usdPrePurchase, 4),
            'usd_profit'           => round($usdProfit, 4),
        ];
    }

    public function clientSummary(int $clientId): array
    {
        $lotes = TreasuryLot::query()
            ->where('client_id', $clientId)
            ->whereIn('status', ['open', 'partial'])
            ->get();

        $usd = (float) $lotes->sum('usd_remaining');
        $brl = (float) $lotes->sum(fn ($l) => (float) $l->usd_remaining * (float) $l->cost_rate);
        $custoMedio = $usd > 0 ? round($brl / $usd, 6) : null;

        return [
            'usd_em_caixa_cliente' => round($usd, 4),
            'brl_custo_cliente'    => round($brl, 2),
            'custo_medio_cliente'  => $custoMedio,
        ];
    }

    public function addLot(float $usdAmount, float $costRate, ?string $notes = null, ?\DateTimeInterface $purchasedAt = null): TreasuryLot
    {
        if ($usdAmount <= 0) throw new \InvalidArgumentException('USD deve ser maior que zero.');
        if ($costRate <= 0)  throw new \InvalidArgumentException('Taxa de custo inválida.');

        return DB::transaction(function () use ($usdAmount, $costRate, $notes, $purchasedAt) {
            return TreasuryLot::create([
                'created_by'       => Auth::id(),
                'source'           => 'owner',
                'usd_amount'       => $usdAmount,
                'cost_rate'        => $costRate,
                'brl_cost'         => round($usdAmount * $costRate, 8),
                'usd_remaining'    => $usdAmount,
                'realized_pnl_brl' => 0,
                'status'           => 'open',
                'purchased_at'     => $purchasedAt ?? now(),
                'notes'            => $notes,
            ]);
        });
    }

    /**
     * Carrega lotes abertos ordenados FIFO. Se $clientId for informado,
     * lotes daquele cliente vêm primeiro.
     */
    protected function loadOpenLotsForClient(?int $clientId): \Illuminate\Support\Collection
    {
        $rows = TreasuryLot::query()
            ->whereIn('status', ['open', 'partial'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($clientId === null) return $rows;

        $matem = $rows->filter(fn ($l) => (int) ($l->client_id ?? 0) === $clientId)->values();
        $resto = $rows->reject(fn ($l) => (int) ($l->client_id ?? 0) === $clientId)->values();

        return $matem->concat($resto);
    }

    /**
     * Consome lotes FIFO. Atualiza status/PnL. Não cria transação aqui.
     *
     * Se o USD pedido for maior que o disponível, consome o que tem e cria um
     * lote 'shortfall' com o déficit (usd_remaining negativo). O caixa fica
     * negativo até um aporte ou pré-compra futura cobrir o rombo.
     *
     * @return array{custo_brl: float, pnl_brl: float, pnl_usd: float, shortfall_usd: float, shortfall_lot: ?TreasuryLot}
     */
    protected function consumeLots(?int $clientId, float $usdAmount, float $sellRate): array
    {
        $lotes = $this->loadOpenLotsForClient($clientId);

        $disponivel = (float) $lotes->sum('usd_remaining');
        $shortfall  = 0.0;
        if ($usdAmount > $disponivel + 0.0001) {
            $shortfall = round($usdAmount - max(0.0, $disponivel), 8);
        }

        // Quanto efetivamente conseguimos retirar dos lotes existentes.
        $consumirDosLotes = min($usdAmount, max(0.0, $disponivel));
        $restante = $consumirDosLotes;
        $custoBrlTotal = 0.0;
        $pnlBrlTotal = 0.0;

        foreach ($lotes as $lote) {
            if ($restante <= 0.00000001) break;
            $disp = (float) $lote->usd_remaining;
            if ($disp <= 0.00000001) continue;

            $usdConsumir = min($disp, $restante);
            $costRate = (float) $lote->cost_rate;
            $custoBrlLote = $usdConsumir * $costRate;
            $vendaBrlLote = $usdConsumir * $sellRate;
            $pnlLote = $vendaBrlLote - $custoBrlLote;

            $lote->usd_remaining = round($disp - $usdConsumir, 8);
            $lote->realized_pnl_brl = (float) $lote->realized_pnl_brl + $pnlLote;
            $lote->status = $lote->usd_remaining <= 0.00000001 ? 'closed' : 'partial';
            if ($lote->status === 'closed') $lote->usd_remaining = 0;
            $lote->save();

            $custoBrlTotal += $custoBrlLote;
            $pnlBrlTotal   += $pnlLote;
            $restante      -= $usdConsumir;
        }

        // Déficit: registra um lote 'shortfall' com USD negativo.
        // Custo presumido = sellRate (lucro=0 nessa fatia). Caixa do cliente
        // (e total) ficam com saldo negativo até serem cobertos.
        $shortfallLot = null;
        if ($shortfall > 0.00000001) {
            $shortfallLot = TreasuryLot::create([
                'created_by'       => Auth::id(),
                'source'           => 'shortfall',
                'client_id'        => $clientId,
                'pre_purchase_id'  => null,
                'usd_amount'       => -$shortfall,
                'cost_rate'        => $sellRate,
                'brl_cost'         => -round($shortfall * $sellRate, 8),
                'usd_remaining'    => -$shortfall,
                'realized_pnl_brl' => 0,
                'status'           => 'open',
                'purchased_at'     => now(),
                'notes'            => 'Déficit no caixa: entregue US$ ' . number_format($shortfall, 4, ',', '.') .
                                      ' acima do saldo disponível @ ' . number_format($sellRate, 4, ',', '.'),
            ]);

            // Considera o déficit como custo nominal à taxa de venda — PnL nessa parte = 0.
            $custoBrlTotal += $shortfall * $sellRate;
        }

        // Converte o lucro em R$ para USD na taxa da venda. Esse é o ganho que
        // "sobra" no caixa em dólar (lote 'profit' criado em quem chamar).
        $pnlUsd = ($sellRate > 0) ? round($pnlBrlTotal / $sellRate, 8) : 0.0;

        return [
            'custo_brl'     => round($custoBrlTotal, 8),
            'pnl_brl'       => round($pnlBrlTotal, 8),
            'pnl_usd'       => $pnlUsd,
            'shortfall_usd' => $shortfall,
            'shortfall_lot' => $shortfallLot,
        ];
    }

    /**
     * Cria um lote 'profit' para que o lucro em USD entre no caixa da empresa.
     */
    protected function bookProfitLot(float $pnlUsd, float $sellRate, ?int $clientId, ?string $notes): void
    {
        if ($pnlUsd <= 0.00000001) return;

        TreasuryLot::create([
            'created_by'       => Auth::id(),
            'source'           => 'profit',
            'client_id'        => $clientId,
            'pre_purchase_id'  => null,
            'usd_amount'       => $pnlUsd,
            'cost_rate'        => 0,
            'brl_cost'         => 0,
            'usd_remaining'    => $pnlUsd,
            'realized_pnl_brl' => 0,
            'status'           => 'open',
            'purchased_at'     => now(),
            'notes'            => 'Lucro venda @ ' . number_format($sellRate, 4, ',', '.') .
                                  ($notes ? ' — ' . $notes : ''),
        ]);
    }

    /**
     * Cria um lote 'close' — USD comprado pelo dono na hora do fechamento (parte
     * que não estava pré-comprada). O custo é a taxa que o cliente fechou, ou seja,
     * lucro nessa parte = 0; o lucro só surge se o caixa for vendido depois numa
     * cotação maior.
     */
    public function bookCloseLot(int $clientId, float $usdAmount, float $closeRate, ?string $notes = null): ?TreasuryLot
    {
        if ($usdAmount <= 0.00000001) return null;
        if ($closeRate <= 0) throw new \InvalidArgumentException('Taxa de fechamento inválida.');

        return TreasuryLot::create([
            'created_by'       => Auth::id(),
            'source'           => 'close',
            'client_id'        => $clientId,
            'pre_purchase_id'  => null,
            'usd_amount'       => $usdAmount,
            'cost_rate'        => $closeRate,
            'brl_cost'         => round($usdAmount * $closeRate, 8),
            'usd_remaining'    => $usdAmount,
            'realized_pnl_brl' => 0,
            'status'           => 'open',
            'purchased_at'     => now(),
            'notes'            => 'Fechamento @ ' . number_format($closeRate, 4, ',', '.') .
                                  ($notes ? ' — ' . $notes : ''),
        ]);
    }

    /**
     * Consome USD do caixa (FIFO priorizando lotes do cliente) para entregar diretamente
     * no fechamento. Cria UM lote 'profit' pelo lucro USD residual e retorna detalhes.
     *
     * Não cria TreasurySale nem mexe em saldos do cliente — quem chama (fechamento)
     * fica responsável por debitar BRL e creditar USD na carteira do cliente.
     *
     * @return array{custo_brl: float, pnl_brl: float, pnl_usd: float, shortfall_usd: float, shortfall_lot: ?TreasuryLot}
     */
    public function deliverFromCash(int $clientId, float $usdAmount, float $sellRate, ?string $notes = null): array
    {
        if ($usdAmount <= 0.00000001) {
            return ['custo_brl' => 0.0, 'pnl_brl' => 0.0, 'pnl_usd' => 0.0, 'shortfall_usd' => 0.0, 'shortfall_lot' => null];
        }
        if ($sellRate <= 0) throw new \InvalidArgumentException('Taxa de venda inválida.');

        $consumo = $this->consumeLots($clientId, $usdAmount, $sellRate);
        $this->bookProfitLot($consumo['pnl_usd'], $sellRate, $clientId, $notes);

        return $consumo;
    }

    /**
     * Reconcilia um lote 'pre_purchase' recém-criado contra lotes 'shortfall'
     * abertos do mesmo cliente (FIFO). O shortfall foi criado quando o dono vendeu
     * USD sem ter caixa — assumindo custo = sellRate (PnL=0). Agora que entrou USD
     * a uma taxa real (rateCompra), calcula o PnL real (sellRate − rateCompra) sobre
     * a parte coberta e propaga para a Transaction USD da venda original
     * (linkada via treasury_lots.transaction_id no shortfall).
     *
     * Não altera o saldo NET do caixa: shortfall (negativo) + pre_purchase (positivo)
     * já se cancelam. Aqui apenas marcamos o shortfall como 'closed' (consumido) e
     * descontamos a mesma quantidade do pre_purchase, de modo que a fatia do
     * pre_purchase usada para tampar o rombo não fique disponível para vendas futuras.
     */
    public function reconcileShortfall(TreasuryLot $newLot): void
    {
        if ($newLot->source !== 'pre_purchase') return;
        if ((float) $newLot->usd_remaining <= 0.00000001) return;

        $shortfalls = TreasuryLot::query()
            ->where('source', 'shortfall')
            ->where('client_id', $newLot->client_id)
            ->where('usd_remaining', '<', 0)
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($shortfalls->isEmpty()) return;

        $disponivel = (float) $newLot->usd_remaining;
        $rateCompra = (float) $newLot->cost_rate;

        foreach ($shortfalls as $sf) {
            if ($disponivel <= 0.00000001) break;

            $deficit = -1 * (float) $sf->usd_remaining; // positivo
            if ($deficit <= 0.00000001) continue;

            $cobrir = min($disponivel, $deficit);
            $sellRate = (float) $sf->cost_rate; // foi gravado como sellRate na criação
            $pnlBrlExtra = round($cobrir * ($sellRate - $rateCompra), 8);
            $pnlUsdExtra = $sellRate > 0 ? round($pnlBrlExtra / $sellRate, 8) : 0.0;

            // Atualiza shortfall: aumenta usd_remaining (vai em direção a 0), grava PnL.
            $sf->usd_remaining = round((float) $sf->usd_remaining + $cobrir, 8);
            $sf->realized_pnl_brl = round((float) $sf->realized_pnl_brl + $pnlBrlExtra, 8);
            if ($sf->usd_remaining >= -0.00000001) {
                $sf->usd_remaining = 0;
                $sf->status = 'closed';
            } else {
                $sf->status = 'partial';
            }
            $sf->save();

            // Desconta do pre_purchase a mesma fatia (USD usado para cobrir o rombo
            // não está disponível pra venda futura — o saldo NET continua coerente).
            $newLot->usd_remaining = round((float) $newLot->usd_remaining - $cobrir, 8);
            if ($newLot->usd_remaining <= 0.00000001) {
                $newLot->usd_remaining = 0;
                $newLot->status = 'closed';
            } else {
                $newLot->status = 'partial';
            }
            $newLot->save();

            // Propaga PnL pra Transaction USD da venda original.
            if ($sf->transaction_id && abs($pnlBrlExtra) > 0.00000001) {
                $tx = Transaction::find($sf->transaction_id);
                if ($tx) {
                    $tx->realized_pnl_brl = round((float) ($tx->realized_pnl_brl ?? 0) + $pnlBrlExtra, 2);
                    $tx->realized_pnl_usd = round((float) ($tx->realized_pnl_usd ?? 0) + $pnlUsdExtra, 4);
                    $tx->save();
                }
            }

            // Espelha o lucro USD no caixa como lote 'profit' (mesmo padrão de bookProfitLot).
            if ($pnlUsdExtra > 0.00000001) {
                $this->bookProfitLot($pnlUsdExtra, $sellRate, $newLot->client_id,
                    'Reconciliação shortfall @ venda ' . number_format($sellRate, 4, ',', '.') .
                    ' / compra ' . number_format($rateCompra, 4, ',', '.'));
            }

            $disponivel -= $cobrir;
        }
    }

    /**
     * Venda avulsa pelo dashboard de caixa (cria USD entrada + BRL saída no cliente).
     */
    public function sellToClient(int $clientId, float $usdAmount, float $sellRate, ?string $notes = null): TreasurySale
    {
        if ($usdAmount <= 0) throw new \InvalidArgumentException('USD deve ser maior que zero.');
        if ($sellRate <= 0)  throw new \InvalidArgumentException('Taxa de venda inválida.');

        return DB::transaction(function () use ($clientId, $usdAmount, $sellRate, $notes) {
            $consumo  = $this->consumeLots($clientId, $usdAmount, $sellRate);
            $brlTotal = round($usdAmount * $sellRate, 2);

            $this->walletService->updateBalance($clientId, 'USD', $usdAmount);
            $this->walletService->updateBalance($clientId, 'BRL', -$brlTotal);

            $sale = TreasurySale::create([
                'client_id'        => $clientId,
                'created_by'       => Auth::id(),
                'usd_amount'       => $usdAmount,
                'sell_rate'        => $sellRate,
                'brl_total'        => $brlTotal,
                'cost_brl'         => $consumo['custo_brl'],
                'realized_pnl_brl' => $consumo['pnl_brl'],
                'realized_pnl_usd' => $consumo['pnl_usd'],
                'notes'            => $notes,
                'transaction_ids'  => null,
            ]);

            // Lucro em USD entra como novo lote no caixa da empresa.
            $this->bookProfitLot($consumo['pnl_usd'], $sellRate, $clientId, $notes);

            $usdTx = Transaction::create([
                'client_id'        => $clientId,
                'type'             => 'deposit',
                'currency'         => 'USD',
                'amount'           => $usdAmount,
                'exchange_rate'    => $sellRate,
                'realized_pnl_brl' => $consumo['pnl_brl'],
                'realized_pnl_usd' => $consumo['pnl_usd'],
                'treasury_sale_id' => $sale->id,
                'description'      => 'Venda do caixa: US$ ' . number_format($usdAmount, 2, ',', '.') .
                                      ' @ ' . number_format($sellRate, 4, ',', '.') .
                                      ($notes ? ' — ' . $notes : ''),
                'status'           => 'finalizado',
            ]);

            $brlTx = Transaction::create([
                'client_id'        => $clientId,
                'type'             => 'withdraw',
                'currency'         => 'BRL',
                'amount'           => -$brlTotal,
                'exchange_rate'    => $sellRate,
                'treasury_sale_id' => $sale->id,
                'description'      => 'Pagamento da venda do caixa (US$ ' . number_format($usdAmount, 2, ',', '.') . ')',
                'status'           => 'finalizado',
            ]);

            $sale->transaction_ids = [$usdTx->id, $brlTx->id];
            $sale->save();

            return $sale;
        });
    }

    /**
     * Finaliza transações USD em 'aguardando_venda' geradas pelo fechamento de dólar.
     * Não mexe em BRL (já foi debitado no fechamento).
     *
     * @param  int[]  $transactionIds
     */
    public function sellPendingTransactions(int $clientId, array $transactionIds, float $sellRate, ?string $notes = null): TreasurySale
    {
        if ($sellRate <= 0) throw new \InvalidArgumentException('Taxa de venda inválida.');
        if (empty($transactionIds)) throw new \InvalidArgumentException('Selecione ao menos uma transação para vender.');

        return DB::transaction(function () use ($clientId, $transactionIds, $sellRate, $notes) {
            $txs = Transaction::query()
                ->whereIn('id', $transactionIds)
                ->where('client_id', $clientId)
                ->where('currency', 'USD')
                ->where('amount', '>', 0)
                ->where('status', 'aguardando_venda')
                ->lockForUpdate()
                ->get();

            if ($txs->isEmpty()) {
                throw new \RuntimeException('Nenhuma transação USD em "aguardando venda" foi encontrada na seleção.');
            }

            $usdTotal = round((float) $txs->sum('amount'), 8);
            $consumo  = $this->consumeLots($clientId, $usdTotal, $sellRate);

            $brlTotal = round($usdTotal * $sellRate, 2);
            $pnlTotal = $consumo['pnl_brl'];

            $sale = TreasurySale::create([
                'client_id'        => $clientId,
                'created_by'       => Auth::id(),
                'usd_amount'       => $usdTotal,
                'sell_rate'        => $sellRate,
                'brl_total'        => $brlTotal,
                'cost_brl'         => $consumo['custo_brl'],
                'realized_pnl_brl' => $pnlTotal,
                'realized_pnl_usd' => $consumo['pnl_usd'],
                'notes'            => $notes,
                'transaction_ids'  => null,
            ]);

            // Lucro em USD entra como novo lote no caixa da empresa.
            $this->bookProfitLot($consumo['pnl_usd'], $sellRate, $clientId, $notes);

            $finalizedIds = [];
            $pnlUsdTotal = $consumo['pnl_usd'];
            foreach ($txs as $tx) {
                $share = $usdTotal > 0 ? ((float) $tx->amount / $usdTotal) : 0.0;
                $pnlShareBrl = round($pnlTotal * $share, 8);
                $pnlShareUsd = round($pnlUsdTotal * $share, 8);

                $tx->status           = 'finalizado';
                $tx->exchange_rate    = $sellRate;
                $tx->realized_pnl_brl = $pnlShareBrl;
                $tx->realized_pnl_usd = $pnlShareUsd;
                $tx->treasury_sale_id = $sale->id;
                $tx->save();

                $finalizedIds[] = $tx->id;
            }

            // Entrega o USD na carteira do cliente.
            $this->walletService->updateBalance($clientId, 'USD', $usdTotal);

            $sale->transaction_ids = $finalizedIds;
            $sale->save();

            return $sale;
        });
    }
}
