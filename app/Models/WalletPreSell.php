<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WalletPreSell extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'source_transaction_id',
        'transaction_id',
        'created_by',
        'brl_amount',
        'usd_amount',
        'sell_rate',
        'brl_remaining',
        'usd_remaining',
        'status',
        'notes',
    ];

    protected $casts = [
        'brl_amount'    => 'float',
        'usd_amount'    => 'float',
        'sell_rate'     => 'float',
        'brl_remaining' => 'float',
        'usd_remaining' => 'float',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sourceTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'source_transaction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
