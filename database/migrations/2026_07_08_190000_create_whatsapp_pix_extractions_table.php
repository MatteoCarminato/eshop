<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_pix_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_group_id')->constrained('whatsapp_groups')->cascadeOnDelete();
            $table->string('message_id')->nullable();
            $table->string('from')->nullable();
            $table->string('image_path');
            $table->string('mimetype')->default('image/jpeg');
            $table->json('ai_data')->nullable();    // JSON extraído: nome_pagador, cpf_cnpj, etc.
            $table->text('ai_raw')->nullable();     // resposta bruta da IA
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_pix_extractions');
    }
};
