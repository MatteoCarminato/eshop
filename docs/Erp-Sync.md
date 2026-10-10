# Sincronização de Catálogo com o ERP (Consoft)

## O que é

Sincroniza as tabelas locais `brands`, `product_groups`, `product_subgroups` e
`products` a partir do ERP legado
Consoft do cliente, para que o catálogo do site (`App\Models\Brand` /
`App\Models\Product`) espelhe o que existe no ERP, sem que o site precise
consultar o ERP em tempo real a cada página.

**Importante — não é Firebird.** Apesar dos nomes de coluna no estilo Firebird
(`RECNO`, `IS_DELETED`), que são apenas uma convenção herdada de um sistema
mais antigo, o servidor real é **MySQL puro**, acessível em
`38.52.135.193:3306`, banco `cec_consulta`. A conexão Laravel `erp` (definida
em `config/database.php`, driver `mysql`) já aponta para lá, com credenciais
vindas de `.env` (`ERP_DB_HOST`, `ERP_DB_PORT`, `ERP_DB_DATABASE`,
`ERP_DB_USERNAME`, `ERP_DB_PASSWORD`, `ERP_DB_CHARSET`).

**A conexão `erp` é somente leitura, por convenção.** Nunca escrever nela,
nunca rodar migrations contra ela, nunca chamar `Schema::connection('erp')`.
Toda escrita acontece nas tabelas locais `brands`/`products` via os models
Eloquent normais (conexão padrão da aplicação).

## Tabelas de origem

- **`MARCAS_MAR`** (marcas, ~28 linhas): `RECNO` (PK), `IS_DELETED` ('Y'/'N'),
  `CODMARC` (código numérico da marca — é o que `PRODUTO_PRO.MARCPRO`
  referencia, **não** `RECNO`), `NOMMARC` (nome).
- **`GRUPO`** (grupos de produto, 12 linhas): `RECNO` (PK), `IS_DELETED`,
  `CODGRU` (código do grupo — é o que `PRODUTO_PRO.GRUPRO` referencia,
  **não** `RECNO`), `NOMGRU` (nome, ex.: CELULAR, TABLET, PERFUME).
- **`SUB`** (subgrupos de produto, 61 linhas): `RECNO` (PK), `IS_DELETED`,
  `CODSGRU` (código do subgrupo — é o que `PRODUTO_PRO.SGRUPRO` referencia,
  **não** `RECNO`), `NOMSGRU` (nome, ex.: TABLET APPLE, MOUNJARO,
  IPHONE LACRADO).
- **`PRODUTO_PRO`** (produtos, ~270 linhas): `RECNO` (PK), `IS_DELETED`,
  `ATIVO` ('S'/'N'), `ENVIA_SITE` ('S'/'N' — "enviar para o site"), `MARCPRO`
  (código da marca, junta com `MARCAS_MAR.CODMARC`), `GRUPRO` (código do
  grupo, junta com `GRUPO.CODGRU`), `SGRUPRO` (código do subgrupo, junta com
  `SUB.CODSGRU`), `NOMELONG` (nome completo), `NOMPRO` (nome curto), `PRECO3`
  (preço principal), `PREATAC` (preço atacado), `PRECOWEB` (preço web),
  `PREVEN` (preço de venda), `PREMIN` (preço mínimo), `ESTOQUE` (quantidade em
  estoque).

### Grupo e subgrupo não são hierárquicos

`SUB` **não** tem coluna apontando para `GRUPO` — são duas listas planas e
independentes. O produto carrega os dois códigos separadamente (`GRUPRO` e
`SGRUPRO`), então subgrupo não é "filho" de grupo e não existe no ERP a
informação de qual subgrupo pertence a qual grupo. Filtrar por grupo e por
subgrupo são dois filtros independentes.

**Atenção ao nome da coluna:** no `PRODUTO_PRO` a coluna do grupo é `GRUPRO`
(não `GRUPO`). Existe também `GRUESP` ("grupo especial"), que não é usado aqui.

## Semântica do upsert (idempotente e não-destrutivo)

A sincronização roda quantas vezes for preciso sem duplicar nem "resetar"
edições feitas por um admin no site. Ela faz upsert por `recno`:

