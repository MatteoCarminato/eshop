# 🚀 Próximos Passos — Laravel SaaS + WhatsApp Bot

> Documentação técnica para o desenvolvimento do painel Laravel que vai controlar o bot Node.js de disparo de WhatsApp. O painel será um **SaaS multi-tenant** com login/senha, gestão de campanhas e integração com o bot via API.

---

## 📐 Arquitetura Geral

```
┌───────────────────────────────────────────────────────────────┐
│                    USUÁRIO (Browser)                          │
│              Laravel SaaS — Painel Web                        │
│   Login → Dashboard → Campanhas → Contatos → Relatórios      │
└────────────────────────┬──────────────────────────────────────┘
                         │ HTTP (API REST)
                         ▼
┌───────────────────────────────────────────────────────────────┐
│              NODE.JS — WhatsApp Bot (por instância)           │
│   whatsapp-web.js → Puppeteer → WhatsApp Web                 │
│   Cada tenant tem SUA instância do bot rodando                │
└────────────────────────┬──────────────────────────────────────┘
                         │
                         ▼
┌───────────────────────────────────────────────────────────────┐
│                    WhatsApp (Meta)                            │
│          Mensagens enviadas para os contatos                  │
└───────────────────────────────────────────────────────────────┘
```

### Comunicação Laravel ↔ Bot Node.js

O Laravel **não** envia mensagens diretamente. Ele se comunica com o bot Node.js via **API REST** (já existente no bot). O fluxo é:

1. **Laravel** cria uma campanha e chama `POST /api/send` do bot
2. **Bot Node.js** executa o disparo e reporta status via `GET /api/status`
3. **Laravel** faz polling do status ou recebe **webhook** do bot

---

## 🗄️ Modelagem do Banco de Dados (Laravel)

### Diagrama ER Simplificado

```
users (tenants)
  ├── whatsapp_instances (1:N) — Instâncias do bot por usuário
  ├── campaigns (1:N)          — Campanhas de envio
  │     ├── campaign_contacts (1:N)  — Contatos selecionados para a campanha
  │     └── campaign_messages (1:N)  — Log de cada mensagem enviada
  ├── contacts (1:N)           — Contatos importados/sincronizados
  ├── labels (1:N)             — Etiquetas sincronizadas do WhatsApp
  │     └── contact_label (N:N)      — Pivot: contato ↔ etiqueta
  └── templates (1:N)          — Templates de mensagem personalizados
```

### Migrations

#### `users` (default do Laravel + campos extras)

| Coluna             | Tipo        | Descrição                                   |
| ------------------ | ----------- | ------------------------------------------- |
| id                 | bigint PK   | —                                           |
| name               | string      | Nome do usuário/empresa                     |
| email              | string UK   | Login                                       |
| password           | string      | Senha (bcrypt)                              |
| plan               | enum        | `free`, `basic`, `pro`, `enterprise`        |
| max_messages_day   | int         | Limite diário de mensagens por plano        |
| max_instances      | int         | Quantas instâncias WhatsApp pode ter        |
| is_active          | boolean     | Conta ativa/suspensa                        |
| trial_ends_at      | timestamp?  | Fim do período trial                        |
| timestamps         |             | created_at, updated_at                      |

#### `whatsapp_instances`

| Coluna             | Tipo        | Descrição                                   |
| ------------------ | ----------- | ------------------------------------------- |
| id                 | bigint PK   | —                                           |
| user_id            | FK → users  | Dono da instância                           |
| name               | string      | Nome amigável (ex: "WhatsApp Comercial")    |
| phone_number       | string?     | Número conectado (preenchido após QR scan)  |
| status             | enum        | `disconnected`, `qr_pending`, `connected`   |
| bot_url            | string      | URL do bot Node.js (ex: `http://bot-1:3000`)|
| bot_api_key        | string      | API Key do bot Node.js                      |
| session_data       | text?       | Dados de sessão (opcional, backup)          |
| last_seen_at       | timestamp?  | Último heartbeat do bot                     |
| timestamps         |             | created_at, updated_at                      |

