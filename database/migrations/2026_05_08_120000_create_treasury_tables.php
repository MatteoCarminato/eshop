<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('treasury_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Valores do APORTE original (compra do dono com seu próprio dinheiro)
            $table->decimal('usd_amount', 18, 8);    // USD comprado
            $table->decimal('cost_rate', 18, 8);     // BRL por USD na compra (custo)
            $table->decimal('brl_cost', 18, 8);      // R$ que saíram do bolso do dono

            // Saldo disponível (vai sendo abatido nas vendas FIFO)
            $table->decimal('usd_remaining', 18, 8);

            // PnL acumulado em R$ realizado nas vendas deste lote
            $table->decimal('realized_pnl_brl', 18, 8)->default(0);

            // open = nada vendido, partial = parcialmente vendido, closed = totalmente vendido
            $table->enum('status', ['open', 'partial', 'closed'])->default('open');

            $table->timestamp('purchased_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        // Tabela das vendas do caixa para clientes (auditoria + PnL por venda).
        Schema::create('treasury_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('usd_amount', 18, 8);    // USD vendido ao cliente
            $table->decimal('sell_rate', 18, 8);     // BRL/USD usado nesta venda
            $table->decimal('brl_total', 18, 8);     // BRL recebido (= usd_amount * sell_rate)
            $table->decimal('cost_brl', 18, 8);      // BRL de custo consumido dos lotes
            $table->decimal('realized_pnl_brl', 18, 8); // brl_total - cost_brl (lucro do dono)

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_sales');
        Schema::dropIfExists('treasury_lots');
    }
};
