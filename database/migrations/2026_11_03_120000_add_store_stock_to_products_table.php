<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estoque só da loja ARMANDO ATACADO (LOCAL.QUALOC com LOCLOC = 1).
     *
     * O `stock` continua sendo o PRODUTO_PRO.ESTOQUE, que soma todos os locais
     * (loja, depósitos, assistência) e alimenta loja/Orbita/ComprasPY. Esta
     * coluna é o saldo de balcão, mostrado na grade de /products-admin.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('store_stock', 18, 4)->default(0)->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('store_stock');
        });
    }
};
