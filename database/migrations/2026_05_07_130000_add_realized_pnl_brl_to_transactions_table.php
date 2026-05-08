<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // PnL realizado em R$ (lucro/prejuízo) gravado na transação USD gerada por um fechamento.
            $table->decimal('realized_pnl_brl', 15, 5)->nullable()->after('exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('realized_pnl_brl');
        });
    }
};
