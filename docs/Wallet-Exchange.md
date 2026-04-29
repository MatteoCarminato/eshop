# 💱 Módulo de Câmbio / Carteira Multi-Moeda (Laravel)

## 📌 Objetivo

Construir um sistema simples e robusto para:

* Receber valores em:

  * BRL (PIX / dinheiro)
  * USD (dinheiro)
  * USDT (cripto)
* Controlar saldo por cliente (carteira)
* Registrar histórico completo (auditoria)
* Realizar conversões entre moedas
* Saber **como cada valor entrou**

---

# 🧠 Conceitos Fundamentais

## 1. Separação de responsabilidades

| Conceito     | Responsável por |
| ------------ | --------------- |
| Wallets      | Saldo atual     |
| Transactions | Histórico real  |

👉 **Regra de ouro:**

> Nunca confie apenas no saldo. O histórico é a verdade.

---

## 2. Moeda ≠ Forma de pagamento

| Tipo           | Exemplo           |
| -------------- | ----------------- |
| Currency       | BRL, USD, USDT    |
| Payment Method | pix, cash, crypto |

---

# 🧱 Estrutura do Banco de Dados

## 📌 1. wallets

```sql
id
client_id
currency (BRL, USD, USDT)
balance DECIMAL(15,2)
created_at
updated_at
```

### Regras:

* 1 wallet por moeda por usuário
* saldo sempre atualizado após cada operação

---

## 📌 2. transactions

```sql
id
client_id

type ENUM('deposit', 'withdraw', 'exchange_in', 'exchange_out')

currency (BRL, USD, USDT)
amount DECIMAL(15,2)

payment_method (pix, cash, crypto)

# Para câmbio
converted_currency NULL
converted_amount DECIMAL(15,2) NULL
exchange_rate DECIMAL(15,6) NULL

description TEXT NULL

created_at
```

---

# 🔁 Tipos de Transação

| Tipo         | Descrição                |
| ------------ | ------------------------ |
| deposit      | Entrada de valor         |
| withdraw     | Saída de valor           |
| exchange_out | Saída da moeda origem    |
| exchange_in  | Entrada da moeda destino |

---

# 🔥 Casos de Uso

## 💰 1. Depósito via PIX (BRL)

```json
{
  "type": "deposit",
  "currency": "BRL",
  "amount": 1000,
  "payment_method": "pix"
}
```

👉 Atualização:

```
wallet BRL += 1000
```

---

## 💵 2. Entrada em dólar físico

```json
{
  "type": "deposit",
  "currency": "USD",
  "amount": 100,
  "payment_method": "cash"
}
```

---

## 🪙 3. Entrada em USDT

```json
{
  "type": "deposit",
  "currency": "USDT",
  "amount": 50,
  "payment_method": "crypto"
}
```

---

## 💱 4. Conversão (BRL → USD)

### Entrada:

* R$ 1000 → $200
* taxa: 0.20

### Transação 1 (saída BRL)

```json
{
  "type": "exchange_out",
  "currency": "BRL",
  "amount": -1000
}
```

### Transação 2 (entrada USD)

```json
{
  "type": "exchange_in",
  "currency": "USD",
  "amount": 200,
  "exchange_rate": 0.20,
  "converted_currency": "BRL",
  "converted_amount": 1000
}
```

---

# ⚙️ Regras de Negócio

## ✔️ Sempre usar transação de banco

```php
DB::transaction(function () {
    // criar transaction
    // atualizar wallet
});
```

---

## ✔️ Atualização de saldo

```php
$wallet->balance += $amount;
$wallet->save();
```

---

## ✔️ Nunca alterar histórico

* Transactions são imutáveis
* Correções = nova transação

---

# 🧠 Lógica de Conversão

```php
function convert($amount, $rate) {
    return $amount * $rate;
}
```

---

# 🧩 Estrutura de Models (Laravel)

## Wallet.php

```php
class Wallet extends Model
{
    protected $fillable = [
        'client_id',
        'currency',
        'balance'
    ];

    public function user()
    {
        return $this->belongsTo(Client::class);
    }
}
```

---

## Transaction.php

```php
class Transaction extends Model
{
    protected $fillable = [
        'client_id',
        'type',
        'currency',
        'amount',
        'payment_method',
        'converted_currency',
        'converted_amount',
        'exchange_rate',
        'description'
    ];

    public function user()
    {
        return $this->belongsTo(Client::class);
    }
}
```

---

# 🚀 Services

## CurrencyService (simples)

```php
class CurrencyService
{
    public function convert($amount, $rate)
    {
        return $amount * $rate;
    }
}
```

---

## WalletService

```php
class WalletService
{
    public function updateBalance($clientId, $currency, $amount)
    {
        $wallet = Wallet::firstOrCreate([
            'client_id' => $clientId,
            'currency' => $currency
        ]);

        $wallet->balance += $amount;
        $wallet->save();
    }
}
```

---

# 🔄 Fluxos

## 📥 Entrada

1. Criar transaction (deposit)
2. Atualizar wallet

---

## 🔁 Conversão

1. Criar transaction (exchange_out)
2. Criar transaction (exchange_in)
3. Atualizar duas wallets

---

## 📤 Saída

1. Criar transaction (withdraw)
2. Atualizar wallet (-)

---

# 📊 Consultas úteis

## Saldo do usuário

```php
Wallet::where('client_id', $clientId)->get();
```

---

## Extrato

```php
Transaction::where('client_id', $clientId)
    ->orderBy('created_at', 'desc')
    ->get();
```

---

# ⚠️ Boas práticas

* Usar `DECIMAL` (nunca float)
* Indexar `client_id`
* Validar saldo antes de saque
* Logar erros de conversão
* Cache de cotação (se usar API)

---

# 🔮 Evoluções futuras

* 📈 Histórico de cotação
* 💰 Spread (lucro no câmbio)
* 🔐 Permissões por operador
* 📊 Dashboard financeiro
* 🧾 Exportação (Excel/PDF)

---

# ✔️ Resumo Final

Você terá:

✅ Controle de saldo por moeda
✅ Histórico completo
✅ Conversões seguras
✅ Auditoria confiável
✅ Base pronta para escalar

---

Se quiser próximo passo, posso te gerar:

* migrations prontas
* controllers
* API REST
* tela Blade (extrato + carteira)

Só pedir 👍
