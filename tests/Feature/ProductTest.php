<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\RoleModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Creates and returns an authenticated user granted the given module keys,
     * so requests pass the `module:` middleware guarding the products routes.
     *
     * @param array<int, string> $moduleKeys
     */
    protected function actingAsAuthorized(array $moduleKeys = ['products.view', 'products.manage']): User
    {
        $role = Role::create(['name' => 'Catalog Role ' . Str::random(8)]);

        foreach ($moduleKeys as $key) {
            RoleModule::create(['role_id' => $role->id, 'module_key' => $key]);
        }

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user);

        return $user;
    }

    public function test_guests_are_redirected_to_login()
    {
        $response = $this->get(route('products.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_users_without_module_permission_are_forbidden()
    {
        $this->actingAsAuthorized([]);

        $response = $this->get(route('products.index'));

        $response->assertForbidden();
    }

    public function test_it_can_list_products()
    {
        $this->actingAsAuthorized();
        Product::factory()->count(5)->create();

        $response = $this->get(route('products.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.products.index');
        $response->assertViewHas('products');
    }

    public function test_it_can_show_create_form()
    {
        $this->actingAsAuthorized();

        $response = $this->get(route('products.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.products.create');
    }

    public function test_it_can_create_a_product()
    {
        $this->actingAsAuthorized();

        $data = [
            'name' => 'Camiseta Azul Premium',
            'short_name' => 'Camiseta Azul',
            'price' => 99.9,
            'stock' => 10,
            'active' => '1',
        ];

        $response = $this->post(route('products.store'), $data);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('products', [
            'name' => 'Camiseta Azul Premium',
            'short_name' => 'Camiseta Azul',
            'slug' => 'camiseta-azul-premium',
        ]);
    }

    public function test_it_requires_name_when_creating_product()
    {
        $this->actingAsAuthorized();

        $data = [
            'price' => 50,
        ];

        $response = $this->post(route('products.store'), $data);

        $response->assertSessionHasErrors('name');
    }

    public function test_it_requires_price_when_creating_product()
    {
        $this->actingAsAuthorized();

        $data = [
            'name' => 'Produto Sem Preço',
        ];

        $response = $this->post(route('products.store'), $data);

        $response->assertSessionHasErrors('price');
    }

    public function test_it_rejects_negative_price()
    {
        $this->actingAsAuthorized();

        $data = [
            'name' => 'Produto Preço Negativo',
            'price' => -10,
        ];

        $response = $this->post(route('products.store'), $data);

        $response->assertSessionHasErrors('price');
    }

    public function test_it_generates_a_unique_slug_when_names_collide()
    {
        $this->actingAsAuthorized();

        $this->post(route('products.store'), [
            'name' => 'Produto Repetido',
            'price' => 10,
        ]);

        $this->post(route('products.store'), [
            'name' => 'Produto Repetido',
            'price' => 20,
        ]);

        $this->assertDatabaseHas('products', ['slug' => 'produto-repetido']);
        $this->assertDatabaseHas('products', ['slug' => 'produto-repetido-2']);
    }

    public function test_it_can_show_a_product()
    {
        $this->actingAsAuthorized();
        $product = Product::factory()->create();

        $response = $this->get(route('products.show', $product));

        $response->assertStatus(200);
        $response->assertViewIs('admin.products.show');
        $response->assertViewHas('product', $product);
    }

    public function test_it_can_show_edit_form()
    {
        $this->actingAsAuthorized();
        $product = Product::factory()->create();

        $response = $this->get(route('products.edit', $product));

        $response->assertStatus(200);
        $response->assertViewIs('admin.products.edit');
        $response->assertViewHas('product', $product);
    }

    public function test_edit_and_show_pages_render_with_existing_photo_and_gallery()
    {
        $this->actingAsAuthorized();
        $product = Product::factory()->create(['image_url' => 'https://exemplo.com/capa.jpg']);
        ProductImage::create(['product_id' => $product->id, 'url' => 'https://exemplo.com/galeria-1.jpg', 'sort_order' => 0]);
        ProductImage::create(['product_id' => $product->id, 'url' => 'https://exemplo.com/galeria-2.jpg', 'sort_order' => 1]);

        $this->get(route('products.edit', $product))->assertStatus(200);
        $this->get(route('products.show', $product))->assertStatus(200);
    }

    public function test_it_can_update_a_product()
    {
        $this->actingAsAuthorized();
        $product = Product::factory()->create(['name' => 'Nome Antigo', 'price' => 10]);

        $data = [
            'name' => 'Nome Antigo',
            'price' => 150,
            'stock' => 5,
        ];

        $response = $this->put(route('products.update', $product), $data);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => 150,
        ]);
    }

    public function test_it_regenerates_slug_when_name_changes_on_update()
    {
        $this->actingAsAuthorized();
        $product = Product::factory()->create(['name' => 'Nome Original', 'slug' => 'nome-original', 'price' => 10]);

        $this->put(route('products.update', $product), [
            'name' => 'Nome Alterado',
            'price' => 10,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Nome Alterado',
            'slug' => 'nome-alterado',
        ]);
    }

    public function test_it_can_delete_a_product()
    {
        $this->actingAsAuthorized();
        $product = Product::factory()->create();

        $response = $this->delete(route('products.destroy', $product));

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_it_can_search_products_by_name()
    {
        $this->actingAsAuthorized();
        Product::factory()->create(['name' => 'Tênis Corrida Pro']);
        Product::factory()->create(['name' => 'Mochila Executiva']);

        $response = $this->get(route('products.index', ['search' => 'Tênis']));

        $response->assertStatus(200);
        $response->assertSee('Tênis Corrida Pro');
        $response->assertDontSee('Mochila Executiva');
    }

    public function test_it_can_filter_products_by_active_status()
    {
        $this->actingAsAuthorized();
        Product::factory()->create(['name' => 'Produto Ativo Um', 'active' => true]);
        Product::factory()->create(['name' => 'Produto Inativo Um', 'active' => false]);

        $response = $this->get(route('products.index', ['active' => '0']));

        $response->assertStatus(200);
        $response->assertSee('Produto Inativo Um');
        $response->assertDontSee('Produto Ativo Um');
    }

    public function test_it_can_filter_products_by_brand()
    {
        if (!class_exists(\App\Models\Brand::class)) {
            $this->markTestSkipped('App\\Models\\Brand not available yet (built by a parallel workstream).');
        }

        $this->actingAsAuthorized();

        $brandA = \App\Models\Brand::factory()->create(['name' => 'Marca A']);
        $brandB = \App\Models\Brand::factory()->create(['name' => 'Marca B']);

        Product::factory()->create(['name' => 'Produto Marca A', 'brand_id' => $brandA->id]);
        Product::factory()->create(['name' => 'Produto Marca B', 'brand_id' => $brandB->id]);

        $response = $this->get(route('products.index', ['brand' => $brandA->id]));

        $response->assertStatus(200);
        $response->assertSee('Produto Marca A');
        $response->assertDontSee('Produto Marca B');
    }

    public function test_it_accepts_a_valid_brand_id_on_create()
    {
        if (!class_exists(\App\Models\Brand::class)) {
            $this->markTestSkipped('App\\Models\\Brand not available yet (built by a parallel workstream).');
        }

        $this->actingAsAuthorized();
        $brand = \App\Models\Brand::factory()->create();

        $response = $this->post(route('products.store'), [
            'name' => 'Produto Com Marca',
            'price' => 30,
            'brand_id' => $brand->id,
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'name' => 'Produto Com Marca',
            'brand_id' => $brand->id,
        ]);
    }

    public function test_it_rejects_an_invalid_brand_id()
    {
        $this->actingAsAuthorized();

        $response = $this->post(route('products.store'), [
            'name' => 'Produto Marca Inválida',
            'price' => 30,
            'brand_id' => 999999,
        ]);

        $response->assertSessionHasErrors('brand_id');
    }

    public function test_it_can_upload_a_main_photo_when_creating_a_product()
    {
        Storage::fake('do_spaces');
        $this->actingAsAuthorized();

        $response = $this->post(route('products.store'), [
            'name' => 'Produto Com Foto',
            'price' => 100,
            'image' => UploadedFile::fake()->image('capa.jpg'),
        ]);

        $response->assertRedirect(route('products.index'));
        $product = Product::where('name', 'Produto Com Foto')->firstOrFail();
        $this->assertNotNull($product->image_url);
        Storage::disk('do_spaces')->assertExists(
            Str::after($product->image_url, Storage::disk('do_spaces')->url(''))
        );

        // Nome do arquivo = md5 (32 chars hex) + "-" + slug do nome do produto,
        // pra URL da imagem carregar palavras-chave reais (SEO).
        $filename = basename($product->image_url);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}-produto-com-foto\.jpg$/', $filename);
    }

    public function test_uploaded_main_photo_takes_priority_over_image_url_when_both_are_sent()
    {
        Storage::fake('do_spaces');
        $this->actingAsAuthorized();
        $product = Product::factory()->create(['image_url' => null]);

        $this->put(route('products.update', $product), [
            'name' => $product->name,
            'price' => $product->price,
            'image_url' => 'https://exemplo.com/antiga.jpg',
            'image' => UploadedFile::fake()->image('nova.jpg'),
        ]);

        $product->refresh();
        $this->assertNotSame('https://exemplo.com/antiga.jpg', $product->image_url);
    }

    public function test_it_can_upload_gallery_photos()
    {
        Storage::fake('do_spaces');
        $this->actingAsAuthorized();
        $product = Product::factory()->create();

        $response = $this->put(route('products.update', $product), [
            'name' => $product->name,
            'price' => $product->price,
            'gallery' => [
                UploadedFile::fake()->image('foto1.jpg'),
                UploadedFile::fake()->image('foto2.jpg'),
            ],
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertSame(2, $product->images()->count());
    }

    public function test_it_appends_new_gallery_photos_without_touching_existing_ones()
    {
        Storage::fake('do_spaces');
        $this->actingAsAuthorized();
        $product = Product::factory()->create();
        $existing = ProductImage::create(['product_id' => $product->id, 'url' => 'https://exemplo.com/ja-existia.jpg', 'sort_order' => 0]);

        $this->put(route('products.update', $product), [
            'name' => $product->name,
            'price' => $product->price,
            'gallery' => [UploadedFile::fake()->image('nova.jpg')],
        ]);

        $this->assertSame(2, $product->images()->count());
        $this->assertDatabaseHas('product_images', ['id' => $existing->id, 'url' => 'https://exemplo.com/ja-existia.jpg']);
    }

    public function test_it_can_remove_a_gallery_photo_on_update()
    {
        Storage::fake('do_spaces');
        $this->actingAsAuthorized();
        $product = Product::factory()->create();
        $toRemove = ProductImage::create(['product_id' => $product->id, 'url' => 'https://exemplo.com/remover.jpg', 'sort_order' => 0]);
        $toKeep = ProductImage::create(['product_id' => $product->id, 'url' => 'https://exemplo.com/manter.jpg', 'sort_order' => 1]);

        $this->put(route('products.update', $product), [
            'name' => $product->name,
            'price' => $product->price,
            'remove_gallery' => [$toRemove->id],
        ]);

        $this->assertDatabaseMissing('product_images', ['id' => $toRemove->id]);
        $this->assertDatabaseHas('product_images', ['id' => $toKeep->id]);
    }

    public function test_price_is_required_but_other_price_fields_are_optional()
    {
        $this->actingAsAuthorized();

        $response = $this->post(route('products.store'), [
            'name' => 'Produto Preços Opcionais',
            'price' => 45.5,
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'name' => 'Produto Preços Opcionais',
            'wholesale_price' => null,
            'web_price' => null,
            'sale_price' => null,
            'min_price' => null,
        ]);
    }
}
