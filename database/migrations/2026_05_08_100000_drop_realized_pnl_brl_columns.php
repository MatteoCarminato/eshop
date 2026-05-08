<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'realized_pnl_brl')) {
                $table->dropColumn('realized_pnl_brl');
            }
        });

        Schema::table('wallet_pre_purchases', function (Blueprint $table) {
            if (Schema::hasColumn('wallet_pre_purchases', 'realized_pnl_brl')) {
                $table->dropColumn('realized_pnl_brl');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('realized_pnl_brl', 15, 5)->nullable()->after('exchange_rate');
        });

        Schema::table('wallet_pre_purchases', function (Blueprint $table) {
            $table->decimal('realized_pnl_brl', 15, 5)->default(0)->after('usd_remaining');
        });
    }
};
