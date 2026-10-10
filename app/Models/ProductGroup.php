<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Grupo de produto do ERP Consoft (tabela `GRUPO`): CELULAR, TABLET, PERFUME,
 * EMAGRECEDOR, etc. É a classificação de primeiro nível vinda do ERP, separada
 * das `categories` locais (que são um chute do ProductClassifierService em
 * cima do nome e podem ser editadas no admin).
 */
class ProductGroup extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'recno',
        'code',
        'name',
        'slug',
        'active',
        'synced_at',
    ];

    protected $casts = [
        'recno' => 'integer',
        'code' => 'integer',
        'active' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
