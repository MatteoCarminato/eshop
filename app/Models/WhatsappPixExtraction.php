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

    public function group(): BelongsTo
    {
        return $this->belongsTo(WhatsappGroup::class, 'whatsapp_group_id');
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }
}
