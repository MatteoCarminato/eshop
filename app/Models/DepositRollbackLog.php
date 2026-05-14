<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepositRollbackLog extends Model
{
    protected $fillable = [
        'deposit_transaction_id',
        'client_id',
        'deleted_by',
        'reason',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
