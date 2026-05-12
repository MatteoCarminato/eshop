# CLAUDE.md — WhatsApp Bot Terrenos

## 📋 Visão Geral do Projeto

Bot de disparo em massa de mensagens personalizadas via WhatsApp com imagem, usando **whatsapp-web.js**. Cada mensagem é enviada com o **nome do contato** para parecer natural e evitar detecção como bot. O projeto roda em **Node.js** com padrão **CommonJS**.

---

## 🏗️ Arquitetura do Projeto

```
whatsapp-bot-terrenos/
├── CLAUDE.md                    # Este arquivo (instruções para o Claude)
├── package.json
├── .env                         # Variáveis de ambiente (NÃO commitar)
├── .env.example                 # Template das variáveis de ambiente
├── .gitignore
├── .eslintrc.js                 # ESLint config (Airbnb base)
├── .prettierrc                  # Prettier config
├── .editorconfig                # Editor config
├── src/
│   ├── index.js                 # Entry point — inicializa o WhatsApp client
│   ├── config/
│   │   └── env.js               # Carrega e valida variáveis de ambiente
│   ├── client/
│   │   └── whatsapp.js          # Inicialização e eventos do WhatsApp client
│   ├── contacts/
│   │   ├── whatsappLoader.js  # Carrega contatos direto do WhatsApp conectado
│   │   ├── loader.js            # Carrega contatos do CSV/JSON (fallback)
│   │   └── validator.js         # Valida contatos (WhatsApp ou CSV)
│   ├── messages/
│   │   ├── sender.js            # Lógica de envio de mensagens em massa
│   │   ├── templates.js         # Templates de mensagens com placeholders
│   │   └── scheduler.js         # Controle de delay entre mensagens
│   ├── media/
│   │   └── imageHandler.js      # Carrega e prepara imagens para envio
│   ├── utils/
│   │   ├── logger.js            # Logger com Winston ou console formatado
│   │   ├── delay.js             # Função de delay randômico entre envios
│   │   └── formatPhone.js       # Formata números de telefone BR
│   └── api/
│       └── server.js            # Express API para controle (start/stop/status)
├── data/
│   ├── contacts.csv             # Lista de contatos (nome, telefone)
│   └── contacts.example.csv     # Exemplo de CSV de contatos
├── media/
│   └── .gitkeep                 # Pasta para imagens de envio
├── logs/
│   └── .gitkeep                 # Pasta para logs
└── tests/
    ├── contacts/
    │   ├── loader.test.js
    │   └── validator.test.js
    ├── messages/
    │   ├── sender.test.js
    │   └── templates.test.js
    └── utils/
        ├── delay.test.js
        └── formatPhone.test.js
```

---

## 🔧 Stack Técnica

| Tecnologia          | Versão   | Propósito                              |
| ------------------- | -------- | -------------------------------------- |
| Node.js             | >= 18    | Runtime                                |
| whatsapp-web.js     | ^1.34.6  | Client WhatsApp Web                    |
| express             | ^5.2.1   | API REST para controle do bot          |
| dotenv              | ^17.3.1  | Variáveis de ambiente                  |
| qrcode-terminal     | ^0.12.0  | QR code no terminal para login         |
| csv-parse           | latest   | Parse de CSV de contatos               |
| winston             | latest   | Logging estruturado                    |
| eslint              | latest   | Linting com padrão Airbnb              |
| prettier            | latest   | Formatação de código                   |
| jest                | latest   | Testes unitários                       |

---

## 📐 Padrões e Convenções de Código

### Estilo — ESLint Airbnb Base + Prettier

- **Config**: `eslint-config-airbnb-base` (sem React)
- **Prettier** integrado via `eslint-config-prettier` + `eslint-plugin-prettier`
- Aspas simples (`'string'`)
- Ponto e vírgula obrigatório
- Trailing comma em multiline
- Indentação: 2 espaços
- Max line length: 100 caracteres
- CommonJS (`require`/`module.exports`) — NÃO usar ES Modules

### Nomenclatura

| Tipo               | Convenção         | Exemplo               |
| ------------------ | ----------------- | --------------------- |
| Arquivos           | camelCase.js      | `imageHandler.js`     |
| Pastas             | kebab-case ou singular | `contacts/`, `media/` |
| Variáveis          | camelCase         | `contactList`         |
| Constantes globais | UPPER_SNAKE_CASE  | `MAX_DELAY_MS`        |
| Funções            | camelCase         | `sendMessage()`       |
| Classes            | PascalCase        | `MessageSender`       |

### Regras de Código

