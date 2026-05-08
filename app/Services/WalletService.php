<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletPrePurchase;
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

    /**
     * Resumo das pré-compras em aberto para um cliente.
     * - usd_pre_comprado: total de USD ainda não entregue ao cliente.
     * - brl_em_aberto: total de R$ que o dono "deve" ao cliente em aberto.
     * - pnl_realizado: PnL acumulado já liquidado (lotes parciais ou fechados).
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
        $pnl = (float) WalletPrePurchase::query()
            ->where('client_id', $clientId)
            ->sum('realized_pnl_brl');

        $taxaMedia = $usd > 0 ? round($brl / $usd, 6) : null;

        return [
            'usd_pre_comprado' => round($usd, 2),
            'brl_em_aberto'    => round($brl, 2),
            'pnl_realizado'    => round($pnl, 2),
            'taxa_media'       => $taxaMedia,
            'has_open'         => $rows->isNotEmpty(),
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
        ?string $notes = null
    ): array {
        if ($rate <= 0) {
            throw new \InvalidArgumentException('Taxa inválida.');
        }
        if ($brlAmount <= 0) {
            throw new \InvalidArgumentException('Valor em R$ inválido.');
        }

        return DB::transaction(function () use ($clientId, $brlAmount, $rate, $notes) {
            $deposits = $this->openBrlDepositsQuery($clientId)
                ->lockForUpdate()
                ->get();

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
                    'realized_pnl_brl'      => 0,
                    'status'                => 'open',
                    'notes'                 => $notes,
                ]);

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
     * Calcula PnL em R$ usando a taxa do cliente (cliente_rate). Atualiza brl_pre_purchased do depósito.
     *
     * @return array{usd_consumido_lotes: float, brl_consumido_lotes: float, pnl_brl: float}
     */
    public function consumePrePurchasesOnClose(Transaction $deposit, float $brlBeingConsumed, float $clienteRate): array
    {
        $brlPre = (float) $deposit->brl_pre_purchased;
        if ($brlPre <= 0.005 || $brlBeingConsumed <= 0.005) {
            return ['usd_consumido_lotes' => 0.0, 'brl_consumido_lotes' => 0.0, 'pnl_brl' => 0.0];
        }

        // Quanto da operação atual atinge a parte pré-comprada do depósito.
        $brlParaLotes = round(min($brlBeingConsumed, $brlPre), 2);
        if ($brlParaLotes <= 0.005) {
            return ['usd_consumido_lotes' => 0.0, 'brl_consumido_lotes' => 0.0, 'pnl_brl' => 0.0];
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
        $pnlTotal = 0.0;

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
            $usdParteLote = $rateLote > 0 ? round($consumir / $rateLote, 2) : 0.0;

            // PnL_R$ = brl_lote × (cliente_rate − rate_lote) / rate_lote
            $pnl = $rateLote > 0
                ? round($consumir * ($clienteRate - $rateLote) / $rateLote, 2)
                : 0.0;

            $lote->brl_remaining = round((float) $lote->brl_remaining - $consumir, 2);
            $lote->usd_remaining = round((float) $lote->usd_remaining - $usdParteLote, 2);
            $lote->realized_pnl_brl = round((float) $lote->realized_pnl_brl + $pnl, 2);

            if ($lote->brl_remaining <= 0.005) {
                $lote->brl_remaining = 0;
                $lote->usd_remaining = 0;
                $lote->status = 'closed';
            } else {
                $lote->status = 'partial';
            }
            $lote->save();

            $usdConsumidoTotal += $usdParteLote;
            $pnlTotal += $pnl;
            $restante = round($restante - $consumir, 2);
        }

        // Diminui brl_pre_purchased do depósito-fonte
        $deposit->brl_pre_purchased = round(max(0.0, (float) $deposit->brl_pre_purchased - $brlParaLotes), 2);
        $deposit->save();

        return [
            'usd_consumido_lotes' => round($usdConsumidoTotal, 2),
            'brl_consumido_lotes' => $brlParaLotes,
            'pnl_brl'             => round($pnlTotal, 2),
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
}
