<?php

namespace App\Services\Erp;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductSubgroup;
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
 * - Marcas, grupos e subgrupos devem ser sincronizados antes de produtos, pois
 *   a resolução de `brand_id`/`product_group_id`/`product_subgroup_id` de um
 *   produto depende desses registros já terem sido gravados.
 * - Grupo e subgrupo (GRUPO/SUB) são exceção à regra de "só na criação": eles
 *   SÃO atualizados a cada sync. Diferente de brand_id/category_id, não há
 *   tela no admin para reatribuí-los — são classificação crua do ERP, e o
 *   ponto deles é justamente espelhar o ERP para filtrar o catálogo.
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
            'groups' => $this->syncGroups($dryRun),
            'subgroups' => $this->syncSubgroups($dryRun),
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
     * Sincroniza grupos de produto a partir de GRUPO (NOMGRU).
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function syncGroups(bool $dryRun = false): array
    {
        return $this->syncClassification(
            ProductGroup::class,
            $this->fetchErpGroups(),
            'CODGRU',
            'NOMGRU',
            'grupos',
            $dryRun,
        );
    }

    /**
     * Sincroniza subgrupos de produto a partir de SUB (NOMSGRU).
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function syncSubgroups(bool $dryRun = false): array
    {
        return $this->syncClassification(
            ProductSubgroup::class,
            $this->fetchErpSubgroups(),
            'CODSGRU',
            'NOMSGRU',
            'subgrupos',
            $dryRun,
        );
    }

    /**
     * Upsert comum de GRUPO/SUB: as duas tabelas têm a mesma forma no ERP
     * (RECNO + código + nome) e o mesmo destino local, então a única coisa que
     * muda é o model e o nome das colunas.
     *
     * Igual a marcas, não sobrescreve `slug` nem `active` de registro já
     * existente — podem ter sido ajustados no site.
     *
     * @param class-string<ProductGroup|ProductSubgroup> $modelClass
     * @return array{created: int, updated: int, skipped: int}
     */
    protected function syncClassification(
        string $modelClass,
        Collection $rows,
        string $codeColumn,
        string $nameColumn,
        string $label,
        bool $dryRun = false,
    ): array {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        try {
            foreach ($rows as $row) {
                if ($this->isDeleted($row)) {
                    $counts['skipped']++;
                    continue;
                }

                $code = (int) ($row->{$codeColumn} ?? 0);
                $name = trim((string) ($row->{$nameColumn} ?? ''));

                // Sem código não há como ligar produto nenhum a este registro.
                if ($code === 0 || $name === '') {
                    $counts['skipped']++;
                    continue;
                }

                $recno = (int) $row->RECNO;
                $existente = $modelClass::where('recno', $recno)->first();

                if (!$existente) {
                    if (!$dryRun) {
                        $modelClass::create([
                            'recno' => $recno,
                            'code' => $code,
                            'name' => $name,
                            'slug' => $this->uniqueSlug($modelClass, Str::slug($name)),
                            'active' => true,
                            'synced_at' => now(),
                        ]);
                    }
                    $counts['created']++;
                } else {
                    if (!$dryRun) {
                        $existente->update([
                            'code' => $code,
                            'name' => $name,
                            'synced_at' => now(),
                        ]);
                    }
                    $counts['updated']++;
                }
            }
        } catch (\Throwable $e) {
            Log::error("ErpCatalogSyncService: falha ao sincronizar {$label} do ERP", [
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
            // `code => id local` em uma etapa: diferente de marcas, guardamos o
            // CODGRU/CODSGRU na tabela local, então não precisa do pulo extra
            // por RECNO.
            $groupMap = ProductGroup::pluck('id', 'code')->all();
            $subgroupMap = ProductSubgroup::pluck('id', 'code')->all();
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

                // PRODUTO_PRO.GRUPRO junta com GRUPO.CODGRU e PRODUTO_PRO.SGRUPRO
                // com SUB.CODSGRU (nenhum dos dois junta por RECNO). Código 0 ou
                // sem correspondência fica null em vez de barrar o produto.
                $codgru = (int) ($row->GRUPRO ?? 0);
                $codsgru = (int) ($row->SGRUPRO ?? 0);
                $groupId = $codgru !== 0 ? ($groupMap[$codgru] ?? null) : null;
                $subgroupId = $codsgru !== 0 ? ($subgroupMap[$codsgru] ?? null) : null;

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
                    // Atualizados a cada sync (ver docblock da classe): são
                    // classificação do ERP, não há edição manual no site.
                    'product_group_id' => $groupId,
                    'product_subgroup_id' => $subgroupId,
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
     * Busca os grupos do ERP (GRUPO). Extraído em método próprio (e
     * sobrescrevível) pelo mesmo motivo de fetchErpBrands().
     */
    protected function fetchErpGroups(): Collection
    {
        return DB::connection('erp')->table('GRUPO')->get();
    }

    /**
     * Busca os subgrupos do ERP (SUB). Mesmo motivo acima.
     */
    protected function fetchErpSubgroups(): Collection
    {
        return DB::connection('erp')->table('SUB')->get();
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
