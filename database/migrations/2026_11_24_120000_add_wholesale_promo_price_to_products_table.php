<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promoção de atacado (U$): coluna "Promo" da grade /products-admin, lida pelo
 * #promo do WhatsApp. Só do painel — o sync do ERP não toca nela — e separada
 * de `promo_price` (promoção da loja Orbita/ComprasPY) e de `sale_price`
 * (PREVEN do ERP). 0 = sem promoção; vale quando > 0 e o produto tem estoque.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('wholesale_promo_price', 18, 8)->default(0)->after('promo_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('wholesale_promo_price');
        });
    }
};
