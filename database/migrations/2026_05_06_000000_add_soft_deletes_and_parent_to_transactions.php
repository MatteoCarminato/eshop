<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->softDeletes();
            $table->unsignedBigInteger('parent_transaction_id')->nullable()->after('id');
            $table->index('parent_transaction_id');
            $table->foreign('parent_transaction_id')
                ->references('id')->on('transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['parent_transaction_id']);
            $table->dropIndex(['parent_transaction_id']);
            $table->dropColumn('parent_transaction_id');
            $table->dropSoftDeletes();
        });
    }
};