#### `contacts`

| Coluna             | Tipo        | Descrição                                   |
| ------------------ | ----------- | ------------------------------------------- |
| id                 | bigint PK   | —                                           |
| user_id            | FK → users  | Dono do contato                             |
| instance_id        | FK → whatsapp_instances | De qual instância veio      |
| name               | string      | Nome (pushname / salvo / manual)            |
| phone              | string      | Número (5545991325057)                      |
| chat_id            | string?     | ChatId do WhatsApp (5545991325057@c.us)     |
| is_saved           | boolean     | Se está salvo na agenda do WhatsApp         |
| pushname           | string?     | Nome do perfil do WhatsApp                  |
| source             | enum        | `whatsapp`, `csv`, `manual`, `api`          |
| is_valid           | boolean     | Se o número é válido no WhatsApp            |
| opted_out          | boolean     | Se pediu para não receber mais (opt-out)    |
| last_message_at    | timestamp?  | Última mensagem enviada                     |
| timestamps         |             | created_at, updated_at                      |
| **UK**             |             | `(user_id, phone)` — sem duplicatas         |

#### `labels`

| Coluna             | Tipo        | Descrição                                   |
| ------------------ | ----------- | ------------------------------------------- |
| id                 | bigint PK   | —                                           |
| user_id            | FK → users  | Dono                                        |
| instance_id        | FK → whatsapp_instances | De qual instância veio      |
| whatsapp_label_id  | string      | ID da etiqueta no WhatsApp                  |
| name               | string      | Nome (Casa, Terreno, etc.)                  |
| color              | string?     | Cor hex da etiqueta                         |
| contacts_count     | int         | Cache: total de contatos nesta etiqueta     |
| synced_at          | timestamp?  | Última sincronização                        |
| timestamps         |             | created_at, updated_at                      |

#### `contact_label` (pivot)

| Coluna             | Tipo              | Descrição                           |
| ------------------ | ----------------- | ----------------------------------- |
| contact_id         | FK → contacts     | —                                   |
| label_id           | FK → labels       | —                                   |
| **PK**             |                   | `(contact_id, label_id)`            |

#### `templates`

| Coluna             | Tipo        | Descrição                                   |
| ------------------ | ----------- | ------------------------------------------- |
| id                 | bigint PK   | —                                           |
| user_id            | FK → users  | Dono                                        |
| name               | string      | Nome do template (ex: "Oferta Terreno")     |
| slug               | string UK   | Slug gerado (oferta-terreno)                |
| body               | text        | Corpo da mensagem com placeholders           |
| placeholders       | json        | Ex: `["name", "location", "price"]`         |
| label_id           | FK? → labels| Etiqueta vinculada (opcional)               |
| is_default         | boolean     | Se é o template padrão                      |
| timestamps         |             | created_at, updated_at                      |

> **Placeholders no body**: Usar `{{name}}`, `{{location}}`, `{{price}}`. O Laravel substitui antes de enviar ao bot.

#### `campaigns`

| Coluna             | Tipo        | Descrição                                   |
| ------------------ | ----------- | ------------------------------------------- |
| id                 | bigint PK   | —                                           |
| user_id            | FK → users  | Dono da campanha                            |
| instance_id        | FK → whatsapp_instances | Instância que vai disparar  |
| name               | string      | Nome da campanha                            |
| template_id        | FK → templates | Template usado                           |
| image_path         | string?     | Caminho da imagem (storage do Laravel)      |
| target_type        | enum        | `all`, `label`, `contacts`, `csv`           |
| target_label_id    | FK? → labels| Etiqueta alvo (quando target_type = label)  |
| status             | enum        | `draft`, `scheduled`, `running`, `paused`, `completed`, `failed` |
| scheduled_at       | timestamp?  | Agendamento (enviar no futuro)              |
| started_at         | timestamp?  | Quando começou o envio                      |
| completed_at       | timestamp?  | Quando terminou                             |
| total_contacts     | int         | Total de contatos na campanha               |
| sent_count         | int         | Mensagens enviadas com sucesso              |
| failed_count       | int         | Mensagens que falharam                      |
| timestamps         |             | created_at, updated_at                      |

