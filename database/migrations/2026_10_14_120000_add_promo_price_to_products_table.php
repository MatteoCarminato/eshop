<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promoção do site: preço "por" opcional, digitado só no painel.
 *
 * Diferente de `sale_price`, que é o PREVEN do ERP (espelhado do atacado e
 * gravado de volta no PRODUTO_PRO — ver ProductAdminController::PRICE_COLUMNS),
 * `promo_price` não é tocada pelo sync nem pelo espelho do atacado: nasce 0 e
 * só muda quando alguém edita o produto no painel. 0 = sem promoção, e aí a
 * loja mostra o Preço Web.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('promo_price', 18, 8)->default(0)->after('sale_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('promo_price');
        });
    }
};
