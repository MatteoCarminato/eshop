<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Cria uma nova transação de forma imutável.
     * Sempre usar dentro de transação de banco.
     *
     * @param array $data
     * @return Transaction
     */
    public function create(array $data): Transaction
    {
        // Nunca altera, só cria
        return Transaction::create($data);
    }

    /**
     * Valida saldo suficiente antes de saque/conversão.
     *
     * @param float $saldoAtual
     * @param float $valorSolicitado
     * @return bool
     */
    public function validarSaldo(float $saldoAtual, float $valorSolicitado): bool
    {
        return $saldoAtual >= $valorSolicitado;
    }
}
