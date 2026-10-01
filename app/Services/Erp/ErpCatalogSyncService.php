<?php

namespace App\Services\Erp;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductClassifierService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sincroniza o catálogo local (tabelas `brands`/`products`) a partir do
 * ERP Consoft (MySQL, conexão somente-leitura `erp` — ver config/database.php).
 *
 * Regras de sincronização (ver docs/Erp-Sync.md para detalhes):
 * - Idempotente: upsert por `recno`.
 * - Não destrutiva: campos "próprios do site" (slug, description, image_url,
 *   featured, category_id, storage, color_name e, em produtos, active/brand_id)
 *   só são definidos na primeira criação do registro local — nunca são
 *   sobrescritos numa atualização.
 * - Marcas devem ser sincronizadas antes de produtos, pois a resolução do
 *   `brand_id` de um produto depende das marcas já terem sido gravadas.
 * - Todo produto novo já é classificado (categoria/armazenamento/cor) na
 *   criação via ProductClassifierService — mesma lógica usada pelo comando
 *   `products:classify` (ver docs/Product-Classification.md).
 */
class ErpCatalogSyncService
{
    public function __construct(private ProductClassifierService $classifier)
    {
    }

    /**
     * Executa a sincronização completa: marcas primeiro, depois produtos.
     *
     * @return array{brands: array{created: int, updated: int, skipped: int}, products: array{created: int, updated: int, skipped: int}}
     */
    public function syncAll(bool $dryRun = false): array
    {
        return [
            'brands' => $this->syncBrands($dryRun),
            'products' => $this->syncProducts($dryRun),
        ];
    }

    /**
     * Sincroniza marcas a partir de MARCAS_MAR.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function syncBrands(bool $dryRun = false): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        try {
            $rows = $this->fetchErpBrands();

            foreach ($rows as $row) {
                if ($this->isDeleted($row)) {
                    $counts['skipped']++;
                    continue;
                }

                $recno = (int) $row->RECNO;
                $name = trim((string) $row->NOMMARC);

                $brand = Brand::where('recno', $recno)->first();

                if (!$brand) {
                    if (!$dryRun) {
                        Brand::create([
                            'recno' => $recno,
                            'name' => $name,
                            'slug' => $this->uniqueSlug(Brand::class, Str::slug($name)),
                            'active' => true,
                            'synced_at' => now(),
                        ]);
                    }
                    $counts['created']++;
                } else {
                    // Nunca tocar em slug/logo_url/active aqui — podem ter sido
                    // editados manualmente no admin do site após o sync inicial.
                    if (!$dryRun) {
                        $brand->update([
                            'name' => $name,
                            'synced_at' => now(),
                        ]);
                    }
                    $counts['updated']++;
                }
            }
        } catch (\Throwable $e) {
            Log::error('ErpCatalogSyncService: falha ao sincronizar marcas do ERP', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $counts;
    }

    /**
     * Sincroniza produtos a partir de PRODUTO_PRO.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function syncProducts(bool $dryRun = false): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        try {
            $brandMap = $this->buildBrandMap();
            $rows = $this->fetchErpProducts();

            foreach ($rows as $row) {
                if ($this->isDeleted($row)) {
                    $counts['skipped']++;
                    continue;
                }

                $recno = (int) $row->RECNO;
                $name = trim((string) $row->NOMELONG);
                $shortName = trim((string) ($row->NOMPRO ?? ''));
                $shortName = $shortName === '' ? null : $shortName;

                $codmarc = isset($row->MARCPRO) ? (int) $row->MARCPRO : null;
                $brandId = $codmarc !== null ? ($brandMap[$codmarc] ?? null) : null;

                $preco3 = $this->toDecimal($row->PRECO3, 0);
                $preven = $this->toDecimal($row->PREVEN ?? null);
                $webPrice = $this->toDecimal($row->PRECOWEB ?? null);

                // PRECO3 às vezes também vem zerado (produto ainda sem preço
                // principal cadastrado no ERP) mas com PREVEN preenchido —
                // nesse caso usa PREVEN como preço principal.
                $price = $preco3 > 0 ? $preco3 : (($preven !== null && $preven > 0) ? $preven : $preco3);

                $syncedAttributes = [
                    'name' => $name,
                    'short_name' => $shortName,
                    'price' => $price,
                    'wholesale_price' => $this->toDecimal($row->PREATAC ?? null),
                    // PRECOWEB costuma vir zerado/vazio no ERP para a maioria dos
                    // produtos — nesse caso cai para o preço principal já resolvido
                    // acima em vez de exibir R$ 0.
                    'web_price' => $webPrice !== null && $webPrice > 0 ? $webPrice : $price,
                    'sale_price' => $preven,
                    'min_price' => $this->toDecimal($row->PREMIN ?? null),
                    'stock' => $this->toDecimal($row->ESTOQUE, 0),
                    'synced_at' => now(),
                ];

                $product = Product::where('recno', $recno)->first();

                if (!$product) {
                    if (!$dryRun) {
                        $category = $this->classifier->classifyCategory($name);

                        Product::create(array_merge($syncedAttributes, [
                            'recno' => $recno,
                            'brand_id' => $brandId,
                            'category_id' => $this->findOrCreateCategory($category),
                            'storage' => $this->classifier->extractStorage($name),
                            'color_name' => $this->classifier->extractColor($name),
                            'model' => $this->classifier->extractModel($name),
                            'slug' => $this->uniqueSlug(Product::class, Str::slug($name)),
                            'active' => (($row->ATIVO ?? null) === 'S') && (($row->ENVIA_SITE ?? null) === 'S'),
                        ]));
                    }
                    $counts['created']++;
                } else {
                    // Nunca tocar em slug/description/image_url/active/featured/brand_id/
                    // category_id/storage/color_name/model aqui — podem ter sido editados
                    // manualmente no admin do site (categoria/storage/cor são só um
                    // ponto de partida gerado por ProductClassifierService na criação).
                    if (!$dryRun) {
                        $product->update($syncedAttributes);
                    }
                    $counts['updated']++;
                }
            }
        } catch (\Throwable $e) {
            Log::error('ErpCatalogSyncService: falha ao sincronizar produtos do ERP', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $counts;
    }

    /**
     * Monta o mapa `CODMARC (ERP) => brand_id (local)` usado para resolver o
     * `brand_id` de cada produto. Construído uma única vez por execução do
     * sync de produtos para evitar N+1 queries.
     *
     * `brands.recno` guarda o `MARCAS_MAR.RECNO`, mas `PRODUTO_PRO.MARCPRO`
     * referencia `MARCAS_MAR.CODMARC` — por isso a composição em dois passos:
     * CODMARC => RECNO (no ERP) e depois RECNO => id (local).
     *
     * @return array<int, int>
     */
    protected function buildBrandMap(): array
    {
        $codmarcToErpRecno = $this->fetchErpBrandCodeMap();

        if ($codmarcToErpRecno->isEmpty()) {
            return [];
        }

        $erpRecnos = $codmarcToErpRecno->map(fn ($recno) => (int) $recno)->values()->all();

        $erpRecnoToLocalId = Brand::whereIn('recno', $erpRecnos)->pluck('id', 'recno');

        $map = [];

        foreach ($codmarcToErpRecno as $codmarc => $erpRecno) {
            $erpRecno = (int) $erpRecno;

            if (isset($erpRecnoToLocalId[$erpRecno])) {
                $map[(int) $codmarc] = (int) $erpRecnoToLocalId[$erpRecno];
            }
        }

        return $map;
    }

