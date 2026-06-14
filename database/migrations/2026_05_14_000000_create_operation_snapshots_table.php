<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshots de operações financeiras reversíveis.
 *
 * Cada operação de carteira (depósito, pré-compra, pré-venda, fechamento) é
 * envolvida por OperationReversalService::capture(), que registra aqui o DIFF
 * completo do estado tocado (transações, carteiras, lotes de caixa, pré-compras,
 * pré-vendas e vendas do caixa). A reversão restaura exatamente esse diff,
 * desfazendo inclusive efeitos colaterais (lucro, shortfall, reconciliação,
 * finalização de depósito e splits).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('operation_snapshots', function (Blueprint $table) {
            $table->id();

            // Tipo lógico: deposit_brl, deposit_usd, pre_purchase, pre_sell, fechamento.
            $table->string('type', 30);

            $table->foreignId('client_id')->nullable()
                ->constrained('clients')->nullOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            // IDs de depósitos BRL (ou transações-âncora) envolvidos — usado para
            // mostrar os botões de reversão na linha certa da carteira.
            $table->json('deposit_ids')->nullable();

            // Rótulo amigável para exibição/auditoria.
            $table->string('label', 255)->nullable();

            // Diff completo do estado tocado (created/modified/trashed por tabela).
            $table->json('payload');

            // Quem reverteu e quando (null = ainda ativa).
            $table->foreignId('reversed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reverse_reason', 500)->nullable();

            $table->timestamps();

            $table->index(['client_id', 'reversed_at']);
            $table->index(['type', 'reversed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_snapshots');
    }
};
