<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleModule extends Model
{
    protected $table = 'role_module';

    protected $fillable = [
        'role_id',
        'module_key',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
