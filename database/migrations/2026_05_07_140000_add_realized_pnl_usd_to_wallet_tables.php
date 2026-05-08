<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // PnL realizado em USD gravado na transação USD gerada por um fechamento.
            // Substitui realized_pnl_brl (que ficou conceitualmente errado: o ganho é em dólar).
            $table->decimal('realized_pnl_usd', 18, 8)->nullable()->after('realized_pnl_brl');
        });

        Schema::table('wallet_pre_purchases', function (Blueprint $table) {
            $table->decimal('realized_pnl_usd', 18, 8)->default(0)->after('realized_pnl_brl');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('realized_pnl_usd');
        });

        Schema::table('wallet_pre_purchases', function (Blueprint $table) {
            $table->dropColumn('realized_pnl_usd');
        });
    }
};
