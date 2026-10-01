# 🛍️ API Orbita ("Colmeia") - Documentação

API pública somente-leitura, consumida pelo frontend Next.js do Orbita (`orbita/web`), que o próprio código do front já se referia como "Colmeia" (`src/lib/api.ts`).

## 🎯 Autenticação

Header `X-Orbita-Token`, comparado contra `services.orbita.token` (`ORBITA_API_TOKEN` no `.env`). Ver `App\Http\Middleware\EnsureOrbitaToken`.

O valor já configurado no `.env` do eshop é o mesmo que `orbita/web/.env.local` (`API_TOKEN`) já esperava — não precisa mudar nada do lado do Orbita.

## 📂 Rotas (`routes/api.php`, prefixo automático `/api`)

| Rota | Controller | Status |
|---|---|---|
| `GET /api/orbita/products` | `OrbitaProductController@index` | ✅ Dados reais (`products`/`brands`/`categories`), com facets |
| `GET /api/orbita/products/{slug}` | `OrbitaProductController@show` | ✅ Dados reais |
| `GET /api/orbita/promotions` | `OrbitaProductController@promotions` | ⚠️ Sempre `[]` — sem conceito de promoção no eshop ainda |
| `GET /api/orbita/categories/home` | `OrbitaSiteController@categoriesHome` | ✅ Categorias reais, sem imagem própria (`image: null`) |
| `GET /api/orbita/categories/menu` | `OrbitaSiteController@categoriesMenu` | ✅ Categorias reais, sem sub-categoria (`children: []`) |
| `GET /api/orbita/settings` | `OrbitaSiteController@settings` | ⚠️ Sempre `null` (front cai no fallback "LYNCE ST") |
| `GET /api/orbita/banners` | `OrbitaSiteController@banners` | ⚠️ Sempre `[]` — sem admin de banners ainda |
| `GET /api/orbita/popups` | `OrbitaSiteController@popups` | ⚠️ Sempre `[]` |
| `GET /api/orbita/sellers` | `OrbitaSiteController@sellers` | ⚠️ Sempre `[]` |

As rotas "⚠️" respondem `200` com lista/valor vazio (nunca `404`), porque o Orbita já trata isso como "seção não aparece" — devolver `404` quebraria a página inteira (só `getPromotedProducts` tem try/catch no front; os outros lançam e derrubam o layout/home).

## 🔎 `GET /api/orbita/products`

Atende dois usos do front com o mesmo path: `getProducts()` (só lê `data`) e `searchProducts()` (lê `data` + `meta`, com facets/paginação/faixa de preço).

Query params aceitos: `q`, `category`, `brand`, `model`, `storage`, `color`, `price_min`, `price_max`, `sort` (`newest|price_asc|price_desc|name_asc`), `page`, `limit`.

`category` filtra por slug (`Product->category`), `brand` por nome (case-insensitive, `Product->brand`), `storage`/`color` por igualdade exata (mesmos valores gerados por `ProductClassifierService` — ver `docs/Product-Classification.md`). `model` é aceito (não quebra a chamada) mas **ignorado** — o eshop não agrupa produtos por modelo/variante ainda.

`meta.facets` traz contagens reais de `brands`, `categories`, `storage` e `colors` (calculadas sobre o resultado filtrado, excluindo a própria dimensão — mesma técnica do mock local do front). `models` fica sempre `[]`.

### Regra de negócio: produto nunca aparece sem preço/estoque

`OrbitaCatalogService::sellableQuery()` filtra sempre `active = true AND price > 0 AND stock > 0`, em toda listagem/busca/detalhe. Isso espelha uma regra que o próprio front já documenta esperar do Colmeia (`src/lib/api.ts`, função `isSellable`) — aqui é a fonte de verdade; o filtro do front é só um cinto e suspensório.

## 🗺️ Mapeamento de campos (`App\Http\Resources\Orbita\ProductResource`)

| Campo Orbita | Origem no eshop |
|---|---|
| `id`, `slug`, `name` | diretos |
| `brand` | `product.brand.name` (`''` se sem marca) |
| `brandLogo` | só no `show()` (detalhe), `product.brand.logo_url` |
| `description` | `product.description ?? ''` |
| `price` | `product.price` |
| `priceUsd` / `pricePyg` | convertido via `ExchangeRateService` (USD/BRL real) + taxa Gs./USD fixa (`OrbitaCatalogService::PYG_PER_USD = 6400`, sem fonte real ainda) |
| `availability` | sempre `InStock` (produtos sem estoque já são excluídos antes) |
| `sku` | `ERP-{recno}` ou `SITE-{id}` (placeholder — eshop não guarda SKU real do ERP) |
| `category` / `categorySlug` | `product.category.{name,slug}`, ou `"Outros"` / `"outros"` se não classificado |
| `storage` | `product.storage` (ex.: `"256GB"`) — omitido se nulo |
| `colorName` | `product.color_name` (ex.: `"Blue"`) — omitido se nulo; o front resolve o hex do swatch sozinho |
| `images` / `image` | `[{url: image_url, alt: name}]` quando `image_url` existe, senão vazio |
| `art` | sempre `""` (placeholder neutro — sem classificação de tipo de produto) |
| `compareAtPrice`, `promotion`, `model` (agrupamento de variante), `colorHex`, `tag`, `rating`, `specs`, `variants`, `gtin`, `mpn` | **omitidos** — sem equivalente no eshop |

`category`/`storage`/`colorName` vêm de `App\Services\Catalog\ProductClassifierService`, rodado uma vez sobre o catálogo existente (`php artisan products:classify`) e automaticamente em todo produto novo criado pelo `erp:sync-catalog`. Ver `docs/Product-Classification.md`.

## 🔮 Próximos passos (se/quando fizer sentido)

- [ ] Agrupamento de variantes (`model`) — mesmo produto em cores/armazenamentos diferentes como "irmãos" no detalhe
- [ ] SKU/GTIN reais (talvez `CODWEB`/`REFER` do ERP — ver `docs/Erp-Sync.md`)
- [ ] Câmbio Guarani real (hoje é uma taxa fixa aproximada)
- [ ] Admin de banners/popups/sellers (hoje sempre vazio) e de categorias (hoje só leitura, geradas por classificador)
- [ ] Imagem própria por categoria (hoje `image: null` sempre)
- [ ] Upload de múltiplas imagens por produto (hoje só `image_url` único)
