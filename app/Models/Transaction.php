<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'client_id',
        'type',
        'currency',
        'amount',
        'payment_method',
        'converted_currency',
        'converted_amount',
        'exchange_rate',
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
}