1. **Sempre** usar `const` por padrão; `let` somente quando reatribuição for necessária; **nunca** `var`
2. **Sempre** usar arrow functions para callbacks: `array.map((item) => item.name)`
3. **Sempre** usar destructuring quando possível: `const { name, phone } = contact;`
4. **Sempre** usar template literals: `` `Olá, ${name}!` ``
5. **Sempre** tratar erros com try/catch em funções async
6. **Sempre** logar erros com o logger, nunca `console.log` direto em produção
7. **Sempre** adicionar JSDoc em funções públicas
8. **Nunca** hardcodar valores — usar `.env` ou constantes
9. **Nunca** commitar `.env`, `node_modules/`, `.wwebjs_auth/`, `.wwebjs_cache/`
10. **Nunca** usar `==` — sempre `===`

---

## 🤖 Skills do Claude

### Skill: Criar Novo Template de Mensagem

Quando o usuário pedir para criar um novo template de mensagem:

1. Abrir `src/messages/templates.js`
2. Adicionar nova função que recebe `{ name, ...params }` e retorna string
3. Usar template literals com `${name}` obrigatório no corpo
4. Exportar a função no `module.exports`
5. Criar teste correspondente em `tests/messages/templates.test.js`
6. Rodar `npm run lint` para garantir conformidade

Exemplo de template:
```js
/**
 * Template de mensagem para oferta de terreno.
 * @param {Object} params
 * @param {string} params.name - Nome do contato
 * @param {string} params.location - Localização do terreno
 * @param {string} params.price - Preço formatado
 * @returns {string} Mensagem formatada
 */
const ofertaTerreno = ({ name, location, price }) =>
  `Olá ${name}! 👋\n\nTemos uma oportunidade incrível para você!\n\n📍 *${location}*\n💰 A partir de *${price}*\n\nTem interesse? Responda esta mensagem! 😊`;
```

### Skill: Gerenciar Contatos do WhatsApp

Os contatos são carregados **diretamente do WhatsApp conectado** (não mais do CSV).
O módulo `src/contacts/whatsappLoader.js` oferece 3 funções:

1. `loadContactsFromWhatsApp(client)` — Todos os chats individuais (não grupos)
2. `loadSavedContacts(client)` — Apenas contatos salvos na agenda
3. `loadFilteredContacts(client, filters)` — Com filtros:
   - `savedOnly: true` — Só contatos salvos
   - `withName: true` — Só quem tem nome (pushname ou salvo), ignora números sem nome
   - `excludePhones: ['5511...']` — Excluir números específicos

Cada contato retornado tem: `{ name, phone, chatId, isMyContact }`
- `name` = nome salvo na agenda OU pushname do perfil OU número como fallback
- `chatId` = ID direto para envio (ex: `5511999998888@c.us`)
- O `chatId` é usado diretamente pelo sender, sem precisar formatar

### Skill: Gerenciar Etiquetas (WhatsApp Business)

O bot suporta envio segmentado por **etiquetas do WhatsApp Business**.
Módulo: `src/contacts/whatsappLoader.js`

1. `getLabels(client)` — Lista todas as etiquetas com `{ id, name }`
2. `loadContactsByLabelName(client, 'Terreno')` — Carrega contatos de uma etiqueta pelo nome
3. `loadContactsByLabelId(client, '1')` — Carrega contatos de uma etiqueta pelo ID
4. `loadFilteredContacts(client, { labelName: 'Casa' })` — Atalho com filtros

Templates automáticos por etiqueta (`src/messages/templates.js`):
- Etiqueta "Terreno" → `terreno({ name })` — mensagem sobre lotes/terrenos
- Etiqueta "Casa" → `casa({ name })` — mensagem sobre casas
- Outra etiqueta → `ofertaPadrao({ name })` — template genérico
- `getTemplateByLabel(labelName)` — retorna a função de template correta

Config (`.env`):
- `SEND_MODE=label` — ativa modo etiqueta
- `SEND_LABEL=Terreno` — nome da etiqueta para envio

API:
- `GET /api/labels` — lista etiquetas disponíveis
- `POST /api/send { "labelName": "Casa" }` — envia para etiqueta específica

### Skill: Adicionar Contatos via CSV (Fallback)

O CSV ainda é suportado como fallback via `src/contacts/loader.js`:

1. O formato do CSV é: `nome,telefone` (com header)
2. Telefone deve estar no formato: `5511999998888` (código país + DDD + número)
3. Validar com `src/contacts/validator.js` antes de processar
4. Logar contatos inválidos sem interromper o processo

### Skill: Configurar Envio em Massa

Quando o usuário pedir para configurar ou ajustar o envio:

1. Verificar `src/messages/scheduler.js` para delays
2. Delay mínimo recomendado entre mensagens: **30-60 segundos** (anti-ban)
3. Delay randômico para parecer humano: `MIN_DELAY + Math.random() * (MAX_DELAY - MIN_DELAY)`
4. Máximo recomendado por sessão: **50-100 mensagens**
5. Sempre incluir o nome do contato na mensagem
6. Enviar imagem junto usando `MessageMedia.fromFilePath()`

