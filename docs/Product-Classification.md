# 🏷️ Categorias & Características dos Produtos

Como o eshop deriva **categoria**, **armazenamento** e **cor** dos produtos a partir do nome vindo do ERP — não há esses campos na origem (`PRODUTO_PRO`), então são inferidos por um classificador determinístico baseado em palavras-chave (`App\Services\Catalog\ProductClassifierService`), não por IA.

## 🎯 Por quê

O ERP não tem conceito de Categoria, e o nome do produto (`NOMELONG`) já carrega toda a informação relevante em texto livre (ex.: `"IPHONE 17 PRO MAX 256GB US ORANGE"`, `"MACBOOK AIR 13 M4 16GB/256GB SSD - MIDNIGHT"`). O classificador extrai daí o que o frontend Orbita já sabe renderizar (facet de marca/categoria/armazenamento/cor, swatch de cor — ver `docs/Orbita-Api.md`).

## 📐 Como funciona (`ProductClassifierService`)

- **Categoria**: lista ordenada de regras `slug => [nome, [palavras-chave]]`. A primeira regra cujo termo aparece no nome (case-insensitive) vence — categorias mais específicas vêm antes das genéricas. Produto que não bate com nenhuma regra cai em `"Outros"`.
- **Armazenamento**: regex `\d+\s*(GB|TB)` — pega o **maior** valor entre todas as ocorrências (não a primeira), porque a ordem RAM/Armazenamento varia entre linhas de produto no ERP (`"16GB/256GB SSD"` vs `"256GB/8GB"`), mas armazenamento é sempre maior que RAM nesse catálogo.
- **Cor**: dicionário de termos ordenado das frases mais específicas pras mais genéricas (`"SPACE GRAY"` antes de `"GRAY"`), pra não perder cores compostas.

## 🗂️ Categorias derivadas do catálogo real (262 produtos, set/2026)

| Categoria | Palavras-chave | Produtos |
|---|---|---|
| Celular | IPHONE, XIAOMI, REDMI | 170 |
| Performance & Longevidade | MOUNJARO, TIRZEPATIDE(A), RETATRUDITE/RETATRUTIDE, TB500, CJC-IPA, GHK-CU, GLOW, NAD+, LANDERGOLD | 27 |
| Notebook | MACBOOK, MACMINI | 14 |
| Perfume | PERF (prefixo) | 13 |
| Smartwatch | WATCH | 9 |
| Estética | BOTOX, PREENCHEDOR, MASCARA CAPILAR | 8 |
| Tablet | IPAD | 7 |
| Eletrônicos | FIRE STICK, LIQUIDIFICADOR, OCULOS | 6 |
| Acessórios | AIRTAG, CABO, USB-C | 3 |
| Games | PLAYSTATION | 1 |
| Outros (não classificado) | — | 4 |

Os 4 produtos em "Outros" são linhas de teste/incompletas no ERP (`"XX"`, `"FRAG"`, `"SWAP 10 10"`, `"PRODUTO TESTE SINCRONIZACAO"`), não produtos reais.

## 🚀 Quando roda

1. **Backfill único**: `php artisan products:classify` — classifica todo produto com `category_id`, `storage` ou `color_name` nulo. `--dry-run` mostra o resultado sem gravar; `--force` reclassifica tudo, mesmo já classificado (útil depois de mudar uma regra).
2. **Automático em todo produto novo**: `ErpCatalogSyncService::syncProducts()` chama o mesmo classificador na criação de cada produto novo vindo do `erp:sync-catalog` — então o catálogo se mantém classificado sozinho conforme o ERP cresce.

Em ambos os casos, **nunca reclassifica um produto que já tem categoria/storage/cor definidos** (a não ser com `--force`) — se o admin reatribuir a categoria manualmente pela tela de edição do produto, um resync não desfaz isso, mesmo padrão de "campos próprios do site" usado em `slug`/`active`/`description` (ver `docs/Erp-Sync.md`).

## ✏️ Ajustando as regras

As regras vivem só em `ProductClassifierService::CATEGORY_RULES`/`COLOR_DICTIONARY` — adicionar uma palavra-chave nova e rodar `php artisan products:classify --force` reclassifica o catálogo inteiro com a regra atualizada. Categorias existentes são reaproveitadas por slug (`Category::firstOrCreate(['slug' => ...])`), então renomear o `name` de uma regra não duplica a categoria — só se o `slug` mudar.

## 🔮 Limitações conhecidas

- Sem admin de categoria (CRUD) — hoje é só leitura, geradas pelo classificador. Produto pode ser reatribuído a uma categoria existente pela tela de edição, mas criar uma categoria nova exige editar as regras do classificador.
- Sem agrupamento de variantes (`model`) — produtos com o mesmo modelo em cores/armazenamentos diferentes não são linkados como "irmãos" (campo `variants` do Orbita).
- Categoria/armazenamento/cor de produtos criados manualmente pelo admin (sem `recno`, não vindos do ERP) não são classificados automaticamente — só produtos sincronizados do ERP passam pelo classificador na criação.
