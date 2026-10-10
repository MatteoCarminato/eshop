<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grupos e subgrupos de produto vindos do ERP Consoft (tabelas `GRUPO` e
     * `SUB`). São duas listas planas e independentes — `SUB` não tem coluna
     * apontando para `GRUPO`, então subgrupo NÃO é filho de grupo: o produto
     * carrega os dois códigos separadamente.
     *
     * Os nomes `product_groups`/`product_subgroups` evitam colisão com a
     * tabela `groups` já existente, que é de grupos de clientes/WhatsApp e não
     * tem nada a ver com catálogo.
     */
    public function up(): void
    {
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            // Identidade do upsert, igual a brands: GRUPO.RECNO.
            $table->unsignedInteger('recno')->nullable()->unique();
            // GRUPO.CODGRU — é ESTE o código que PRODUTO_PRO.GRUPRO referencia,
            // não o RECNO. Guardado localmente para o sync de produtos resolver
            // o grupo em uma etapa só.
            $table->unsignedInteger('code')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_subgroups', function (Blueprint $table) {
            $table->id();
            // SUB.RECNO
            $table->unsignedInteger('recno')->nullable()->unique();
            // SUB.CODSGRU — referenciado por PRODUTO_PRO.SGRUPRO.
            $table->unsignedInteger('code')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_subgroups');
        Schema::dropIfExists('product_groups');
    }
};
