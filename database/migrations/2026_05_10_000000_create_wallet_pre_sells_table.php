<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pré-venda de dólar pelo dono usando R$ do cliente.
 *
 * Espelha a estrutura de wallet_pre_purchases mas representa o lado da VENDA:
 *   - exchange_rate na compra → custo do dono
 *   - sell_rate na venda      → preço cobrado do cliente
 *
 * Quando o R$ de um depósito tem AMBOS lados (compra + venda) registrados,
 * o fechamento entrega o USD ao cliente direto e contabiliza o lucro.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_pre_sells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('source_transaction_id')->nullable()
                ->constrained('transactions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->decimal('brl_amount', 15, 5);     // R$ comprometidos com a promessa de venda
            $table->decimal('usd_amount', 15, 5);     // US$ que o dono prometeu entregar
            $table->decimal('sell_rate', 15, 6);      // taxa cobrada do cliente

            $table->decimal('brl_remaining', 15, 5);  // R$ ainda não fechados
            $table->decimal('usd_remaining', 15, 5);  // US$ ainda não entregues

            $table->enum('status', ['open', 'partial', 'closed'])->default('open');

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index('source_transaction_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // R$ deste depósito que já está com venda antecipada registrada.
            $table->decimal('brl_pre_sold', 15, 5)->default(0)->after('brl_pre_purchased');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('brl_pre_sold');
        });
        Schema::dropIfExists('wallet_pre_sells');
    }
};
