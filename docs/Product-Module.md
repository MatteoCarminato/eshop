# Módulo Product - Documentação

## Arquitetura

Este módulo segue os mesmos padrões do módulo Client: Service Layer + FormRequests + Controller fino.

### Estrutura

```
app/
├── Http/
│   ├── Controllers/
│   │   └── ProductController.php          # Controller fino (apenas coordena)
│   └── Requests/
│       └── Product/
│           ├── StoreProductRequest.php    # Validação de criação
│           └── UpdateProductRequest.php   # Validação de atualização
│
├── Services/
│   └── ProductService.php                 # Lógica de negócio (filtros, geração de slug)
│
├── Models/
│   └── Product.php                        # Model com relacionamento Brand e scopes

database/
├── factories/
│   └── ProductFactory.php
└── migrations/
    └── 2026_09_27_100100_create_products_table.php

resources/views/admin/products/
├── index.blade.php
├── create.blade.php
├── edit.blade.php
└── show.blade.php

tests/
├── Feature/
│   └── ProductTest.php
└── Unit/
    └── ProductServiceTest.php
```

## Fluxo de dados

```
Request → StoreProductRequest/UpdateProductRequest (validação) → ProductController → ProductService → Product → Database
                                                                       ↓
                                                                   View (Blade)
```

`Product` pertence opcionalmente a uma `Brand` (`brand_id`, `nullOnDelete`). O relacionamento é apenas
consumido aqui (leitura); o módulo Brand é responsabilidade de outro workstream.

## Funcionalidades do Service

`ProductService` não tem interface, é uma classe simples com os métodos:

- `list(?int $perPage = null)` — lista todos os produtos, paginado ou não.
- `filter(?string $search, ?int $brandId, ?bool $active, ?int $perPage = null)` — busca por `name`/`short_name`
  (case-insensitive) e filtra por marca e por status ativo/inativo, combináveis.
- `findById(int $id)` — busca por ID (`findOrFail`).
- `create(StoreProductRequest $request)` — cria o produto e gera o `slug` automaticamente.
- `update(UpdateProductRequest $request, Product $product)` — atualiza o produto; regenera o `slug` apenas
  quando o `name` muda.
- `delete(Product $product)` — soft delete.

### Geração de slug

O campo `slug` **não é um input do formulário** — é gerado no Service a partir do `name` via
`Str::slug()`, com sufixo `-2`, `-3`, ... em caso de colisão (checando inclusive produtos com soft delete,
já que a coluna tem índice único simples). Mesmo padrão adotado pelo módulo Brand.

### Normalização de booleans

`active` e `featured` seguem o mesmo padrão de switches usado no restante do app (campo hidden `0` +
checkbox `1`): quando a chave não é enviada no payload, `prepareForValidation()` normaliza para `false`.
As telas de criação/edição sempre enviam o campo hidden, então o comportamento de UI é o esperado
(padrão "Ativo" marcado na criação); só uma chamada direta ao Service sem passar por essas telas resulta
em `false` por omissão.

## Testes

```bash
# Todos os testes do módulo
php artisan test --filter=Product

# Somente feature (HTTP/CRUD)
php artisan test tests/Feature/ProductTest.php

# Somente unit (Service)
php artisan test tests/Unit/ProductServiceTest.php
```

Cobertura:
- CRUD completo via HTTP (create/show/edit/update/delete)
- Validações (nome e preço obrigatórios, preço negativo, `brand_id` inválido)
- Geração e unicidade de slug (incluindo colisão com registros soft-deleted)
- Filtros de busca (`search`), marca (`brand`) e status (`active`) via query string
- Gate de módulo (`module:products.view` / `module:products.manage`): visitante é redirecionado ao
  login, usuário autenticado sem o módulo recebe 403

Os testes de rota autenticam um usuário de teste criando um `Role` avulso com as entradas de
`role_module` necessárias (`products.view`/`products.manage`), em vez de depender de um usuário admin
fixo — assim o teste também serve como verificação do middleware `module:`.

Alguns testes de filtro/relacionamento por marca usam `App\Models\Brand`/`BrandFactory`; se esses ainda
não existirem no momento da execução (dependem de um workstream paralelo), esses casos específicos são
pulados com `markTestSkipped()` em vez de falhar o suite inteiro.

## Uso

```php
use App\Services\ProductService;
use App\Http\Requests\Product\StoreProductRequest;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    public function store(StoreProductRequest $request)
    {
        $product = $this->productService->create($request);

        return redirect()->route('products.index')
            ->with('success', 'Produto cadastrado!');
    }
}
```

