<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Services\BrandService;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BrandService $brandService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->brandService = new BrandService();
    }

    /**
     * FormRequest::create() não passa pelo roteador, então $this->route('brand')
     * (usado pela regra Rule::unique(...)->ignore() em UpdateBrandRequest) ficaria
     * nulo. Simulamos aqui o parâmetro de rota vinculado pelo route-model-binding
     * real do BrandController@update.
     */
    protected function bindBrandRouteParameter(UpdateBrandRequest $request, Brand $brand): void
    {
        $request->setRouteResolver(function () use ($brand) {
            return new class($brand) {
                public function __construct(private Brand $brand) {}

                public function parameter($name, $default = null)
                {
                    // Illuminate\Http\Request::__get() faz fallback para
                    // route($key) quando a chave não está no payload, então só
                    // devemos responder pelo parâmetro real da rota ('brand').
                    return $name === 'brand' ? $this->brand : $default;
                }
            };
        });
    }

    /**
     * FormRequest::create() constrói a request sem passar pelo container, então
     * a validação automática (que normalmente ocorre ao resolver a FormRequest
     * via injeção de dependência do controller) nunca é disparada e
     * `$request->validated()` explodiria com $this->validator nulo. Resolvemos
     * isso chamando validateResolved() manualmente, como o container faria.
     */
    protected function resolve(\Illuminate\Foundation\Http\FormRequest $request): \Illuminate\Foundation\Http\FormRequest
    {
        $request->setContainer($this->app)->validateResolved();

        return $request;
    }

    public function test_it_can_list_all_brands(): void
    {
        Brand::factory()->count(5)->create();

        $brands = $this->brandService->list();

        $this->assertCount(5, $brands);
    }

    public function test_it_can_list_brands_paginated(): void
    {
        Brand::factory()->count(20)->create();

        $brands = $this->brandService->list(10);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $brands);
        $this->assertEquals(10, $brands->perPage());
    }

    public function test_it_can_find_brand_by_id(): void
    {
        $brand = Brand::factory()->create();

        $found = $this->brandService->findById($brand->id);

        $this->assertEquals($brand->id, $found->id);
        $this->assertEquals($brand->name, $found->name);
    }

    public function test_it_can_filter_brands_by_search_term(): void
    {
        Brand::factory()->create(['name' => 'Nike']);
        Brand::factory()->create(['name' => 'Adidas']);

        $results = $this->brandService->filter('Nike');

        $this->assertCount(1, $results);
        $this->assertEquals('Nike', $results->first()->name);
    }

    public function test_it_generates_a_slug_from_the_name_when_creating(): void
    {
        $request = $this->resolve(StoreBrandRequest::create(route('brands.store'), 'POST', [
            'name' => 'Nike Brasil',
            'active' => '1',
        ]));

        $brand = $this->brandService->create($request);

        $this->assertEquals('nike-brasil', $brand->slug);
        $this->assertTrue($brand->active);
    }

    public function test_it_generates_a_unique_slug_when_name_collides(): void
    {
        Brand::factory()->create(['name' => 'Nike', 'slug' => 'nike']);

        // "Nike!" gera a mesma base de slug ("nike") que a marca já existente.
        $request = $this->resolve(StoreBrandRequest::create(route('brands.store'), 'POST', [
            'name' => 'Nike!',
        ]));

        $brand = $this->brandService->create($request);

        $this->assertNotEquals('nike', $brand->slug);
        $this->assertStringStartsWith('nike', $brand->slug);
    }

    public function test_it_updates_slug_when_name_changes(): void
    {
        $brand = Brand::factory()->create(['name' => 'Nike', 'slug' => 'nike']);

        $request = UpdateBrandRequest::create(route('brands.update', $brand), 'PUT', [
            'name' => 'Nike Sports',
        ]);
        $this->bindBrandRouteParameter($request, $brand);
        $this->resolve($request);

        $updated = $this->brandService->update($request, $brand);

        $this->assertEquals('nike-sports', $updated->slug);
    }

    public function test_it_keeps_slug_when_name_is_unchanged(): void
    {
        $brand = Brand::factory()->create(['name' => 'Nike', 'slug' => 'nike']);

        $request = UpdateBrandRequest::create(route('brands.update', $brand), 'PUT', [
            'name' => 'Nike',
            'logo_url' => 'https://example.com/logo.png',
        ]);
        $this->bindBrandRouteParameter($request, $brand);
        $this->resolve($request);

        $updated = $this->brandService->update($request, $brand);

        $this->assertEquals('nike', $updated->slug);
        $this->assertEquals('https://example.com/logo.png', $updated->logo_url);
    }

    public function test_it_can_delete_a_brand(): void
    {
        $brand = Brand::factory()->create();

        $result = $this->brandService->delete($brand);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('brands', ['id' => $brand->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('brands', ['id' => $brand->id]);
    }
}
