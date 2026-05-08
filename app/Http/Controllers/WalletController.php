<?php

namespace App\Http\Controllers;

use App\Http\Requests\Wallet\DepositRequest;
use App\Http\Requests\Wallet\WithdrawRequest;
use App\Http\Requests\Wallet\ExchangeRequest;
use App\Services\WalletService;
use App\Services\TransactionService;
use App\Services\CurrencyService;
use App\Models\Transaction;
use App\Models\TransactionRateChangeLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;


class WalletController extends Controller
{
    protected $walletService;
    protected $transactionService;
    protected $currencyService;

    public function __construct(WalletService $walletService, TransactionService $transactionService, CurrencyService $currencyService)
    {
        $this->walletService = $walletService;
        $this->transactionService = $transactionService;
        $this->currencyService = $currencyService;
    }

    /**
     * Exibe as carteiras do cliente
     */
    public function index(Request $request)
    {
        $clients = \App\Models\Client::query()
            ->where('is_exchange_client', true)
            ->orderBy('name')
            ->get();

        $clientIds = $clients->pluck('id');

        $walletTotals = \App\Models\Wallet::query()
            ->whereIn('client_id', $clientIds)
            ->select('currency', DB::raw('COALESCE(SUM(balance), 0) as total_balance'))
            ->groupBy('currency')
            ->pluck('total_balance', 'currency');

        $totals = [
            'BRL' => (float) ($walletTotals['BRL'] ?? 0),
            'USD' => (float) ($walletTotals['USD'] ?? 0),
        ];

        $walletsByClient = \App\Models\Wallet::query()
            ->whereIn('client_id', $clientIds)
            ->get()
            ->groupBy('client_id')
            ->map(function ($wallets) {
                return [
                    'BRL' => (float) optional($wallets->firstWhere('currency', 'BRL'))->balance,
                    'USD' => (float) optional($wallets->firstWhere('currency', 'USD'))->balance,
                ];
            });

        // Resumo de pré-compra por cliente (USD pré-comprado, BRL em aberto = devo, PnL realizado).
        $prePurchaseByClient = [];
        foreach ($clientIds as $cid) {
            $prePurchaseByClient[$cid] = $this->walletService->prePurchaseSummary((int) $cid);
        }

        // Totais consolidados.
        $totals['DEVO_BRL'] = 0.0;        // R$ que devo aos clientes (pré-compras em aberto)
        $totals['USD_PRE']  = 0.0;        // USD pré-comprado total
        $totals['PNL']      = 0.0;        // PnL realizado total
        $totals['CLIENTE_DEVE_BRL'] = 0.0; // saldo BRL negativo (cliente está negativo → me deve)
        $totals['CLIENTE_DEVE_USD'] = 0.0; // idem em USD

        foreach ($clientIds as $cid) {
            $totals['DEVO_BRL'] += $prePurchaseByClient[$cid]['brl_em_aberto'] ?? 0;
            $totals['USD_PRE']  += $prePurchaseByClient[$cid]['usd_pre_comprado'] ?? 0;
            $totals['PNL']      += $prePurchaseByClient[$cid]['pnl_realizado'] ?? 0;

            $w = $walletsByClient[$cid] ?? ['BRL' => 0, 'USD' => 0];
            if (($w['BRL'] ?? 0) < 0) $totals['CLIENTE_DEVE_BRL'] += abs($w['BRL']);
            if (($w['USD'] ?? 0) < 0) $totals['CLIENTE_DEVE_USD'] += abs($w['USD']);
        }

        return view('admin.wallet.index', compact(
            'clients',
            'totals',
            'walletsByClient',
            'prePurchaseByClient'
        ));
    }

     /**
     * Exibe as transações do cliente
     */
    public function transactions(Request $request)
    {
        $clientId = $request->get('client_id');
        $transactions = null;
        if ($clientId) {
            $transactions = \App\Models\Transaction::where('client_id', $clientId)->orderByDesc('created_at')->get();
        }
        return view('admin.wallet.transactions', compact('transactions'));
    }

