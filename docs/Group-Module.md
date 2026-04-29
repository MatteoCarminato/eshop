# Group Module

## Visão Geral
O módulo de Grupos permite criar, editar, listar e excluir grupos de clientes. Um cliente pode pertencer a vários grupos e um grupo pode conter vários clientes (relacionamento N:N).

## Funcionalidades
- Cadastro, edição, listagem e exclusão de grupos
- Associação rápida de clientes a grupos (via tela do grupo e via listagem de clientes)
- Busca por nome de grupo
- Visualização dos clientes de cada grupo
- Proteção contra duplicidade de associação

## Estrutura de Arquivos
- `app/Models/Group.php` — Model principal
- `database/migrations/2026_04_29_000000_create_groups_table.php` — Migration de grupos e pivot
- `app/Http/Controllers/GroupController.php` — Controller resource
- `app/Services/GroupService.php` — Camada de serviço
- `app/Http/Requests/Group/StoreGroupRequest.php` — Validação de criação
- `app/Http/Requests/Group/UpdateGroupRequest.php` — Validação de edição
- `resources/views/admin/groups/` — Views (index, create, edit, show)
- `routes/web.php` — Resource + rota POST para adicionar clientes

## Relacionamentos
- `Group` possui N:N com `Client` via tabela `group_client`
- Métodos: `$group->clients()`, `$client->groups()`

## Rotas
- `Route::resource('groups', GroupController::class);`
- `Route::post('groups/{group}/add-clients', [GroupController::class, 'addClients'])->name('groups.addClients');`

## Telas
- **Listagem:** Busca, paginação, ações (ver, editar, excluir), contador de clientes
- **Cadastro/Edição:** Nome, descrição
- **Detalhes:** Dados do grupo, lista de clientes, formulário para adicionar clientes rapidamente

## Observações
- Não permite cliente duplicado no mesmo grupo
- Associação em massa via tela do grupo e via listagem de clientes
- Sidebar com menu "Grupos"

---
