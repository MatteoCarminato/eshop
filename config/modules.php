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
        'wallet.pnl.view' => [
            'label' => 'Visualizar lucro/PNL da carteira',
            'group' => 'Câmbio',
            'description' => 'Permite ver Lucro Realizado e PnL no painel e na carteira do cliente.',
        ],
        'wallet.manage' => [
            'label' => 'Operar carteiras (depósito/saque/conversão/fechamento)',
            'group' => 'Câmbio',
            'description' => 'Realizar operações financeiras nas carteiras dos clientes.',
        ],
        'wallet.delete' => [
            'label' => 'Deletar depósitos (rollback)',
            'group' => 'Câmbio',
            'description' => 'Apagar depósitos com soft delete e auditoria via rollback.',
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

        'whatsapp.view' => [
            'label' => 'Visualizar módulo WhatsApp',
            'group' => 'Comunicação',
            'description' => 'Acessar painel de conexão WhatsApp e status da instância.',
        ],
        'whatsapp.manage' => [
            'label' => 'Operar módulo WhatsApp',
            'group' => 'Comunicação',
            'description' => 'Atualizar conexão, QR e operações de envio no WhatsApp.',
        ],
    ],

];
