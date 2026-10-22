<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'recno',
        'erp_code',
        'web_code',
        'brand_id',
        'category_id',
        'product_group_id',
        'product_subgroup_id',
        'name',
        'short_name',
        'price',
        'wholesale_price',
        'web_price',
        'sale_price',
        'promo_price',
        'min_price',
        'average_price',
        'stock',
        'storage',
        'color_name',
        'model',
        'slug',
        'description',
        'image_url',
        'active',
        'featured',
        'synced_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'recno' => 'integer',
        'price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'web_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'promo_price' => 'decimal:2',
        'min_price' => 'decimal:2',
        'average_price' => 'decimal:2',
        'stock' => 'decimal:4',
        'active' => 'boolean',
        'featured' => 'boolean',
        'synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', "%{$search}%")
                     ->orWhere('short_name', 'like', "%{$search}%");
    }

    /**
     * Relacionamento: marca do produto.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Grupo do ERP (GRUPO/NOMGRU) — ex.: CELULAR, TABLET.
     */
    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }

    /**
     * Subgrupo do ERP (SUB/NOMSGRU) — ex.: TABLET APPLE, MOUNJARO. Não é
     * filho do grupo: são duas classificações independentes.
     */
    public function productSubgroup(): BelongsTo
    {
        return $this->belongsTo(ProductSubgroup::class);
    }

    /**
     * Filtros por grupo/subgrupo. Separados por id local e por código do ERP
     * de propósito: um scope que aceitasse "id ou código" no mesmo parâmetro
     * poderia casar o registro errado em silêncio, já que id e code são
     * sequências independentes (o id local 5 não é o grupo de código 5).
     */
    public function scopeOfGroup($query, int $groupId)
    {
        return $query->where('product_group_id', $groupId);
    }

    public function scopeOfSubgroup($query, int $subgroupId)
    {
        return $query->where('product_subgroup_id', $subgroupId);
    }

    public function scopeOfGroupCode($query, int $code)
    {
        return $query->whereHas('productGroup', fn ($q) => $q->where('code', $code));
    }

    public function scopeOfSubgroupCode($query, int $code)
    {
        return $query->whereHas('productSubgroup', fn ($q) => $q->where('code', $code));
    }

    /**
     * Galeria de fotos do produto (além da foto principal em `image_url`),
     * exibida no detalhe do produto no Orbita. Ordenada por `sort_order`.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Accessors & Mutators
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'R$ ' . number_format((float) $this->price, 2, ',', '.');
    }

    /**
     * Promoção válida: `promo_price` preenchida E abaixo do Preço Web. Promo
     * maior ou igual ao Preço Web é erro de digitação — seria um "desconto"
     * negativo na vitrine —, então é simplesmente ignorada.
     */
    public function hasSitePromo(): bool
    {
        $promo = (float) $this->promo_price;

        return $promo > 0 && $promo < (float) $this->web_price;
    }

    /**
     * Código do anúncio nas integrações de fora (`<codigo>` do feed XML e
     * `code` da API do ComprasPY): o CODWEB do ERP. Sem ele, cai no CODNNN,
     * depois no recno e por último no id local — os dois canais sempre
     * mandam o mesmo código pro mesmo produto.
     */
    public function feedCode(): string
    {
        foreach ([$this->web_code, $this->erp_code] as $code) {
            $code = trim((string) $code);
            if ($code !== '') {
                return $code;
            }
        }

        return (string) ($this->recno ?? $this->id);
    }

    /**
     * Preço que a loja online exibe: a promoção do painel quando válida,
     * senão o Preço Web. `price` (PRECO3/varejo do ERP) não é usado na loja —
     * ver OrbitaCatalogService.
     */
    public function sitePrice(): float
    {
        return $this->hasSitePromo() ? (float) $this->promo_price : (float) $this->web_price;
    }

    /**
     * Preço "de" (riscado) da loja: só existe quando há promoção no painel —
     * aí o Preço Web é o valor riscado. Sem promoção, null.
     */
    public function siteCompareAtPrice(): ?float
    {
        return $this->hasSitePromo() ? (float) $this->web_price : null;
    }

    /**
     * Indica se o produto foi importado/sincronizado do ERP (possui RECNO).
     */
    public function getIsFromErpAttribute(): bool
    {
        return !is_null($this->recno);
    }
}
