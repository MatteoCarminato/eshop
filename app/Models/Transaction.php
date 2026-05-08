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
        'realized_pnl_brl',
        'description',
        'status',
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
}
