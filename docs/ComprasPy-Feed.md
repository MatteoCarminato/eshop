# ComprasPY (feed XML + API de importação)

O ComprasPY (agregador de ofertas) lê um XML do catálogo em
**`https://eshopcell.com/compraspy/feedseshop.xml`**. Essa URL já está
cadastrada do lado deles, então ela é contrato: o caminho e o formato não
mudam, só quem os produz.

Antes, quem produzia era um cron no painel legado
(`/compraspy/gerar_feed_compraspy.php` + `feed_precoweb_lib.php`), que lia
`PRODUTO_PRO ⨯ tb_precoweb_manual` e gravava o XML num arquivo do
`public_html`. Esse cron pode ser desligado — ver "Desligar o legado".

## Como funciona agora

```
products (tabela local, já sincronizada do ERP por erp:sync-catalog)
   │
   ├─ ComprasPyFeedService ──► XML em cache (1h)
   │        ▲                        │
   │        │                        ▼
   │   compraspy:feed          GET /compraspy/feedseshop.xml   (painel, público)
   │   (scheduler, 1x/h)                   │
   │   + botão "Atualizar XML"             ▼
   │                           GET /compraspy/feedseshop.xml   (loja, proxy Next)
   │                                       │
   └───────────────────────────────────────┴──► ComprasPY
```

Duas pontas porque os domínios são diferentes: o catálogo está no painel
(`eshop.lynce.net`) e a URL cadastrada é a da loja (`eshopcell.com`, que é o
Orbita/Next.js). Os dois rodam no mesmo servidor, em pastas vizinhas, então o
proxy é uma chamada local.

### Painel (este repo)

| Peça | Arquivo |
| --- | --- |
| Montagem do XML | `app/Services/Feed/ComprasPyFeedService.php` |
| Envio pela API deles | `app/Services/Feed/ComprasPyImportService.php` |
| Rota pública + ação do botão | `app/Http/Controllers/ComprasPyFeedController.php`, `routes/web.php` |
| Command do scheduler | `app/Console/Commands/GenerateComprasPyFeedCommand.php`, `routes/console.php` |
| Configuração | `config/feeds.php` |
| Testes | `tests/Feature/ComprasPyFeedTest.php`, `tests/Feature/ComprasPyImportTest.php` |

- `GET /compraspy/feedseshop.xml` — **rota pública**, sem auth: o agregador
  busca sem credencial, como fazia com o arquivo estático. Também roda fora da
  pilha de sessão/cookie do grupo `web` — resposta cacheável, sem `Set-Cookie`.
- `php artisan compraspy:feed` — remonta o XML e reaquece o cache. Agendado
  **de hora em hora**. Aceita `--dry-run` (monta e mostra o resumo sem gravar
  no cache).
- Botão **"Atualizar XML"** na grade Produtos × Preços (`/products-admin`,
  canto superior direito, ao lado de "Exportar"): republica na hora, pra quem
  acabou de mexer em preço/estoque não esperar o ciclo. Exige
  `products.manage`.

Não há arquivo em disco: o XML é montado sob demanda e cacheado. Isso elimina
o lock, o `.tmp`/`rename` e a dependência de permissão de escrita que o cron
legado precisava.

### Loja (repo `orbita`)

`web/src/app/compraspy/feedseshop.xml/route.ts` só repassa o XML do painel.
Ele resolve a URL de origem assim:

1. `COMPRASPY_FEED_URL`, se estiver no `.env`; senão
2. deriva de `API_BASE_URL` (mesma origem, path `/compraspy/feedseshop.xml`).

Em erro do painel, responde 502 — **nunca um XML vazio**, porque feed vazio o
ComprasPY leria como "a loja não tem mais nada" e derrubaria os anúncios.

O cache do proxy é de 60s de propósito: o cache longo é o do painel, e um
cache de 1h aqui faria o botão "Atualizar XML" levar até uma hora pra aparecer
no domínio da loja.

## Envio na hora pela API deles (`POST /api/products/import/`)

O XML é lido de hora em hora — tempo demais pra um preço. Então, **ao salvar o
Preço Web na grade `/products-admin`, aquele produto é empurrado na hora** pra
API de importação do ComprasPY (`ComprasPyImportService`, chamado de
`ProductAdminController::updatePrices`). Os dois canais convivem: o XML é a base
do catálogo (inclusive de quem nunca teve o preço editado) e o envio é o
empurrão pontual de quem acabou de mudar.

Como o aviso ao Orbita, o envio sai **depois da resposta** (não atrasa o Tab na
grade) e é **melhor-esforço**: ComprasPY fora do ar, sem credencial ou
recusando só gera log e nunca desfaz a gravação — o feed XML corrige no ciclo
seguinte.

### Contrato (do schema deles)

`https://products.comprasparaguai.com.br/api/schema/swagger-ui/`

