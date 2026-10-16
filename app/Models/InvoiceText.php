<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Textos editáveis de um modelo de invoice (`tb_invoice` no painel legado).
 *
 * Os ids são fixos e batem com os do legado: 1 = modelo clássico,
 * 3 = PCS Wireless, 5 = SCal Mobile. Por isso o incremento é desligado.
 */
class InvoiceText extends Model
{
    /** Modelos conhecidos — os mesmos ids usados pela tela. */
    public const MODELO_CLASSICO = 1;
    public const MODELO_PCS = 3;
    public const MODELO_SCAL = 5;

    protected $table = 'invoice_texts';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'txt1', 'txt2', 'txt3', 'txt4', 'txt5', 'txt6', 'txt7',
        'txt8', 'txt9', 'txt10', 'txt11', 'txt12', 'txt13', 'txt14',
    ];

    /**
     * Devolve a linha do modelo, criando-a vazia se ainda não existir.
     * Evita que a tela quebre num banco novo sem as linhas semeadas.
     */
    public static function doModelo(int $id): self
    {
        return static::firstOrCreate(['id' => $id]);
    }

    /**
     * Os textos como array txt1..txtN, já com string vazia no lugar de null.
     *
     * @return array<string, string>
     */
    public function textos(int $quantidade = 14): array
    {
        $textos = [];

        for ($i = 1; $i <= $quantidade; $i++) {
            $textos['txt'.$i] = (string) ($this->{'txt'.$i} ?? '');
        }

        return $textos;
    }
}
