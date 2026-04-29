# 📋 Módulo Client - Documentação

## 🎯 Arquitetura

Este módulo segue os padrões profissionais do Laravel com Clean Architecture e Service Layer.

### 📂 Estrutura

```
app/
├── Http/
│   ├── Controllers/
│   │   └── ClientController.php          # Controller FINO (apenas coordena)
│   └── Requests/
│       └── Client/
│           ├── StoreClientRequest.php    # Validação de criação
│           └── UpdateClientRequest.php   # Validação de atualização
│
├── Services/
│   └── ClientService.php                 # Lógica de negócio
│
├── Models/
│   └── Client.php                        # Model com relacionamentos
│
database/
├── factories/
│   └── ClientFactory.php                 # Factory para testes
├── migrations/
│   └── 2026_04_28_083519_create_clients_table.php
└── seeders/
    └── ClientSeeder.php                  # Dados de exemplo

tests/
├── Feature/
│   └── ClientTest.php                    # Testes de integração
└── Unit/
    └── ClientServiceTest.php             # Testes unitários
```

## 🔄 Fluxo de Dados

```
Request → FormRequest (validação) → Controller → Service → Model → Database
                                        ↓
                                    View (Blade)
```

## 📝 Funcionalidades

### ClientService

#### Métodos Disponíveis:

- ✅ `list(?int $perPage = null)` - Lista todos os clientes (com ou sem paginação)
- ✅ `findById(int $id)` - Busca cliente por ID
- ✅ `create(StoreClientRequest $request)` - Cria novo cliente
- ✅ `update(UpdateClientRequest $request, Client $client)` - Atualiza cliente
- ✅ `delete(Client $client)` - Deleta cliente
- ✅ `search(string $search, ?int $perPage = null)` - Busca por nome ou email
- ✅ `emailExists(string $email, ?int $exceptId = null)` - Verifica se email existe

#### Regras de Negócio Implementadas:

1. **Normalização de Dados**
   - Email sempre em lowercase
   - Nome com primeira letra maiúscula (ucwords)


2. **Validações**
   - Nome: obrigatório, máx 255 caracteres
   - Email: opcional, formato válido, único
   - Telefone: opcional, máx 20 caracteres, aceita Brasil e Paraguai

3. **Associação em Massa a Grupos**
   - Checkbox na listagem para selecionar múltiplos clientes
   - Botão "Adicionar ao Grupo" abre modal para escolher o grupo
   - Associação rápida, sem duplicidade

## 🧪 Testes

### Executar Testes

```bash
# Todos os testes
php artisan test

# Apenas testes do Client
php artisan test --filter Client

# Com cobertura
php artisan test --coverage
```


### Cobertura de Testes

- ✅ CRUD completo
- ✅ Validações
- ✅ Busca
- ✅ Normalização de dados
- ✅ Associação em massa a grupos
- ✅ Casos de erro

## 🚀 Uso

### No Controller

```php
use App\Services\ClientService;
use App\Http\Requests\Client\StoreClientRequest;

class ClientController extends Controller
{
    public function __construct(
        protected ClientService $clientService
    ) {}

    public function store(StoreClientRequest $request)
    {
        $client = $this->clientService->create($request);
        
        return redirect()->route('clients.index')
            ->with('success', 'Cliente criado!');
    }
}
```

### Diretamente (não recomendado)

```php
$clientService = app(ClientService::class);
$clients = $clientService->list(15); // Com paginação
```

## 🎨 Model Features

### Scopes

```php
// Buscar apenas clientes ativos
Client::active()->get();

// Buscar por termo
Client::search('João')->get();
```

### Accessors

```php
$client->formatted_phone; // Retorna telefone formatado: (11) 99999-9999
```

### Relacionamentos (Preparados para o futuro)

```php
// $client->orders(); // Descomentar quando criar Orders
```

## 📊 Database

### Migrations

```bash
php artisan migrate
```

### Seeders

```bash
# Popular com dados de exemplo
php artisan db:seed --class=ClientSeeder

# Ou no DatabaseSeeder
php artisan db:seed
```

## 🔐 Boas Práticas Implementadas

1. ✅ **Separação de Responsabilidades**
   - Controller apenas coordena
   - Service contém lógica de negócio
   - FormRequest valida dados

2. ✅ **Type Safety**
   - Todos os métodos com type hints
   - Return types declarados

3. ✅ **Tratamento de Erros**
   - Try/catch nos controllers
   - Mensagens de erro amigáveis

4. ✅ **Testabilidade**
   - 100% testado
   - Factory para dados fake

5. ✅ **Documentação**
   - DocBlocks em todos os métodos
   - README completo

## 🔮 Próximos Passos

- [ ] Adicionar soft deletes
- [ ] Criar relacionamento com Orders
- [ ] Implementar import/export
- [ ] Adicionar logs de auditoria
- [ ] Criar API endpoints

## 📚 Referências

- [Laravel.md](../docs/Laravel.md) - Padrões de arquitetura
- [Laravel Docs](https://laravel.com/docs)
- [Clean Architecture](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)