    /**
     * Exibe a carteira do cliente com saldos em BRL e USD e botões de ação
     */
    public function clientWallet(\App\Models\Client $client, Request $request)
    {
        $wallets = $client->wallets()->get()->keyBy('currency');
        $balances = [
            'BRL' => $wallets['BRL']->balance ?? 0,
            'USD' => $wallets['USD']->balance ?? 0,
        ];

        [$dateFrom, $dateTo] = $this->parseDateRange($request);

        $transactions = $client->transactions()
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->orderByDesc('created_at')
            ->get();

        $prePurchaseSummary = $this->walletService->prePurchaseSummary($client->id);
        $brlAvailableForPrePurchase = $this->walletService->brlAvailableForPrePurchase($client->id);

        $prePurchases = \App\Models\WalletPrePurchase::query()
            ->where('client_id', $client->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.wallet.client', compact(
            'client',
            'balances',
            'transactions',
            'prePurchaseSummary',
            'brlAvailableForPrePurchase',
            'prePurchases',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Exporta extrato do cliente em CSV (UTF-8 com BOM, ; como separador) — sem cores.
     * Layout (3 colunas lado a lado):
     *   Saldo Fulano  R$ xx,xx   U$ xxx,xx
     *   Entradas R$ | Saídas U$ | Entradas U$
     */
    public function exportClientCsv(\App\Models\Client $client, Request $request)
    {
        [$dateFrom, $dateTo] = $this->parseDateRange($request);

        $wallets = $client->wallets()->get()->keyBy('currency');
        $brl = (float) ($wallets['BRL']->balance ?? 0);
        $usd = (float) ($wallets['USD']->balance ?? 0);

        $base = $client->transactions()
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->orderBy('created_at');

        $all = $base->get();

        $entradasBrl = $all->filter(fn ($t) => $t->type === 'deposit' && $t->currency === 'BRL' && (float) $t->amount > 0)->values();
        $saidasUsd   = $all->filter(fn ($t) => $t->currency === 'USD' && (float) $t->amount < 0)->values();
        $entradasUsd = $all->filter(fn ($t) => $t->currency === 'USD' && (float) $t->amount > 0)->values();

        $rowsCount = max($entradasBrl->count(), $saidasUsd->count(), $entradasUsd->count());

        // Helpers de formatação local pt-BR.
        $br = fn ($v, $dec = 2) => number_format((float) $v, $dec, ',', '.');
        $dt = fn ($t) => optional($t->created_at)->format('d/m/Y H:i');

        $filename = sprintf(
            'extrato_%s_%s.csv',
            \Illuminate\Support\Str::slug($client->name),
            now()->format('Ymd_His')
        );

        return response()->streamDownload(function () use (
            $client, $brl, $usd, $dateFrom, $dateTo,
            $entradasBrl, $saidasUsd, $entradasUsd, $rowsCount, $br, $dt
        ) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 → Excel/Numbers reconhecem acentos e separador ;
            fwrite($out, "\xEF\xBB\xBF");

            $sep = ';';

            // Cabeçalho
            fputcsv($out, ["Extrato — {$client->name}"], $sep);
            $periodo = 'Período: ' . ($dateFrom ? $dateFrom->format('d/m/Y') : 'início') .
                       ' até ' . ($dateTo ? $dateTo->format('d/m/Y') : 'hoje');
            fputcsv($out, [$periodo], $sep);
            fputcsv($out, [
                'Saldo ' . $client->name,
                'R$ ' . $br($brl),
                'U$ ' . $br($usd),
            ], $sep);
            fputcsv($out, [], $sep);

            // Cabeçalhos das 3 colunas (lado a lado).
            fputcsv($out, [
                'Entradas R$', '', '', '',
                'Saídas U$', '', '',
                'Entradas U$', '', '',
            ], $sep);
            fputcsv($out, [
                'Data', 'Valor R$', 'Taxa', 'Valor U$',
                'Data', 'Valor U$', 'Descrição',
                'Data', 'Valor U$', 'Descrição',
            ], $sep);

            for ($i = 0; $i < $rowsCount; $i++) {
                $e  = $entradasBrl->get($i);
                $s  = $saidasUsd->get($i);
                $eu = $entradasUsd->get($i);

                $eValorUsd = null;
                if ($e) {
                    if ($e->converted_currency === 'USD' && $e->converted_amount !== null) {
                        $eValorUsd = (float) $e->converted_amount;
                    } elseif ((float) $e->exchange_rate > 0) {
                        $eValorUsd = (float) $e->amount / (float) $e->exchange_rate;
                    }
                }

                fputcsv($out, [
                    $e ? $dt($e) : '',
                    $e ? $br($e->amount) : '',
                    $e && $e->exchange_rate ? $br($e->exchange_rate, 4) : '',
                    $eValorUsd !== null ? $br($eValorUsd) : '',

                    $s ? $dt($s) : '',
                    $s ? $br(abs((float) $s->amount)) : '',
                    $s ? (string) ($s->description ?? '') : '',

                    $eu ? $dt($eu) : '',
                    $eu ? $br((float) $eu->amount) : '',
                    $eu ? (string) ($eu->description ?? '') : '',
                ], $sep);
            }

            // Totais.
            $totalEntradaBrl = (float) $entradasBrl->sum('amount');
            $totalSaidaUsd   = (float) $saidasUsd->sum(fn ($t) => abs((float) $t->amount));
            $totalEntradaUsd = (float) $entradasUsd->sum('amount');

            fputcsv($out, [], $sep);
            fputcsv($out, [
                'TOTAL', 'R$ ' . $br($totalEntradaBrl), '', '',
                'TOTAL', 'U$ ' . $br($totalSaidaUsd), '',
                'TOTAL', 'U$ ' . $br($totalEntradaUsd), '',
            ], $sep);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Lê os parâmetros de filtro de período (date_from / date_to) da request.
     *
     * @return array{0: ?\Illuminate\Support\Carbon, 1: ?\Illuminate\Support\Carbon}
     */
    protected function parseDateRange(Request $request): array
    {
        $from = $request->input('date_from');
        $to   = $request->input('date_to');

        try {
            $dateFrom = $from ? \Illuminate\Support\Carbon::parse($from)->startOfDay() : null;
        } catch (\Throwable $e) { $dateFrom = null; }
        try {
            $dateTo = $to ? \Illuminate\Support\Carbon::parse($to)->endOfDay() : null;
        } catch (\Throwable $e) { $dateTo = null; }

        return [$dateFrom, $dateTo];
    }

    /**
     * Pré-compra de dólar pelo dono usando o BRL do cliente.
     * Não altera o saldo da carteira: apenas registra o lote e reserva o R$ no(s) depósito(s).
     */
    public function prePurchaseDollar(Request $request)
    {
        $validated = $request->validate([
            'client_id'     => 'required|exists:clients,id',
            'amount'        => 'required|numeric|min:0.01',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'description'   => 'nullable|string|max:255',
        ]);

        try {
            $lotes = $this->walletService->prePurchaseDollar(
                (int) $validated['client_id'],
                (float) $validated['amount'],
                (float) $validated['exchange_rate'],
                $validated['description'] ?? null
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $totalUsd = array_sum(array_map(fn ($l) => (float) $l->usd_amount, $lotes));
        $totalBrl = array_sum(array_map(fn ($l) => (float) $l->brl_amount, $lotes));

        return back()->with('success',
            'Pré-compra registrada: R$ ' . number_format($totalBrl, 2, ',', '.') .
            ' → US$ ' . number_format($totalUsd, 2, ',', '.') .
            ' (taxa ' . number_format((float) $validated['exchange_rate'], 4, ',', '.') . ').'
        );
    }

    /**
     * Depósito
     */
    public function deposit(DepositRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();
            $client = \App\Models\Client::findOrFail($data['client_id']);

            $exchangeRate = null;
            $convertedAmount = null;

            if ($data['currency'] === 'BRL') {
                $baseRate = (float) $data['fee'];
                $exchangeRate = $baseRate + ($client->spread_points * 0.01);
                $convertedAmount = round($data['amount'] / $exchangeRate, 2);
            }

            $this->walletService->updateBalance($data['client_id'], $data['currency'], $data['amount']);
            $this->transactionService->create([
                'client_id' => $data['client_id'],
                'type' => 'deposit',
                'currency' => $data['currency'],
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'exchange_rate' => $exchangeRate,
                'converted_currency' => $data['currency'] === 'BRL' ? 'USD' : null,
                'converted_amount' => $convertedAmount,
                'status' => $data['currency'] === 'BRL' ? 'ambos_abertos' : null,
            ]);
            return response()->json(['message' => 'Depósito realizado com sucesso.']);
        });
    }

    /**
     * Busca a cotação atual USD/BRL no investing.com e cacheia por 60s.
     */
    public function fetchUsdBrlRate()
    {
        try {
            $rate = Cache::remember('usd_brl_rate', 15, function () {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
                ])->timeout(10)->get('https://br.investing.com/currencies/usd-brl');

                if (!$response->ok()) {
                    return null;
                }

                $html = $response->body();

                // Procura: data-test="instrument-price-last">4,9208<
                if (preg_match('/data-test="instrument-price-last"[^>]*>([\d.,]+)</', $html, $m)) {
                    $raw = str_replace('.', '', $m[1]);   // remove separador de milhar
                    $raw = str_replace(',', '.', $raw);   // vírgula -> ponto
                    return (float) $raw;
                }

                return null;
            });

            if ($rate === null) {
                return response()->json(['success' => false, 'message' => 'Não foi possível obter a cotação.'], 502);
            }

            return response()->json([
                'success' => true,
                'rate' => $rate,
                'source' => 'br.investing.com',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Erro ao consultar cotação.'], 500);
        }
    }

    /**
     * Atualiza a taxa de uma transação de entrada BRL e audita a alteração.
     */
    public function updateDepositRate(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'exchange_rate' => 'required|numeric|min:0.000001',
        ]);

        if (!($transaction->type === 'deposit' && $transaction->currency === 'BRL' && $transaction->amount > 0)) {
            return back()->with('error', 'A taxa só pode ser alterada para depósitos de entrada em BRL.');
        }

        if (in_array($transaction->status, ['fechado', 'finalizado'], true)) {
            return response()->json([
                'message' => 'A taxa não pode ser alterada em uma transação fechada/finalizada.',
            ], 422);
        }

        return DB::transaction(function () use ($validated, $transaction) {
            $oldRate = (float) ($transaction->exchange_rate ?? 0);
            $newRate = (float) $validated['exchange_rate'];

            $transaction->exchange_rate = $newRate;
            $transaction->converted_currency = 'USD';
            $transaction->converted_amount = round($transaction->amount / $newRate, 2);
            $transaction->save();

            TransactionRateChangeLog::create([
                'transaction_id' => $transaction->id,
                'client_id' => $transaction->client_id,
                'changed_by' => Auth::id(),
                'old_rate' => $oldRate,
                'new_rate' => $newRate,
            ]);

            return back()->with('success', 'Taxa atualizada com sucesso.');
        });
    }

    /**
     * Atualiza a taxa de várias transações de entrada BRL e audita as alterações.
     */
    public function updateDepositRateBulk(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'transaction_ids' => 'required|array|min:1',
            'transaction_ids.*' => 'integer|exists:transactions,id',
        ]);

        $newRate = (float) $validated['exchange_rate'];

        return DB::transaction(function () use ($validated, $newRate) {
            $transactions = Transaction::query()
                ->whereIn('id', $validated['transaction_ids'])
                ->where('client_id', $validated['client_id'])
                ->where('type', 'deposit')
                ->where('currency', 'BRL')
                ->where('amount', '>', 0)
                ->whereNotIn('status', ['fechado', 'finalizado'])
                ->get();

            if ($transactions->isEmpty()) {
                return back()->with('error', 'Nenhuma transação válida foi selecionada para atualização em lote.');
            }

            foreach ($transactions as $transaction) {
                $oldRate = (float) ($transaction->exchange_rate ?? 0);

                $transaction->exchange_rate = $newRate;
                $transaction->converted_currency = 'USD';
                $transaction->converted_amount = round($transaction->amount / $newRate, 2);
                $transaction->save();

                TransactionRateChangeLog::create([
                    'transaction_id' => $transaction->id,
                    'client_id' => $transaction->client_id,
                    'changed_by' => Auth::id(),
                    'old_rate' => $oldRate,
                    'new_rate' => $newRate,
                ]);
            }

            return back()->with('success', 'Taxa atualizada em lote para ' . $transactions->count() . ' registro(s).');
        });
    }

