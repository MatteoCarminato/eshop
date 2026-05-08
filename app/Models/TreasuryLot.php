<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreasuryLot extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by',
        'source',
        'client_id',
        'pre_purchase_id',
        'usd_amount',
        'cost_rate',
        'brl_cost',
        'usd_remaining',
        'realized_pnl_brl',
        'status',
        'purchased_at',
        'notes',
    ];

    protected $casts = [
        'usd_amount'       => 'float',
        'cost_rate'        => 'float',
        'brl_cost'         => 'float',
        'usd_remaining'    => 'float',
        'realized_pnl_brl' => 'float',
        'purchased_at'     => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function prePurchase()
    {
        return $this->belongsTo(WalletPrePurchase::class, 'pre_purchase_id');
    }
}
