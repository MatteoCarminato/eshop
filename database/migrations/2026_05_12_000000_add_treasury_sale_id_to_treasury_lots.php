<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('treasury_lots', function (Blueprint $table) {
            $table->foreignId('treasury_sale_id')->nullable()->after('transaction_id')
                ->constrained('treasury_sales')->nullOnDelete();
            $table->index('treasury_sale_id');
        });
    }

    public function down(): void
    {
        Schema::table('treasury_lots', function (Blueprint $table) {
            $table->dropForeign(['treasury_sale_id']);
            $table->dropIndex(['treasury_sale_id']);
            $table->dropColumn('treasury_sale_id');
        });
    }
};
