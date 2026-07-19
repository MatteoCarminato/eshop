<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappGroup extends Model
{
    protected $fillable = [
        'client_id',
        'chat_id',
        'name',
        'participants_count',
        'ai_active',
    ];

    protected $casts = [
        'ai_active' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
