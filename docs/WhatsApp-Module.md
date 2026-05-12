# 📲 Módulo de WhatsApp (Laravel + Node) — Passo a passo

Este documento descreve como implementar, dentro da sua plataforma Laravel, uma tela para:

- conectar no WhatsApp Web;
- selecionar **Clientes** ou **Grupos de clientes**;
- enviar **Texto**, **Imagem** e **Arquivo**;
- disparar mensagens em massa com controle e rastreabilidade.

---

## 1) Arquitetura recomendada

## Objetivo
Separar responsabilidades:

- **Laravel (app principal):** UI, regras de negócio, seleção de clientes/grupos, permissões, fila e histórico;
- **Node (motor WhatsApp):** conexão WhatsApp Web (QR/session), envio real de mensagens e callbacks de status.

## Fluxo alto nível
1. Usuário abre tela no Laravel.
2. Laravel consulta Node para status da sessão (`connected`, `disconnected`, `qr_pending`).
3. Se precisar, exibe QR na tela (vindo do Node).
4. Usuário escolhe clientes/grupos, escreve mensagem e anexa mídia.
5. Laravel grava campanha/envio e envia jobs para fila.
6. Worker Laravel chama API Node para disparar cada destinatário.
7. Node responde status; Laravel registra em histórico.

---

## 2) Stack sugerida

## Laravel
- Queue: `redis` + `php artisan queue:work`
- Storage: `storage/app/public/whatsapp`
- HTTP Client: `Illuminate\Support\Facades\Http`

## Node (serviço separado)
- Runtime: Node 20+
- Lib WhatsApp Web: `whatsapp-web.js` (com `puppeteer`)
- API: `express`
- Real-time QR/status (opcional e recomendado): `socket.io`
- Persistência de sessão: `LocalAuth` (ou pasta dedicada por instância)

---

## 3) Modelagem (Laravel)

Crie migrations para controle completo:

### 3.1 `whatsapp_instances`
- `id`
- `name` (ex.: "instancia_principal")
- `status` (`disconnected`, `qr_pending`, `connected`, `error`)
- `session_key` (identificador da sessão no Node)
- `last_qr` (text, nullable)
- `last_seen_at` (nullable)
- `meta` (json, nullable)
- timestamps

### 3.2 `whatsapp_campaigns`
- `id`
- `created_by` (fk users)
- `instance_id` (fk whatsapp_instances)
- `title` (nullable)
- `message_type` (`text`, `image`, `file`)
- `message_text` (longText, nullable)
- `media_path` (nullable)
- `media_name` (nullable)
- `status` (`draft`, `queued`, `sending`, `done`, `failed`)
- timestamps

### 3.3 `whatsapp_campaign_recipients`
- `id`
- `campaign_id` (fk)
- `client_id` (fk clients)
- `phone_e164` (string)
- `status` (`pending`, `sent`, `failed`)
- `provider_message_id` (nullable)
- `error` (text, nullable)
- `sent_at` (nullable)
- timestamps

> Observação: seus grupos já existem no domínio (`groups`). Use tabela pivô para clientes no grupo, se ainda não houver (`client_group`).

---

## 4) Serviço Node (WhatsApp)

Crie um serviço separado, por exemplo em `/whatsapp-gateway`.

## Endpoints mínimos do Node

### Sessão
- `POST /instances/:key/start` → inicia cliente WhatsApp
- `POST /instances/:key/stop` → encerra
- `GET /instances/:key/status` → status atual
- `GET /instances/:key/qr` → último QR (base64)

### Envio
- `POST /messages/send`
  - body:
    - `instanceKey`
    - `to` (telefone em E.164)
    - `type` (`text` | `image` | `file`)
    - `text` (opcional)
    - `mediaUrl` (opcional)
    - `fileName` (opcional)

### Segurança
- Header compartilhado: `X-Internal-Token`
- Rejeitar requests sem token válido

---

## 5) Integração Laravel ↔ Node

## Configuração `.env`

```env
WHATSAPP_NODE_URL=http://127.0.0.1:3001
WHATSAPP_NODE_TOKEN=seu_token_interno
```

## Classe de integração (Laravel)
Criar `app/Services/WhatsappGatewayService.php` com métodos:

- `startInstance(string $key)`
- `getInstanceStatus(string $key)`
- `getInstanceQr(string $key)`
- `sendText(...)`
- `sendMedia(...)`

Sempre usar timeout + retry.

---

## 6) Tela no Laravel (admin)

Criar página: `resources/views/admin/whatsapp/index.blade.php`

## Blocos da tela

### 6.1 Conexão WhatsApp
- Card com status da instância
- Botão `Conectar`
- Área para renderizar QR (polling ou socket)
- Indicador "Conectado" quando autenticado

### 6.2 Seleção de público
- Aba 1: **Clientes** (multiselect)
- Aba 2: **Grupos** (multiselect)
- Ao escolher grupo, expandir para clientes únicos (sem duplicidade)

