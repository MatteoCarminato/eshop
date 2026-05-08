<?php

/*
|--------------------------------------------------------------------------
| Catálogo de módulos do sistema
|--------------------------------------------------------------------------
|
| Cada módulo possui uma chave (`key`) usada no middleware `module:<key>`,
| um rótulo (`label`) exibido nas telas, um agrupamento (`group`)
| usado apenas para organizar a UI e uma descrição curta.
|
| Para criar um novo módulo, basta adicionar uma entrada aqui e proteger
| as rotas correspondentes com `->middleware('module:<key>')`.
|
*/

return [

    'modules' => [

        'roles.manage' => [
            'label' => 'Gerenciar cargos e permissões',
            'group' => 'Administração',
            'description' => 'Criar, editar e excluir cargos e suas permissões de módulo.',
        ],

        'users.manage' => [
            'label' => 'Gerenciar funcionários',
            'group' => 'Administração',
            'description' => 'Cadastrar funcionários e definir seus cargos de acesso.',
        ],

        'clients.view' => [
            'label' => 'Visualizar clientes',
            'group' => 'Clientes',
            'description' => 'Acesso à listagem e detalhes de clientes.',
        ],
        'clients.manage' => [
            'label' => 'Gerenciar clientes',
            'group' => 'Clientes',
            'description' => 'Criar, editar e excluir clientes.',
        ],

        'groups.view' => [
            'label' => 'Visualizar grupos',
            'group' => 'Grupos',
            'description' => 'Acesso à listagem e detalhes de grupos de clientes.',
        ],
        'groups.manage' => [
            'label' => 'Gerenciar grupos',
            'group' => 'Grupos',
            'description' => 'Criar, editar e excluir grupos.',
        ],

        'wallet.view' => [
            'label' => 'Visualizar carteiras',
            'group' => 'Câmbio',
            'description' => 'Acesso ao painel de carteiras e listagem de clientes.',
        ],
        'wallet.manage' => [
            'label' => 'Operar carteiras (depósito/saque/conversão/fechamento)',
            'group' => 'Câmbio',
            'description' => 'Realizar operações financeiras nas carteiras dos clientes.',
        ],

        'treasury.view' => [
            'label' => 'Visualizar caixa próprio (USD)',
            'group' => 'Câmbio',
            'description' => 'Ver saldo, lotes e vendas do caixa próprio em dólar.',
        ],
        'treasury.manage' => [
            'label' => 'Operar caixa próprio (aportes e vendas)',
            'group' => 'Câmbio',
            'description' => 'Aportar USD no caixa e vender USD do caixa para clientes.',
        ],
    ],

];
