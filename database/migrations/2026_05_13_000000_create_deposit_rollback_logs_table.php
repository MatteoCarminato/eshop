<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deposit_rollback_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposit_transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            $table->index(['deposit_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_rollback_logs');
    }
};