## Model Features

### Scopes

```php
Product::active()->get();          // where active = true
Product::featured()->get();        // where featured = true
Product::search('camiseta')->get(); // where name/short_name like
```

### Accessors

```php
$product->formatted_price;  // "R$ 199,90"
$product->is_from_erp;      // true quando recno não é nulo
```

### Relacionamentos

```php
$product->brand;  // App\Models\Brand|null
$product->images; // Illuminate\Database\Eloquent\Collection<ProductImage>, ordenada por sort_order
```

### Fotos (foto principal + galeria)

Dois conceitos separados:

- **Foto principal** (`products.image_url`) — a foto usada nos cards de listagem da loja (equivalente a
  `image` no contrato do Orbita, ver `docs/Orbita-Api.md`). Pode ser definida de duas formas no admin:
  upload de arquivo (campo `image`, armazenado no DigitalOcean Spaces) ou colando uma URL já hospedada
  (campo `image_url`, texto livre). Se os dois vierem preenchidos no mesmo request, o **upload tem
  prioridade** e sobrescreve o valor de `image_url`.
- **Galeria** (tabela `product_images`, `product_id` + `url` + `sort_order`) — fotos extras mostradas no
  carrossel ao abrir o produto na loja (`ProductGallery` no Orbita). Upload múltiplo (campo `gallery[]`,
  até 10 arquivos por vez, sempre **acrescentados** ao final da galeria existente — nunca substituem as
  fotos já cadastradas). Remoção de uma foto específica da galeria é feita marcando seu checkbox
  "Remover" na tela de edição (campo `remove_gallery[]`, lista de IDs de `product_images`).

Upload (foto principal e galeria) vai pro disco `do_spaces` (DigitalOcean Spaces, mesma convenção do
módulo de WhatsApp — ver `WhatsappWebhookController`), em
`eshop-{env}/products/{Y}/{m}/{d}/{md5}-{slug-do-nome}.{ext}`, com visibilidade pública. O `md5` é de um
UUID gerado na hora (garante arquivo único mesmo se o mesmo arquivo for reenviado — não é hash do
conteúdo), e o slug do nome do produto vai concatenado pra URL da imagem carregar palavras-chave reais
em vez de um identificador sem significado (bom pra SEO de busca de imagens). O arquivo de origem **não é
apagado do Spaces** quando uma foto é removida da galeria ou substituída — só o registro no banco some
(órfão aceitável, evita lógica frágil de parse de URL pra apagar objeto remoto). `ProductService` concentra
toda essa lógica (`storeImage()`, `addGalleryImages()`).

`App\Http\Resources\Orbita\ProductResource` expõe `image` (capa) e `images` (capa + galeria, sem
duplicar a capa caso ela também tenha sido adicionada à galeria) pro contrato do front Orbita. O `alt`
de cada imagem é `"{nome do produto} - eShop Cell"`, não só o nome — também pensado pra SEO (texto
alternativo descritivo, com marca, pra buscadores e leitores de tela).

## Database

Ver `database/migrations/2026_09_27_100100_create_products_table.php`. Destaques:
- `recno` (nullable, unique) — chave do PRODUTO_PRO do ERP; nulo = produto cadastrado manualmente no site.
- `brand_id` (nullable, `nullOnDelete`) — marca associada.
- Preços em `decimal(18,8)` espelhando o ERP (`price`, `wholesale_price`, `web_price`, `sale_price`,
  `min_price`); `stock` em `decimal(18,4)`.
- `slug` (unique), `description`, `image_url`, `active`, `featured` — campos próprios do site.
- `synced_at` — preenchido pela sincronização com o ERP (`CatalogSyncController`, fora deste módulo).
- `softDeletes()` + índice em `active`.

```bash
php artisan migrate
```

## Boas Práticas Implementadas

1. **Separação de Responsabilidades** — Controller apenas coordena; Service contém a lógica de negócio
   (filtros, geração de slug); FormRequest valida e normaliza os dados.
2. **Type Safety** — métodos do Service com type hints e return types.
3. **Tratamento de Erros** — try/catch no Controller nas ações de escrita, com mensagens amigáveis em
   PT-BR via flash session.
4. **Testabilidade** — Service testado isoladamente (Unit) e fluxo HTTP completo testado (Feature),
   incluindo o gate de permissão por módulo.
5. **Campos ERP somente leitura** — `recno` e `synced_at` nunca aparecem em formulários; são exibidos
   apenas na tela de detalhes (`show.blade.php`) quando presentes.
