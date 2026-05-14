<?php

namespace App\Services;

use App\Models\DepositRollbackLog;
use App\Models\Transaction;
use App\Models\TreasuryLot;
use App\Models\Wallet;
use App\Models\WalletPrePurchase;
use App\Models\WalletPreSell;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Atualiza o saldo da carteira do cliente para a moeda informada.
     * Cria a carteira se não existir.
     */
    public function updateBalance(int $clientId, string $currency, float $amount): Wallet
    {
        return DB::transaction(function () use ($clientId, $currency, $amount) {
            $normalizedCurrency = strtoupper(trim($currency));

            $wallet = Wallet::firstOrCreate([
                'client_id' => $clientId,
                'currency' => $normalizedCurrency,
            ]);

            $currentBalance = (float) ($wallet->balance ?? 0);
            $wallet->balance = $currentBalance + $amount;
            $wallet->save();

            return $wallet;
        });
    }

    protected function computeBrlBalanceFromLedger(int $clientId): float
    {
        $transactions = Transaction::query()
            ->where('client_id', $clientId)
            ->where('currency', 'BRL')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $computedBalance = 0.0;

        foreach ($transactions as $transaction) {
            $amount = round((float) $transaction->amount, 2);

            if ($transaction->type === 'withdraw') {
                $computedBalance -= abs($amount);
                continue;
            }

            if ($transaction->type === 'exchange_out') {
                $computedBalance -= abs($amount);
                continue;
            }

            if ($transaction->type === 'exchange_in') {
                $computedBalance += abs($amount);
                continue;
            }

            if ($transaction->type === 'deposit') {
                if (in_array($transaction->status, ['fechado', 'finalizado'], true)) {
                    continue;
                }

                $computedBalance += abs($amount);
                continue;
            }

            $computedBalance += $amount;
        }

        return round($computedBalance, 2);
    }

    protected function computeUsdBalanceFromLedger(int $clientId): float
    {
        return round((float) Transaction::query()
            ->where('client_id', $clientId)
            ->where('currency', 'USD')
            ->sum('amount'), 2);
    }

    /**
     * Recalcula o saldo BRL do cliente a partir do ledger atual.
     *
     * Regra aplicada:
     * - depósitos BRL abertos somam;
     * - depósitos BRL finalizados/fechados não entram;
     * - saídas BRL (withdraw/exchange_out) subtraem;
     * - entradas BRL em exchange_in somam.
     */
    public function recalculateBrlBalance(int $clientId, bool $dryRun = false): array
    {
        return DB::transaction(function () use ($clientId, $dryRun) {
            $wallet = Wallet::firstOrCreate([
                'client_id' => $clientId,
                'currency' => 'BRL',
            ]);

            $computedBalance = $this->computeBrlBalanceFromLedger($clientId);
            $currentBalance = round((float) $wallet->balance, 2);
            $difference = round($computedBalance - $currentBalance, 2);

            if (!$dryRun) {
                $wallet->balance = $computedBalance;
                $wallet->save();
            }

            return [
                'client_id' => $clientId,
                'currency' => 'BRL',
                'current_balance' => $currentBalance,
                'computed_balance' => $computedBalance,
                'difference' => $difference,
                'mode' => $dryRun ? 'dry-run' : 'applied',
            ];
        });
    }

    public function recalculateUsdBalance(int $clientId, bool $dryRun = false): array
    {
        return DB::transaction(function () use ($clientId, $dryRun) {
            $wallet = Wallet::firstOrCreate([
                'client_id' => $clientId,
                'currency' => 'USD',
            ]);

            $computedBalance = $this->computeUsdBalanceFromLedger($clientId);
            $currentBalance = round((float) $wallet->balance, 2);
            $difference = round($computedBalance - $currentBalance, 2);

            if (!$dryRun) {
                $wallet->balance = $computedBalance;
                $wallet->save();
            }

            return [
                'client_id' => $clientId,
                'currency' => 'USD',
                'current_balance' => $currentBalance,
                'computed_balance' => $computedBalance,
                'difference' => $difference,
                'mode' => $dryRun ? 'dry-run' : 'applied',
            ];
        });
    }

    public function rollbackDeposit(int $depositId, ?int $deletedBy = null, ?string $reason = null, bool $dryRun = false): array
    {
        return DB::transaction(function () use ($depositId, $deletedBy, $reason, $dryRun) {
            $deposit = Transaction::query()
                ->whereKey($depositId)
                ->where('type', 'deposit')
                ->where('currency', 'BRL')
                ->where('amount', '>', 0)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($deposit->status, ['fechado', 'finalizado'], true)) {
                throw new \RuntimeException('Depósito já finalizado/fechado. Use rollback específico da operação fechada.');
            }

            $hasChildren = Transaction::query()
                ->where('parent_transaction_id', $deposit->id)
                ->exists();

            if ($hasChildren) {
                throw new \RuntimeException('Depósito possui transações derivadas (split/fechamento). Não é seguro apagar automaticamente.');
            }

            $preSells = WalletPreSell::query()
                ->where('source_transaction_id', $deposit->id)
                ->lockForUpdate()
                ->get();

            if ($preSells->isNotEmpty()) {
                throw new \RuntimeException('Depósito possui pré-venda vinculada. Desfaça a pré-venda antes do rollback do depósito.');
            }

            $prePurchases = WalletPrePurchase::query()
                ->where('source_transaction_id', $deposit->id)
                ->lockForUpdate()
                ->get();

            $blockedPrePurchase = $prePurchases->first(function (WalletPrePurchase $lot) {
                return !in_array($lot->status, ['open'], true)
                    || (float) $lot->brl_remaining < (float) $lot->brl_amount - 0.005
                    || (float) $lot->usd_remaining < (float) $lot->usd_amount - 0.0001;
            });

            if ($blockedPrePurchase) {
                throw new \RuntimeException('Depósito possui pré-compra já consumida/fechada. Faça o undo da pré-compra antes.');
            }

            $cashLots = TreasuryLot::query()
                ->whereIn('pre_purchase_id', $prePurchases->pluck('id')->all())
                ->lockForUpdate()
                ->get();

            $summary = [
                'deposit_id' => (int) $deposit->id,
                'client_id' => (int) $deposit->client_id,
                'amount_brl' => round((float) $deposit->amount, 2),
                'status' => (string) $deposit->status,
                'pre_purchase_lots' => $prePurchases->count(),
                'pre_purchase_ids' => $prePurchases->pluck('id')->values()->all(),
                'cash_lots' => $cashLots->count(),
                'cash_lot_ids' => $cashLots->pluck('id')->values()->all(),
                'deleted_by' => $deletedBy,
                'reason' => $reason,
                'dry_run' => $dryRun,
            ];

            if ($dryRun) {
                return $summary;
            }

            foreach ($cashLots as $lot) {
                $lot->delete();
            }

            foreach ($prePurchases as $lot) {
                $lot->delete();
            }

            $deposit->delete();

            DepositRollbackLog::create([
                'deposit_transaction_id' => $deposit->id,
                'client_id' => $deposit->client_id,
                'deleted_by' => $deletedBy,
                'reason' => $reason,
                'payload' => $summary,
            ]);

            $brlWallet = Wallet::firstOrCreate([
                'client_id' => (int) $deposit->client_id,
                'currency' => 'BRL',
            ]);
            $usdWallet = Wallet::firstOrCreate([
                'client_id' => (int) $deposit->client_id,
                'currency' => 'USD',
            ]);

            $brlWallet->balance = $this->computeBrlBalanceFromLedger((int) $deposit->client_id);
            $usdWallet->balance = $this->computeUsdBalanceFromLedger((int) $deposit->client_id);
            $brlWallet->save();
            $usdWallet->save();

            $summary['wallet_brl_after'] = round((float) $brlWallet->balance, 2);
            $summary['wallet_usd_after'] = round((float) $usdWallet->balance, 2);

            return $summary;
        });
    }

    /**
     * Resumo das pré-compras em aberto para um cliente.
     * - usd_pre_comprado: total de USD ainda não entregue ao cliente.
     * - brl_em_aberto: total de R$ que o dono "deve" ao cliente em aberto.
     * - pnl_realizado_usd: PnL acumulado já liquidado (em USD — é o ganho efetivo do dono).
     * - taxa_media: taxa média ponderada das pré-compras em aberto.
     */
    public function prePurchaseSummary(int $clientId): array
    {
        $rows = WalletPrePurchase::query()
            ->where('client_id', $clientId)
            ->whereIn('status', ['open', 'partial'])
            ->get();

        $usd = (float) $rows->sum('usd_remaining');
        $brl = (float) $rows->sum('brl_remaining');

        // PnL realizado em USD: lê das transações USD finalizadas (mesma fonte do
        // dashboard geral). O campo `realized_pnl_usd` é gravado no
        // TreasuryService::consumeLots como `pnl_brl / sell_rate`, ou seja, é o
        // ganho líquido convertido em dólares na taxa da venda — número fiel.
        // Antes liamos de wallet_pre_purchases.realized_pnl_usd, que era apenas a
        // diferença "usdComprado - usdVendido" calculada em finalizeDepositIfCovered
        // e divergia do PnL real.
        $pnlUsd = (float) Transaction::query()
            ->where('client_id', $clientId)
            ->whereNotNull('realized_pnl_usd')
            ->sum('realized_pnl_usd');

        $taxaMedia = $usd > 0 ? round($brl / $usd, 6) : null;

        return [
            'usd_pre_comprado'  => round($usd, 2),
            'brl_em_aberto'     => round($brl, 2),
            'pnl_realizado_usd' => round($pnlUsd, 4),
            'taxa_media'        => $taxaMedia,
            'has_open'          => $rows->isNotEmpty(),
        ];
    }

    /**
     * Disponível em BRL para pré-compra: soma dos depósitos BRL abertos do cliente
     * descontando o que já está pré-comprado em cada um.
     */
    public function brlAvailableForPrePurchase(int $clientId): float
    {
        $deposits = $this->openBrlDepositsQuery($clientId)->get();
        $total = 0.0;
        foreach ($deposits as $tx) {
            $total += max(0.0, (float) $tx->amount - (float) $tx->brl_pre_purchased);
        }
        return round($total, 2);
    }

    /**
     * Pré-compra: o dono compra USD usando R$ que estão em depósitos BRL abertos do cliente.
     * NÃO altera saldos da carteira nem cria transações de exchange.
     * Apenas reserva o R$ no(s) depósito(s) e cria lote(s) em wallet_pre_purchases.
     *
     * @return array<int, WalletPrePurchase>
     */
    public function prePurchaseDollar(
        int $clientId,
        float $brlAmount,
        float $rate,
        ?string $notes = null,
        ?array $transactionIds = null
    ): array {
        if ($rate <= 0) {
            throw new \InvalidArgumentException('Taxa inválida.');
        }
        if ($brlAmount <= 0) {
            throw new \InvalidArgumentException('Valor em R$ inválido.');
        }

        return DB::transaction(function () use ($clientId, $brlAmount, $rate, $notes, $transactionIds) {
            $query = $this->openBrlDepositsQuery($clientId);

            // Se o front passou IDs selecionados, restringe o pool somente a esses depósitos.
            // Mantém a ordem FIFO entre os selecionados (consumir o mais antigo primeiro
            // ainda é desejável para alinhar a história do cliente).
            if (is_array($transactionIds) && count($transactionIds) > 0) {
                $query->whereIn('id', $transactionIds);
            }

            $deposits = $query->lockForUpdate()->get();

            $disponivelTotal = 0.0;
            foreach ($deposits as $tx) {
                $disponivelTotal += max(0.0, (float) $tx->amount - (float) $tx->brl_pre_purchased);
            }
            $disponivelTotal = round($disponivelTotal, 2);

            if ($brlAmount > $disponivelTotal + 0.005) {
                throw new \RuntimeException(
                    'Valor solicitado (R$ ' . number_format($brlAmount, 2, ',', '.') .
                    ') excede o disponível para pré-compra (R$ ' .
                    number_format($disponivelTotal, 2, ',', '.') . ').'
                );
            }

            $remaining = round($brlAmount, 2);
            $lotes = [];

            foreach ($deposits as $tx) {
                if ($remaining <= 0.005) {
                    break;
                }

                $livre = round((float) $tx->amount - (float) $tx->brl_pre_purchased, 2);
                if ($livre <= 0.005) {
                    continue;
                }

                $consumir = min($livre, $remaining);
                $consumir = round($consumir, 2);
                $usdLote  = round($consumir / $rate, 2);

                $lote = WalletPrePurchase::create([
                    'client_id'             => $clientId,
                    'source_transaction_id' => $tx->id,
                    'created_by'            => Auth::id(),
                    'brl_amount'            => $consumir,
                    'usd_amount'            => $usdLote,
                    'exchange_rate'         => $rate,
                    'brl_remaining'         => $consumir,
                    'usd_remaining'         => $usdLote,
                    'realized_pnl_usd'      => 0,
                    'status'                => 'open',
                    'notes'                 => $notes,
                ]);

                // Espelha o USD comprado no caixa próprio (origem = pré-compra do cliente).
                // Assim o caixa já reflete o USD que está "em estoque" mesmo que ainda não
                // tenha sido entregue ao cliente.
                $cashLot = TreasuryLot::create([
                    'created_by'       => Auth::id(),
                    'source'           => 'pre_purchase',
                    'client_id'        => $clientId,
                    'pre_purchase_id'  => $lote->id,
                    'usd_amount'       => $usdLote,
                    'cost_rate'        => $rate,
                    'brl_cost'         => $consumir,
                    'usd_remaining'    => $usdLote,
                    'realized_pnl_brl' => 0,
                    'status'           => 'open',
                    'purchased_at'     => now(),
                    'notes'            => $notes,
                ]);

                // Se o cliente tem lotes 'shortfall' abertos (vendeu antes de comprar),
                // este USD recém-entrado cobre o rombo: calcula PnL real (sellRate − rateCompra)
                // e propaga para a Transaction USD da venda original.
                app(TreasuryService::class)->reconcileShortfall($cashLot);

                $tx->brl_pre_purchased = round((float) $tx->brl_pre_purchased + $consumir, 2);
                $tx->save();

                $lotes[] = $lote;
                $remaining = round($remaining - $consumir, 2);
            }

            return $lotes;
        });
    }

    /**
     * Liquida (consome) lotes de pré-compra de um depósito BRL específico durante um fechamento real.
     * Calcula PnL em USD (= ganho efetivo do dono em dólares).
     *
     *   Exemplo: lote comprado a 5,00 (10 R$ → 2 USD). Cliente fecha a 5,20:
     *     usd_que_o_cliente_levaria = 10 / 5,20 = 1,9231
     *     usd_originalmente_comprado = 10 / 5,00 = 2,0000
     *     pnl_usd = 2,0000 - 1,9231 = 0,0769 (entra no caixa do dono)
     *
     *   Equivalente fechado: pnl_usd = brl_lote * (1/rate_lote - 1/cliente_rate)
     *
     * @return array{usd_consumido_lotes: float, brl_consumido_lotes: float, pnl_usd: float}
     */
    public function consumePrePurchasesOnClose(Transaction $deposit, float $brlBeingConsumed, float $clienteRate): array
    {
        $brlPre = (float) $deposit->brl_pre_purchased;
        if ($brlPre <= 0.005 || $brlBeingConsumed <= 0.005) {
            return ['usd_consumido_lotes' => 0.0, 'brl_consumido_lotes' => 0.0, 'pnl_usd' => 0.0];
        }

        // Quanto da operação atual atinge a parte pré-comprada do depósito.
        $brlParaLotes = round(min($brlBeingConsumed, $brlPre), 2);
        if ($brlParaLotes <= 0.005) {
            return ['usd_consumido_lotes' => 0.0, 'brl_consumido_lotes' => 0.0, 'pnl_usd' => 0.0];
        }

        $lotes = WalletPrePurchase::query()
            ->where('source_transaction_id', $deposit->id)
            ->whereIn('status', ['open', 'partial'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $restante = $brlParaLotes;
        $usdConsumidoTotal = 0.0;
        $pnlUsdTotal = 0.0;

        foreach ($lotes as $lote) {
            if ($restante <= 0.005) {
                break;
            }

            $brlLote = (float) $lote->brl_remaining;
            if ($brlLote <= 0.005) {
                continue;
            }

            $consumir = min($brlLote, $restante);
            $consumir = round($consumir, 2);

            $rateLote = (float) $lote->exchange_rate;
            $usdParteLote = $rateLote > 0 ? ($consumir / $rateLote) : 0.0;

            // PnL agora é calculado APENAS na venda do caixa (TreasuryService::sellPendingTransactions),
            // pois o fechamento em dólar não entrega o USD ao cliente — ele fica em "aguardando_venda".
            $pnlUsd = 0.0;

            $lote->brl_remaining = round((float) $lote->brl_remaining - $consumir, 2);
            $lote->usd_remaining = round((float) $lote->usd_remaining - $usdParteLote, 4);

            if ($lote->brl_remaining <= 0.005) {
                $lote->brl_remaining = 0;
                $lote->usd_remaining = 0;
                $lote->status = 'closed';
            } else {
                $lote->status = 'partial';
            }
            $lote->save();

            $usdConsumidoTotal += $usdParteLote;
            $pnlUsdTotal += $pnlUsd;
            $restante = round($restante - $consumir, 2);
        }

        // Diminui brl_pre_purchased do depósito-fonte
        $deposit->brl_pre_purchased = round(max(0.0, (float) $deposit->brl_pre_purchased - $brlParaLotes), 2);
        $deposit->save();

        return [
            'usd_consumido_lotes' => round($usdConsumidoTotal, 4),
            'brl_consumido_lotes' => $brlParaLotes,
            'pnl_usd'             => round($pnlUsdTotal, 8),
        ];
    }

    /**
     * Query base: depósitos BRL abertos (não finalizados/fechados) de um cliente, ordenados FIFO.
     */
    public function openBrlDepositsQuery(int $clientId)
    {
        return Transaction::query()
            ->where('client_id', $clientId)
            ->where('type', 'deposit')
            ->where('currency', 'BRL')
            ->where('amount', '>', 0)
            ->whereNotIn('status', ['fechado', 'finalizado'])
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Finaliza o depósito BRL quando a PRÉ-VENDA já cobre todo o valor do depósito
     * (a venda equivale ao fechamento — o USD já foi entregue ao cliente).
     * Quando finaliza:
     *   - debita o R$ do cliente (sai a "casca" do depósito da carteira),
     *   - grava no depósito a taxa média de venda (visão do cliente),
     *   - marca status = 'finalizado'.
     *
     * A pré-compra é apenas controle interno do dono (custo) e não bloqueia o fechamento.
     *
     * Retorna true se finalizou nesta chamada, false caso contrário.
     */
    public function finalizeDepositIfCovered(Transaction $deposit): bool
    {
        return DB::transaction(function () use ($deposit) {
            $deposit->refresh();

            if (in_array($deposit->status, ['fechado', 'finalizado'], true)) {
                return false;
            }

            $amount  = round((float) $deposit->amount, 2);
            $brlPre  = round((float) ($deposit->brl_pre_purchased ?? 0), 2);
            $brlSold = round((float) ($deposit->brl_pre_sold ?? 0), 2);

            // Só finaliza quando COMPRA e VENDA já cobrem todo o depósito.
            if ($brlPre + 0.005 < $amount || $brlSold + 0.005 < $amount) {
                return false;
            }

            // Taxa média de VENDA (visão do cliente) sobre TODOS os lotes de pré-venda deste depósito.
            $sells  = WalletPreSell::where('source_transaction_id', $deposit->id)->get();
            $brlSum = (float) $sells->sum('brl_amount');
            $usdSum = (float) $sells->sum('usd_amount');
            $taxa   = $usdSum > 0 ? round($brlSum / $usdSum, 6) : null;

            $deposit->status = 'finalizado';
            if ($taxa) {
                $deposit->exchange_rate      = $taxa;
                $deposit->converted_currency = 'USD';
                $deposit->converted_amount   = round($usdSum, 2);
            }
            $deposit->save();

            // Liquida (fecha) os lotes de PRÉ-COMPRA deste depósito.
            // O USD pré-comprado pelo dono já foi efetivamente entregue ao cliente
            // via venda — então não há mais "deve ao cliente" pendente nem USD em aberto.
            // PnL USD por lote = USD pré-comprado − USD vendido (proporcional ao R$ do lote).
            $purchases = WalletPrePurchase::where('source_transaction_id', $deposit->id)
                ->whereIn('status', ['open', 'partial'])
                ->get();

            $taxaVendaMedia = $taxa ?: 0.0;

            // Mapa: transaction_id da venda → R$ vendido nesse lote (para distribuir PnL).
            $brlPorVendaTx = [];
            foreach ($sells as $s) {
                $tid = (int) ($s->transaction_id ?? 0);
                if ($tid <= 0) continue;
                $brlPorVendaTx[$tid] = round(($brlPorVendaTx[$tid] ?? 0.0) + (float) $s->brl_amount, 2);
            }
            $brlVendaTotal = array_sum($brlPorVendaTx);

            foreach ($purchases as $lote) {
                $brlRem = (float) $lote->brl_remaining;
                if ($brlRem <= 0.005) {
                    $lote->brl_remaining = 0;
                    $lote->usd_remaining = 0;
                    $lote->status        = 'closed';
                    $lote->save();
                    continue;
                }

                $rateCompra = (float) $lote->exchange_rate;
                $usdComprado = $rateCompra > 0 ? round($brlRem / $rateCompra, 4) : 0.0;
                $usdVendido  = $taxaVendaMedia > 0 ? round($brlRem / $taxaVendaMedia, 4) : $usdComprado;
                $pnlUsdLote  = round($usdComprado - $usdVendido, 8);
                $pnlBrlLote  = round($pnlUsdLote * $taxaVendaMedia, 8);

                $lote->brl_remaining   = 0;
                $lote->usd_remaining   = 0;
                $lote->realized_pnl_usd = round((float) $lote->realized_pnl_usd + $pnlUsdLote, 8);
                $lote->status          = 'closed';
                $lote->save();

                // Propaga PnL para a(s) Transaction(s) USD da venda, proporcional ao
                // R$ vendido em cada uma. Sem isso, prePurchaseSummary (que lê de
                // transactions.realized_pnl_usd) não enxerga o ganho da pré-compra.
                if ($brlVendaTotal > 0.005 && abs($pnlUsdLote) > 0.00000001) {
                    $usdRestante = $pnlUsdLote;
                    $brlRestante = $pnlBrlLote;
                    $items = $brlPorVendaTx;
                    $i = 0; $n = count($items);
                    foreach ($items as $tid => $brlVenda) {
                        $i++;
                        if ($i === $n) {
                            $usdShare = round($usdRestante, 4);
                            $brlShare = round($brlRestante, 2);
                        } else {
                            $frac = $brlVenda / $brlVendaTotal;
                            $usdShare = round($pnlUsdLote * $frac, 4);
                            $brlShare = round($pnlBrlLote * $frac, 2);
                            $usdRestante -= $usdShare;
                            $brlRestante -= $brlShare;
                        }
                        $tx = Transaction::find($tid);
                        if ($tx) {
                            $tx->realized_pnl_usd = round((float) ($tx->realized_pnl_usd ?? 0) + $usdShare, 4);
                            $tx->realized_pnl_brl = round((float) ($tx->realized_pnl_brl ?? 0) + $brlShare, 2);
                            $tx->save();
                        }
                    }
                }
            }

            // Zera os marcadores no depósito (já está finalizado, mas mantém consistente).
            $deposit->brl_pre_purchased = 0;
            $deposit->brl_pre_sold      = 0;
            $deposit->save();

            // Debita o R$ do cliente — a "casca" do depósito sai da carteira.
            $this->updateBalance((int) $deposit->client_id, 'BRL', -$amount);

            return true;
        });
    }

    // ==========================================================================
    //  PRÉ-VENDA — espelha a pré-compra mas representa o lado da venda ao cliente.
    // ==========================================================================

    /**
     * Resumo das pré-vendas em aberto.
     */
    public function preSellSummary(int $clientId): array
    {
        $rows = WalletPreSell::query()
            ->where('client_id', $clientId)
            ->whereIn('status', ['open', 'partial'])
            ->get();

        $usd = (float) $rows->sum('usd_remaining');
        $brl = (float) $rows->sum('brl_remaining');
        $taxa = $usd > 0 ? round($brl / $usd, 6) : null;

        return [
            'usd_pre_vendido' => round($usd, 2),
            'brl_em_aberto'   => round($brl, 2),
            'taxa_media'      => $taxa,
            'has_open'        => $rows->isNotEmpty(),
        ];
    }

    /**
     * Disponível em BRL para pré-venda: depósitos BRL abertos descontando o que
     * já está pré-vendido em cada um.
     */
    public function brlAvailableForPreSell(int $clientId): float
    {
        $deposits = $this->openBrlDepositsQuery($clientId)->get();
        $total = 0.0;
        foreach ($deposits as $tx) {
            $total += max(0.0, (float) $tx->amount - (float) ($tx->brl_pre_sold ?? 0));
        }
        return round($total, 2);
    }

    /**
     * Pré-venda: o dono "fixa" a taxa que vai cobrar do cliente sobre R$ de depósitos
     * abertos. Não altera saldo, não cria transação. Apenas reserva o R$ no(s)
     * depósito(s) e cria lote(s) em wallet_pre_sells.
     *
     * @return array<int, WalletPreSell>
     */
    public function preSellDollar(
        int $clientId,
        float $brlAmount,
        float $sellRate,
        ?string $notes = null,
        ?array $transactionIds = null
    ): array {
        $sellRate = round($sellRate, 4);
        if ($sellRate <= 0)  throw new \InvalidArgumentException('Taxa de venda inválida.');
        if ($brlAmount <= 0) throw new \InvalidArgumentException('Valor em R$ inválido.');

        return DB::transaction(function () use ($clientId, $brlAmount, $sellRate, $notes, $transactionIds) {
            $query = $this->openBrlDepositsQuery($clientId);

            if (is_array($transactionIds) && count($transactionIds) > 0) {
                $query->whereIn('id', $transactionIds);
            }

            $deposits = $query->lockForUpdate()->get();

            $disponivelTotal = 0.0;
            foreach ($deposits as $tx) {
                $disponivelTotal += max(0.0, (float) $tx->amount - (float) ($tx->brl_pre_sold ?? 0));
            }
            $disponivelTotal = round($disponivelTotal, 2);

            if ($brlAmount > $disponivelTotal + 0.005) {
                throw new \RuntimeException(
                    'Valor solicitado (R$ ' . number_format($brlAmount, 2, ',', '.') .
                    ') excede o disponível para pré-venda (R$ ' .
                    number_format($disponivelTotal, 2, ',', '.') . ').'
                );
            }

            $remaining = round($brlAmount, 2);
            $lotes = [];

            foreach ($deposits as $tx) {
                if ($remaining <= 0.005) break;

                $livre = round((float) $tx->amount - (float) ($tx->brl_pre_sold ?? 0), 2);
                if ($livre <= 0.005) continue;

                $consumir = min($livre, $remaining);
                $consumir = round($consumir, 2);
                $usdLote  = round($consumir / $sellRate, 4);

                $lote = WalletPreSell::create([
                    'client_id'             => $clientId,
                    'source_transaction_id' => $tx->id,
                    'created_by'            => Auth::id(),
                    'brl_amount'            => $consumir,
                    'usd_amount'            => $usdLote,
                    'sell_rate'             => $sellRate,
                    'brl_remaining'         => $consumir,
                    'usd_remaining'         => $usdLote,
                    'status'                => 'open',
                    'notes'                 => $notes,
                ]);

                $tx->brl_pre_sold = round((float) ($tx->brl_pre_sold ?? 0) + $consumir, 2);
                $tx->save();

                $lotes[] = $lote;
                $remaining = round($remaining - $consumir, 2);
            }

            return $lotes;
        });
    }

    /**
     * Liquida (consome) lotes de pré-venda de um depósito específico durante o fechamento.
     * Retorna USD que deve ser entregue ao cliente.
     *
     * @return array{usd_a_entregar: float, brl_consumido_lotes: float}
     */
    public function consumePreSellsOnClose(Transaction $deposit, float $brlBeingConsumed): array
    {
        $brlPreSold = (float) ($deposit->brl_pre_sold ?? 0);
        if ($brlPreSold <= 0.005 || $brlBeingConsumed <= 0.005) {
            return ['usd_a_entregar' => 0.0, 'brl_consumido_lotes' => 0.0];
        }

        $brlParaLotes = round(min($brlBeingConsumed, $brlPreSold), 2);
        if ($brlParaLotes <= 0.005) {
            return ['usd_a_entregar' => 0.0, 'brl_consumido_lotes' => 0.0];
        }

        $lotes = WalletPreSell::query()
            ->where('source_transaction_id', $deposit->id)
            ->whereIn('status', ['open', 'partial'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $restante = $brlParaLotes;
        $usdEntregar = 0.0;

        foreach ($lotes as $lote) {
            if ($restante <= 0.005) break;

            $brlLote = (float) $lote->brl_remaining;
            if ($brlLote <= 0.005) continue;

            $consumir = round(min($brlLote, $restante), 2);
            $sellRate = (float) $lote->sell_rate;
            $usdParte = $sellRate > 0 ? ($consumir / $sellRate) : 0.0;

            $lote->brl_remaining = round((float) $lote->brl_remaining - $consumir, 2);
            $lote->usd_remaining = round((float) $lote->usd_remaining - $usdParte, 4);

            if ($lote->brl_remaining <= 0.005) {
                $lote->brl_remaining = 0;
                $lote->usd_remaining = 0;
                $lote->status = 'closed';
            } else {
                $lote->status = 'partial';
            }
            $lote->save();

            $usdEntregar += $usdParte;
            $restante = round($restante - $consumir, 2);
        }

        $deposit->brl_pre_sold = round(max(0.0, (float) ($deposit->brl_pre_sold ?? 0) - $brlParaLotes), 2);
        $deposit->save();

        return [
            'usd_a_entregar'      => round($usdEntregar, 4),
            'brl_consumido_lotes' => $brlParaLotes,
        ];
    }
}
