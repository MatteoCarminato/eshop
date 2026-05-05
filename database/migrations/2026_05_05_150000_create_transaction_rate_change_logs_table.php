<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transaction_rate_change_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->decimal('old_rate', 15, 6)->nullable();
            $table->decimal('new_rate', 15, 6);
            $table->timestamps();

            $table->index('transaction_id');
            $table->index('client_id');
            $table->index('changed_by');

            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_rate_change_logs');
    }
};
