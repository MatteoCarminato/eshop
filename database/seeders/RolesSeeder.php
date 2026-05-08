<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::firstOrCreate(
            ['slug' => 'administrador'],
            [
                'name' => 'Administrador',
                'description' => 'Acesso total ao sistema.',
                'is_admin' => true,
            ]
        );

        $operador = Role::firstOrCreate(
            ['slug' => 'operador-cambio'],
            [
                'name' => 'Operador de Câmbio',
                'description' => 'Operações de carteira e visualização de clientes.',
                'is_admin' => false,
            ]
        );

        $operador->syncModules([
            'clients.view',
            'wallet.view',
            'wallet.manage',
        ]);

        // Vincula o primeiro usuário como administrador, se existir e ainda não tiver cargo.
        $firstUser = User::query()->orderBy('id')->first();

        if ($firstUser && !$firstUser->role_id) {
            $firstUser->role_id = $admin->id;
            $firstUser->save();
        }
    }
}
