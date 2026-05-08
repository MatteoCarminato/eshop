<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Role extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_admin',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
    ];

    /**
     * Permissões de módulo (linhas da pivot `role_module`).
     */
    public function modulePermissions(): HasMany
    {
        return $this->hasMany(RoleModule::class);
    }

    /**
     * Usuários que possuem este cargo.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Conjunto de keys de módulos atribuídos a este cargo.
     *
     * @return array<int, string>
     */
    public function moduleKeys(): array
    {
        return $this->modulePermissions()->pluck('module_key')->all();
    }

    /**
     * Verifica se o cargo possui acesso ao módulo informado.
     */
    public function hasModule(string $moduleKey): bool
    {
        if ($this->is_admin) {
            return true;
        }

        return $this->modulePermissions()
            ->where('module_key', $moduleKey)
            ->exists();
    }

    /**
     * Sincroniza as permissões de módulo do cargo com o array informado.
     *
     * @param array<int, string> $moduleKeys
     */
    public function syncModules(array $moduleKeys): void
    {
        $valid = array_keys(config('modules.modules', []));
        $filtered = array_values(array_unique(array_intersect($moduleKeys, $valid)));

        $this->modulePermissions()->delete();

        foreach ($filtered as $key) {
            $this->modulePermissions()->create(['module_key' => $key]);
        }
    }

    /**
     * Mutator: garante slug único e em formato kebab.
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value;

        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = static::generateUniqueSlug($value, $this->id);
        }
    }

    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'cargo';
        $slug = $base;
        $i = 1;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
