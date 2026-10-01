<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria um usuário com um cargo que tem acesso aos módulos de marcas,
        // para que o middleware `module:brands.view|brands.manage` não retorne 403.
        $role = Role::create([
            'name' => 'Catálogo Test Role',
            'is_admin' => false,
        ]);
        $role->syncModules(['brands.view', 'brands.manage']);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user);
    }

    public function test_it_can_list_brands(): void
    {
        Brand::factory()->count(5)->create();

        $response = $this->get(route('brands.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.brands.index');
        $response->assertViewHas('brands');
    }

    public function test_it_can_show_create_form(): void
    {
        $response = $this->get(route('brands.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.brands.create');
    }

    public function test_it_can_create_a_brand(): void
    {
        $data = [
            'name' => 'Nike',
            'logo_url' => 'https://example.com/nike.png',
            'active' => '1',
        ];

        $response = $this->post(route('brands.store'), $data);

        $response->assertRedirect(route('brands.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('brands', [
            'name' => 'Nike',
            'logo_url' => 'https://example.com/nike.png',
            'slug' => 'nike',
            'active' => true,
        ]);
    }

    public function test_it_auto_generates_slug_on_create(): void
    {
        $response = $this->post(route('brands.store'), [
            'name' => 'Adidas Original',
        ]);

        $response->assertRedirect(route('brands.index'));
        $this->assertDatabaseHas('brands', [
            'name' => 'Adidas Original',
            'slug' => 'adidas-original',
        ]);
    }

    public function test_it_generates_a_unique_slug_when_names_collide_on_the_base_slug(): void
    {
        Brand::factory()->create(['name' => 'Puma', 'slug' => 'puma']);

        $response = $this->post(route('brands.store'), [
            'name' => 'Puma!',
        ]);

        $response->assertRedirect(route('brands.index'));
        $this->assertDatabaseHas('brands', ['name' => 'Puma!', 'slug' => 'puma-2']);
    }

    public function test_it_requires_name_when_creating_brand(): void
    {
        $response = $this->post(route('brands.store'), [
            'logo_url' => 'https://example.com/logo.png',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_it_requires_unique_name_when_creating_brand(): void
    {
        Brand::factory()->create(['name' => 'Nike']);

        $response = $this->post(route('brands.store'), [
            'name' => 'Nike',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_it_rejects_invalid_logo_url(): void
    {
        $response = $this->post(route('brands.store'), [
            'name' => 'Reebok',
            'logo_url' => 'not-a-url',
        ]);

        $response->assertSessionHasErrors('logo_url');
    }

    public function test_logo_url_is_optional(): void
    {
        $response = $this->post(route('brands.store'), [
            'name' => 'Reebok',
        ]);

        $response->assertRedirect(route('brands.index'));
        $this->assertDatabaseHas('brands', [
            'name' => 'Reebok',
            'logo_url' => null,
        ]);
    }

    public function test_it_can_show_a_brand(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->get(route('brands.show', $brand));

        $response->assertStatus(200);
        $response->assertViewIs('admin.brands.show');
        $response->assertViewHas('brand', $brand);
    }

    public function test_it_can_show_edit_form(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->get(route('brands.edit', $brand));

        $response->assertStatus(200);
        $response->assertViewIs('admin.brands.edit');
        $response->assertViewHas('brand', $brand);
    }

    public function test_it_can_update_a_brand(): void
    {
        $brand = Brand::factory()->create(['name' => 'Nike', 'slug' => 'nike']);

        $data = [
            'name' => 'Nike Sports',
            'logo_url' => 'https://example.com/new-logo.png',
            'active' => '1',
        ];

        $response = $this->put(route('brands.update', $brand), $data);

        $response->assertRedirect(route('brands.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Nike Sports',
            'slug' => 'nike-sports',
            'logo_url' => 'https://example.com/new-logo.png',
        ]);
    }

    public function test_it_keeps_the_same_slug_when_updating_without_changing_the_name(): void
    {
        $brand = Brand::factory()->create(['name' => 'Nike', 'slug' => 'nike']);

        $response = $this->put(route('brands.update', $brand), [
            'name' => 'Nike',
            'active' => '1',
        ]);

        $response->assertRedirect(route('brands.index'));
        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'slug' => 'nike',
        ]);
    }

    public function test_it_can_delete_a_brand(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->delete(route('brands.destroy', $brand));

        $response->assertRedirect(route('brands.index'));
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('brands', ['id' => $brand->id]);
    }

    public function test_it_can_search_brands_by_name(): void
    {
        Brand::factory()->create(['name' => 'Nike']);
        Brand::factory()->create(['name' => 'Adidas']);

        $response = $this->get(route('brands.index', ['search' => 'Nike']));

        $response->assertStatus(200);
        $response->assertSee('Nike');
        $response->assertDontSee('Adidas');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        auth()->logout();

        $response = $this->get(route('brands.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_users_without_the_brands_module_are_forbidden(): void
    {
        $role = Role::create([
            'name' => 'Sem Acesso',
            'is_admin' => false,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($user)->get(route('brands.index'));

        $response->assertForbidden();
    }
}