    /**
     * Saque
     */
    public function withdraw(WithdrawRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();
            $wallet = $this->walletService->updateBalance($data['client_id'], $data['currency'], -$data['amount']);
            if ($wallet->balance < 0) {
                throw new \Exception('Saldo insuficiente.');
            }
            $this->transactionService->create([
                'client_id' => $data['client_id'],
                'type' => 'withdraw',
                'currency' => $data['currency'],
                'amount' => -$data['amount'],
            ]);
            return response()->json(['message' => 'Saque realizado com sucesso.']);
        });
    }

    /**
     * Conversão de moedas
     */
    public function exchange(ExchangeRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();

            // Spread do cliente: cada ponto = R$ 0,01 sobre a taxa
            $client = \App\Models\Client::findOrFail($data['client_id']);
            $effectiveRate = $data['exchange_rate'] + ($client->spread_points * 0.01);

            // Saída da moeda de origem
            $walletOrigem = $this->walletService->updateBalance($data['client_id'], $data['from_currency'], -$data['amount']);
            if ($walletOrigem->balance < 0) {
                throw new \Exception('Saldo insuficiente na moeda de origem.');
            }
            // Valor convertido usando taxa efetiva (base + spread)
            $convertedAmount = $this->currencyService->convert($data['amount'], $effectiveRate);
            // Entrada na moeda destino
            $this->walletService->updateBalance($data['client_id'], $data['to_currency'], $convertedAmount);
            // Registrar transações
            $this->transactionService->create([
                'client_id' => $data['client_id'],
                'type' => 'exchange_out',
                'currency' => $data['from_currency'],
                'amount' => -$data['amount'],
                'converted_currency' => $data['to_currency'],
                'converted_amount' => $convertedAmount,
                'exchange_rate' => $effectiveRate,
            ]);
            $this->transactionService->create([
                'client_id' => $data['client_id'],
                'type' => 'exchange_in',
                'currency' => $data['to_currency'],
                'amount' => $convertedAmount,
                'converted_currency' => $data['from_currency'],
                'converted_amount' => $data['amount'],
                'exchange_rate' => $effectiveRate,
            ]);
            return response()->json(['message' => 'Conversão realizada com sucesso.']);
        });
    }

    /**
     * Fechamento em dólar: consome BRL (FIFO – do registro mais antigo para o mais novo)
     * dentro do conjunto de transações selecionadas, podendo "quebrar" um registro
     * em duas partes (parte finalizada + parte que sobra) via soft-delete do original.
     *
     * Entrada esperada:
     *  - amount        => valor TOTAL em BRL que será convertido nesta operação
     *  - exchange_rate => taxa usada na conversão (será gravada em todos os registros finalizados)
     *  - transaction_ids => ids das transações BRL elegíveis (pool da seleção)
     */
    public function fechamentoDolar(Request $request)
    {
        $validated = $request->validate([
            'client_id'        => 'required|exists:clients,id',
            'exchange_rate'    => 'required|numeric|min:0.000001',
            'transaction_ids'  => 'required|string',
            'amount'           => 'required|numeric|min:0.01',
            'date'             => 'required|date',
            'description'      => 'required|string|max:255',
        ]);

        $ids = array_values(array_filter(array_map('intval', explode(',', $validated['transaction_ids']))));
        if (empty($ids)) {
            return back()->with('error', 'Selecione ao menos uma transação para fechar.');
        }

        $newRate   = (float) $validated['exchange_rate'];
        $remaining = round((float) $validated['amount'], 2);

        return DB::transaction(function () use ($validated, $ids, $newRate, $remaining) {
            // Pool elegível: BRL, depósitos positivos, ainda abertos, do cliente, ordenados FIFO.
            $candidates = Transaction::query()
                ->whereIn('id', $ids)
                ->where('client_id', $validated['client_id'])
                ->where('type', 'deposit')
                ->where('currency', 'BRL')
                ->where('amount', '>', 0)
                ->whereNotIn('status', ['fechado', 'finalizado'])
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            $totalDisponivel = round((float) $candidates->sum('amount'), 2);

            if ($remaining > $totalDisponivel + 0.005) {
                return back()->with('error',
                    'O valor solicitado (R$ ' . number_format($remaining, 2, ',', '.') .
                    ') excede o disponível nas transações selecionadas (R$ ' .
                    number_format($totalDisponivel, 2, ',', '.') . ').');
            }

            $totalBrlConsumido = 0.0;
            $totalUsdGerado    = 0.0;
            $totalPnlBrl       = 0.0;

            foreach ($candidates as $tx) {
                if ($remaining <= 0.005) {
                    break;
                }

                $valorTx = round((float) $tx->amount, 2);

                if ($valorTx <= $remaining + 0.005) {
                    // Consome o registro INTEIRO: marca como finalizado já com a nova taxa.
                    $convertido = round($valorTx / $newRate, 2);

                    // Liquida lotes de pré-compra deste depósito (PnL).
                    $pre = $this->walletService->consumePrePurchasesOnClose($tx, $valorTx, $newRate);
                    $totalPnlBrl += (float) $pre['pnl_brl'];

                    $oldRate = (float) ($tx->exchange_rate ?? 0);
                    $tx->exchange_rate      = $newRate;
                    $tx->converted_currency = 'USD';
                    $tx->converted_amount   = $convertido;
                    $tx->status             = 'finalizado';
                    $tx->save();

                    if ($oldRate !== $newRate) {
                        TransactionRateChangeLog::create([
                            'transaction_id' => $tx->id,
                            'client_id'      => $tx->client_id,
                            'changed_by'     => Auth::id(),
                            'old_rate'       => $oldRate,
                            'new_rate'       => $newRate,
                        ]);
                    }

                    $totalBrlConsumido += $valorTx;
                    $totalUsdGerado    += $convertido;
                    $remaining         -= $valorTx;
                } else {
                    // Consumo PARCIAL → split: soft-delete do original + 2 novos registros.
                    $consumido = round($remaining, 2);
                    $sobra     = round($valorTx - $consumido, 2);

                    // Regra: não permitir split se o depósito tem pré-compra que ultrapasse a sobra.
                    // Isso garante que toda parte pré-comprada seja liquidada nesta operação.
                    $brlPre = round((float) $tx->brl_pre_purchased, 2);
                    if ($brlPre > $consumido + 0.005) {
                        throw new \RuntimeException(
                            'O depósito BRL de R$ ' . number_format($valorTx, 2, ',', '.') .
                            ' tem pré-compra de R$ ' . number_format($brlPre, 2, ',', '.') .
                            '. Para fechar parcialmente é preciso liquidar ao menos toda a parte pré-comprada.'
                        );
                    }

                    // Liquida lotes de pré-compra deste depósito sobre a parte consumida.
                    $pre = $this->walletService->consumePrePurchasesOnClose($tx, $consumido, $newRate);
                    $totalPnlBrl += (float) $pre['pnl_brl'];

                    // 1) Parte consumida: finalizada com a nova taxa.
                    $finalizada = new Transaction([
                        'parent_transaction_id' => $tx->id,
                        'client_id'             => $tx->client_id,
                        'type'                  => $tx->type,
                        'currency'              => $tx->currency,
                        'amount'                => $consumido,
                        'payment_method'        => $tx->payment_method,
                        'converted_currency'    => 'USD',
                        'converted_amount'      => round($consumido / $newRate, 2),
                        'exchange_rate'         => $newRate,
                        'description'           => $tx->description,
                        'status'                => 'finalizado',
                    ]);
                    $finalizada->created_at = $tx->created_at;
                    $finalizada->updated_at = now();
                    $finalizada->save();

                    // 2) Parte restante: preserva os dados originais (data, payment_method, status).
                    $restante = new Transaction([
                        'parent_transaction_id' => $tx->id,
                        'client_id'             => $tx->client_id,
                        'type'                  => $tx->type,
                        'currency'              => $tx->currency,
                        'amount'                => $sobra,
                        'payment_method'        => $tx->payment_method,
                        'converted_currency'    => $tx->converted_currency,
                        'converted_amount'      => ($tx->exchange_rate && $tx->exchange_rate > 0)
                            ? round($sobra / (float) $tx->exchange_rate, 2)
                            : null,
                        'exchange_rate'         => $tx->exchange_rate,
                        'description'           => $tx->description,
                        'status'                => $tx->status,
                    ]);
                    $restante->created_at = $tx->created_at;
                    $restante->updated_at = now();
                    $restante->save();

                    // 3) Soft-delete do registro original.
                    $tx->delete();

                    $totalBrlConsumido += $consumido;
                    $totalUsdGerado    += $finalizada->converted_amount;
                    $remaining          = 0;
                }
            }

            // Cria a entrada consolidada em USD (status finalizado).
            $usdTx = new Transaction([
                'client_id'        => $validated['client_id'],
                'type'             => 'deposit',
                'currency'         => 'USD',
                'amount'           => round($totalUsdGerado, 2),
                'exchange_rate'    => $newRate,
                'realized_pnl_brl' => round($totalPnlBrl, 2),
                'description'      => $validated['description'],
                'status'           => 'finalizado',
            ]);
            $usdTx->created_at = $validated['date'];
            $usdTx->updated_at = now();
            $usdTx->save();

            // Atualiza saldos reais da carteira no fechamento:
            // BRL diminui pelo valor convertido e USD aumenta pelo valor gerado.
            $this->walletService->updateBalance((int) $validated['client_id'], 'BRL', -$totalBrlConsumido);
            $this->walletService->updateBalance((int) $validated['client_id'], 'USD', $totalUsdGerado);

            return redirect()->back()->with('success',
                'Fechamento em dólar realizado: R$ ' . number_format($totalBrlConsumido, 2, ',', '.') .
                ' → US$ ' . number_format($totalUsdGerado, 2, ',', '.') .
                ' (taxa ' . number_format($newRate, 4, ',', '.') . ').' .
                ($totalPnlBrl != 0
                    ? ' PnL pré-compra: ' . ($totalPnlBrl >= 0 ? '+' : '') . 'R$ ' . number_format($totalPnlBrl, 2, ',', '.')
                    : '')
            );
        });
    }
}
