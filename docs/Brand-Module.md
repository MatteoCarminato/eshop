# 📋 Módulo Brand (Marca) - Documentação

## 🎯 Arquitetura

Este módulo segue os mesmos padrões do módulo Client: Clean Architecture com Service Layer e Controller fino.

### 📂 Estrutura

```
app/
├── Http/
│   ├── Controllers/
│   │   └── BrandController.php           # Controller FINO (apenas coordena)
│   └── Requests/
│       └── Brand/
│           ├── StoreBrandRequest.php     # Validação de criação
│           └── UpdateBrandRequest.php    # Validação de atualização
│
├── Services/
│   └── BrandService.php                  # Lógica de negócio (inclui geração de slug)
│
├── Models/
│   └── Brand.php                         # Model com scopes e relacionamento com Product

database/
├── factories/
│   └── BrandFactory.php                  # Factory para testes
└── migrations/
    └── 2026_09_27_100000_create_brands_table.php

resources/views/admin/brands/
├── index.blade.php                       # Listagem + busca + sincronizar com ERP
├── create.blade.php
├── edit.blade.php
└── show.blade.php

tests/
├── Feature/
│   └── BrandTest.php                     # Testes de integração (HTTP)
└── Unit/
    └── BrandServiceTest.php              # Testes unitários do Service
```

## 🔄 Fluxo de Dados

```
Request → FormRequest (validação) → Controller → Service → Model → Database
                                        ↓
                                    View (Blade)
```

A sincronização com o ERP (`POST /brands/sync`) é responsabilidade do
`CatalogSyncController`, implementado em outra frente de trabalho — este
módulo apenas aponta um botão/form para a rota `brands.sync`.

## 📝 Funcionalidades do Service

### BrandService

- ✅ `list(?int $perPage = null)` - Lista todas as marcas ordenadas por nome (com ou sem paginação)
- ✅ `filter(?string $search, ?int $perPage = null)` - Filtra por nome
- ✅ `findById(int $id)` - Busca marca por ID
- ✅ `create(StoreBrandRequest $request)` - Cria nova marca, gerando o slug
- ✅ `update(UpdateBrandRequest $request, Brand $brand)` - Atualiza marca, regenerando o slug se o nome mudou
- ✅ `delete(Brand $brand)` - Remove marca (soft delete)

### Regras de Negócio Implementadas

1. **Slug automático**
   - `slug` não é um campo do formulário — é gerado a partir do `name` via `Str::slug()`.
   - Em caso de colisão, um sufixo numérico incremental é adicionado (`nike`, `nike-2`, `nike-3`, ...).
   - No update, o slug só é regenerado se o nome tiver mudado.
   - A checagem de colisão considera também marcas com soft delete (`withTrashed()`), pois a
     coluna `slug` é única no banco mesmo para registros excluídos.

2. **Validações**
   - Nome: obrigatório, máx 255 caracteres, único.
   - URL do logo: opcional, precisa ser uma URL válida.
   - Ativo: booleano opcional (normalizado a partir do switch do formulário).

3. **Campos de origem do ERP (`recno`, `synced_at`)**
   - Não são expostos nos formulários de criação/edição — são preenchidos apenas
     pela camada de sincronização com o ERP.
   - Exibidos como somente leitura na tela de detalhes (`show`) e, quando presentes,
     também na tela de edição, indicando a origem do registro.

## 🧪 Testes

### Executar Testes

```bash
# Todos os testes
php artisan test

# Apenas testes de Brand
php artisan test --filter Brand
```

### Cobertura de Testes

- ✅ CRUD completo via HTTP (index, create, store, show, edit, update, destroy)
- ✅ Autenticação/autorização via módulos (`brands.view` / `brands.manage`)
- ✅ Geração automática de slug e resolução de colisões
- ✅ Validações (nome obrigatório, nome único, URL de logo inválida)
- ✅ Busca por nome
- ✅ Testes unitários do Service isolados do HTTP

> Observação: os testes usam nomes de método `test_*` (em vez de anotações
> `/** @test */`) porque a versão do PHPUnit instalada no projeto não coleta
> mais testes anotados apenas via doc-comment — apenas por prefixo `test`
> ou pelo atributo `#[Test]`.

## 🚀 Uso

### No Controller

```php
use App\Services\BrandService;
use App\Http\Requests\Brand\StoreBrandRequest;

class BrandController extends Controller
{
    public function __construct(
        protected BrandService $brandService
    ) {}

    public function store(StoreBrandRequest $request)
    {
        $brand = $this->brandService->create($request);

        return redirect()->route('brands.index')
            ->with('success', 'Marca cadastrada!');
    }
}
```

## 🎨 Model Features

### Scopes

```php
// Buscar apenas marcas ativas
Brand::active()->get();

// Buscar por termo no nome
Brand::search('Nike')->get();
```

### Accessors

```php
$brand->is_from_erp; // true quando `recno` não é nulo
```

### Relacionamentos

```php
$brand->products(); // hasMany(Product::class) — módulo Product
```

## 📊 Database

### Migration

Tabela `brands`: `id`, `recno` (nullable, único — RECNO da MARCAS_MAR do ERP),
`name`, `slug` (único), `logo_url` (nullable), `active` (default true),
`synced_at` (nullable), timestamps, soft deletes.

```bash
php artisan migrate
```

## 🔐 Boas Práticas Implementadas

1. ✅ **Separação de Responsabilidades** — Controller apenas coordena, Service contém a regra de negócio (slug), FormRequest valida.
2. ✅ **Type Safety** — Métodos com type hints e return types.
3. ✅ **Tratamento de Erros** — Try/catch nos métodos de escrita do Controller.
4. ✅ **Testabilidade** — Factory dedicada e testes de unidade/feature.

## 📚 Referências

- [Client-Module.md](Client-Module.md) - Módulo irmão do qual esta estrutura foi clonada
- [Laravel Docs](https://laravel.com/docs)