### Skill: Debug de Conexão WhatsApp

Quando houver problemas de conexão:

1. Verificar se a pasta `.wwebjs_auth/` existe (sessão salva)
2. Se QR code não aparece, deletar `.wwebjs_auth/` e reiniciar
3. Verificar se Chromium está instalado (necessário para whatsapp-web.js)
4. Verificar logs em `logs/` para erros detalhados
5. Eventos importantes: `ready`, `authenticated`, `auth_failure`, `disconnected`

### Skill: Rodar Testes

Quando o usuário pedir para testar:

1. `npm test` — roda todos os testes
2. `npm run test:watch` — modo watch
3. `npm run test:coverage` — com cobertura
4. Testes ficam em `tests/` espelhando a estrutura de `src/`
5. Naming: `[modulo].test.js`

---

## 🕵️ Agents

### Agent: Revisor de Código

**Trigger**: Antes de qualquer commit ou quando pedido review

**Checklist**:
- [ ] ESLint passa sem erros (`npm run lint`)
- [ ] Prettier formatou tudo (`npm run format`)
- [ ] Nenhum `console.log` direto (usar logger)
- [ ] Nenhum valor hardcoded (usar env/constantes)
- [ ] Funções públicas têm JSDoc
- [ ] Erros são tratados com try/catch
- [ ] Testes cobrem a funcionalidade
- [ ] Nenhum dado sensível no código

### Agent: Anti-Ban

**Trigger**: Sempre que modificar lógica de envio

**Regras para evitar banimento do WhatsApp**:
- [ ] Delay entre mensagens >= 30 segundos
- [ ] Delay é randômico (não fixo)
- [ ] Mensagem contém nome do contato (personalização)
- [ ] Não envia mais de 100 msgs por sessão
- [ ] Tem intervalo longo (5-10 min) a cada 20 mensagens
- [ ] Mensagens não são 100% idênticas (variação)
- [ ] Horário de envio é comercial (8h-20h)
- [ ] Imagem acompanha a mensagem (engagement natural)

### Agent: Validador de Contatos

**Trigger**: Quando contatos são carregados ou modificados

**Validações**:
- [ ] Número tem formato correto: `55` + DDD(2) + Número(8-9)
- [ ] Nome não está vazio
- [ ] Sem duplicatas de número
- [ ] Número tem entre 12-13 dígitos
- [ ] Log de contatos inválidos para revisão

### Agent: Monitor de Envio

**Trigger**: Durante execução do disparo em massa

**Monitoramento**:
- [ ] Log de cada mensagem enviada (contato, horário, status)
- [ ] Contagem de sucesso/falha
- [ ] Detecção de erro de conexão com retry
- [ ] Relatório final (total, sucesso, falhas, tempo)
- [ ] Salvar relatório em `logs/`

---

## 📁 Fluxo Principal do Sistema

```
1. Inicializar → src/index.js
2. Carregar .env → src/config/env.js
3. Conectar WhatsApp → src/client/whatsapp.js (exibe QR code)
4. Autenticar → salva sessão em .wwebjs_auth/
5. Registrar listeners de mensagem → src/listeners/messageListener.js
   (encaminha msgs recebidas/enviadas/acks para Laravel via webhook)
6. Iniciar Express API → src/api/server.js
7. Carregar contatos:
   a. SEND_MODE=csv → src/contacts/loader.js (arquivo CSV)
   b. SEND_MODE=whatsapp → src/contacts/whatsappLoader.js (todos os contatos)
   c. SEND_MODE=label → src/contacts/whatsappLoader.js (por etiqueta)
8. Validar contatos → src/contacts/validator.js
9. Selecionar template (genérico ou por etiqueta) → src/messages/templates.js
10. Preparar imagem → src/media/imageHandler.js
11. Para cada contato:
   a. Montar mensagem com nome (pushname/salvo) → src/messages/templates.js
   b. Aguardar delay randômico → src/messages/scheduler.js
   c. Enviar msg + imagem usando chatId → src/messages/sender.js
   d. Logar resultado → src/utils/logger.js
12. Relatório final → logs/
```

---

## 📬 Módulo de Inbox / Chat em Tempo Real (Fase 5.5.3)

### Arquitetura

```
Contato envia msg no WhatsApp
  → whatsapp-web.js recebe (client.on('message'))
  → src/listeners/messageListener.js filtra e extrai dados
  → src/webhooks/notifier.js POST para WEBHOOK_URL do Laravel
  → Laravel salva msg, cria conversa, exibe no Inbox

Operador envia msg pelo Painel Laravel
  → Laravel chama POST /api/send-text ou /api/send-media no bot
  → Bot envia via whatsapp-web.js
  → Retorna messageId para Laravel salvar
  → client.on('message_ack') notifica Laravel sobre entrega/leitura
```

