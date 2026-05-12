# 📖 Manual de Uso — WhatsApp Bot Terrenos

## Teste Rápido (Envio para 1 contato via CSV)

---

### 1️⃣ Pré-requisitos

- **Node.js** >= 18 instalado
- **Google Chrome** ou **Chromium** instalado (o whatsapp-web.js usa por baixo)
- Dependências instaladas (`npm install` já foi feito)

---

### 2️⃣ Configurar o `.env`

O arquivo `.env` já está criado. Verifique:

```env
SEND_MODE=csv                      # ← Usa contatos do arquivo CSV
CONTACTS_CSV_PATH=data/contacts.csv  # ← Caminho do CSV
API_KEY=mude-esta-chave-secreta      # ← Troque por uma chave sua
DEFAULT_IMAGE_PATH=media/oferta.jpg  # ← Imagem que será enviada junto
```

> ⚠️ **IMPORTANTE**: `SEND_MODE=csv` garante que só os contatos do arquivo serão usados.
> Quando quiser disparar para todos os contatos do WhatsApp, mude para `SEND_MODE=whatsapp`.

---

### 3️⃣ Preparar o CSV de contatos

O arquivo `data/contacts.csv` já está com seu contato de teste:

```csv
nome,telefone
João Silva,5545991325057
```

**Formato do telefone**: `55` + DDD (2 dígitos) + número (8 ou 9 dígitos)

Para adicionar mais contatos, basta adicionar linhas:
```csv
nome,telefone
João Silva,5545991325057
Maria Souza,5521988887777
```

---

### 4️⃣ Colocar a imagem de envio

Coloque a imagem que quer enviar na pasta `media/`:

```bash
# Copie sua imagem para a pasta media
cp /caminho/da/sua/imagem.jpg media/oferta.jpg
```

> Se não colocar imagem, a mensagem será enviada **só com texto** (sem erro).

---

### 5️⃣ Iniciar o bot

```bash
npm start
```

Você verá algo como:

```
[2026-03-16 17:00:00] info: 🤖 Iniciando WhatsApp Bot Terrenos...
[2026-03-16 17:00:05] info: Inicializando WhatsApp client...
```

#### 📱 Escanear QR Code

Um **QR code** aparecerá no terminal. Escaneie com seu WhatsApp:

1. Abra o WhatsApp no celular
2. Vá em **Configurações** → **Dispositivos vinculados**
3. Toque em **Vincular dispositivo**
4. Escaneie o QR code do terminal

Após escanear:

```
[2026-03-16 17:00:15] info: 🔐 Autenticado com sucesso!
[2026-03-16 17:00:20] info: ✅ WhatsApp client conectado e pronto!
[2026-03-16 17:00:20] info: 🌐 API rodando em http://localhost:3000
[2026-03-16 17:00:20] info: Bot pronto para envio!
```

> 💡 Na próxima vez que iniciar, **não precisa escanear de novo** — a sessão fica salva em `.wwebjs_auth/`.

---

### 6️⃣ Disparar o envio (via API)

Com o bot rodando, abra **outro terminal** e execute:

```bash
curl -X POST http://localhost:3000/api/send \
  -H "Content-Type: application/json" \
  -H "x-api-key: mude-esta-chave-secreta" \
  -d '{}'
```

O bot vai:
1. Ler o `data/contacts.csv`
2. Pegar o contato "João Silva" (5545991325057)
3. Montar a mensagem: `"Olá João Silva! 👋 ..."`
4. Enviar a mensagem + imagem para esse número

Você verá no terminal:

```
[...] info: 📄 Modo CSV — carregando de: data/contacts.csv
[...] info: 📋 1 contatos carregados do CSV
[...] info: ✅ 1 contatos válidos | ❌ 0 inválidos
[...] info: 🚀 Iniciando envio em massa para 1 contatos...
[...] info: ✅ Mensagem enviada para 5545991325057@c.us
[...] info: 🏁 Envio finalizado!
```

---

### 7️⃣ Outros comandos da API

#### Ver status do bot
```bash
curl http://localhost:3000/api/status \
  -H "x-api-key: mude-esta-chave-secreta"
```

#### Ver health check (sem autenticação)
```bash
curl http://localhost:3000/health
```

#### Parar envio em andamento
```bash
curl -X POST http://localhost:3000/api/stop \
  -H "x-api-key: mude-esta-chave-secreta"
```

