<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_pix_extractions', function (Blueprint $table) {
            $table->string('numero_transacao')->nullable()->after('mimetype')->index();
            $table->string('status')->default('confirmed')->after('numero_transacao'); // confirmed | duplicate
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_pix_extractions', function (Blueprint $table) {
            $table->dropIndex(['numero_transacao']);
            $table->dropColumn(['numero_transacao', 'status']);
        });
    }
};