#### `campaign_contacts` (contatos selecionados para a campanha)

| Coluna             | Tipo            | Descrição                               |
| ------------------ | --------------- | --------------------------------------- |
| id                 | bigint PK       | —                                       |
| campaign_id        | FK → campaigns  | —                                       |
| contact_id         | FK → contacts   | —                                       |
| status             | enum            | `pending`, `sent`, `failed`, `skipped`  |
| sent_at            | timestamp?      | Quando foi enviada                      |
| error_message      | string?         | Motivo da falha                         |
| timestamps         |                 | created_at, updated_at                  |

---

## 🖥️ Telas do Painel (Laravel + Blade/Livewire ou Inertia)

### 1. Auth

| Tela               | Rota             | Descrição                                |
| ------------------- | ---------------- | ---------------------------------------- |
| Login              | `/login`          | Email + senha                            |
| Registro           | `/register`       | Nome, email, senha, plano                |
| Esqueci Senha      | `/forgot-password`| Reset por email                          |
| Perfil             | `/profile`        | Editar dados, trocar senha               |

### 2. Dashboard

| Tela               | Rota              | Descrição                               |
| ------------------- | ----------------- | --------------------------------------- |
| Dashboard          | `/dashboard`       | Resumo: instâncias conectadas, campanhas ativas, msgs enviadas hoje, gráficos |

**Cards do Dashboard**:
- 📱 Instâncias: `X conectadas / Y total`
- 📨 Mensagens hoje: `X / Y limite`
- 🏷️ Etiquetas sincronizadas: `X`
- 📊 Campanhas ativas: `X`
- 📈 Gráfico: Mensagens enviadas nos últimos 7 dias

### 3. Instâncias WhatsApp

| Tela                      | Rota                              | Descrição                   |
| ------------------------- | --------------------------------- | --------------------------- |
| Listar instâncias         | `/instances`                      | Cards com status (conectado/desconectado) |
| Criar instância           | `/instances/create`               | Nome + URL do bot           |
| QR Code                   | `/instances/{id}/qr`              | Exibir QR para escanear     |
| Detalhes                  | `/instances/{id}`                 | Status, número, último heartbeat |

**Fluxo de conexão**:
1. Usuário cria instância → Laravel provisiona container do bot (ou aponta para URL)
2. Laravel chama `GET /health` do bot para verificar se está online
3. Bot exibe QR → Laravel renderiza QR na tela (via polling/websocket)
4. Após scan, status muda para `connected`

### 4. Contatos

| Tela                      | Rota                              | Descrição                   |
| ------------------------- | --------------------------------- | --------------------------- |
| Listar contatos           | `/contacts`                       | Tabela com busca, filtros por etiqueta e source |
| Importar CSV              | `/contacts/import`                | Upload CSV                  |
| Sincronizar do WhatsApp   | `/contacts/sync`                  | Puxa contatos via `GET /api/contacts` do bot |
| Detalhes do contato       | `/contacts/{id}`                  | Info + histórico de mensagens |

**Funcionalidades**:
- Filtrar por etiqueta (dropdown multi-select)
- Filtrar por origem (WhatsApp, CSV, manual)
- Busca por nome ou telefone
- Marcar como opt-out
- Bulk select para adicionar em campanha

### 5. Etiquetas

