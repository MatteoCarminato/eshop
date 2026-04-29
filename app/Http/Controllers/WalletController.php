<?php

namespace App\Http\Controllers;

use App\Http\Requests\Wallet\DepositRequest;
use App\Http\Requests\Wallet\WithdrawRequest;
use App\Http\Requests\Wallet\ExchangeRequest;
use App\Services\WalletService;
use App\Services\TransactionService;
use App\Services\CurrencyService;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
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
     * Exibe a carteira do cliente com saldos em BRL, USD, USDT e botões de ação
     */
    public function clientWallet(\App\Models\Client $client)
    {
        $wallets = $client->wallets()->get()->keyBy('currency');
        $balances = [
            'BRL' => $wallets['BRL']->balance ?? 0,
            'USD' => $wallets['USD']->balance ?? 0,
            'USDT' => $wallets['USDT']->balance ?? 0,
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
            $this->walletService->updateBalance($data['client_id'], $data['currency'], $data['amount']);
            $this->transactionService->create([
                'client_id' => $data['client_id'],
                'type' => 'deposit',
                'currency' => $data['currency'],
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
            ]);
            return response()->json(['message' => 'Depósito realizado com sucesso.']);
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
            // Saída da moeda de origem
            $walletOrigem = $this->walletService->updateBalance($data['client_id'], $data['from_currency'], -$data['amount']);
            if ($walletOrigem->balance < 0) {
                throw new \Exception('Saldo insuficiente na moeda de origem.');
            }
            // Valor convertido
            $convertedAmount = $this->currencyService->convert($data['amount'], $data['exchange_rate']);
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
                'exchange_rate' => $data['exchange_rate'],
            ]);
            $this->transactionService->create([
                'client_id' => $data['client_id'],
                'type' => 'exchange_in',
                'currency' => $data['to_currency'],
                'amount' => $convertedAmount,
                'converted_currency' => $data['from_currency'],
                'converted_amount' => $data['amount'],
                'exchange_rate' => $data['exchange_rate'],
            ]);
            return response()->json(['message' => 'Conversão realizada com sucesso.']);
        });
    }
}
