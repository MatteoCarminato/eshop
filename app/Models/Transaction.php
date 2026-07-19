<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'parent_transaction_id',
        'type',
        'currency',
        'amount',
        'payment_method',
        'converted_currency',
        'converted_amount',
        'exchange_rate',
        'realized_pnl_usd',
        'realized_pnl_brl',
        'treasury_sale_id',
        'description',
        'status',
        'whatsapp_pix_extraction_id',
    ];

    /**
     * Relacionamento com o cliente.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Transação "pai" (caso este registro tenha sido gerado por um split).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_transaction_id');
    }

    /**
     * Comprovante de PIX (WhatsApp) que originou este depósito, quando aplicável.
     */
    public function whatsappPixExtraction(): BelongsTo
    {
        return $this->belongsTo(WhatsappPixExtraction::class);
    }
}