### 6.3 Composição da mensagem
- Tipo: `Texto`, `Imagem`, `Arquivo`
- Campo texto (obrigatório para texto)
- Upload mídia (obrigatório para imagem/arquivo)
- Preview simples (nome do arquivo/imagem)

### 6.4 Disparo
- Botão `Enviar agora`
- Mostrar quantidade de destinatários
- Salvar campanha + enfileirar disparos

### 6.5 Histórico
- Lista de campanhas e status
- Clique para ver destinatário por destinatário (enviado/falhou)

---

## 7) Regras de envio

1. Normalizar telefone para E.164 (ex.: `+5511999999999`).
2. Ignorar clientes sem telefone válido (logar erro por destinatário).
3. Remover duplicados por telefone na mesma campanha.
4. Aplicar limite de taxa (ex.: 1 msg/seg) para reduzir bloqueio.
5. Usar fila (`SendWhatsappMessageJob`) para escalabilidade.

---

## 8) Jobs e fila

## Job sugerido
`app/Jobs/SendWhatsappMessageJob.php`

Responsabilidades:
- receber `recipient_id`;
- buscar campanha + destinatário;
- chamar `WhatsappGatewayService`;
- atualizar `status`, `provider_message_id`, `error`, `sent_at`.

## Processamento
- Criar fila dedicada: `whatsapp`
- Rodar worker dedicado:

```bash
php artisan queue:work --queue=whatsapp --tries=3 --sleep=1
```

---

## 9) Upload de imagem/arquivo

1. Upload no Laravel para `storage/app/public/whatsapp/campaigns/{id}`.
2. Salvar caminho em `whatsapp_campaigns.media_path`.
3. Expor URL pública assinada/segura para o Node consumir.
4. Node baixa e envia mídia ao WhatsApp.

Tipos permitidos (exemplo):
- Imagem: `jpg`, `jpeg`, `png`, `webp`
- Arquivo: `pdf`, `doc`, `docx`, `xls`, `xlsx`, `txt`, `zip`

---

## 10) Rotas sugeridas (Laravel)

```php
Route::prefix('admin/whatsapp')->name('admin.whatsapp.')->group(function () {
    Route::get('/', [WhatsappController::class, 'index'])->name('index');
    Route::post('/instance/connect', [WhatsappController::class, 'connect'])->name('instance.connect');
    Route::get('/instance/status', [WhatsappController::class, 'status'])->name('instance.status');
    Route::get('/instance/qr', [WhatsappController::class, 'qr'])->name('instance.qr');

    Route::post('/campaigns', [WhatsappController::class, 'storeCampaign'])->name('campaigns.store');
    Route::get('/campaigns/{campaign}', [WhatsappController::class, 'showCampaign'])->name('campaigns.show');
});
```

---

## 11) Segurança e compliance

- Proteger tela com permissão (ex.: apenas admin).
- Logar usuário que criou campanha (`created_by`).
- Não expor token do Node no front.
- Limitar tamanho de arquivo e validar MIME.
- Ter campo de opt-out no cliente (recomendado).

---

## 12) Roadmap de implementação (ordem prática)

## Fase 1 — Base
- [ ] Criar migrations e models de WhatsApp
- [ ] Criar serviço Node com endpoints de sessão (start/status/qr)
- [ ] Conectar Laravel ao Node (service + config)

## Fase 2 — Tela de conexão
- [ ] Criar página admin `/admin/whatsapp`
- [ ] Exibir status e QR em tempo real
- [ ] Validar fluxo conectar/desconectar

## Fase 3 — Campanha e destinatários
- [ ] Seleção de clientes e grupos
- [ ] Resolver destinatários finais sem duplicidade
- [ ] Salvar campanha + recipients

## Fase 4 — Disparo
- [ ] Criar `SendWhatsappMessageJob`
- [ ] Enfileirar disparos em lote
- [ ] Atualizar status por destinatário

## Fase 5 — Mídia
- [ ] Upload e armazenamento seguro
- [ ] Envio de imagem/arquivo via Node
- [ ] Logs de falha por mídia inválida

## Fase 6 — Produção
- [ ] Supervisão de processos (Supervisor/PM2)
- [ ] Métricas e alertas
- [ ] Política de limite e retentativas

---

## 13) Checklist de pronto

- [ ] Instância conecta via QR dentro da plataforma
- [ ] Envia texto para 1 cliente e para N clientes
- [ ] Envia imagem e arquivo com sucesso
- [ ] Seleção por grupo funciona sem duplicidade
- [ ] Histórico mostra `pending/sent/failed`
- [ ] Falhas registradas por destinatário
- [ ] Permissões e validações de segurança ativas

---

## 14) Observações importantes

- `whatsapp-web.js` depende da estabilidade do WhatsApp Web; mantenha o Node isolado e monitorado.
- Se volume crescer muito, considere no futuro provider oficial (API Business) para menor risco operacional.

---

Se quiser, no próximo passo eu já gero o **esqueleto inicial** (migrations + controller + rotas + service Laravel + boilerplate Node Express) em cima deste plano.
