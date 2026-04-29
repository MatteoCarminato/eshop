<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_list_clients()
    {
        Client::factory()->count(5)->create();

        $response = $this->get(route('clients.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.clients.index');
        $response->assertViewHas('clients');
    }

    /** @test */
    public function it_can_show_create_form()
    {
        $response = $this->get(route('clients.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.clients.create');
    }

    /** @test */
    public function it_can_create_a_client()
    {
        $data = [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'phone' => '11999999999',
        ];

        $response = $this->post(route('clients.store'), $data);

        $response->assertRedirect(route('clients.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('clients', $data);
    }

    /** @test */
    public function it_requires_name_when_creating_client()
    {
        $data = [
            'email' => 'joao@example.com',
            'phone' => '11999999999',
        ];

        $response = $this->post(route('clients.store'), $data);

        $response->assertSessionHasErrors('name');
    }

    /** @test */
    public function it_requires_valid_email_when_creating_client()
    {
        $data = [
            'name' => 'João Silva',
            'email' => 'invalid-email',
            'phone' => '11999999999',
        ];

        $response = $this->post(route('clients.store'), $data);

        $response->assertSessionHasErrors('email');
    }

    /** @test */
    public function it_requires_unique_email_when_creating_client()
    {
        Client::factory()->create(['email' => 'joao@example.com']);

        $data = [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'phone' => '11999999999',
        ];

        $response = $this->post(route('clients.store'), $data);

        $response->assertSessionHasErrors('email');
    }

    /** @test */
    public function it_can_show_a_client()
    {
        $client = Client::factory()->create();

        $response = $this->get(route('clients.show', $client));

        $response->assertStatus(200);
        $response->assertViewIs('admin.clients.show');
        $response->assertViewHas('client', $client);
    }

    /** @test */
    public function it_can_show_edit_form()
    {
        $client = Client::factory()->create();

        $response = $this->get(route('clients.edit', $client));

        $response->assertStatus(200);
        $response->assertViewIs('admin.clients.edit');
        $response->assertViewHas('client', $client);
    }

    /** @test */
    public function it_can_update_a_client()
    {
        $client = Client::factory()->create();

        $data = [
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'phone' => '11888888888',
        ];

        $response = $this->put(route('clients.update', $client), $data);

        $response->assertRedirect(route('clients.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('clients', $data);
    }

    /** @test */
    public function it_can_delete_a_client()
    {
        $client = Client::factory()->create();

        $response = $this->delete(route('clients.destroy', $client));

        $response->assertRedirect(route('clients.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    /** @test */
    public function it_can_search_clients()
    {
        Client::factory()->create(['name' => 'João Silva', 'email' => 'joao@example.com']);
        Client::factory()->create(['name' => 'Maria Santos', 'email' => 'maria@example.com']);

        $response = $this->get(route('clients.index', ['search' => 'João']));

        $response->assertStatus(200);
        $response->assertSee('João Silva');
        $response->assertDontSee('Maria Santos');
    }

    /** @test */
    public function phone_is_optional()
    {
        $data = [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
        ];

        $response = $this->post(route('clients.store'), $data);

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'phone' => null,
        ]);
    }
}