| Tela                      | Rota                              | Descrição                   |
| ------------------------- | --------------------------------- | --------------------------- |
| Listar etiquetas          | `/labels`                         | Cards com nome, cor, total de contatos |
| Sincronizar               | `/labels/sync`                    | Puxa via `GET /api/labels` do bot |
| Detalhes da etiqueta      | `/labels/{id}`                    | Contatos dentro da etiqueta |

### 6. Templates

| Tela                      | Rota                              | Descrição                   |
| ------------------------- | --------------------------------- | --------------------------- |
| Listar templates          | `/templates`                      | Cards com preview da mensagem |
| Criar/Editar template     | `/templates/create`               | Editor com placeholders `{{name}}`, preview ao vivo |
| Vincular a etiqueta       | —                                 | Dropdown: qual etiqueta usa este template |

**Preview ao vivo**: Conforme o usuário digita, mostra como a mensagem ficaria com dados fake (ex: `{{name}}` → "João Silva").

### 7. Campanhas (⭐ Tela principal)

| Tela                      | Rota                              | Descrição                   |
| ------------------------- | --------------------------------- | --------------------------- |
| Listar campanhas          | `/campaigns`                      | Tabela: nome, status, progresso, data |
| Criar campanha            | `/campaigns/create`               | Wizard de criação (ver abaixo) |
| Detalhes / Monitoramento  | `/campaigns/{id}`                 | Progress bar em tempo real  |
| Relatório                 | `/campaigns/{id}/report`          | Sucesso/falha por contato   |

#### Wizard de Criação de Campanha (4 etapas)

```
Etapa 1: Configuração Básica
├── Nome da campanha
├── Selecionar instância WhatsApp
└── Imagem (upload opcional)

Etapa 2: Público-alvo (★ escolha crucial)
├── 🔘 Todos os contatos
├── 🔘 Por etiqueta → Dropdown: [Casa] [Terreno] [...]
├── 🔘 Selecionar contatos → Tabela com checkboxes
└── 🔘 Upload CSV → Arquivo com nome,telefone

Etapa 3: Mensagem
├── Selecionar template existente OU
├── Escrever mensagem personalizada
├── Preview da mensagem com nome real
└── Placeholders: {{name}}, {{phone}}, {{label}}

Etapa 4: Agendamento
├── 🔘 Enviar agora
├── 🔘 Agendar para → DateTimePicker
├── Delay entre mensagens (min/max em segundos)
└── Limite de mensagens por sessão
```

### 8. Relatórios

| Tela                      | Rota                              | Descrição                   |
| ------------------------- | --------------------------------- | --------------------------- |
| Relatórios gerais         | `/reports`                        | Gráficos: msgs/dia, taxa de sucesso |
| Exportar                  | `/reports/export`                 | Download CSV dos relatórios |

---

## 🔌 API do Laravel (para o frontend e integrações)

### Endpoints Internos (Laravel → Bot Node.js)

O Laravel consome a API do bot já existente. Para cada instância, ele usa a `bot_url` + `bot_api_key` salvas no banco:

```php
// Exemplo: Service class do Laravel
class WhatsAppBotService
{
    public function __construct(
        private WhatsappInstance $instance
    ) {}

    public function send(Campaign $campaign): array
    {
        return Http::withHeaders([
            'x-api-key' => $this->instance->bot_api_key,
        ])->post("{$this->instance->bot_url}/api/send", [
            'labelName'    => $campaign->targetLabel?->name,
            'imagePath'    => $campaign->image_path,
            'extraParams'  => $campaign->template->placeholders_values,
        ])->json();
    }

    public function getStatus(): array
    {
        return Http::withHeaders([
            'x-api-key' => $this->instance->bot_api_key,
        ])->get("{$this->instance->bot_url}/api/status")->json();
    }

    public function getLabels(): array
    {
        return Http::withHeaders([
            'x-api-key' => $this->instance->bot_api_key,
        ])->get("{$this->instance->bot_url}/api/labels")->json();
    }

    public function getContacts(array $filters = []): array
    {
        return Http::withHeaders([
            'x-api-key' => $this->instance->bot_api_key,
        ])->get("{$this->instance->bot_url}/api/contacts", $filters)->json();
    }

    public function stop(): array
    {
        return Http::withHeaders([
            'x-api-key' => $this->instance->bot_api_key,
        ])->post("{$this->instance->bot_url}/api/stop")->json();
    }
}
```

