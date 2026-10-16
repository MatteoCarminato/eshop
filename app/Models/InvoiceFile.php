<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PDF de invoice gerado ou recebido (`tb_invoice_file` no painel legado).
 *
 * `tipo` separa o histórico em duas listas na tela: `saida` (PDF gerado aqui)
 * e `entrada` (PDF enviado por upload). `imais` guarda os IMEIs bipados,
 * concatenados com `-`, exatamente como o legado grava.
 */
class InvoiceFile extends Model
{
    protected $table = 'invoice_files';

    protected $fillable = [
        'nome',
        'imais',
        'travado',
        'tipo',
        'file_arquivo',
    ];

    protected $casts = [
        'travado' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** Código exibido na listagem: id com 6 dígitos (000078). */
    public function codigoFormatado(): string
    {
        return str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * IMEIs como lista, sem vazios.
     *
     * @return array<int, string>
     */
    public function imaisArray(): array
    {
        $imais = array_filter(
            array_map('trim', explode('-', (string) $this->imais)),
            fn (string $imai) => $imai !== ''
        );

        return array_values($imais);
    }
}
