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
            $table->string('bank_transaction_id')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_pix_extractions', function (Blueprint $table) {
            $table->dropIndex(['bank_transaction_id']);
            $table->dropColumn('bank_transaction_id');
        });
    }
};