### Webhook do Bot → Laravel (a implementar no Node.js)

Para o Laravel receber atualizações em tempo real do bot, **adicionar webhook** no bot Node.js:

```
Bot envia POST para Laravel quando:
- Mensagem enviada com sucesso
- Mensagem falhou
- Envio concluído
- Bot desconectou
- QR code gerado
```

```
POST {LARAVEL_URL}/api/webhooks/whatsapp
Headers: x-webhook-secret: {secret}
Body: {
  "event": "message_sent" | "message_failed" | "campaign_completed" | "disconnected" | "qr_generated",
  "instance_id": "...",
  "data": { ... }
}
```

---

## 🔧 Mudanças Necessárias no Bot Node.js

### 1. Webhook de Eventos (novo)

Adicionar ao bot a capacidade de enviar webhooks para o Laravel:

```
Nova env var:
  WEBHOOK_URL=https://meuapp.com/api/webhooks/whatsapp
  WEBHOOK_SECRET=secret-key-123

Novo módulo:
  src/webhooks/notifier.js
    - notifyEvent(event, data) → POST para WEBHOOK_URL

Eventos a disparar:
  - message_sent    → { phone, chatId, campaignId }
  - message_failed  → { phone, chatId, error, campaignId }
  - send_completed  → { campaignId, sent, failed, total }
  - status_changed  → { status: 'connected' | 'disconnected' }
  - qr_generated    → { qrCode: 'base64...' }
```

### 2. Campaign ID (novo)

O `POST /api/send` do bot precisa aceitar um `campaignId` do Laravel para rastrear as mensagens:

```json
POST /api/send
{
  "campaignId": "uuid-da-campanha",
  "labelName": "Terreno",
  "imagePath": "...",
  "template": "custom",
  "customMessage": "Olá {{name}}! ..."
}
```

### 3. Template Custom (novo)

Além dos templates fixos, o bot precisa aceitar uma `customMessage` com placeholders `{{name}}` que vem do Laravel:

```
POST /api/send
{
  "customMessage": "Olá {{name}}, temos terrenos em {{location}}!",
  "extraParams": { "location": "Cascavel - PR" }
}
```

### 4. QR Code via API (novo)

Endpoint para o Laravel pegar o QR code sem precisar olhar o terminal:

```
GET /api/qrcode → { "qr": "base64png..." } ou { "status": "connected" }
```

### 5. Endpoint de Contatos por Etiqueta (já existe ✅)

```
GET /api/labels        → Lista etiquetas
GET /api/contacts      → Lista contatos (com filtros)
POST /api/send         → Envio com labelName
POST /api/stop         → Para envio
GET /api/status        → Status atual
GET /health            → Health check
```

---

## 🐳 Infraestrutura (Deploy)

### Opção 1: Docker Compose (recomendada para início)

```
docker-compose.yml
├── laravel-app          # PHP 8.3 + Laravel 11
│   ├── port: 8000
│   └── volumes: ./storage
├── mysql                # MySQL 8.0
│   └── port: 3306
├── redis                # Cache + Filas
│   └── port: 6379
├── whatsapp-bot-1       # Node.js bot (instância 1)
│   ├── port: 3001
│   └── volumes: ./.wwebjs_auth
├── whatsapp-bot-2       # Node.js bot (instância 2)
│   ├── port: 3002
│   └── volumes: ./.wwebjs_auth
└── nginx                # Reverse proxy
    └── port: 80/443
```

### Opção 2: Escalável (futuro)

