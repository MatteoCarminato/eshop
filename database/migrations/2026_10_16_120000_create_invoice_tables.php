<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de Invoice, portado do painel legado (painel.php?view=invoice).
 *
 * `invoice_texts` é o `tb_invoice` do legado: uma linha por modelo de invoice,
 * guardando os campos de texto que o usuário edita na tela (Bill To / Ship To).
 * O legado usa ids fixos — 1 (modelo clássico), 3 (PCS Wireless) e 5 (SCal
 * Mobile) — e a tela grava neles por id, então os ids são preservados aqui.
 *
 * `invoice_files` é o `tb_invoice_file`: todo PDF gerado (tipo `saida`) ou
 * enviado por upload (tipo `entrada`), com a lista de IMEIs (`imais`, separada
 * por `-`) e a trava que impede novas leituras.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_texts', function (Blueprint $table) {
            // Sem auto-increment: os ids são os do legado (1, 3, 5).
            $table->unsignedInteger('id')->primary();

            for ($i = 1; $i <= 14; $i++) {
                $table->string('txt'.$i, 255)->default('');
            }

            $table->timestamps();
        });

        Schema::create('invoice_files', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 255)->default('');
            $table->text('imais')->default('');
            $table->boolean('travado')->default(false);
            // `saida` = gerado aqui; `entrada` = PDF recebido via upload.
            $table->string('tipo', 20)->default('saida');
            $table->string('file_arquivo', 255);
            $table->timestamps();

            $table->index(['tipo', 'created_at']);
        });

        $agora = now();

        // Mesmos valores padrão que estão hoje no painel legado.
        DB::table('invoice_texts')->insert([
            [
                'id' => 1,
                'txt1' => '', 'txt2' => '', 'txt3' => '', 'txt4' => '', 'txt5' => '',
                'txt6' => '', 'txt7' => '', 'txt8' => '', 'txt9' => '', 'txt10' => '',
                'txt11' => '', 'txt12' => '', 'txt13' => '', 'txt14' => '',
                'created_at' => $agora, 'updated_at' => $agora,
            ],
            [
                'id' => 3,
                'txt1' => 'TEKCOM GROUP INC', 'txt2' => 'Arturo Chang', 'txt3' => '5930 NW 99TH AVE',
                'txt4' => 'UNIT #5', 'txt5' => 'DORAL FL 33178', 'txt6' => 'United States',
                'txt7' => '305-363-9913', 'txt8' => 'TEKCOM GROUP INC', 'txt9' => 'Arturo Chang',
                'txt10' => '5930 NW 99TH AVE', 'txt11' => 'UNIT #5', 'txt12' => 'DORAL FL 33178',
                'txt13' => 'United States', 'txt14' => '305-363-9913',
                'created_at' => $agora, 'updated_at' => $agora,
            ],
            [
                'id' => 5,
                'txt1' => 'jb bussines', 'txt2' => 'Ruc 8010276-8', 'txt3' => 'calle rio de janeiro',
                'txt4' => 'CIUDAD DEL ESTE', 'txt5' => 'PARAGUAY', 'txt6' => 'CIF',
                'txt7' => '11013 nw 30st suite 115', 'txt8' => 'MIAMI, FL 33172', 'txt9' => '',
                'txt10' => '', 'txt11' => '', 'txt12' => '', 'txt13' => '', 'txt14' => '',
                'created_at' => $agora, 'updated_at' => $agora,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_files');
        Schema::dropIfExists('invoice_texts');
    }
};
