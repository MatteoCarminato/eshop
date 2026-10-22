<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CODWEB do PRODUTO_PRO — o código do produto nas integrações de fora.
     *
     * É ele que identifica o anúncio no ComprasPY (`<codigo>` do feed XML e
     * `code` da API de importação). Fica separado do `erp_code` (CODNNN)
     * porque esse continua sendo o que a gravação de preço no ERP usa pra
     * achar a linha — hoje os dois têm o mesmo valor, mas são campos distintos
     * no ERP e podem divergir.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('web_code', 20)->nullable()->after('erp_code')->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['web_code']);
            $table->dropColumn('web_code');
        });
    }
};