### Listeners Registrados (src/listeners/messageListener.js)

| Evento | Filtros | Webhook Disparado |
|--------|---------|-------------------|
| `client.on('message')` | Ignora grupos (@g.us), broadcasts, notificações de sistema | `message_received` |
| `client.on('message_ack')` | Só msgs fromMe, só ack 1-3 | `message_ack` |
| `client.on('message_create')` | Só fromMe (msgs enviadas fora do painel) | `message_sent_external` |
| `client.on('disconnected')` | — | `disconnected` |
| `client.on('qr')` | — | `qr_generated` |
| `client.on('ready')` | — | `status_changed` (connected) |
| `client.on('authenticated')` | — | `status_changed` (connected) |

**Flag de controle**: `ENABLE_MESSAGE_FORWARDING=true` no `.env` para ativar.

### Endpoints de Chat (src/api/server.js)

| Método | Path | Body/Query | Response |
|--------|------|------------|----------|
| `POST` | `/api/send-text` | `{ phone, message }` | `{ success, messageId, timestamp }` |
| `POST` | `/api/send-media` | `{ phone, mediaUrl, mediaType?, message? }` | `{ success, messageId, timestamp }` |
| `POST` | `/api/typing` | `{ phone, duration? }` | `{ success, duration }` |
| `GET` | `/api/chats` | `?limit=50` | `{ total, chats: [{ chatId, name, lastMessage, timestamp, unreadCount }] }` |
| `GET` | `/api/chats/:chatId/messages` | `?limit=50` | `{ total, messages: [{ id, body, timestamp, fromMe, ack, type, hasMedia }] }` |

### Webhook Payloads (src/webhooks/notifier.js)

Formato padrão de todos os webhooks:
```json
{
  "event": "message_received",
  "instance_id": 1,
  "data": { ... },
  "timestamp": "2026-03-19T..."
}
```

Headers: `Content-Type: application/json`, `x-webhook-secret: {secret}`
Retry: 3 tentativas com backoff exponencial (2s, 4s, 6s).

---

## ⚙️ Variáveis de Ambiente (.env)

```env
# WhatsApp
WWEBJS_CACHE_TYPE=local

# API
PORT=3000
API_KEY=sua-chave-secreta-aqui

# Modo de envio: csv | whatsapp | label
SEND_MODE=csv
SEND_LABEL=             # Nome da etiqueta (quando SEND_MODE=label)

# Envio
MIN_DELAY_MS=30000
MAX_DELAY_MS=60000
MAX_MESSAGES_PER_SESSION=100
PAUSE_AFTER_MESSAGES=20
PAUSE_DURATION_MS=300000

# Imagem
DEFAULT_IMAGE_PATH=media/oferta.jpg

# Webhook para Laravel (Inbox / Tempo Real)
WEBHOOK_URL=            # Ex: https://meuapp.com/api/webhooks/whatsapp
WEBHOOK_SECRET=         # Deve bater com WHATSAPP_BOT_WEBHOOK_SECRET do Laravel
INSTANCE_ID=0           # ID da instância no banco do Laravel
ENABLE_MESSAGE_FORWARDING=false  # true para ativar encaminhamento de msgs via webhook

# Logs
LOG_LEVEL=info
```

---

## 🚀 Comandos Disponíveis

```bash
npm start              # Inicia o bot
npm run dev            # Inicia com nodemon (dev)
npm run lint           # Roda ESLint
npm run lint:fix       # Corrige erros do ESLint automaticamente
npm run format         # Formata com Prettier
npm test               # Roda testes com Jest
npm run test:watch     # Testes em modo watch
npm run test:coverage  # Testes com relatório de cobertura
```

---

## 🛡️ Segurança — NUNCA commitar

- `.env` (variáveis de ambiente)
- `.wwebjs_auth/` (sessão do WhatsApp)
- `.wwebjs_cache/` (cache do Chromium)
- `node_modules/`
- `logs/*.log`
- Dados pessoais de contatos (`data/contacts.csv`)

---

## 📝 Notas para o Claude

1. **Sempre** personalizar mensagens com o nome do contato — é o diferencial anti-bot
2. **Sempre** implementar delays randômicos — sem isso, o número será banido
3. **Sempre** usar o logger (Winston) em vez de console.log
4. **Sempre** validar contatos antes do envio
5. **Sempre** rodar lint após mudanças de código
6. **Preferir** composição sobre herança
7. **Preferir** funções puras e módulos pequenos
8. **Manter** arquivos com no máximo ~200 linhas
9. O projeto usa **CommonJS** (require/module.exports), NÃO usar import/export
10. Telefones brasileiros: código país `55` + DDD (2 dígitos) + número (8 ou 9 dígitos)
