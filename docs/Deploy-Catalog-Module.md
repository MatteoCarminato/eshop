# Runbook: deploy do módulo de Catálogo (Produtos/Marcas/Categorias) em produção

Este documento é um roteiro para quem (humano ou outra instância do Claude)
for subir esta branch/commit em produção e popular o catálogo pela primeira
vez. Ele assume que o deploy do código (git pull, composer install, build de
assets) já foi feito por outro processo — aqui começa a partir do código já
publicado no servidor.

Contexto: ver `docs/Product-Module.md`, `docs/Brand-Module.md`,
`docs/Erp-Sync.md`, `docs/Product-Classification.md` e `docs/Orbita-Api.md`
para entender cada peça. Este runbook só lista a sequência de comandos.

## 0. Pré-requisitos (uma vez só)

Confirme que o `.env` de produção tem essas chaves preenchidas (ver
`.env.example` para o template — os nomes são os mesmos):

```
ERP_DB_HOST=
ERP_DB_PORT=3306
ERP_DB_DATABASE=
ERP_DB_USERNAME=
ERP_DB_PASSWORD=
ERP_DB_CHARSET=utf8mb4

ORBITA_API_TOKEN=
```

- `ERP_DB_*`: credenciais **somente leitura** do MySQL do ERP Consoft
  (`cec_consulta`, servidor `38.52.135.193:3306` conforme `docs/Erp-Sync.md`
  — confirme se produção acessa esse IP/porta, pode precisar liberar
  firewall). Sem isso, `erp:sync-catalog` falha ao conectar.
- `ORBITA_API_TOKEN`: precisa ser **o mesmo valor** que `orbita/web` usa em
  produção como `API_TOKEN` (variável server-side do Next.js, não
  `NEXT_PUBLIC_*`). Se não baterem, a API responde 401/403 pro front e o
  site do Orbita fica sem produtos.

Se qualquer uma dessas estiver vazia, pare aqui e preencha antes de
continuar — os passos abaixo vão falhar ou rodar incompletos.

## 1. Migrations

```bash
php artisan migrate --force
```

Cria/ajusta `brands`, `products`, `categories` e os campos de
categoria/variante adicionados depois (armazenamento, cor, modelo). Não é
destrutivo para o restante do banco — só adiciona tabelas/colunas novas.

## 2. Sincronizar com o ERP (popula marcas e produtos)

Primeiro um dry-run pra conferir que a conexão com o ERP está ok e ver o
volume de dados, sem gravar nada:

```bash
php artisan erp:sync-catalog --dry-run
```

Confira a tabela impressa (Criados/Atualizados/Ignorados para Marcas e
Produtos). Se os números fizerem sentido (hoje são ~28 marcas e ~260-270
produtos no ERP), rode de verdade:

```bash
php artisan erp:sync-catalog
```

Isso cria localmente todas as marcas (`brands`) e produtos (`products`) a
partir de `MARCAS_MAR`/`PRODUTO_PRO` do ERP, com preço, estoque, slug, e
**já classifica cada produto novo automaticamente** (categoria,
armazenamento, cor, modelo — ver próximo passo).

É idempotente: pode rodar de novo a qualquer momento sem duplicar nada
(upsert por `recno`) e sem sobrescrever campos "do site" (slug, descrição,
imagem, destaque, categoria/variante editados manualmente no admin depois
da criação — ver `docs/Erp-Sync.md`).

O agendamento automático (`routes/console.php`, a cada 30 minutos) já
cuida de manter isso sincronizado depois — **confirme que o cron do
Laravel está rodando em produção** (`* * * * * php artisan schedule:run`
no crontab do servidor), senão o sync automático nunca dispara.

## 3. Backfill de classificação (categorias, armazenamento, cor, modelo)

O passo 2 já classifica produtos **novos** automaticamente. Este comando
é para garantir que **todo** produto no banco tenha
categoria/armazenamento/cor/modelo preenchidos — útil logo após o primeiro
sync (idempotente, seguro rodar sempre):

```bash
php artisan products:classify
```

Por padrão só processa produtos com algum campo vazio (`category_id`,
`storage`, `color_name` ou `model` nulos). Pra reclassificar **tudo** do
zero (ex.: depois de melhorar as regras do `ProductClassifierService`):

```bash
php artisan products:classify --force
```

Use `--dry-run` em qualquer um dos dois pra ver a tabela de
categorias/contagens sem gravar nada antes de rodar de verdade.

## 4. Verificação pós-deploy

```bash
# Contagem rápida
php artisan tinker --execute="echo 'brands=' . App\Models\Brand::count() . ' products=' . App\Models\Product::count() . PHP_EOL;"

# Confere que a API pública responde (troca SEU_TOKEN pelo ORBITA_API_TOKEN real)
curl -s -H "X-Orbita-Token: SEU_TOKEN" "https://SEU_DOMINIO/api/orbita/products?limit=3" | head -c 500
```

Espera-se um JSON com `data` contendo até 3 produtos, cada um já com
`category`, `storage`/`colorName` quando aplicável, e `price`/`priceUsd`
maiores que zero (produtos com preço ou estoque zerado **não aparecem**
nessa API de propósito — ver regra de negócio em `docs/Orbita-Api.md`).

Se a lista vier vazia mas `brands`/`products` tiverem contagem > 0, o mais
provável é todo produto estar com `active=false`, `price=0` ou `stock=0`
— confira a origem desses três campos no ERP antes de desconfiar da API.

No site do Orbita (`orbita/web`, branch `EshopCell`), recarregue a home e
confirme que os cards de produto deixam de usar os dados mock e passam a
mostrar os produtos reais vindos do eshop.

## 5. Problemas conhecidos / fora de escopo deste deploy

- `GET /api/orbita/settings` sempre devolve `data: null` (sem admin de
  configurações da loja ainda) — o Orbita cai no fallback local
  (`BRANDING_MOCK`, já ajustado pra "eShop" no front). Não é bug deste
  deploy, é limitação conhecida documentada em `docs/Orbita-Api.md`.
- `banners`, `popups`, `sellers`, `promotions` sempre vazios pelo mesmo
  motivo (sem admin ainda) — o front já trata isso como "seção não
  aparece", não quebra nada.
- **Bug de permissão conhecido, não corrigido ainda**: em Produtos, Marcas,
  Clientes e Grupos, a permissão "visualizar" (`.view`) atualmente também
  libera criar/editar/excluir, porque as rotas usam
  `Route::resource(...)->middleware('module:X.view')` sem separar as
  ações de escrita atrás de `X.manage` (diferente de Carteira/Caixa/
  WhatsApp, que já fazem essa separação corretamente). Ver conversa
  anterior — correção pendente, intencionalmente não aplicada ainda.
