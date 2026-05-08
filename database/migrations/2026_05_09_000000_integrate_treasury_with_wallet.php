<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integra o caixa próprio (treasury) ao fluxo de carteira:
 *
 *  - treasury_lots: ganha origem (owner/pre_purchase) + ligação opcional ao cliente
 *    e ao lote de pré-compra que originou o USD no caixa.
 *
 *  - transactions: ganha realized_pnl_brl (PnL realizado em R$ na venda do caixa)
 *    e treasury_sale_id (ligação à venda que finalizou aquela transação USD).
 *
 *  - treasury_sales: ganha transaction_ids (json) com a lista de transações USD
 *    que foram entregues nesta venda.
 *
 *  - O status 'aguardando_venda' (string livre) passa a ser usado em transactions
 *    USD criadas pelo fechamento — significa "BRL foi consumido, mas o USD ainda
 *    não foi entregue/vendido para o cliente".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('treasury_lots', function (Blueprint $table) {
            $table->string('source', 20)->default('owner')->after('created_by');
            $table->foreignId('client_id')->nullable()->after('source')
                ->constrained('clients')->nullOnDelete();
            $table->foreignId('pre_purchase_id')->nullable()->after('client_id')
                ->constrained('wallet_pre_purchases')->nullOnDelete();
            $table->index(['source', 'client_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('realized_pnl_brl', 18, 8)->nullable()->after('realized_pnl_usd');
            $table->foreignId('treasury_sale_id')->nullable()->after('realized_pnl_brl')
                ->constrained('treasury_sales')->nullOnDelete();
        });

        Schema::table('treasury_sales', function (Blueprint $table) {
            $table->json('transaction_ids')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('treasury_sales', function (Blueprint $table) {
            $table->dropColumn('transaction_ids');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treasury_sale_id');
            $table->dropColumn('realized_pnl_brl');
        });

        Schema::table('treasury_lots', function (Blueprint $table) {
            $table->dropIndex(['source', 'client_id']);
            $table->dropConstrainedForeignId('pre_purchase_id');
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn('source');
        });
    }
};
