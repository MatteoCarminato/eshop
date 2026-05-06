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
        $clientId = $request->get('client_id');
        $wallets = null;
        if ($clientId) {
            $wallets = \App\Models\Wallet::where('client_id', $clientId)->get();
        }
        return view('admin.wallet.index', compact('wallets'));
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
    public function clientWallet(\App\Models\Client $client)
    {
        $wallets = $client->wallets()->get()->keyBy('currency');
        $balances = [
            'BRL' => $wallets['BRL']->balance ?? 0,
            'USD' => $wallets['USD']->balance ?? 0,
        ];
        $transactions = $client->transactions()->orderByDesc('created_at')->get();
        return view('admin.wallet.client', compact('client', 'balances', 'transactions'));
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

            foreach ($candidates as $tx) {
                if ($remaining <= 0.005) {
                    break;
                }

                $valorTx = round((float) $tx->amount, 2);

                if ($valorTx <= $remaining + 0.005) {
                    // Consome o registro INTEIRO: marca como finalizado já com a nova taxa.
                    $convertido = round($valorTx / $newRate, 2);

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
                'client_id'     => $validated['client_id'],
                'type'          => 'deposit',
                'currency'      => 'USD',
                'amount'        => round($totalUsdGerado, 2),
                'exchange_rate' => $newRate,
                'description'   => $validated['description'],
                'status'        => 'finalizado',
            ]);
            $usdTx->created_at = $validated['date'];
            $usdTx->updated_at = now();
            $usdTx->save();

            return redirect()->back()->with('success',
                'Fechamento em dólar realizado: R$ ' . number_format($totalBrlConsumido, 2, ',', '.') .
                ' → US$ ' . number_format($totalUsdGerado, 2, ',', '.') .
                ' (taxa ' . number_format($newRate, 4, ',', '.') . ').');
        });
    }
}
