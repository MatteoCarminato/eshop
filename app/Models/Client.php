<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**x    
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'spread_points',
        'is_exchange_client',
        'type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'spread_points' => 'decimal:2',
        'is_exchange_client' => 'boolean',
        'type' => 'string',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->whereNotNull('email');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', "%{$search}%")
                     ->orWhere('email', 'like', "%{$search}%");
    }

    /**
     * Accessors & Mutators
     */
    public function getFormattedPhoneAttribute(): ?string
    {
        if (!$this->phone) {
            return null;
        }

        // Exemplo de formatação de telefone brasileiro
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $this->phone);
    }

    public function groups()
    {
        return $this->belongsToMany(\App\Models\Group::class, 'group_client');
    }
    /**
     * Relacionamento: carteiras do cliente
     */
    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    /**
     * Relacionamento: transações do cliente
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
