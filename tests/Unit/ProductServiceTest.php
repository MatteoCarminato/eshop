<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\ProductService;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Redirector;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productService = new ProductService();
    }

    /**
     * Build a fully-resolved StoreProductRequest: container + redirector are
     * set and validateResolved() is run, exactly like Laravel does when it
     * injects the FormRequest into a controller action. Without this,
     * ->validated() throws because $this->validator is never populated.
     */
    protected function makeStoreRequest(array $data): StoreProductRequest
    {
        $request = StoreProductRequest::create('/products', 'POST', $data);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));
        $request->validateResolved();

        return $request;
    }

    protected function makeUpdateRequest(array $data): UpdateProductRequest
    {
        $request = UpdateProductRequest::create('/products/1', 'PUT', $data);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));
        $request->validateResolved();

        return $request;
    }

    public function test_it_can_list_all_products()
    {
        Product::factory()->count(5)->create();

        $products = $this->productService->list();

        $this->assertCount(5, $products);
    }

    public function test_it_can_list_products_paginated()
    {
        Product::factory()->count(20)->create();

        $products = $this->productService->list(10);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $products);
        $this->assertEquals(10, $products->perPage());
    }

    public function test_it_can_find_product_by_id()
    {
        $product = Product::factory()->create();

        $found = $this->productService->findById($product->id);

        $this->assertEquals($product->id, $found->id);
    }

    public function test_it_creates_a_product_and_generates_a_slug_from_the_name()
    {
        $request = $this->makeStoreRequest([
            'name' => 'Tênis Esportivo Confort',
            'price' => 199.9,
        ]);

        $product = $this->productService->create($request);

        $this->assertEquals('Tênis Esportivo Confort', $product->name);
        $this->assertEquals('tenis-esportivo-confort', $product->slug);
    }

    public function test_it_defaults_active_and_featured_to_false_when_not_sent()
    {
        // Mirrors the boolean-switch convention used across the app (hidden
        // "0" field + checkbox "1"): when the flag key is entirely absent
        // from the payload, prepareForValidation() normalizes it to false.
        $request = $this->makeStoreRequest([
            'name' => 'Produto Sem Flags',
            'price' => 10,
        ]);

        $product = $this->productService->create($request);

        $this->assertFalse($product->active);
        $this->assertFalse($product->featured);
    }

    public function test_it_generates_unique_slug_on_name_collision()
    {
        $product1 = $this->productService->create(
            $this->makeStoreRequest(['name' => 'Bermuda Jeans', 'price' => 50])
        );

        $product2 = $this->productService->create(
            $this->makeStoreRequest(['name' => 'Bermuda Jeans', 'price' => 60])
        );

        $this->assertEquals('bermuda-jeans', $product1->slug);
        $this->assertEquals('bermuda-jeans-2', $product2->slug);
    }

    public function test_it_avoids_slug_collision_with_soft_deleted_products()
    {
        $existing = Product::factory()->create(['name' => 'Produto Excluido', 'slug' => 'produto-excluido']);
        $existing->delete();

        $product = $this->productService->create(
            $this->makeStoreRequest(['name' => 'Produto Excluido', 'price' => 15])
        );

        $this->assertEquals('produto-excluido-2', $product->slug);
    }

    public function test_it_updates_a_product_and_keeps_slug_when_name_is_unchanged()
    {
        $product = Product::factory()->create(['name' => 'Produto X', 'slug' => 'produto-x', 'price' => 10]);

        $updated = $this->productService->update(
            $this->makeUpdateRequest(['name' => 'Produto X', 'price' => 25]),
            $product
        );

        $this->assertEquals(25, (float) $updated->price);
        $this->assertEquals('produto-x', $updated->slug);
    }

    public function test_it_regenerates_slug_when_name_changes_on_update()
    {
        $product = Product::factory()->create(['name' => 'Produto Y', 'slug' => 'produto-y', 'price' => 10]);

        $updated = $this->productService->update(
            $this->makeUpdateRequest(['name' => 'Produto Z', 'price' => 10]),
            $product
        );

        $this->assertEquals('Produto Z', $updated->name);
        $this->assertEquals('produto-z', $updated->slug);
    }

    public function test_it_can_delete_a_product()
    {
        $product = Product::factory()->create();

        $result = $this->productService->delete($product);

        $this->assertTrue($result);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_it_can_filter_products_by_search_term()
    {
        Product::factory()->create(['name' => 'Camisa Polo']);
        Product::factory()->create(['name' => 'Calça Jeans']);

        $results = $this->productService->filter('Polo', null, null, null);

        $this->assertCount(1, $results);
        $this->assertEquals('Camisa Polo', $results->first()->name);
    }

    public function test_it_can_filter_products_by_short_name()
    {
        Product::factory()->create(['name' => 'Produto Longo Um', 'short_name' => 'Curto Especial']);
        Product::factory()->create(['name' => 'Produto Longo Dois', 'short_name' => 'Outro Nome']);

        $results = $this->productService->filter('Especial', null, null, null);

        $this->assertCount(1, $results);
        $this->assertEquals('Produto Longo Um', $results->first()->name);
    }

    public function test_it_can_filter_products_by_active_flag()
    {
        Product::factory()->create(['name' => 'Ativo 1', 'active' => true]);
        Product::factory()->create(['name' => 'Inativo 1', 'active' => false]);

        $activeOnly = $this->productService->filter(null, null, null, true);
        $inactiveOnly = $this->productService->filter(null, null, null, false);

        $this->assertCount(1, $activeOnly);
        $this->assertCount(1, $inactiveOnly);
        $this->assertEquals('Ativo 1', $activeOnly->first()->name);
        $this->assertEquals('Inativo 1', $inactiveOnly->first()->name);
    }

    public function test_it_can_filter_products_by_brand_id()
    {
        if (!class_exists(\App\Models\Brand::class)) {
            $this->markTestSkipped('App\\Models\\Brand not available yet (built by a parallel workstream).');
        }

        $brand = \App\Models\Brand::factory()->create();
        Product::factory()->create(['name' => 'Com Marca', 'brand_id' => $brand->id]);
        Product::factory()->create(['name' => 'Sem Marca']);

        $results = $this->productService->filter(null, $brand->id, null, null);

        $this->assertCount(1, $results);
        $this->assertEquals('Com Marca', $results->first()->name);
    }

    public function test_it_can_filter_products_by_category_id()
    {
        if (!class_exists(\App\Models\Category::class)) {
            $this->markTestSkipped('App\\Models\\Category not available.');
        }

        $category = \App\Models\Category::factory()->create();
        Product::factory()->create(['name' => 'Com Categoria', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Sem Categoria']);

        $results = $this->productService->filter(null, null, $category->id, null);

        $this->assertCount(1, $results);
        $this->assertEquals('Com Categoria', $results->first()->name);
    }

    public function test_it_can_filter_products_paginated()
    {
        Product::factory()->count(15)->create();

        $results = $this->productService->filter(null, null, null, null, 5);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $results);
        $this->assertEquals(5, $results->perPage());
    }
}
