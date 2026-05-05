<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Pontos de spread: cada ponto = R$ 0,01 somado ao câmbio base do cliente
            $table->unsignedSmallInteger('spread_points')->default(0)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('spread_points');
        });
    }
};