- Credencial no header `token` (token da loja); `Authorization: Bearer <jwt>`
  também é aceito. Configurar **uma só**: `COMPRASPY_API_TOKEN` ou
  `COMPRASPY_API_BEARER`. Vazias = envio desligado, vale só o XML.
  **As duas juntas dão 401.** O token que eles entregam é um UUID, não um JWT;
  mandado também como Bearer, o middleware de JWT deles recusa com
  `{"detail":"Given token not valid for any token type"}` antes de olhar o
  header `token` (conferido contra a API de produção — com o header `token`
  sozinho, a mesma chamada devolve 207). O curl que o Swagger UI mostra manda
  os dois porque preenche os dois campos de auth da tela; `headers()` manda um,
  com precedência pro `token`.
- Body é uma **lista**, no máximo **500 produtos** por lote (`pushMany()` já
  fatia nesse tamanho).
- Obrigatórios: `code`, `name.pt`, `price`, `stock`, `description.pt`,
  `url_image`, `link`.
- Sucesso é **207**, com `products_processed`/`products_failed` e os motivos em
  `validation_errors` — lote aceito em parte é normal, e o que falhou vai pro
  log com o motivo.
- `url_image` tem de terminar em `.png`/`.jpg`/`.jpeg`/`.webp` (extensão olhada
  no caminho, query string é aceita) e a imagem é baixada **de forma
  assíncrona**: a URL precisa continuar de pé depois do envio.

### Como cada campo é preenchido

| Campo deles | De onde sai |
| --- | --- |
| `code` | `Product::feedCode()`: `products.web_code` (CODWEB) — mesmo código do `<codigo>` do XML |
| `name.pt` | `products.name`. Sem `name.es`: não há tradução no catálogo, e o campo é opcional |
| `price`, `price_iva`, `price_ecommerce_br` | **`products.web_price`**, em dólar, os três com o mesmo valor |
| `stock` | `1` quando vendável; **`0`** quando não é mais (é assim que se derruba um anúncio) |
| `brand` | `brands.name` |
| `description.pt` | `products.description` sem HTML; vazia (o caso da maioria), vai o nome do produto |
| `url_image` | `products.image_url` absolutizada |
| `link`, `link_purchase`, `link_ecommerce_br` | `{COMPRASPY_STORE_URL}/produtos/{slug}` — a mesma página do XML |
| `force_image_update` | `COMPRASPY_API_FORCE_IMAGE_UPDATE` (padrão `false`) |

Duas diferenças deliberadas em relação ao XML:

- **Preço:** aqui vai o Preço Web puro; o XML publica o `sitePrice()`
  (promoção do painel quando válida). O gatilho do envio é a edição dessa
  coluna, e é ela que o anúncio acompanha. Produto em promoção, portanto,
  aparece no XML pelo preço promocional e por este canal pelo Preço Web.
- **Estoque:** o XML publica a quantidade do ERP; aqui vai `1`/`0`, porque pro
  anúncio só importa se tem ou não tem.

### Produto sem foto fica de fora

`url_image` é obrigatória lá. Na produção a cobertura é boa — dos 30
vendáveis, 28 têm foto em formato aceito —, mas **os `SWAP …` não têm**, e
produto sem foto usável é pulado (com log) em vez de recusado do outro lado.
Eles continuam no XML, onde a imagem é opcional. Duas saídas:

1. cadastrar as fotos no admin (o certo); ou
2. `COMPRASPY_API_FALLBACK_IMAGE` com a URL de uma imagem genérica da loja —
   aí o produto é anunciado com ela em vez de ficar de fora.

### Estado do catálogo do lado deles (22/10/2026)

`GET /api/products/list/` (mesma credencial) mostra o que o ComprasPY tem da
loja. Na última verificação: **58 produtos, nenhum alterado desde 11/08/2026**,
`updated_at` quase todos nulos e todos os `link` ainda apontando pro
`eshopcell.com/compraspy/produto.php?id=...` do site legado. Os códigos de lá
nem coincidem com os do feed atual.

Ou seja: o XML novo está no ar e correto (30 itens, links `/produtos/{slug}`,
imagens do bucket de produção), mas **eles não o leram nenhuma vez** — a
importação deles parou quando o cron legado morreu, em agosto. Vale cobrar o
re-cadastro/re-crawl com eles, porque o XML é a base do catálogo; a API de
importação é o caminho que não depende de eles vir buscar.

Esse endpoint de listagem é só leitura e serve de conferência depois de
qualquer envio:

```bash
curl -s "https://products.comprasparaguai.com.br/api/products/list/?code=376" \
  -H "token: $COMPRASPY_API_TOKEN"
```

## O que entra no feed

