<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Espelha o preço médio do ERP (PRODUTO_PRO.CUSMED) para que a grade de
     * preços mostre o custo médio ao lado do atacado — é o número que dá a
     * margem de olho ao varrer a lista corrigindo preço.
     *
     * Nullable, igual aos outros preços do ERP: produto sem movimento de
     * compra vem com o campo vazio, e `null` ("não tem") é diferente de `0`
     * ("custo zero"). Mesma precisão das outras colunas de preço — a origem é
     * decimal(19,6) no ERP.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('average_price', 18, 8)->nullable()->after('min_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('average_price');
        });
    }
};