- **Marcas** (`ErpCatalogSyncService::syncBrands()`):
  - Marca nova (recno ainda não existe localmente): cria com `recno`, `name`
    (= `NOMMARC` tratado), `slug` (slugificado e deduplicado), `active = true`
    e `synced_at = now()`.
  - Marca já existente: atualiza **apenas** `name` e `synced_at`. **Nunca**
    sobrescreve `slug`, `logo_url` ou `active` — esses são considerados
    "campos do site" e podem ter sido editados manualmente no admin depois do
    sync inicial.
  - Linhas com `IS_DELETED = 'Y'` são ignoradas (contadas como "skipped").

- **Grupos e subgrupos** (`ErpCatalogSyncService::syncGroups()` e
  `syncSubgroups()`): as duas tabelas têm a mesma forma no ERP (RECNO +
  código + nome) e o mesmo formato local, então compartilham o upsert
  `syncClassification()`.
  - Registro novo: cria com `recno`, `code` (= `CODGRU`/`CODSGRU`), `name`,
    `slug` (slugificado e deduplicado), `active = true` e `synced_at`.
  - Registro existente: atualiza **apenas** `code`, `name` e `synced_at` —
    nunca `slug` nem `active`, igual à regra de marcas.
  - Linhas com `IS_DELETED = 'Y'`, sem código (`0`) ou sem nome são ignoradas
    (contadas como "skipped") — sem código não há como ligar produto nenhum.

- **Produtos** (`ErpCatalogSyncService::syncProducts()`):
  - Produto novo: cria com `recno`, `brand_id` (resolvido — ver abaixo),
    `name` (= `NOMELONG`), `short_name` (= `NOMPRO`, `null` se vazio), preços
    (`price`, `wholesale_price`, `web_price`, `sale_price`, `min_price`),
    `stock`, `slug` (slugificado e deduplicado a partir de `NOMELONG`),
    `active = (ATIVO === 'S' && ENVIA_SITE === 'S')` e `synced_at = now()`.
  - Produto já existente: atualiza **apenas** `name`, `short_name`, os
    preços, `stock`, `product_group_id`, `product_subgroup_id` e `synced_at`.
    **Nunca** sobrescreve `slug`,
    `description`, `image_url`, `active`, `featured` nem `brand_id` — se um
    admin reatribuiu manualmente a marca de um produto ou o marcou como
    destaque/inativo pelo admin do site, um re-sync não desfaz isso.
  - Preços/estoque zerados no ERP são gravados como `0`, nunca convertidos
    para `null` — só ficam `null` quando o próprio ERP traz o campo vazio.
  - Exceções (cadeia de fallback para preços zerados/vazios no ERP):
    - `price` (= `PRECO3`): se vier `0`, cai para `PREVEN` (preço de venda)
      quando este for `> 0`. Alguns produtos no ERP ainda não têm `PRECO3`
      cadastrado mas já têm `PREVEN` preenchido.
    - `web_price` (= `PRECOWEB`): se vier `0`/vazio, cai para o `price` já
      resolvido acima (`PRECO3` ou, na ausência dele, `PREVEN`). Na prática
      o ERP não alimenta `PRECOWEB` para a maior parte do catálogo.
    - Sem nenhum desses valores preenchidos no ERP, o produto fica mesmo com
      preço `0` — não há de onde puxar um valor melhor.
  - Linhas com `IS_DELETED = 'Y'` são ignoradas (contadas como "skipped").

### Grupo/subgrupo são a exceção ao "só na criação"

`product_group_id` e `product_subgroup_id` **são** atualizados a cada sync,
diferente de `brand_id`/`category_id`. O motivo: não existe tela no admin do
site para reatribuir grupo ou subgrupo — são classificação crua do ERP, e o
objetivo delas é justamente espelhar o ERP para filtrar o catálogo. Se um
produto for reclassificado de CELULAR para TABLET no ERP, o próximo sync move
o produto. Já marca e categoria podem ter sido corrigidas à mão no site
(categoria, em particular, nasce de um chute do `ProductClassifierService`
sobre o nome), por isso continuam intocadas.

