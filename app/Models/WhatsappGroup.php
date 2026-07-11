<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappGroup extends Model
{
    protected $fillable = [
        'chat_id',
        'name',
        'participants_count',
        'ai_active',
    ];

    protected $casts = [
        'ai_active' => 'boolean',
    ];
}