Mesma regra de vendável da loja (`active` + `web_price > 0` + `stock > 0`, ver
`OrbitaCatalogService::sellableQuery()`), mais um piso de preço
(`COMPRASPY_FEED_MIN_PRICE`, padrão US$ 0,10) herdado do legado contra produto
com preço esquecido virar anúncio.

A flag por produto `destino_xml` do painel legado **não foi portada**: o feed
segue a vitrine, então anúncio e loja não podem divergir. Se voltar a ser
necessário escolher produto a produto o que vai pro ComprasPY, isso vira uma
coluna em `products` + um toggle no admin, e o filtro entra em
`ComprasPyFeedService::eligibleQuery()`.

### Formato do item

Idêntico ao feed antigo — as tags fora do RSS padrão (`codigo`, `preco`,
`estoque`, `disponibilidade`, `link_imagem`) e o sufixo `" USD"` no preço são
contrato do ComprasPY:

```xml
<item>
  <title>IPHONE 17 256GB LL BLACK</title>
  <link>https://eshopcell.com/produtos/iphone-17-256gb-ll-black</link>
  <description></description>
  <codigo>1618</codigo>
  <preco>915.00 USD</preco>
  <estoque>49</estoque>
  <disponibilidade>em estoque</disponibilidade>
  <link_imagem>https://cdn.exemplo.com/iphone-17.webp</link_imagem>
</item>
```

Duas mudanças de conteúdo em relação ao legado:

- **`<link>`** aponta pra loja Orbita (`/produtos/{slug}`). O
  `produto.php?id=RECNO` do site antigo não existe mais.
- **`<preco>`** é o preço efetivo da loja: a promoção do painel quando válida
  (`promo_price` preenchida e abaixo do Preço Web), senão o Preço Web. Já está
  em USD — o preço do ERP é negociado em dólar.

`<codigo>` é o **CODWEB** do ERP (`products.web_code`), o código do produto
nas integrações — o mesmo `code` mandado pela API de importação, os dois saem
de `Product::feedCode()`. Sem CODWEB cai no CODNNN (`erp_code`), depois no
`recno` e por último no id local. Hoje CODWEB e CODNNN têm o mesmo valor em
todos os produtos, então a troca não mudou nenhum código publicado.

## Deploy

### 1. Painel (`eshop.lynce.net`)

Nada de migration. Opcional no `.env` (os padrões já servem produção):

```
COMPRASPY_STORE_URL=https://eshopcell.com
COMPRASPY_FEED_CACHE_TTL=3600
COMPRASPY_FEED_MIN_PRICE=0.10
```

`COMPRASPY_STORE_URL` é o domínio da **loja**, não o do painel: é a página que
o ComprasPY abre quando alguém clica no anúncio.

Pra ligar o envio na hora (seção "Envio na hora pela API deles"), **uma** das
duas credenciais — enquanto as duas estiverem vazias, só o XML funciona e nada
é enviado:

```
COMPRASPY_API_TOKEN=            # token da loja (header `token`)
COMPRASPY_API_BEARER=           # ou o JWT (Authorization: Bearer)
COMPRASPY_API_FALLBACK_IMAGE=   # opcional: imagem pra produto sem foto
```

Pra conferir que o envio está de pé depois de preencher, salve um Preço Web na
grade e olhe o log — sucesso é silencioso, recusa e lote parcial aparecem como
`ComprasPY recusou ...`:

```bash
php artisan config:clear
tail -f storage/logs/laravel.log | grep -i compraspy
```

O scheduler do Laravel precisa estar no cron do servidor (já está, por causa
de `erp:sync-catalog`):

```bash
* * * * * cd /caminho/do/eshop && php artisan schedule:run >> /dev/null 2>&1
```

Confira e aqueça o cache:

```bash
php artisan compraspy:feed
curl -I https://eshop.lynce.net/compraspy/feedseshop.xml   # 200, application/xml
```

### 2. Loja (`eshopcell.com`, repo `orbita`)

Build/deploy normal do Next. Se o painel não estiver na mesma origem que
`API_BASE_URL`, adicione ao `.env` da loja:

```
COMPRASPY_FEED_URL=https://eshop.lynce.net/compraspy/feedseshop.xml
```

Confira na URL que o ComprasPY usa:

```bash
curl -I https://eshopcell.com/compraspy/feedseshop.xml   # 200, application/xml
```

### 3. Desligar o legado

Só depois do passo 2 responder 200 com o XML novo:

- remova a linha do cron antigo (`gerar_feed_compraspy.php`);
- o diretório `compraspy/` do `public_html` legado pode ser arquivado. Se ele
  ainda estiver servindo `feedseshop.xml` no mesmo domínio, **o arquivo
  estático ganha do proxy** — o Next só responde o que o servidor web não
  resolveu antes como arquivo. Apague ou renomeie o `.xml` antigo, senão o
  ComprasPY continua lendo um feed congelado.
