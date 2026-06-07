<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappScheduledMessage extends Model
{
    protected $fillable = [
        'name',
        'message',
        'client_ids',
        'group_ids',
        'weekdays',
        'is_active',
        'last_run_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'client_ids' => 'array',
            'group_ids' => 'array',
            'weekdays' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'created_by_user_id' => 'integer',
        ];
    }
}
