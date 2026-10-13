<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CODNNN do PRODUTO_PRO — o código do produto no ERP.
     *
     * Já tínhamos o `recno` (R_E_C_N_O_), mas ele não serve pra gravar preço: o
     * usuário de escrita do ERP (`update1`) não tem grant nem de SELECT na
     * coluna RECNO, só em CODNNN/CODPRO. Todo UPDATE de preço tem que casar por
     * CODNNN, então ele precisa estar do lado de cá.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Índice simples, não único: se o ERP repetir um código, o sync
            // continua passando e o problema aparece na gravação, não no sync.
            $table->string('erp_code', 20)->nullable()->after('recno')->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['erp_code']);
            $table->dropColumn('erp_code');
        });
    }
};