    /**
     * Busca todas as marcas do ERP (MARCAS_MAR). Extraído em método próprio
     * (e sobrescrevível) para que os testes unitários não precisem tocar a
     * conexão real `erp`.
     */
    protected function fetchErpBrands(): Collection
    {
        return DB::connection('erp')->table('MARCAS_MAR')->get();
    }

    /**
     * Busca todos os produtos do ERP (PRODUTO_PRO). Extraído em método próprio
     * (e sobrescrevível) pelo mesmo motivo de fetchErpBrands().
     */
    protected function fetchErpProducts(): Collection
    {
        return DB::connection('erp')->table('PRODUTO_PRO')->get();
    }

    /**
     * Mapa bruto `CODMARC => RECNO` direto do ERP (sem filtro de IS_DELETED —
     * marcas excluídas simplesmente não terão correspondente local, então o
     * lookup falha naturalmente). Extraído em método próprio pelo mesmo
     * motivo de fetchErpBrands()/fetchErpProducts().
     *
     * @return \Illuminate\Support\Collection<int|string, int|string>
     */
    protected function fetchErpBrandCodeMap(): Collection
    {
        return DB::connection('erp')->table('MARCAS_MAR')->pluck('RECNO', 'CODMARC');
    }

    protected function isDeleted(object $row): bool
    {
        return ($row->IS_DELETED ?? 'N') === 'Y';
    }

    /**
     * Converte um valor decimal vindo do PDO (string, ex.: "27700.000000")
     * para float, preservando zero como valor válido e null quando aplicável.
     */
    protected function toDecimal(mixed $value, mixed $default = null): ?float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return (float) $value;
    }

    /**
     * Gera um slug único a partir de um slug base, tentando "-2", "-3", etc.
     * em caso de colisão. Usado apenas na criação — o sync nunca atualiza
     * slugs de registros já existentes.
     */
    protected function uniqueSlug(string $modelClass, string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while ($modelClass::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @var array<string, int> Cache local (por execução) de slug de categoria => id.
     */
    private array $categoryCache = [];

    /**
     * @param array{slug: string, name: string} $category
     */
    protected function findOrCreateCategory(array $category): int
    {
        if (!isset($this->categoryCache[$category['slug']])) {
            $this->categoryCache[$category['slug']] = Category::firstOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name']]
            )->id;
        }

        return $this->categoryCache[$category['slug']];
    }
}
