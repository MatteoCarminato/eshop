<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappPixExtraction extends Model
{
    protected $fillable = [
        'whatsapp_group_id',
        'message_id',
        'from',
        'image_path',
        'image_hash',
        'mimetype',
        'numero_transacao',
        'pix_nome',
        'pix_valor',
        'pix_data',
        'status',
        'bank_transaction_id',
        'ai_data',
        'ai_raw',
    ];

    protected $casts = [
        'ai_data' => 'array',
    ];

    /**
     * Só o EndToEndId completo do PIX ("E" + ISPB de 8 dígitos + AAAAMMDDHHMM + 11 caracteres = 32)
     * identifica uma transação de forma única. A IA às vezes devolve o ID cortado na quebra de
     * linha do comprovante (ex.: "E18236120202026091", igual para todo PIX do Nubank na mesma
     * dezena do mês) ou a chave PIX do destinatário no lugar do ID — nesses casos o número não
     * pode ser usado sozinho para acusar duplicidade.
     */
    public static function isTxidConfiavel(?string $txid): bool
    {
        return $txid !== null && preg_match('/^E\d{20}[A-Za-z0-9]{11}$/', $txid) === 1;
    }

    /**
     * Checa se essa imagem (hash) já foi usada em algum comprovante confirmado — seja
     * vindo de um grupo de WhatsApp real ou simulado via entrada manual de depósito.
     */
    public static function imageAlreadyUsed(string $imageHash, ?int $excludeId = null): bool
    {
        $query = self::where('status', 'confirmed')->where('image_hash', $imageHash);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public static function extensionFromMime(string $mimetype): string
    {
        return match (true) {
            str_contains($mimetype, 'jpeg') => 'jpg',
            str_contains($mimetype, 'png')  => 'png',
            str_contains($mimetype, 'gif')  => 'gif',
            str_contains($mimetype, 'webp') => 'webp',
            str_contains($mimetype, 'pdf')  => 'pdf',
            default                         => 'bin',
        };
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(WhatsappGroup::class, 'whatsapp_group_id');
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }
}
