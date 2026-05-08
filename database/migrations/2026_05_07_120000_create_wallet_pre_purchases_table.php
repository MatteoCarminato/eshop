<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_pre_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            // Depósito BRL que serviu de "fundo" para a pré-compra
            $table->foreignId('source_transaction_id')->nullable()
                ->constrained('transactions')->nullOnDelete();
            // Quem fez a pré-compra (admin)
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            // Valores originais da pré-compra
            $table->decimal('brl_amount', 15, 5);     // R$ pegos do cliente para esta pré-compra
            $table->decimal('usd_amount', 15, 5);     // US$ comprados pelo dono nesta operação
            $table->decimal('exchange_rate', 15, 6);  // Taxa usada na pré-compra

            // Saldo restante (vai sendo abatido quando o cliente fecha o BRL)
            $table->decimal('brl_remaining', 15, 5);  // R$ ainda comprometidos com o cliente (= dívida em aberto)
            $table->decimal('usd_remaining', 15, 5);  // US$ ainda não entregues ao cliente

            // PnL acumulado em R$ realizado nas liquidações deste lote
            $table->decimal('realized_pnl_brl', 15, 5)->default(0);

            // open = nenhum consumo, partial = parcialmente consumido, closed = totalmente liquidado
            $table->enum('status', ['open', 'partial', 'closed'])->default('open');

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index('source_transaction_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Quanto desse depósito BRL já foi pré-comprado (reservado pelo dono).
            // Garante que não é possível pré-comprar duas vezes a mesma quantia.
            $table->decimal('brl_pre_purchased', 15, 5)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('brl_pre_purchased');
        });

        Schema::dropIfExists('wallet_pre_purchases');
    }
};
