<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga lotes de caixa (especialmente shortfall) à transação USD que os originou,
 * permitindo propagar PnL real ao reconciliar o shortfall com uma pré-compra futura.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('treasury_lots', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->after('pre_purchase_id')
                ->constrained('transactions')->nullOnDelete();
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('treasury_lots', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropIndex(['transaction_id']);
            $table->dropColumn('transaction_id');
        });
    }
};
