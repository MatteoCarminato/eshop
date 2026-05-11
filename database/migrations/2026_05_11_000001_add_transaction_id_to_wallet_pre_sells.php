<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga lotes de pré-venda à Transaction USD criada em preSellDollar.
 * Permite que finalizeDepositIfCovered propague o PnL real (calculado quando a
 * pré-compra finaliza o depósito) de volta para a Transaction USD da venda.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wallet_pre_sells', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->after('source_transaction_id')
                ->constrained('transactions')->nullOnDelete();
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_pre_sells', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropIndex(['transaction_id']);
            $table->dropColumn('transaction_id');
        });
    }
};
