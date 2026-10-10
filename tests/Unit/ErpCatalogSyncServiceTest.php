<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductSubgroup;
use App\Services\Catalog\ProductClassifierService;
use App\Services\Erp\ErpCatalogSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Testa a lógica de sincronização isoladamente da conexão real `erp`:
 * o método fabricante (makeService) sobrescreve fetchErpBrands()/
 * fetchErpProducts()/fetchErpBrandCodeMap() com dados fake, então nenhuma
 * chamada de rede ao servidor ERP real acontece nestes testes. As tabelas
 * locais `brands`/`products` usam o sqlite em memória padrão dos testes
 * (ver phpunit.xml), via RefreshDatabase.
 */
class ErpCatalogSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeService(
        Collection $brands,
        Collection $products,
        ?Collection $brandCodeMap = null,
        ?Collection $groups = null,
        ?Collection $subgroups = null,
    ): ErpCatalogSyncService {
        return new class($brands, $products, $brandCodeMap, $groups, $subgroups) extends ErpCatalogSyncService {
            public function __construct(
                private Collection $brands,
                private Collection $products,
                private ?Collection $brandCodeMap,
                private ?Collection $groups,
                private ?Collection $subgroups,
            ) {
                parent::__construct(new ProductClassifierService());
            }

            protected function fetchErpGroups(): Collection
            {
                return $this->groups ?? collect();
            }

            protected function fetchErpSubgroups(): Collection
            {
                return $this->subgroups ?? collect();
            }

            protected function fetchErpBrands(): Collection
            {
                return $this->brands;
            }

            protected function fetchErpProducts(): Collection
            {
                return $this->products;
            }

            protected function fetchErpBrandCodeMap(): Collection
            {
                return $this->brandCodeMap ?? collect();
            }
        };
    }

    protected function brandRow(array $overrides = []): object
    {
        return (object) array_merge([
            'RECNO' => 1,
            'IS_DELETED' => 'N',
            'CODMARC' => 1,
            'NOMMARC' => 'APPLE',
        ], $overrides);
    }

    protected function groupRow(array $overrides = []): object
    {
        return (object) array_merge([
            'RECNO' => 6,
            'IS_DELETED' => 'N',
            'CODGRU' => 6,
            'NOMGRU' => 'CELULAR',
        ], $overrides);
    }

    protected function subgroupRow(array $overrides = []): object
    {
        return (object) array_merge([
            'RECNO' => 8,
            'IS_DELETED' => 'N',
            'CODSGRU' => 8,
            'NOMSGRU' => 'IPHONE LACRADO',
        ], $overrides);
    }

    protected function productRow(array $overrides = []): object
    {
        return (object) array_merge([
            'RECNO' => 100,
            'IS_DELETED' => 'N',
            'GRUPRO' => 6,
            'SGRUPRO' => 8,
            'ATIVO' => 'S',
            'ENVIA_SITE' => 'S',
            'MARCPRO' => 1,
            'NOMELONG' => 'IPHONE 16 PRO MAX 256GB',
            'NOMPRO' => 'IPHONE 16 PRO MAX',
            'PRECO3' => '7999.000000',
            'PREATAC' => '7500.000000',
            'PRECOWEB' => '7999.00',
            'PREVEN' => '7999.000000',
            'PREMIN' => '7000.000000',
            'ESTOQUE' => '10.000000000',
        ], $overrides);
    }

    public function test_sync_brands_creates_new_brand(): void
    {
        $service = $this->makeService(collect([$this->brandRow()]), collect());

        $result = $service->syncBrands();

        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0], $result);

        $brand = Brand::where('recno', 1)->first();
        $this->assertNotNull($brand);
        $this->assertSame('APPLE', $brand->name);
        $this->assertSame('apple', $brand->slug);
        $this->assertTrue($brand->active);
        $this->assertNotNull($brand->synced_at);
    }

    public function test_sync_brands_updates_existing_brand_without_touching_site_owned_fields(): void
    {
        $brand = Brand::create([
            'recno' => 1,
            'name' => 'APPLE OLD',
            'slug' => 'apple-custom-slug',
            'logo_url' => '/img/apple.png',
            'active' => false,
            'synced_at' => now()->subDay(),
        ]);

        $service = $this->makeService(collect([$this->brandRow(['NOMMARC' => 'APPLE NOVO'])]), collect());

        $result = $service->syncBrands();

        $this->assertSame(['created' => 0, 'updated' => 1, 'skipped' => 0], $result);

        $brand->refresh();
        $this->assertSame('APPLE NOVO', $brand->name);
        $this->assertSame('apple-custom-slug', $brand->slug);
        $this->assertSame('/img/apple.png', $brand->logo_url);
        $this->assertFalse($brand->active);
    }

    public function test_sync_brands_skips_deleted_rows(): void
    {
        $service = $this->makeService(collect([$this->brandRow(['IS_DELETED' => 'Y'])]), collect());

        $result = $service->syncBrands();

        $this->assertSame(['created' => 0, 'updated' => 0, 'skipped' => 1], $result);
        $this->assertSame(0, Brand::count());
    }

    public function test_sync_brands_generates_unique_slug_on_name_collision(): void
    {
        Brand::create([
            'recno' => 99,
            'name' => 'Existing',
            'slug' => 'apple',
            'active' => true,
        ]);

        $service = $this->makeService(collect([$this->brandRow(['RECNO' => 1, 'NOMMARC' => 'Apple'])]), collect());

        $service->syncBrands();

        $brand = Brand::where('recno', 1)->first();
        $this->assertSame('apple-2', $brand->slug);
    }

    public function test_sync_brands_dry_run_does_not_persist(): void
    {
        $service = $this->makeService(collect([$this->brandRow()]), collect());

        $result = $service->syncBrands(true);

        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0], $result);
        $this->assertSame(0, Brand::count());
    }

    public function test_sync_products_creates_new_product_and_resolves_brand_via_codmarc(): void
    {
        $brand = Brand::create([
            'recno' => 5, // MARCAS_MAR.RECNO
            'name' => 'APPLE',
            'slug' => 'apple',
            'active' => true,
        ]);

        // CODMARC (2) => MARCAS_MAR.RECNO (5), como no ERP real (PRODUTO_PRO.MARCPRO
        // referencia CODMARC, não RECNO).
        $brandCodeMap = collect(['2' => '5']);

        $service = $this->makeService(
            collect(),
            collect([$this->productRow(['MARCPRO' => 2])]),
            $brandCodeMap
        );

        $result = $service->syncProducts();

        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0], $result);

        $product = Product::where('recno', 100)->first();
        $this->assertNotNull($product);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('IPHONE 16 PRO MAX 256GB', $product->name);
        $this->assertSame('IPHONE 16 PRO MAX', $product->short_name);
        $this->assertTrue($product->active);
        $this->assertSame('iphone-16-pro-max-256gb', $product->slug);
    }

    public function test_sync_products_leaves_brand_id_null_when_codmarc_unresolved(): void
    {
        $service = $this->makeService(collect(), collect([$this->productRow(['MARCPRO' => 999])]), collect());

        $service->syncProducts();

        $product = Product::where('recno', 100)->first();
        $this->assertNull($product->brand_id);
    }

    public function test_sync_products_updates_existing_product_without_touching_site_owned_fields(): void
    {
        $reassignedBrand = Brand::create(['recno' => 6, 'name' => 'SAMSUNG', 'slug' => 'samsung', 'active' => true]);
        Brand::create(['recno' => 5, 'name' => 'APPLE', 'slug' => 'apple', 'active' => true]);

        $product = Product::create([
            'recno' => 100,
            'brand_id' => $reassignedBrand->id, // admin reatribuiu manualmente a marca
            'name' => 'OLD NAME',
            'short_name' => 'OLD',
            'price' => 1,
            'stock' => 1,
            'slug' => 'custom-slug',
            'description' => 'Descrição escrita pelo admin',
            'image_url' => '/img/custom.png',
            'active' => false, // admin desativou manualmente
            'featured' => true, // admin destacou manualmente
            'synced_at' => now()->subDay(),
        ]);

        $brandCodeMap = collect(['2' => '5']);

        $service = $this->makeService(
            collect(),
            collect([$this->productRow(['MARCPRO' => 2, 'NOMELONG' => 'IPHONE 16 PRO MAX 256GB NOVO'])]),
            $brandCodeMap
        );

        $result = $service->syncProducts();

        $this->assertSame(['created' => 0, 'updated' => 1, 'skipped' => 0], $result);

        $product->refresh();
        $this->assertSame('IPHONE 16 PRO MAX 256GB NOVO', $product->name);
        $this->assertSame(7999.0, (float) $product->price);
        $this->assertSame('custom-slug', $product->slug);
        $this->assertSame('Descrição escrita pelo admin', $product->description);
        $this->assertSame('/img/custom.png', $product->image_url);
        $this->assertFalse($product->active);
        $this->assertTrue($product->featured);
        $this->assertSame($reassignedBrand->id, $product->brand_id);
    }

    public function test_sync_products_skips_deleted_rows(): void
    {
        $service = $this->makeService(collect(), collect([$this->productRow(['IS_DELETED' => 'Y'])]), collect());

        $result = $service->syncProducts();

        $this->assertSame(['created' => 0, 'updated' => 0, 'skipped' => 1], $result);
        $this->assertSame(0, Product::count());
    }

    public function test_sync_products_generates_unique_slug_on_name_collision(): void
    {
        Product::create([
            'recno' => 50,
            'name' => 'Existing',
            'slug' => 'iphone-16-pro-max-256gb',
            'price' => 1,
            'stock' => 1,
            'active' => true,
        ]);

        $service = $this->makeService(collect(), collect([$this->productRow()]), collect());

        $service->syncProducts();

        $product = Product::where('recno', 100)->first();
        $this->assertSame('iphone-16-pro-max-256gb-2', $product->slug);
    }

    public function test_sync_products_treats_zero_price_as_valid_value_not_null(): void
    {
        $service = $this->makeService(
            collect(),
            collect([$this->productRow(['PRECO3' => '0.000000', 'PREVEN' => '0.000000', 'PREATAC' => null])]),
            collect()
        );

        $service->syncProducts();

        $product = Product::where('recno', 100)->first();
        $this->assertSame(0.0, (float) $product->price);
        $this->assertNull($product->wholesale_price);
    }

    public function test_sync_products_falls_back_web_price_to_preco3_when_precoweb_is_zero(): void
    {
        $service = $this->makeService(
            collect(),
            collect([$this->productRow(['PRECOWEB' => '0.00'])]),
            collect()
        );

        $service->syncProducts();

        $product = Product::where('recno', 100)->first();
        $this->assertSame(7999.0, (float) $product->price);
        $this->assertSame(7999.0, (float) $product->web_price);
    }

    public function test_sync_products_falls_back_web_price_to_preco3_when_precoweb_is_null(): void
    {
        $service = $this->makeService(
            collect(),
            collect([$this->productRow(['PRECOWEB' => null])]),
            collect()
        );

        $service->syncProducts();

        $product = Product::where('recno', 100)->first();
        $this->assertSame(7999.0, (float) $product->web_price);
    }

    public function test_sync_products_keeps_precoweb_when_greater_than_zero(): void
    {
        $service = $this->makeService(
            collect(),
            collect([$this->productRow(['PRECO3' => '1390.000000', 'PRECOWEB' => '1230.00'])]),
            collect()
        );

        $service->syncProducts();

        $product = Product::where('recno', 100)->first();
        $this->assertSame(1230.0, (float) $product->web_price);
    }

    public function test_sync_products_falls_back_price_to_preven_when_preco3_is_zero(): void
    {
        $service = $this->makeService(
            collect(),
            collect([$this->productRow([
                'PRECO3' => '0.000000',
                'PRECOWEB' => '0.00',
                'PREVEN' => '1325.000000',
            ])]),
            collect()
        );

        $service->syncProducts();

        $product = Product::where('recno', 100)->first();
        $this->assertSame(1325.0, (float) $product->price);
        $this->assertSame(1325.0, (float) $product->web_price);
        $this->assertSame(1325.0, (float) $product->sale_price);
    }

    public function test_sync_products_keeps_price_zero_when_preco3_and_preven_are_both_zero(): void
    {
        $service = $this->makeService(
            collect(),
            collect([$this->productRow([
                'PRECO3' => '0.000000',
                'PRECOWEB' => '0.00',
                'PREVEN' => '0.000000',
            ])]),
            collect()
        );

        $service->syncProducts();

        $product = Product::where('recno', 100)->first();
        $this->assertSame(0.0, (float) $product->price);
        $this->assertSame(0.0, (float) $product->web_price);
    }

    public function test_sync_all_runs_brands_before_products_and_merges_results(): void
    {
        $brandCodeMap = collect(['1' => '1']);

        $service = $this->makeService(
            collect([$this->brandRow()]),
            collect([$this->productRow(['MARCPRO' => 1])]),
            $brandCodeMap
        );

        $result = $service->syncAll();

        $this->assertArrayHasKey('brands', $result);
        $this->assertArrayHasKey('products', $result);
        $this->assertSame(1, $result['brands']['created']);
        $this->assertSame(1, $result['products']['created']);

        $brand = Brand::where('recno', 1)->first();
        $product = Product::where('recno', 100)->first();
        $this->assertSame($brand->id, $product->brand_id);
    }

    // ---- grupos e subgrupos (GRUPO / SUB) ---------------------------------

    public function test_sync_groups_creates_group_with_code_and_slug(): void
    {
        $service = $this->makeService(collect(), collect(), null, collect([$this->groupRow()]));

        $result = $service->syncGroups();

        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0], $result);

        $group = ProductGroup::where('recno', 6)->first();
        $this->assertNotNull($group);
        $this->assertSame(6, $group->code);
        $this->assertSame('CELULAR', $group->name);
        $this->assertSame('celular', $group->slug);
        $this->assertTrue($group->active);
        $this->assertNotNull($group->synced_at);
    }

    public function test_sync_subgroups_creates_subgroup(): void
    {
        $service = $this->makeService(collect(), collect(), null, null, collect([$this->subgroupRow()]));

        $result = $service->syncSubgroups();

        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0], $result);

        $subgroup = ProductSubgroup::where('recno', 8)->first();
        $this->assertSame(8, $subgroup->code);
        $this->assertSame('IPHONE LACRADO', $subgroup->name);
    }

    public function test_sync_groups_updates_name_without_touching_slug_or_active(): void
    {
        $group = ProductGroup::create([
            'recno' => 6,
            'code' => 6,
            'name' => 'CELULAR ANTIGO',
            'slug' => 'slug-escolhido-a-mao',
            'active' => false,
        ]);

        $service = $this->makeService(collect(), collect(), null, collect([$this->groupRow(['NOMGRU' => 'CELULAR NOVO'])]));

        $result = $service->syncGroups();

        $this->assertSame(['created' => 0, 'updated' => 1, 'skipped' => 0], $result);

        $group->refresh();
        $this->assertSame('CELULAR NOVO', $group->name);
        $this->assertSame('slug-escolhido-a-mao', $group->slug);
        $this->assertFalse($group->active);
    }

    public function test_sync_groups_skips_deleted_and_codeless_rows(): void
    {
        $service = $this->makeService(collect(), collect(), null, collect([
            $this->groupRow(['RECNO' => 1, 'IS_DELETED' => 'Y']),
            $this->groupRow(['RECNO' => 2, 'CODGRU' => 0]),
            $this->groupRow(['RECNO' => 3, 'NOMGRU' => '  ']),
        ]));

        $result = $service->syncGroups();

        $this->assertSame(['created' => 0, 'updated' => 0, 'skipped' => 3], $result);
        $this->assertSame(0, ProductGroup::count());
    }

    public function test_sync_groups_dry_run_does_not_persist(): void
    {
        $service = $this->makeService(collect(), collect(), null, collect([$this->groupRow()]), collect([$this->subgroupRow()]));

        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0], $service->syncGroups(true));
        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0], $service->syncSubgroups(true));

        $this->assertSame(0, ProductGroup::count());
        $this->assertSame(0, ProductSubgroup::count());
    }

    public function test_sync_products_resolves_group_and_subgroup_by_code_not_recno(): void
    {
        // RECNO e código propositalmente diferentes: se a resolução usasse
        // RECNO por engano, o produto ficaria sem grupo/subgrupo.
        $service = $this->makeService(
            collect(),
            collect([$this->productRow(['GRUPRO' => 6, 'SGRUPRO' => 8])]),
            null,
            collect([$this->groupRow(['RECNO' => 900, 'CODGRU' => 6])]),
            collect([$this->subgroupRow(['RECNO' => 901, 'CODSGRU' => 8])]),
        );

        $service->syncGroups();
        $service->syncSubgroups();
        $service->syncProducts();

        $product = Product::where('recno', 100)->first();
        $this->assertNotNull($product->product_group_id);
        $this->assertSame('CELULAR', $product->productGroup->name);
        $this->assertSame('IPHONE LACRADO', $product->productSubgroup->name);
    }

    public function test_sync_products_leaves_group_null_when_code_has_no_match(): void
    {
        $service = $this->makeService(
            collect(),
            collect([$this->productRow(['GRUPRO' => 777, 'SGRUPRO' => 0])]),
            null,
            collect([$this->groupRow()]),
            collect([$this->subgroupRow()]),
        );

        $service->syncGroups();
        $service->syncSubgroups();
        $service->syncProducts();

        $product = Product::where('recno', 100)->first();
        $this->assertNull($product->product_group_id);
        $this->assertNull($product->product_subgroup_id);
    }

    public function test_resync_updates_group_and_subgroup_of_existing_product(): void
    {
        // Grupo/subgrupo são classificação do ERP: ao contrário de brand_id,
        // um re-sync DEVE reposicionar o produto se o ERP mudou.
        $groups = collect([$this->groupRow(['RECNO' => 6, 'CODGRU' => 6, 'NOMGRU' => 'CELULAR']),
                           $this->groupRow(['RECNO' => 3, 'CODGRU' => 3, 'NOMGRU' => 'TABLET'])]);
        $subgroups = collect([$this->subgroupRow(['RECNO' => 8, 'CODSGRU' => 8, 'NOMSGRU' => 'IPHONE LACRADO']),
                              $this->subgroupRow(['RECNO' => 4, 'CODSGRU' => 4, 'NOMSGRU' => 'TABLET APPLE'])]);

        $primeiro = $this->makeService(collect(), collect([$this->productRow(['GRUPRO' => 6, 'SGRUPRO' => 8])]), null, $groups, $subgroups);
        $primeiro->syncGroups();
        $primeiro->syncSubgroups();
        $primeiro->syncProducts();

        $this->assertSame('CELULAR', Product::where('recno', 100)->first()->productGroup->name);

        // mesmo produto, agora reclassificado no ERP
        $segundo = $this->makeService(collect(), collect([$this->productRow(['GRUPRO' => 3, 'SGRUPRO' => 4])]), null, $groups, $subgroups);
        $result = $segundo->syncProducts();

        $this->assertSame(1, $result['updated']);

        $product = Product::where('recno', 100)->first();
        $this->assertSame('TABLET', $product->productGroup->name);
        $this->assertSame('TABLET APPLE', $product->productSubgroup->name);
    }

    public function test_scopes_filter_products_by_group_and_subgroup(): void
    {
        $service = $this->makeService(
            collect(),
            collect([
                $this->productRow(['RECNO' => 100, 'NOMELONG' => 'IPHONE 16', 'GRUPRO' => 6, 'SGRUPRO' => 8]),
                $this->productRow(['RECNO' => 101, 'NOMELONG' => 'IPAD 10TH', 'GRUPRO' => 3, 'SGRUPRO' => 4]),
            ]),
            null,
            collect([$this->groupRow(['RECNO' => 6, 'CODGRU' => 6, 'NOMGRU' => 'CELULAR']),
                     $this->groupRow(['RECNO' => 3, 'CODGRU' => 3, 'NOMGRU' => 'TABLET'])]),
            collect([$this->subgroupRow(['RECNO' => 8, 'CODSGRU' => 8, 'NOMSGRU' => 'IPHONE LACRADO']),
                     $this->subgroupRow(['RECNO' => 4, 'CODSGRU' => 4, 'NOMSGRU' => 'TABLET APPLE'])]),
        );

        $service->syncGroups();
        $service->syncSubgroups();
        $service->syncProducts();

        $celular = ProductGroup::where('code', 6)->first();

        // por id local
        $this->assertSame(1, Product::ofGroup($celular->id)->count());
        $this->assertSame('IPHONE 16', Product::ofGroup($celular->id)->first()->name);
        $this->assertSame('IPAD 10TH', Product::ofSubgroup(ProductSubgroup::where('code', 4)->first()->id)->first()->name);

        // pelo código do ERP
        $this->assertSame('IPHONE 16', Product::ofGroupCode(6)->first()->name);
        $this->assertSame('IPAD 10TH', Product::ofGroupCode(3)->first()->name);
        $this->assertSame('IPHONE 16', Product::ofSubgroupCode(8)->first()->name);
        $this->assertSame(0, Product::ofGroupCode(999)->count());
    }
}
