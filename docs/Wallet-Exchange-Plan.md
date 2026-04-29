# 📝 Plano de Ação – Módulo Carteira/Câmbio (Laravel)

## 1. Modelagem e Migrations

- [ ] Criar migrations para as tabelas:
  - `wallets` (saldo por cliente/moeda)
  - `transactions` (histórico detalhado)
- [ ] Garantir índices em `client_id` e uso de `DECIMAL` para valores.

## 2. Models Eloquent

- [ ] Criar models `Wallet` e `Transaction` com relacionamentos para `Client`.
- [ ] Definir fillables e casts apropriados.


## 3. Services

- [x] Implementar `WalletService` para atualização de saldo (`app/Services/WalletService.php`).
- [x] Implementar `CurrencyService` para conversão de moedas (`app/Services/CurrencyService.php`).


## 4. Regras de Negócio

- [x] Garantir uso de transações de banco (`DB::transaction`) em toda operação (implementado nos services).
- [x] Implementar lógica imutável para `Transaction` (nunca editar, só adicionar) via `TransactionService`.
- [x] Validar saldo antes de saques/conversões (`TransactionService::validarSaldo`).

## 5. Controllers e Requests

- [x] Criar controllers para depósitos, saques e conversões (`app/Http/Controllers/WalletController.php`).
- [x] Criar FormRequests para validação de entrada (`app/Http/Requests/Wallet/DepositRequest.php`, `WithdrawRequest.php`, `ExchangeRequest.php`).

## 6. Testes

- [ ] Escrever testes unitários para services.
- [ ] Escrever testes de integração para fluxos principais (depósito, saque, conversão).

## 7. Interface/Admin

- [x] Criar telas Blade para:
  - Visualizar saldo por moeda (`resources/views/admin/wallet/index.blade.php`)
  - Extrato/histórico de transações (`resources/views/admin/wallet/transactions.blade.php`)
  - Formulários de depósito (`deposit.blade.php`), saque (`withdraw.blade.php`) e conversão (`exchange.blade.php`)

## 8. Auditoria e Logs

- [ ] Garantir logging de erros e operações críticas.
- [ ] Validar histórico completo para auditoria.

## 9. Documentação

- [ ] Atualizar/expandir a documentação conforme implementa.
- [ ] Adicionar exemplos de uso da API (se houver).

---

**Dica:** Siga a ordem acima para evitar retrabalho e garantir base sólida. Se quiser, posso gerar as migrations e models iniciais para você começar!
