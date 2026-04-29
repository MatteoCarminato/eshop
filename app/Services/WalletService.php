<?php

namespace App\Services;

use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Atualiza o saldo da carteira do cliente para a moeda informada.
     * Cria a carteira se não existir.
     *
     * @param int $clientId
     * @param string $currency
     * @param float $amount
     * @return Wallet
     */
    public function updateBalance(int $clientId, string $currency, float $amount): Wallet
    {
        return DB::transaction(function () use ($clientId, $currency, $amount) {
            $wallet = Wallet::firstOrCreate([
                'client_id' => $clientId,
                'currency' => $currency,
            ]);
            $wallet->balance += $amount;
            $wallet->save();
            return $wallet;
        });
    }
}