#### Enviar com template personalizado (com preço e localização)
```bash
curl -X POST http://localhost:3000/api/send \
  -H "Content-Type: application/json" \
  -H "x-api-key: mude-esta-chave-secreta" \
  -d '{
    "extraParams": {
      "location": "Cascavel - PR",
      "price": "R$ 45.000"
    }
  }'
```

---

### 8️⃣ Enviar sem imagem (só texto)

Se não tiver imagem na pasta `media/`, o bot envia só o texto automaticamente. Ou você pode forçar mandando `imagePath` vazio:

Se quiser garantir envio **só texto**, basta não ter o arquivo `media/oferta.jpg`.

---

### 9️⃣ Quando quiser enviar para todos do WhatsApp

Mude no `.env`:

```env
SEND_MODE=whatsapp
```

Reinicie o bot (`Ctrl+C` e `npm start`) e dispare via API. Ele vai buscar todos os contatos do seu WhatsApp.

---

## 🔄 Resumo dos Modos

| `.env`               | Comportamento                                                  |
| -------------------- | -------------------------------------------------------------- |
| `SEND_MODE=csv`      | Envia SÓ para os contatos do `data/contacts.csv`               |
| `SEND_MODE=whatsapp` | Envia para TODOS os contatos do WhatsApp conectado              |
| `SEND_MODE=label`    | Envia para contatos de uma **etiqueta** do WhatsApp Business    |

---

## 🏷️ Enviar por Etiqueta (WhatsApp Business)

Se você usa o **WhatsApp Business** e organiza seus contatos com **etiquetas** (ex: "Casa", "Terreno"), pode enviar mensagens segmentadas para cada grupo.

### Configurar o `.env`

```env
SEND_MODE=label
SEND_LABEL=Terreno
```

Troque `Terreno` por `Casa` quando quiser enviar para o outro grupo.

### Como funciona

1. O bot busca a etiqueta pelo nome (case-insensitive: "terreno", "Terreno", "TERRENO" são iguais)
2. Carrega todos os chats/contatos que estão naquela etiqueta
3. Para cada contato, busca o `pushname` (nome do WhatsApp) — funciona **mesmo sem ter o contato salvo**
4. Seleciona automaticamente o template correto:
   - Etiqueta **"Terreno"** → mensagem sobre terrenos
   - Etiqueta **"Casa"** → mensagem sobre casas
   - Qualquer outra → template padrão

### Disparar via API (com etiqueta)

```bash
# Enviar para contatos da etiqueta "Terreno"
curl -X POST http://localhost:3000/api/send \
  -H "Content-Type: application/json" \
  -H "x-api-key: mude-esta-chave-secreta" \
  -d '{"labelName": "Terreno"}'

# Enviar para contatos da etiqueta "Casa"
curl -X POST http://localhost:3000/api/send \
  -H "Content-Type: application/json" \
  -H "x-api-key: mude-esta-chave-secreta" \
  -d '{"labelName": "Casa"}'
```

> 💡 Quando você passa `labelName` via API, ele tem prioridade sobre o `SEND_LABEL` do `.env`.

### Listar etiquetas disponíveis

```bash
curl http://localhost:3000/api/labels \
  -H "x-api-key: mude-esta-chave-secreta"
```

Resposta:
```json
{
  "total": 2,
  "labels": [
    { "id": "1", "name": "Terreno" },
    { "id": "2", "name": "Casa" }
  ]
}
```

---

## ⚠️ Troubleshooting

| Problema                      | Solução                                                |
| ----------------------------- | ------------------------------------------------------ |
| QR code não aparece           | Delete a pasta `.wwebjs_auth/` e reinicie               |
| "Arquivo de contatos não encontrado" | Verifique se `data/contacts.csv` existe           |
| "API Key inválida"            | Confira o header `x-api-key` com o valor do `.env`      |
| Mensagem não chega            | Verifique se o número está correto (55 + DDD + número)  |
| "Envio já em andamento"       | Aguarde o envio atual terminar ou use `/api/stop`       |
| Erro de Chromium              | Instale: `brew install chromium` (macOS)                |

---

## 📂 Arquivos importantes

```
.env                    ← Suas configurações (não commitar!)
data/contacts.csv       ← Lista de contatos para envio
media/oferta.jpg        ← Imagem que será enviada
logs/combined.log       ← Logs de tudo que aconteceu
logs/error.log          ← Só os erros
```
