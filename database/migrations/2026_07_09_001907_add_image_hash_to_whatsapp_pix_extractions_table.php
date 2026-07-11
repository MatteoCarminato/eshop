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
        Schema::table('whatsapp_pix_extractions', function (Blueprint $table) {
            // SHA-256 do binário — detecta exatamente a mesma imagem
            $table->string('image_hash', 64)->nullable()->after('image_path')->index();
            // Campos extraídos em colunas próprias para query de duplicata sem depender do JSON
            $table->string('pix_nome')->nullable()->after('image_hash');
            $table->string('pix_valor')->nullable()->after('pix_nome');
            $table->string('pix_data')->nullable()->after('pix_valor');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_pix_extractions', function (Blueprint $table) {
            $table->dropIndex(['image_hash']);
            $table->dropColumn(['image_hash', 'pix_nome', 'pix_valor', 'pix_data']);
        });
    }
};