```
┌────────────┐     ┌────────────┐     ┌────────────────────┐
│   Nginx    │ ──► │  Laravel   │ ──► │  MySQL + Redis     │
│ (proxy)    │     │  (PHP-FPM) │     │  (RDS / ElastiCache│
└────────────┘     └─────┬──────┘     └────────────────────┘
                         │
                    Queue (Redis)
                         │
              ┌──────────┼──────────┐
              ▼          ▼          ▼
         ┌────────┐ ┌────────┐ ┌────────┐
         │ Bot 1  │ │ Bot 2  │ │ Bot N  │
         │ Node.js│ │ Node.js│ │ Node.js│
         └────────┘ └────────┘ └────────┘
         (1 container por instância WhatsApp)
```

Cada usuário do SaaS pode ter 1+ instâncias de bot. O Laravel gerencia qual container/URL pertence a qual instância.

---

## 📋 Planos e Limites (SaaS)

| Feature              | Free     | Basic        | Pro          | Enterprise   |
| -------------------- | -------- | ------------ | ------------ | ------------ |
| Instâncias WhatsApp  | 1        | 2            | 5            | Ilimitado    |
| Mensagens/dia        | 50       | 500          | 2.000        | 10.000       |
| Campanhas ativas     | 1        | 5            | 20           | Ilimitado    |
| Templates            | 3        | 10           | 50           | Ilimitado    |
| Importar CSV         | ❌       | ✅           | ✅           | ✅           |
| Agendamento          | ❌       | ✅           | ✅           | ✅           |
| Relatórios avançados | ❌       | ❌           | ✅           | ✅           |
| API externa          | ❌       | ❌           | ✅           | ✅           |
| Suporte              | —        | Email        | Chat         | Dedicado     |
| Preço/mês            | R$ 0     | R$ 97        | R$ 197       | R$ 497       |

---

## 🛣️ Roadmap de Desenvolvimento

### Fase 1 — MVP (2-3 semanas)

- [ ] Setup Laravel 11 com Breeze (auth)
- [ ] Migrations: users, whatsapp_instances, contacts, labels, templates, campaigns
- [ ] Models + Relationships + Factories + Seeders
- [ ] CRUD de Instâncias WhatsApp (com status)
- [ ] Sincronização de contatos (Laravel → Bot → DB)
- [ ] Sincronização de etiquetas (Laravel → Bot → DB)
- [ ] CRUD de Templates (com placeholders `{{name}}`)
- [ ] Criação de Campanha (wizard 4 etapas)
- [ ] Disparo de campanha (Laravel chama `POST /api/send` do bot)
- [ ] Tela de monitoramento da campanha (polling status)
- [ ] Dashboard básico

### Fase 2 — Webhook + Tempo Real (1-2 semanas)

- [ ] Webhook no bot Node.js (message_sent, message_failed, etc.)
- [ ] Endpoint no Laravel para receber webhooks
- [ ] Atualização em tempo real do progresso (Livewire/Pusher)
- [ ] QR code via API (bot → Laravel → frontend)
- [ ] Custom messages (template vindo do Laravel)
- [ ] Campaign ID rastreável no bot

### Fase 3 — SaaS (2-3 semanas)

- [ ] Multi-tenancy (scopes por user_id em tudo)
- [ ] Planos e limites (middleware de verificação)
- [ ] Integração com Stripe/Asaas para pagamento
- [ ] Página de preços e checkout
- [ ] Trial de 7 dias
- [ ] Painel admin (gerenciar todos os tenants)

### Fase 4 — Polimento (1-2 semanas)

- [ ] Agendamento de campanhas (Laravel Queue + scheduled_at)
- [ ] Relatórios com gráficos (Chart.js)
- [ ] Exportar relatórios em CSV/PDF
- [ ] Opt-out automático (se contato responde "SAIR")
- [ ] Notificações por email (campanha concluída, bot desconectou)
- [ ] Logs de atividade (audit trail)

### Fase 5 — Escala (contínuo)

