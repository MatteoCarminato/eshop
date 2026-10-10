<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Liga cada produto ao seu grupo e subgrupo do ERP
     * (PRODUTO_PRO.GRUPRO / PRODUTO_PRO.SGRUPRO), para permitir listar e
     * filtrar o catálogo por grupo e por subgrupo.
     *
     * Nullable porque um produto pode vir do ERP com código 0/inexistente —
     * nesse caso fica sem classificação em vez de bloquear o sync.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('product_group_id')->nullable()->after('category_id')
                ->constrained('product_groups')->nullOnDelete();
            $table->foreignId('product_subgroup_id')->nullable()->after('product_group_id')
                ->constrained('product_subgroups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_subgroup_id');
            $table->dropConstrainedForeignId('product_group_id');
        });
    }
};