- **Ordem importa**: marcas, grupos e subgrupos devem rodar **antes** de
  produtos, porque a resolução de `brand_id` / `product_group_id` /
  `product_subgroup_id` de cada produto depende desses registros já estarem
  gravados localmente. `syncAll()` já garante essa ordem, e o botão
  "Sincronizar com ERP" da tela de produtos roda grupos e subgrupos antes.
  Ao chamar os métodos isoladamente (ex.: `--only`), quem dispara é
  responsável por essa ordem.

### Resolução de `brand_id` (CODMARC → RECNO → id local)

`brands.recno` guarda `MARCAS_MAR.RECNO`, mas `PRODUTO_PRO.MARCPRO`
referencia `MARCAS_MAR.CODMARC` — são colunas diferentes. Por isso a
resolução é feita em duas etapas, montadas uma única vez por execução do
sync de produtos (evita N+1 queries):

1. `CODMARC => RECNO` direto do ERP (`MARCAS_MAR`).
2. `RECNO => id local` (`brands.recno => brands.id`).
3. Composição das duas em `CODMARC => brand_id local`, usada para resolver o
   `brand_id` de cada produto pelo seu `MARCPRO`.

Se o `MARCPRO` de um produto não tiver correspondência (marca inexistente ou
ainda não sincronizada), `brand_id` fica `null` na criação — e nunca é
alterado depois, nem mesmo se a marca aparecer num sync futuro (ver acima).

### Resolução de grupo/subgrupo (código → id local, uma etapa)

Diferente de marcas, as tabelas locais `product_groups`/`product_subgroups`
guardam o **código** do ERP (`code` = `CODGRU`/`CODSGRU`) além do `recno`. Como
é o código que o produto referencia, o mapa é direto — `code => id local`,
montado uma vez por execução — sem o pulo extra por RECNO que marcas precisam.

Código `0` ou sem correspondência deixa o campo `null` (produto sem
classificação), em vez de barrar o sync do produto.

## Como rodar manualmente

```bash
# Simula a sincronização sem gravar nada (mostra criados/atualizados/ignorados)
php artisan erp:sync-catalog --dry-run

# Sincroniza marcas e produtos de verdade
php artisan erp:sync-catalog

# Uma entidade só
php artisan erp:sync-catalog --only=marcas
php artisan erp:sync-catalog --only=grupos
php artisan erp:sync-catalog --only=subgrupos
php artisan erp:sync-catalog --only=produtos
```

O comando imprime uma tabela final com `Criados` / `Atualizados` / `Ignorados`
por entidade e retorna código de saída não-zero em caso de erro inesperado
(erro fica registrado em log via `Log::error`).

## Agendamento automático

`routes/console.php` agenda `erp:sync-catalog` para rodar a cada 30 minutos
(`->everyThirtyMinutes()`), com `->withoutOverlapping()` para não empilhar
execuções caso uma demore mais que o intervalo. Para mudar a cadência, edite
a entrada `Schedule::command('erp:sync-catalog')` nesse arquivo (ex.:
`->hourly()`, `->dailyAt('03:00')`, etc. — ver documentação do Laravel Task
Scheduling).

## Botões no admin

As telas de admin de Marcas e Produtos têm um botão "Sincronizar com ERP"
que dispara a sincronização de forma síncrona dentro do próprio request
(`POST /brands/sync` e `POST /products/sync`, via
`App\Http\Controllers\CatalogSyncController`). Como as tabelas são pequenas
(dezenas/centenas de linhas), isso roda rápido o suficiente sem precisar de
fila — o usuário é redirecionado de volta para a listagem com uma mensagem de
sucesso ("Sincronização concluída: X criados, Y atualizados, Z ignorados.")
ou de erro.

## Limitação conhecida

`PRODUTO_PRO` tem ~130 outras colunas no ERP (código de barras, dimensões,
códigos fiscais, flags de promoção, etc.) que **não** são sincronizadas nesta
primeira versão — só os campos listados acima são mapeados. Em particular,
`CODWEB`, `REFER`, `BARRA`, `HTTPLINK` e `FOTOPRO` existem no ERP e poderiam
ser mapeados futuramente caso o site precise de um código de barras, link
externo de imagem, ou flag de "tem foto" — mas ficaram fora do escopo desta
etapa.
