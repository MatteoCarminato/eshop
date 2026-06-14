<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationSnapshot extends Model
{
    protected $fillable = [
        'type',
        'client_id',
        'created_by',
        'deposit_ids',
        'label',
        'payload',
        'reversed_by',
        'reversed_at',
        'reverse_reason',
    ];

    protected $casts = [
        'deposit_ids' => 'array',
        'payload'     => 'array',
        'reversed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