- [ ] Docker Compose para deploy
- [ ] Provisionamento automático de containers do bot
- [ ] Rate limiting por plano
- [ ] Cache com Redis (contatos, etiquetas)
- [ ] API externa para integrações (API tokens por usuário)
- [ ] Documentação da API com Swagger/Scribe

---

## 🧰 Stack Laravel Recomendada

| Pacote                    | Propósito                                  |
| ------------------------- | ------------------------------------------ |
| **Laravel 11**            | Framework PHP                              |
| **Breeze + Blade**        | Auth + scaffolding (ou Jetstream/Filament) |
| **Livewire 3**            | Componentes reativos (tempo real)          |
| **Filament 3** (opção)    | Admin panel pré-pronto (acelera muito)     |
| **Laravel Queue (Redis)** | Filas para campanhas e webhooks            |
| **Laravel Scheduler**     | Cron para campanhas agendadas              |
| **Spatie Media Library**  | Upload de imagens                          |
| **Spatie Permission**     | Roles (admin/user) se necessário           |
| **Laravel Cashier**       | Pagamentos via Stripe                      |
| **Pest PHP**              | Testes                                     |

---

## 📡 API REST do Bot Node.js (referência completa)

Estes são os endpoints **já implementados** no bot que o Laravel vai consumir:

### `GET /health`
Sem auth. Verifica se o bot está online.
```json
→ { "status": "ok", "timestamp": "2026-03-17T..." }
```

### `GET /api/status` 🔑
Retorna status da instância.
```json
→ { "state": "connected", "sending": false, "messagesSent": 42, "lastReport": {...} }
```

### `GET /api/labels` 🔑
Lista etiquetas do WhatsApp Business.
```json
→ { "total": 2, "labels": [{ "id": "1", "name": "Terreno", "color": "#ff0000" }, ...] }
```

### `GET /api/contacts?savedOnly=true&withName=true` 🔑
Lista contatos com filtros.
```json
→ { "total": 150, "contacts": [{ "name": "João", "phone": "5545...", "chatId": "...@c.us" }] }
```

### `POST /api/send` 🔑
Inicia envio de campanha.
```json
← {
    "labelName": "Terreno",
    "imagePath": "media/oferta.jpg",
    "extraParams": { "location": "Cascavel" },
    "savedOnly": false,
    "excludePhones": ["5511999990000"]
  }
→ { "message": "Envio iniciado", "labelName": "Terreno" }
```

### `POST /api/stop` 🔑
Para o envio em andamento.
```json
→ { "message": "Parada solicitada" }
```

> 🔑 = Requer header `x-api-key`

---

## 📝 Exemplo: Fluxo Completo de uma Campanha

```
1. Usuário faz login no painel Laravel
2. Vai em "Instâncias" → verifica que WhatsApp está conectado (verde ✅)
3. Vai em "Contatos" → clica "Sincronizar" → Laravel chama GET /api/contacts → salva no DB
4. Vai em "Etiquetas" → clica "Sincronizar" → Laravel chama GET /api/labels → salva no DB
5. Vai em "Campanhas" → "Nova Campanha"
   a. Nome: "Oferta Terrenos Março"
   b. Instância: "WhatsApp Comercial"
   c. Público: "Por etiqueta" → seleciona "Terreno"
   d. Template: "Oferta Terreno" (com {{name}})
   e. Imagem: upload oferta.jpg
   f. Enviar: "Agora"
6. Laravel cria a campanha no DB (status: running)
7. Laravel chama POST /api/send { labelName: "Terreno" } no bot
8. Bot inicia o disparo → cada mensagem enviada → webhook pro Laravel
9. Laravel atualiza campaign_contacts em tempo real
10. Usuário vê a progress bar avançando na tela
11. Bot termina → webhook "send_completed" → campanha status: completed
12. Usuário vê relatório: 45 enviados, 2 falhas, 0 opt-outs
```
