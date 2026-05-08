<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreasurySale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'created_by',
        'usd_amount',
        'sell_rate',
        'brl_total',
        'cost_brl',
        'realized_pnl_brl',
        'realized_pnl_usd',
        'notes',
        'transaction_ids',
    ];

    protected $casts = [
        'usd_amount'       => 'float',
        'sell_rate'        => 'float',
        'brl_total'        => 'float',
        'cost_brl'         => 'float',
        'realized_pnl_brl' => 'float',
        'realized_pnl_usd' => 'float',
        'transaction_ids'  => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
