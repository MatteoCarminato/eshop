<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('recno')->nullable()->unique();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();

            // Campos espelhados do ERP (PRODUTO_PRO)
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->decimal('price', 18, 8)->default(0);
            $table->decimal('wholesale_price', 18, 8)->nullable();
            $table->decimal('web_price', 18, 8)->nullable();
            $table->decimal('sale_price', 18, 8)->nullable();
            $table->decimal('min_price', 18, 8)->nullable();
            $table->decimal('stock', 18, 4)->default(0);

            // Campos próprios do site
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('featured')->default(false);

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
