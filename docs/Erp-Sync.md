# Sincronização de Catálogo com o ERP (Consoft)

## O que é

Sincroniza as tabelas locais `brands` e `products` a partir do ERP legado
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
- **`PRODUTO_PRO`** (produtos, ~262 linhas): `RECNO` (PK), `IS_DELETED`,
  `ATIVO` ('S'/'N'), `ENVIA_SITE` ('S'/'N' — "enviar para o site"), `MARCPRO`
  (código da marca, junta com `MARCAS_MAR.CODMARC`), `NOMELONG` (nome
  completo), `NOMPRO` (nome curto), `PRECO3` (preço principal), `PREATAC`
  (preço atacado), `PRECOWEB` (preço web), `PREVEN` (preço de venda),
  `PREMIN` (preço mínimo), `ESTOQUE` (quantidade em estoque).

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

- **Produtos** (`ErpCatalogSyncService::syncProducts()`):
  - Produto novo: cria com `recno`, `brand_id` (resolvido — ver abaixo),
    `name` (= `NOMELONG`), `short_name` (= `NOMPRO`, `null` se vazio), preços
    (`price`, `wholesale_price`, `web_price`, `sale_price`, `min_price`),
    `stock`, `slug` (slugificado e deduplicado a partir de `NOMELONG`),
    `active = (ATIVO === 'S' && ENVIA_SITE === 'S')` e `synced_at = now()`.
  - Produto já existente: atualiza **apenas** `name`, `short_name`, os
    preços, `stock` e `synced_at`. **Nunca** sobrescreve `slug`,
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

- **Ordem importa**: a sincronização de marcas deve rodar **antes** da de
  produtos, porque a resolução do `brand_id` de cada produto depende das
  marcas já estarem gravadas localmente. `syncAll()` já garante essa ordem;
  ao chamar `syncBrands()`/`syncProducts()` isoladamente (ex.: `--only`),
  quem dispara é responsável por essa ordem.

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

## Como rodar manualmente

```bash
# Simula a sincronização sem gravar nada (mostra criados/atualizados/ignorados)
php artisan erp:sync-catalog --dry-run

# Sincroniza marcas e produtos de verdade
php artisan erp:sync-catalog

# Só marcas ou só produtos
php artisan erp:sync-catalog --only=marcas
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
