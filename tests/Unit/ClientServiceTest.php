<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Services\ClientService;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ClientService $clientService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientService = new ClientService();
    }

    /** @test */
    public function it_can_list_all_clients()
    {
        Client::factory()->count(5)->create();

        $clients = $this->clientService->list();

        $this->assertCount(5, $clients);
    }

    /** @test */
    public function it_can_list_clients_paginated()
    {
        Client::factory()->count(20)->create();

        $clients = $this->clientService->list(10);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $clients);
        $this->assertEquals(10, $clients->perPage());
    }

    /** @test */
    public function it_can_find_client_by_id()
    {
        $client = Client::factory()->create();

        $found = $this->clientService->findById($client->id);

        $this->assertEquals($client->id, $found->id);
        $this->assertEquals($client->email, $found->email);
    }

    /** @test */
    public function it_normalizes_data_when_creating()
    {
        $request = StoreClientRequest::create(route('clients.store'), 'POST', [
            'name' => 'JOÃO SILVA',
            'email' => 'JOAO@EXAMPLE.COM',
            'phone' => '11999999999',
        ]);

        $client = $this->clientService->create($request);

        $this->assertEquals('João Silva', $client->name);
        $this->assertEquals('joao@example.com', $client->email);
    }

    /** @test */
    public function it_normalizes_data_when_updating()
    {
        $client = Client::factory()->create();

        $request = UpdateClientRequest::create(route('clients.update', $client), 'PUT', [
            'name' => 'MARIA SANTOS',
            'email' => 'MARIA@EXAMPLE.COM',
            'phone' => '11888888888',
        ]);

        $updated = $this->clientService->update($request, $client);

        $this->assertEquals('Maria Santos', $updated->name);
        $this->assertEquals('maria@example.com', $updated->email);
    }

    /** @test */
    public function it_can_delete_a_client()
    {
        $client = Client::factory()->create();

        $result = $this->clientService->delete($client);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    /** @test */
    public function it_can_search_clients_by_name()
    {
        Client::factory()->create(['name' => 'João Silva']);
        Client::factory()->create(['name' => 'Maria Santos']);

        $results = $this->clientService->search('João');

        $this->assertCount(1, $results);
        $this->assertEquals('João Silva', $results->first()->name);
    }

    /** @test */
    public function it_can_search_clients_by_email()
    {
        Client::factory()->create(['email' => 'joao@example.com']);
        Client::factory()->create(['email' => 'maria@example.com']);

        $results = $this->clientService->search('joao@');

        $this->assertCount(1, $results);
        $this->assertEquals('joao@example.com', $results->first()->email);
    }

    /** @test */
    public function it_checks_if_email_exists()
    {
        Client::factory()->create(['email' => 'joao@example.com']);

        $exists = $this->clientService->emailExists('joao@example.com');
        $notExists = $this->clientService->emailExists('maria@example.com');

        $this->assertTrue($exists);
        $this->assertFalse($notExists);
    }

    /** @test */
    public function it_checks_email_exists_except_for_specific_id()
    {
        $client = Client::factory()->create(['email' => 'joao@example.com']);

        $exists = $this->clientService->emailExists('joao@example.com', $client->id);

        $this->assertFalse($exists);
    }
}
