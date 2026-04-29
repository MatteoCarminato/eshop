<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar alguns clientes de exemplo
        Client::factory()->count(50)->create();

        // Criar clientes específicos para testes
        Client::factory()->create([
            'name' => 'João Silva',
            'email' => 'joao.silva@example.com',
            'phone' => '11999999999',
        ]);

        Client::factory()->create([
            'name' => 'Maria Santos',
            'email' => 'maria.santos@example.com',
            'phone' => '11988888888',
        ]);

        Client::factory()->create([
            'name' => 'Pedro Oliveira',
            'email' => 'pedro.oliveira@example.com',
            'phone' => null,
        ]);
    }
}
