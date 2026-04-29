<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->enum('type', ['deposit', 'withdraw', 'exchange_in', 'exchange_out']);
            $table->string('currency', 10); // BRL, USD, USDT
            $table->decimal('amount', 15, 5);
            $table->string('payment_method', 20)->nullable(); // pix, cash, crypto
            $table->string('converted_currency', 10)->nullable();
            $table->decimal('converted_amount', 15, 5)->nullable();
            $table->decimal('exchange_rate', 15, 6)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('client_id');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
