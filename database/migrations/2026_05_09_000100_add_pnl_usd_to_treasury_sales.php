<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona realized_pnl_usd em treasury_sales (= pnl_brl / sell_rate).
 *
 * Toda venda do caixa que tiver lucro >0 vai gerar automaticamente um novo lote
 * 'profit' (USD = pnl em USD, custo = 0) — assim o caixa da empresa cresce em USD
 * com o lucro de cada operação.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('treasury_sales', function (Blueprint $table) {
            $table->decimal('realized_pnl_usd', 18, 8)->nullable()->after('realized_pnl_brl');
        });
    }

    public function down(): void
    {
        Schema::table('treasury_sales', function (Blueprint $table) {
            $table->dropColumn('realized_pnl_usd');
        });
    }
};
