# Feed XML do ComprasPY

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
| Rota pública + ação do botão | `app/Http/Controllers/ComprasPyFeedController.php`, `routes/web.php` |
| Command do scheduler | `app/Console/Commands/GenerateComprasPyFeedCommand.php`, `routes/console.php` |
| Configuração | `config/feeds.php` |
| Testes | `tests/Feature/ComprasPyFeedTest.php` |

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

`<codigo>` continua sendo o código do ERP (`erp_code`, o `CODNNN` do legado),
pra não quebrar quem casa anúncio por esse campo; sem ele, cai no `recno` e
por último no id local.

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
