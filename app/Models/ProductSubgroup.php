<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Subgrupo de produto do ERP Consoft (tabela `SUB`): TABLET APPLE, MOUNJARO,
 * SWAP 14 PRO 256GB 'A+' (USA), etc. É mais específico que o grupo, mas NÃO é
 * filho dele — `SUB` não tem coluna apontando para `GRUPO`, as duas listas são
 * planas e o produto carrega os dois códigos de forma independente.
 */
class ProductSubgroup extends Model
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
