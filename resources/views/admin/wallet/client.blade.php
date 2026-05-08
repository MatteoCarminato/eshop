@extends('layouts.app')
@section('title', 'Carteira do Cliente')
@section('content')
    <div class="page-content wallet-compact">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Carteira de {{ $client->name }}</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">Carteira do Cliente</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Flash messages e erros de validação --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mt-2 mb-0" role="alert">
                    <i class="ri-check-line me-1"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show mt-2 mb-0" role="alert">
                    <i class="ri-error-warning-line me-1"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show mt-2 mb-0" role="alert">
                    <strong><i class="ri-error-warning-line me-1"></i>Erro na validação:</strong>
                    <ul class="mb-0">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            {{-- Linha fina mostrando o caixa USD da empresa (global e do cliente) --}}
            @php
                $caixaTotal = (float) ($treasurySummary['usd_em_caixa'] ?? 0);
                $caixaCliente = (float) ($treasuryClientSummary['usd_em_caixa_cliente'] ?? 0);
                $custoMedioCli = $treasuryClientSummary['custo_medio_cliente'] ?? null;
                $pnlAcumUsd = (float) ($treasurySummary['pnl_acumulado_usd'] ?? 0);
            @endphp
            <div class="row mt-2">
                <div class="col-12">
                    <div class="alert alert-secondary py-2 px-3 mb-0 d-flex flex-wrap align-items-center justify-content-between gap-3 small">
                        <div>
                            <i class="ri-safe-2-line me-1"></i>
                            <strong>Caixa USD da empresa:</strong>
                            US$ {{ number_format($caixaTotal, 2, ',', '.') }}
                            <span class="text-muted ms-2">|</span>
                            <span class="ms-2">deste cliente:</span>
                            <strong class="{{ $caixaCliente > 0 ? 'text-primary' : 'text-muted' }}">
                                US$ {{ number_format($caixaCliente, 2, ',', '.') }}
                            </strong>
                            @if($custoMedioCli)
                                <span class="text-muted ms-1">@ R$ {{ number_format($custoMedioCli, 4, ',', '.') }}</span>
                            @endif
                        </div>
                        <div>
                            <i class="ri-line-chart-line me-1"></i>
                            <span class="text-muted">Lucro acumulado (caixa):</span>
                            <strong class="{{ $pnlAcumUsd > 0 ? 'text-success' : ($pnlAcumUsd < 0 ? 'text-danger' : 'text-muted') }}">
                                {{ $pnlAcumUsd >= 0 ? '+' : '' }}US$ {{ number_format($pnlAcumUsd, 2, ',', '.') }}
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-xl-12">
                    <div class="card crm-widget">
                        <div class="card-body p-0">
                            <div class="row row-cols-xxl-5 row-cols-md-3 row-cols-1 g-0 text-center">
                                <div class="col">
                                    <div class="py-4 px-4">
                                        <h5 class="text-muted text-uppercase fs-13">Saldo em Real (BRL)
                                        </h5>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <i class="ri-space-ship-line display-6 text-muted cfs-22"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h2 class="mb-0 cfs-22 text-success"><span>R$
                                                        {{ number_format($balances['BRL'], 2, ',', '.') }}</span></h2>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="py-4 px-3">
                                        <h5 class="text-muted text-uppercase fs-13">Saldo em Dólar (USD)
                                        </h5>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <i class="ri-money-dollar-box-line display-6 text-muted cfs-22"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h2 class="mb-0 cfs-22 text-info"><span>US$
                                                        {{ number_format($balances['USD'], 2, ',', '.') }}</span></h2>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="py-4 px-3">
                                        <h5 class="text-muted text-uppercase fs-13">USD Pré-comprado</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <i class="ri-shopping-cart-2-line display-6 text-muted cfs-22"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h2 class="mb-0 cfs-22 text-warning">
                                                    US$ {{ number_format($prePurchaseSummary['usd_pre_comprado'], 2, ',', '.') }}
                                                </h2>
                                                @if($prePurchaseSummary['taxa_media'])
                                                    <small class="text-muted">@ {{ number_format($prePurchaseSummary['taxa_media'], 4, ',', '.') }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="py-4 px-3">
                                        <h5 class="text-muted text-uppercase fs-13">Devo ao Cliente (R$)</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <i class="ri-hand-coin-line display-6 text-muted cfs-22"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h2 class="mb-0 cfs-22 text-danger">
                                                    R$ {{ number_format($prePurchaseSummary['brl_em_aberto'], 2, ',', '.') }}
                                                </h2>
                                                <small class="text-muted">Disp. p/ comprar: R$ {{ number_format($brlAvailableForPrePurchase, 2, ',', '.') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="py-4 px-3">
                                        <h5 class="text-muted text-uppercase fs-13">PnL Realizado</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <i class="ri-line-chart-line display-6 text-muted cfs-22"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                @php $pnlUsdCard = (float) ($prePurchaseSummary['pnl_realizado_usd'] ?? 0); @endphp
                                                <h2 class="mb-0 cfs-22 {{ $pnlUsdCard >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ $pnlUsdCard >= 0 ? '+' : '' }}US$ {{ number_format($pnlUsdCard, 4, ',', '.') }}
                                                </h2>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- end card body -->
                </div><!-- end card -->
            </div><!-- end col -->
        </div><!-- end row -->

        <div class="row mb-4">
            <div class="col-12 d-flex flex-wrap justify-content-md-end align-items-center gap-2">
                <button type="button" class="btn btn-success" data-bs-toggle="modal"
                    data-bs-target="#depositModal">Depositar</button>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                    data-bs-target="#withdrawModal">Sacar</button>

            </div>
        </div>

        <!-- Modal Depositar -->
        <div class="modal fade" id="depositModal" tabindex="-1" aria-labelledby="depositModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="depositModalLabel">Depositar</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <form method="POST" action="{{ route('admin.wallet.deposit') }}">
                        @csrf
                        <input type="hidden" name="client_id" value="{{ $client->id }}">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="deposit_currency" class="form-label">Moeda</label>
                                <select name="currency" id="deposit_currency" class="form-select" required>
                                    <option value="BRL">Reais (BRL)</option>
                                    <option value="USD">Dólar (USD)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="deposit_amount" class="form-label">Valor</label>
                                <input type="number" step="0.01" name="amount" id="deposit_amount" class="form-control"
                                    required>
                            </div>
                            <div class="mb-3" id="deposit_fee_group">
                                <label for="deposit_fee" class="form-label d-flex align-items-center gap-1">
                                    Taxa
                                    <i class="ri-question-line text-muted" data-bs-toggle="tooltip"
                                        data-bs-placement="right"
                                        title="Cotação USD/BRL preenchida automaticamente do Investing.com já somada ao spread do cliente."></i>
                                    <span id="deposit_fee_status" class="ms-auto small text-muted"></span>
                                </label>
                                <input type="number" step="0.0001" min="0.0001" name="fee" id="deposit_fee"
                                    class="form-control" value="4.9311" placeholder="4,9311" required>
                                <small class="text-muted d-block mt-1">
                                    Cotação base buscada do Investing + spread do cliente
                                    (<strong>{{ $client->spread_points }}</strong> pts = R$
                                    {{ number_format($client->spread_points * 0.01, 2, ',', '.') }}).
                                    <a href="https://br.investing.com/currencies/usd-brl" target="_blank"
                                        rel="noopener noreferrer">Ver cotação</a>
                                </small>
                            </div>
                            <div class="mb-3">
                                <label for="deposit_payment_method" class="form-label">Forma de Pagamento</label>
                                <select name="payment_method" id="deposit_payment_method" class="form-select" required>
                                    <option value="pix">Pix</option>
                                    <option value="dinheiro">Dinheiro</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success">Depositar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Sacar -->
        <div class="modal fade" id="withdrawModal" tabindex="-1" aria-labelledby="withdrawModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="withdrawModalLabel">Sacar</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <form method="POST" action="{{ route('admin.wallet.withdraw') }}">
                        @csrf
                        <input type="hidden" name="client_id" value="{{ $client->id }}">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="withdraw_currency" class="form-label">Moeda</label>
                                <select name="currency" id="withdraw_currency" class="form-select" required>
                                    <option value="BRL">Reais (BRL)</option>
                                    <option value="USD">Dólar (USD)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="withdraw_amount" class="form-label">Valor</label>
                                <input type="number" step="0.01" name="amount" id="withdraw_amount" class="form-control"
                                    required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Sacar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function updateDepositPaymentMethod() {
                var currency = document.getElementById('deposit_currency').value;
                var payment = document.getElementById('deposit_payment_method');
                var feeGroup = document.getElementById('deposit_fee_group');
                var feeInput = document.getElementById('deposit_fee');
                payment.innerHTML = '';
                if (currency === 'BRL') {
                    payment.innerHTML += '<option value="pix" selected>Pix</option>';
                    payment.innerHTML += '<option value="dinheiro">Dinheiro</option>';
                    payment.disabled = false;
                    payment.required = true;
                    feeGroup.style.display = '';
                    feeInput.disabled = false;
                    feeInput.required = true;
                } else if (currency === 'USD') {
                    payment.innerHTML += '<option value="efetivo" selected>Efetivo</option>';
                    payment.innerHTML += '<option value="usdt">USDT</option>';
                    payment.disabled = false;
                    payment.required = true;
                    feeGroup.style.display = 'none';
                    feeInput.disabled = true;
                    feeInput.required = false;
                } else {
                    payment.innerHTML += '<option value="">Selecione a forma de pagamento</option>';
                    payment.disabled = false;
                    payment.required = true;
                    feeGroup.style.display = 'none';
                    feeInput.disabled = true;
                    feeInput.required = false;
                }
            }

            function toggleAllEntradaRows() {
                var master = document.getElementById('entrada_select_all');
                var items = document.querySelectorAll('.entrada-select-item');
                items.forEach(function (item) {
                    if (!item.disabled) {
                        item.checked = master.checked;
                    }
                });
                updateBulkRateControlsVisibility();
            }

            function updateBulkRateControlsVisibility() {
                var controls = document.getElementById('bulk_rate_controls');
                if (!controls) {
                    return;
                }

                var selectedCount = document.querySelectorAll('.entrada-select-item:checked').length;
                controls.classList.toggle('d-none', selectedCount === 0);
            }

            function saveSingleRate(rateInput) {
                // Impede salvar se o input está desabilitado (linha finalizada)
                if (rateInput.disabled) {
                    return;
                }

                var row = rateInput.closest('tr');
                var url = rateInput.getAttribute('data-url');
                var rateValue = rateInput.value;
                var originalValue = rateInput.getAttribute('data-original-value');

                if (originalValue !== null && parseFloat(originalValue) === parseFloat(rateValue)) {
                    return;
                }

                if (!rateValue || parseFloat(rateValue) <= 0) {
                    alert('Informe uma taxa válida.');
                    return;
                }

                var payload = new FormData();
                payload.append('_token', '{{ csrf_token() }}');
                payload.append('_method', 'PATCH');
                payload.append('exchange_rate', rateValue);

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: payload
                })
                    .then(function (response) {
                        if (response.ok) {
                            rateInput.setAttribute('data-original-value', rateValue);
                            location.reload();
                        } else {
                            alert('Erro ao atualizar a taxa.');
                        }
                    })
                    .catch(function () {
                        alert('Erro ao atualizar a taxa.');
                    });
            }

            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                    new bootstrap.Tooltip(el);
                });

                document.getElementById('deposit_currency').addEventListener('change', updateDepositPaymentMethod);
                updateDepositPaymentMethod();

                // Buscar cotação USD/BRL ao abrir o modal Depositar
                var depositModalEl = document.getElementById('depositModal');
                var depositFeeInput = document.getElementById('deposit_fee');
                var depositFeeStatus = document.getElementById('deposit_fee_status');
                var clientSpread = parseFloat('{{ $client->spread_points }}') || 0;
                var spreadValue = clientSpread * 0.01;

                function fetchUsdBrlRate() {
                    if (document.getElementById('deposit_currency').value !== 'BRL') {
                        return;
                    }
                    depositFeeStatus.textContent = 'Buscando cotação...';
                    depositFeeStatus.className = 'ms-auto small text-muted';

                    fetch('{{ route('admin.wallet.usd-brl-rate', [], false) }}', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data && data.success && data.rate) {
                                var base = parseFloat(data.rate);
                                var finalRate = base + spreadValue;
                                depositFeeInput.value = finalRate.toFixed(4);
                                depositFeeStatus.textContent = 'Base ' + base.toFixed(4) + ' + spread ' + spreadValue.toFixed(2);
                                depositFeeStatus.className = 'ms-auto small text-success';
                            } else {
                                depositFeeStatus.textContent = 'Falha ao obter cotação. Edite manualmente.';
                                depositFeeStatus.className = 'ms-auto small text-danger';
                            }
                        })
                        .catch(function () {
                            depositFeeStatus.textContent = 'Erro ao consultar cotação. Edite manualmente.';
                            depositFeeStatus.className = 'ms-auto small text-danger';
                        });
                }

                if (depositModalEl) {
                    depositModalEl.addEventListener('shown.bs.modal', fetchUsdBrlRate);
                }
                document.getElementById('deposit_currency').addEventListener('change', function () {
                    if (this.value === 'BRL') fetchUsdBrlRate();
                });

                var selectAll = document.getElementById('entrada_select_all');
                if (selectAll) {
                    selectAll.addEventListener('change', toggleAllEntradaRows);
                }

                document.querySelectorAll('.entrada-select-item').forEach(function (item) {
                    item.addEventListener('change', function (e) {
                        if (this.disabled) {
                            this.checked = false;
                            return;
                        }
                        updateBulkRateControlsVisibility();
                    });
                });

                updateBulkRateControlsVisibility();

                document.querySelectorAll('.js-rate-input').forEach(function (input) {
                    input.addEventListener('blur', function () {
                        saveSingleRate(input);
                    });
                });

                // ===== Cotação global da página =====
                // Busca a taxa USD/BRL no Investing + spread do cliente e aplica nos modais
                // de Comprar Dólar (taxa de compra) e Fechar em Dólar (taxa de conversão).
                window.WALLET_RATE = { base: null, final: null, spread: spreadValue };

                function applyRateToTarget(input, statusEl, base, finalRate) {
                    if (!input) return;
                    input.value = finalRate.toFixed(4);
                    if (statusEl) {
                        statusEl.textContent = 'Base ' + base.toFixed(4) + ' + spread ' + spreadValue.toFixed(2);
                        statusEl.className = 'ms-auto small text-success';
                    }
                    // Dispara input para sincronizar BRL/USD nos modais.
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }

                function fetchGlobalRate() {
                    var comprarTaxa = document.getElementById('comprar_taxa');
                    var comprarStatus = document.getElementById('comprar_taxa_status');
                    var fecharTaxa = document.getElementById('fechar_taxa');
                    var fecharStatus = document.getElementById('fechar_taxa_status');
                    var vendaTaxa = document.getElementById('venda_sell_rate');
                    var vendaStatus = document.getElementById('venda_taxa_status');
                    var bulkRateInput = document.querySelector('#bulk_rate_form input[name="exchange_rate"]');

                    [comprarStatus, fecharStatus, vendaStatus].forEach(function (el) {
                        if (el) { el.textContent = 'Buscando cotação...'; el.className = 'ms-auto small text-muted'; }
                    });

                    fetch('{{ route('admin.wallet.usd-brl-rate', [], false) }}', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data && data.success && data.rate) {
                                var base = parseFloat(data.rate);
                                var finalRate = base + spreadValue;
                                window.WALLET_RATE.base = base;
                                window.WALLET_RATE.final = finalRate;
                                applyRateToTarget(comprarTaxa, comprarStatus, base, finalRate);
                                applyRateToTarget(fecharTaxa, fecharStatus, base, finalRate);
                                applyRateToTarget(vendaTaxa, vendaStatus, base, finalRate);
                                if (bulkRateInput) bulkRateInput.value = finalRate.toFixed(4);
                            } else {
                                [comprarStatus, fecharStatus, vendaStatus].forEach(function (el) {
                                    if (el) { el.textContent = 'Falha ao obter cotação. Edite manualmente.'; el.className = 'ms-auto small text-danger'; }
                                });
                            }
                        })
                        .catch(function () {
                            [comprarStatus, fecharStatus, vendaStatus].forEach(function (el) {
                                if (el) { el.textContent = 'Erro ao consultar cotação. Edite manualmente.'; el.className = 'ms-auto small text-danger'; }
                            });
                        });
                }

                // Carrega ao abrir a tela e quando os modais forem reabertos.
                fetchGlobalRate();
                ['comprarDolarModal', 'fecharDolarModal', 'venderDolarModal'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.addEventListener('shown.bs.modal', fetchGlobalRate);
                });

                // Atualiza a página ao submeter os formulários dos modais
                document.querySelectorAll('#depositModal form, #withdrawModal form').forEach(function (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        var formEl = this;
                        var formData = new FormData(formEl);
                        fetch(formEl.action, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': formEl.querySelector('[name=_token]').value
                            },
                            body: formData
                        })
                            .then(function (response) {
                                if (response.ok) {
                                    location.reload();
                                } else {
                                    return response.json().then(function (data) {
                                        alert(data.message || 'Erro ao processar a operação.');
                                    });
                                }
                            })
                            .catch(function () {
                                alert('Erro ao processar a operação.');
                            });
                    });
                });
            });
        </script>

        <form method="GET" class="mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Moeda</label>
                    <select name="currency" class="form-select">
                        <option value="">Todas</option>
                        <option value="BRL" @if(request('currency') == 'BRL') selected @endif>BRL</option>
                        <option value="USD" @if(request('currency') == 'USD') selected @endif>USD</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Método</label>
                    <select name="payment_method" class="form-select">
                        <option value="">Todos</option>
                        <option value="pix" @if(request('payment_method') == 'pix') selected @endif>Pix</option>
                        <option value="dinheiro" @if(request('payment_method') == 'dinheiro') selected @endif>Dinheiro
                        </option>
                        <option value="efetivo" @if(request('payment_method') == 'efetivo') selected @endif>Efetivo
                        </option>
                        <option value="usdt" @if(request('payment_method') == 'usdt') selected @endif>USDT</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo</label>
                    <select name="type" class="form-select">
                        <option value="">Todos</option>
                        <option value="deposit" @if(request('type') == 'deposit') selected @endif>Depósito</option>
                        <option value="withdraw" @if(request('type') == 'withdraw') selected @endif>Saque</option>
                        <option value="exchange_in" @if(request('type') == 'exchange_in') selected @endif>Conversão
                            Entrada</option>
                        <option value="exchange_out" @if(request('type') == 'exchange_out') selected @endif>Conversão
                            Saída</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">De</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Até</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filtrar</button>
                    <a class="btn btn-outline-success"
                        title="Exportar para Excel/CSV (sem cores) com o mesmo período selecionado"
                        href="{{ route('admin.wallet.client.export', array_merge(['client' => $client->id], request()->only(['date_from','date_to','currency','payment_method','type']))) }}">
                        <i class="ri-file-excel-2-line"></i>
                    </a>
                </div>
            </div>
        </form>

        @php
            $filtered = $transactions;
            if (request('currency')) {
                $filtered = $filtered->where('currency', request('currency'));
            }
            if (request('payment_method')) {
                $filtered = $filtered->where('payment_method', request('payment_method'));
            }
            if (request('type')) {
                $filtered = $filtered->where('type', request('type'));
            }
            $filteredTotals = [
                'BRL' => $filtered->where('currency', 'BRL')->sum('amount'),
                'USD' => $filtered->where('currency', 'USD')->sum('amount'),
            ];
        @endphp

        @if(request('currency') || request('payment_method') || request('type'))
            <div class="row mb-2">
                <div class="col-xl-12">
                    <div class="card border border-primary">
                        <div class="card-body p-2">
                            <div class="row text-center">
                                <div class="col">
                                    <span class="fw-bold">Total BRL:</span> R$
                                    {{ number_format($filteredTotals['BRL'], 2, ',', '.') }}
                                </div>
                                <div class="col">
                                    <span class="fw-bold">Total USD:</span> US$
                                    {{ number_format($filteredTotals['USD'], 2, ',', '.') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @php
            $entradaBrl = $filtered->filter(function ($tx) {
                return $tx->type === 'deposit' && $tx->currency === 'BRL' && $tx->amount > 0;
            });

            $saidaUsd = $filtered->filter(function ($tx) {
                return $tx->currency === 'USD' && $tx->amount < 0;
            });

            $entradaUsd = $filtered->filter(function ($tx) {
                return $tx->currency === 'USD' && $tx->amount > 0;
            });
        @endphp

        <div class="row mt-4 g-3">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header border-0 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <h5 class="mb-0 text-uppercase">Entrada</h5>
                        <form id="bulk_rate_form" method="POST" action="{{ route('admin.wallet.update-rate-bulk') }}"
                            novalidate>
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="client_id" value="{{ $client->id }}">
                            <div id="bulk_rate_controls" class="d-flex align-items-center gap-2 d-none">
                                <button type="button"
                                    class="btn btn-sm {{ $prePurchaseSummary['has_open'] ? 'btn-success' : 'btn-primary' }}"
                                    id="btn-comprar-dolar"
                                    data-bs-toggle="modal" data-bs-target="#comprarDolarModal">
                                    <i class="ri-shopping-cart-2-line me-1"></i>Comprar DÓLAR
                                </button>
                                <button type="button" class="btn btn-sm btn-success" id="btn-fechar-dolar"
                                    data-bs-toggle="modal" data-bs-target="#fecharDolarModal">
                                    Fechar em dólar
                                </button>
                                <input type="number" step="0.000001" min="0.000001" name="exchange_rate"
                                    class="form-control form-control-sm" style="width: 140px" value="4.9311"
                                    placeholder="Nova taxa" required>
                                <button type="submit" class="btn btn-sm btn-primary">Aplicar taxa</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 40px">
                                            <input type="checkbox" id="entrada_select_all">
                                        </th>
                                        <th>Data</th>
                                        <th>Valor R$</th>
                                        <th>Taxa</th>
                                        <th>Valor U$</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($entradaBrl as $tx)
                                        @php
                                            $taxa = $tx->exchange_rate;
                                            $valorConvertido = null;
                                            $isLocked = in_array($tx->status, ['fechado', 'finalizado'], true);
                                            $brlPre = (float) ($tx->brl_pre_purchased ?? 0);
                                            $brlLivre = max(0, (float) $tx->amount - $brlPre);
                                            $hasPre = $brlPre > 0.005;

                                            if ($tx->converted_currency === 'USD' && $tx->converted_amount !== null) {
                                                $valorConvertido = $tx->converted_amount;
                                            } elseif ($taxa && $taxa > 0) {
                                                $valorConvertido = $tx->amount / $taxa;
                                            }

                                            // Determina a classe CSS baseada no status
                                            $rowClass = '';
                                            if ($tx->status === 'ambos_abertos') {
                                                $rowClass = 'table-warning'; // Amarelo
                                            } elseif ($tx->status === 'vendido') {
                                                $rowClass = 'table-success'; // Verde
                                            } elseif ($tx->status === 'comprado') {
                                                $rowClass = 'table-danger'; // Vermelho
                                            } elseif ($isLocked) {
                                                $rowClass = 'table-light'; // Branco
                                            }
                                            if ($hasPre && !$isLocked) {
                                                $rowClass = 'table-pre-purchased'; // Verde-claro indicando pré-compra
                                            }
                                        @endphp
                                        <tr class="{{ $rowClass }}"
                                            data-locked="{{ $isLocked ? '1' : '0' }}"
                                            data-pre-purchased="{{ number_format($brlPre, 2, '.', '') }}"
                                            data-brl-livre="{{ number_format($brlLivre, 2, '.', '') }}">
                                            <td>
                                                <input type="checkbox" class="entrada-select-item" form="bulk_rate_form"
                                                    name="transaction_ids[]" value="{{ $tx->id }}"
                                                    @if($isLocked) disabled @endif>
                                            </td>
                                            <td>
                                                {{ $tx->created_at->format('d/m/Y H:i') }}
                                                @if($hasPre)
                                                    <span class="badge bg-success-subtle text-success ms-1"
                                                        title="R$ {{ number_format($brlPre, 2, ',', '.') }} pré-comprado">
                                                        <i class="ri-shopping-cart-2-line"></i>
                                                        {{ number_format($brlPre, 2, ',', '.') }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="fw-bold text-success">{{ number_format($tx->amount, 2, ',', '.') }}</td>
                                            <td>
                                                <div class="d-flex align-items-center gap-1">
                                                    <input type="number" step="0.000001" min="0.000001"
                                                        value="{{ $taxa ? number_format($taxa, 6, '.', '') : '' }}"
                                                        class="form-control form-control-sm js-rate-input"
                                                        data-url="{{ route('admin.wallet.update-rate', $tx) }}"
                                                        data-original-value="{{ $taxa ? number_format($taxa, 6, '.', '') : '' }}"
                                                        style="min-width: 120px" required
                                                        @if($isLocked) disabled readonly @endif>
                                                </div>
                                            </td>
                                            <td>{{ $valorConvertido !== null ? number_format($valorConvertido, 2, ',', '.') : '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center">Sem registros.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header border-0">
                        <h5 class="mb-0 text-uppercase">Saída U$</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Valor U$</th>
                                        <th>Descrição</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($saidaUsd as $tx)
                                        <tr>
                                            <td>{{ $tx->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="fw-bold text-danger">{{ number_format(abs($tx->amount), 2, ',', '.') }}
                                            </td>
                                            <td>{{ $tx->description ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center">Sem registros.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header border-0 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <h5 class="mb-0 text-uppercase">Entrada U$</h5>
                        <div id="venda_pendente_controls" class="d-none">
                            <button type="button" class="btn btn-sm btn-danger"
                                data-bs-toggle="modal" data-bs-target="#venderDolarModal">
                                <i class="ri-money-dollar-circle-line me-1"></i>Vender (<span id="venda_count">0</span>)
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0" id="tabela_entrada_usd">
                                <thead>
                                    <tr>
                                        <th style="width: 36px">
                                            <input type="checkbox" id="venda_select_all">
                                        </th>
                                        <th>Data</th>
                                        <th>Valor U$</th>
                                        <th>Descrição</th>
                                        <th class="text-center" style="width: 36px">
                                            <i class="ri-information-line" title="Lucro/prejuízo deste fechamento"></i>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($entradaUsd as $tx)
                                        @php
                                            $isAguardando = $tx->status === 'aguardando_venda';
                                            $isVendida    = !$isAguardando && !empty($tx->treasury_sale_id);
                                            $rowClass = $isAguardando ? 'table-danger' : ($isVendida ? 'table-success' : '');
                                        @endphp
                                        <tr class="{{ $rowClass }}">
                                            <td class="text-center">
                                                @if($isAguardando)
                                                    <input type="checkbox" class="venda-select-item"
                                                        data-tx-id="{{ $tx->id }}"
                                                        data-usd="{{ number_format($tx->amount, 2, '.', '') }}">
                                                @endif
                                            </td>
                                            <td>{{ $tx->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="fw-bold {{ $isAguardando ? 'text-danger' : 'text-success' }}">
                                                {{ number_format($tx->amount, 2, ',', '.') }}
                                            </td>
                                            <td>
                                                {{ $tx->description ?? '-' }}
                                                @if($isAguardando)
                                                    <span class="badge bg-danger-subtle text-danger ms-1" title="Fechamento aguardando venda">
                                                        Aguardando venda
                                                    </span>
                                                @elseif($isVendida)
                                                    <span class="badge bg-success-subtle text-success ms-1" title="Vendida pelo caixa — finalizada">
                                                        <i class="ri-check-double-line"></i> Vendida
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @php
                                                    $pnlBrl = $tx->realized_pnl_brl;
                                                    $pnlUsd = $tx->realized_pnl_usd;
                                                @endphp
                                                @if($pnlBrl !== null)
                                                    @php
                                                        $pnlVal = (float) $pnlBrl;
                                                        $pnlColor = $pnlVal > 0 ? 'text-success' : ($pnlVal < 0 ? 'text-danger' : 'text-muted');
                                                        $pnlSign  = $pnlVal > 0 ? '+' : '';
                                                        $pnlLabel = $pnlVal > 0 ? 'Lucro' : ($pnlVal < 0 ? 'Prejuízo' : 'Sem PnL');
                                                    @endphp
                                                    <i class="ri-information-line {{ $pnlColor }}"
                                                        data-bs-toggle="tooltip" data-bs-placement="left"
                                                        title="{{ $pnlLabel }} (venda do caixa): {{ $pnlSign }}R$ {{ number_format($pnlVal, 2, ',', '.') }}"></i>
                                                @elseif($pnlUsd !== null)
                                                    @php
                                                        $pnlVal = (float) $pnlUsd;
                                                        $pnlColor = $pnlVal > 0 ? 'text-success' : ($pnlVal < 0 ? 'text-danger' : 'text-muted');
                                                        $pnlSign  = $pnlVal > 0 ? '+' : '';
                                                    @endphp
                                                    <i class="ri-information-line {{ $pnlColor }}"
                                                        data-bs-toggle="tooltip" data-bs-placement="left"
                                                        title="PnL pré-compra: {{ $pnlSign }}US$ {{ number_format($pnlVal, 4, ',', '.') }}"></i>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center">Sem registros.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <style>
        .wallet-compact .table th,
        .wallet-compact .table td {
            font-size: 0.85rem !important;
            padding: 0.18rem 0.35rem !important;
        }

        .wallet-compact .table td.fw-bold,
        .wallet-compact .table td.text-success,
        .wallet-compact .table td.text-danger {
            text-align: right !important;
            font-variant-numeric: tabular-nums;
        }

        /* Cores de status */
        .table-warning {
            background-color: #fff3cd !important;
        }

        .table-warning:hover {
            background-color: #ffe69c !important;
        }

        .table-success {
            background-color: #d1f2eb !important;
        }

        .table-success:hover {
            background-color: #b6e9dd !important;
        }

        .table-danger {
            background-color: #f8d7da !important;
        }

        .table-danger:hover {
            background-color: #f5c6cb !important;
        }

        .table-light {
            background-color: #f8f9fa !important;
            opacity: 0.7;
        }

        /* Input desabilitado em linha finalizada */
        .table-light input:disabled {
            background-color: #e9ecef !important;
            color: #6c757d !important;
            cursor: not-allowed !important;
            opacity: 0.5;
            pointer-events: none !important;
        }

        .table-light input:disabled::placeholder {
            color: #adb5bd !important;
        }

        .table-light input[type="checkbox"]:disabled {
            cursor: not-allowed !important;
            opacity: 0.3;
            pointer-events: none !important;
        }

        /* Linha de depósito que possui pré-compra parcial pelo dono */
        .table-pre-purchased {
            background-color: #d4edda !important;
        }
        .table-pre-purchased:hover {
            background-color: #c3e6cb !important;
        }
    </style>
    <!-- Modal Vender DÓLAR (entrega USD do caixa para o cliente, finalizando aguardando_venda) -->
    <div class="modal fade" id="venderDolarModal" tabindex="-1" aria-labelledby="venderDolarModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="venderDolarForm" method="POST" action="{{ route('admin.treasury.sell-pending') }}">
                    @csrf
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <input type="hidden" id="venda_transaction_ids" value="">
                    <div class="modal-header bg-danger-subtle">
                        <h5 class="modal-title" id="venderDolarModalLabel">
                            <i class="ri-money-dollar-circle-line me-1"></i>Vender DÓLAR ao cliente
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning py-2 small mb-3">
                            Esta ação <strong>entrega o USD</strong> ao cliente e consome o caixa próprio.
                            O R$ correspondente <strong>já foi consumido</strong> no fechamento.
                            <div class="mt-1" id="venda_disponivel_info"></div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label mb-1">Total selecionado</label>
                            <div class="form-control bg-light text-end fw-bold">
                                US$ <span id="venda_total_usd">0,00</span>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label for="venda_sell_rate" class="form-label mb-1 d-flex align-items-center">
                                Taxa de venda (R$/US$)
                                <span id="venda_taxa_status" class="ms-auto small text-muted"></span>
                            </label>
                            <input type="number" step="0.0001" min="0.0001" name="sell_rate" id="venda_sell_rate"
                                class="form-control" placeholder="ex: 5,2000" required>
                            <small class="text-muted">Cotação automaticamente atualizada com a base + spread do cliente
                                (<strong>{{ $client->spread_points }}</strong> pts = R$ {{ number_format($client->spread_points * 0.01, 2, ',', '.') }}).
                            </small>
                        </div>
                        <div class="mb-2">
                            <label class="form-label mb-1">Valor que o cliente está pagando (R$)</label>
                            <input type="text" id="venda_brl_total" class="form-control bg-light text-end" readonly>
                        </div>
                        <div class="mb-2">
                            <label class="form-label mb-1">Lucro estimado</label>
                            <div class="form-control bg-light text-end">
                                <span id="venda_pnl_estimado" class="text-muted">—</span>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label for="venda_notes" class="form-label mb-1">Observações (opcional)</label>
                            <textarea name="notes" id="venda_notes" rows="2" class="form-control" maxlength="500"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="ri-check-line me-1"></i>Confirmar venda
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Comprar Dólar (pré-compra pelo dono — não altera saldo do cliente) -->
    <div class="modal fade" id="comprarDolarModal" tabindex="-1" aria-labelledby="comprarDolarModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="comprarDolarForm" method="POST" action="{{ route('admin.wallet.pre-purchase-dollar') }}">
                    @csrf
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <div id="comprar_transaction_ids_container"></div>
                    <div class="modal-header bg-success-subtle">
                        <h5 class="modal-title" id="comprarDolarModalLabel">
                            <i class="ri-shopping-cart-2-line me-1"></i>Comprar DÓLAR (pré-compra)
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2 small mb-3">
                            Esta operação <strong>não altera o saldo do cliente</strong>. Apenas reserva
                            R$ dos depósitos selecionados (FIFO) e registra que o dono comprou USD a essa taxa.
                            O lucro/prejuízo será calculado quando o cliente fechar o BRL no fechamento real.
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-flex justify-content-between">
                                <span>Taxa de compra</span>
                                <small class="ms-auto small" id="comprar_taxa_status"></small>
                            </label>
                            <input type="number" step="0.000001" min="0.000001" name="exchange_rate"
                                id="comprar_taxa" class="form-control" required>
                            <small class="text-muted d-block mt-1">
                                Cotação base do Investing + spread do cliente
                                (<strong>{{ $client->spread_points }}</strong> pts = R$
                                {{ number_format($client->spread_points * 0.01, 2, ',', '.') }}).
                                <span id="comprar_disponivel" class="ms-2">Disp. seleção: R$ <span id="comprar_disp_valor">0,00</span></span>
                            </small>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Valor a comprar (R$)</label>
                                <input type="number" step="0.01" min="0.01" name="amount" id="comprar_brl"
                                    class="form-control" required>
                                <small class="text-muted">Será reservado dos depósitos abertos (FIFO).</small>
                            </div>
                            <div class="col-6">
                                <label class="form-label">USD comprado</label>
                                <input type="number" step="0.01" min="0.01" id="comprar_usd"
                                    class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <label class="form-label">Observação (opcional)</label>
                            <input type="text" name="description" id="comprar_descricao" class="form-control"
                                placeholder="Ex.: comprei dólar antecipado, USDT, etc.">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="ri-check-line me-1"></i>Confirmar compra
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal Comprar Dólar — sincronização BRL/USD/Taxa e cálculo do disponível na seleção.
        document.addEventListener('DOMContentLoaded', function () {
            var comprarBtn = document.getElementById('btn-comprar-dolar');
            var comprarModal = document.getElementById('comprarDolarModal');
            var comprarForm = document.getElementById('comprarDolarForm');
            var comprarTaxa = document.getElementById('comprar_taxa');
            var comprarBrl = document.getElementById('comprar_brl');
            var comprarUsd = document.getElementById('comprar_usd');
            var comprarDescricao = document.getElementById('comprar_descricao');
            var comprarDispValor = document.getElementById('comprar_disp_valor');

            var dispBrlSelecao = 0;
            var sync = false;

            function updateComprarDescricao() {
                if (!comprarDescricao) return;
                var b = parseFloat(comprarBrl.value);
                var t = parseFloat(comprarTaxa.value);
                var brlFmt = (b > 0 ? b : 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                var taxaFmt = (t > 0 ? t.toFixed(4) : 'N/A');
                comprarDescricao.value = 'Compra DÓLAR (R$ ' + brlFmt + ' @ ' + taxaFmt + ')';
            }

            function recalcUsd() {
                if (sync) return;
                var t = parseFloat(comprarTaxa.value);
                var b = parseFloat(comprarBrl.value);
                if (t > 0 && b > 0) {
                    sync = true;
                    comprarUsd.value = (b / t).toFixed(2);
                    sync = false;
                }
                updateComprarDescricao();
            }

            function recalcBrl() {
                if (sync) return;
                var t = parseFloat(comprarTaxa.value);
                var u = parseFloat(comprarUsd.value);
                if (t > 0 && u > 0) {
                    sync = true;
                    comprarBrl.value = (u * t).toFixed(2);
                    sync = false;
                }
                updateComprarDescricao();
            }

            if (comprarBrl) comprarBrl.addEventListener('input', recalcUsd);
            if (comprarUsd) comprarUsd.addEventListener('input', recalcBrl);
            if (comprarTaxa) comprarTaxa.addEventListener('input', recalcUsd);

            if (comprarBtn) {
                comprarBtn.addEventListener('click', function () {
                    var checked = Array.from(document.querySelectorAll('.entrada-select-item:checked'));
                    dispBrlSelecao = 0;

                    // Popular hidden inputs transaction_ids[] com a seleção atual
                    // (back-end usa esses IDs como pool da pré-compra; sem seleção cai em FIFO global).
                    var hiddenContainer = document.getElementById('comprar_transaction_ids_container');
                    if (hiddenContainer) hiddenContainer.innerHTML = '';

                    if (checked.length > 0) {
                        checked.forEach(function (cb) {
                            var row = cb.closest('tr');
                            var livre = parseFloat(row.getAttribute('data-brl-livre')) || 0;
                            dispBrlSelecao += livre;

                            if (hiddenContainer) {
                                var input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'transaction_ids[]';
                                input.value = cb.value;
                                hiddenContainer.appendChild(input);
                            }
                        });
                    } else {
                        // Sem seleção: pega TODOS os depósitos abertos (livre).
                        document.querySelectorAll('#bulk_rate_form ~ * .entrada-select-item, .entrada-select-item').forEach(function (cb) {
                            if (cb.disabled) return;
                            var row = cb.closest('tr');
                            var livre = parseFloat(row.getAttribute('data-brl-livre')) || 0;
                            dispBrlSelecao += livre;
                        });
                    }

                    dispBrlSelecao = Math.round(dispBrlSelecao * 100) / 100;
                    comprarDispValor.textContent = dispBrlSelecao.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    var bulkRateInput = document.querySelector('#bulk_rate_form input[name="exchange_rate"]');
                    var taxaSugerida = bulkRateInput ? parseFloat(bulkRateInput.value) : 0;
                    if (taxaSugerida > 0) comprarTaxa.value = taxaSugerida;

                    comprarBrl.max = dispBrlSelecao.toFixed(2);
                    if (!comprarBrl.value || parseFloat(comprarBrl.value) > dispBrlSelecao) {
                        comprarBrl.value = dispBrlSelecao.toFixed(2);
                    }
                    recalcUsd();
                    updateComprarDescricao();
                });
            }

            if (comprarForm) {
                comprarForm.addEventListener('submit', function (e) {
                    var t = parseFloat(comprarTaxa.value);
                    var b = parseFloat(comprarBrl.value);
                    if (!(t > 0)) { e.preventDefault(); alert('Informe uma taxa válida.'); return; }
                    if (!(b > 0)) { e.preventDefault(); alert('Informe o valor em R$.'); return; }
                    if (dispBrlSelecao > 0 && b > dispBrlSelecao + 0.005) {
                        e.preventDefault();
                        alert('Valor maior que o disponível (R$ ' +
                            dispBrlSelecao.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + ').');
                    }
                });
            }
        });
    </script>
    <!-- Modal Fechar em Dólar -->
    <div class="modal fade" id="fecharDolarModal" tabindex="-1" aria-labelledby="fecharDolarModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="fecharDolarForm" method="POST" action="{{ route('admin.wallet.fechamento-dolar') }}">
                    @csrf
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <input type="hidden" name="exchange_rate" id="fechar_exchange_rate">
                    <input type="hidden" name="transaction_ids" id="fechar_transaction_ids">
                    <div class="modal-header">
                        <h5 class="modal-title" id="fecharDolarModalLabel">Fechar em dólar</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="fechar_data" class="form-label">Data</label>
                            <input type="datetime-local" name="date" id="fechar_data" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="fechar_taxa" class="form-label d-flex justify-content-between">
                                <span>Taxa de conversão</span>
                                <small class="ms-auto small" id="fechar_taxa_status"></small>
                            </label>
                            <input type="number" step="0.000001" min="0.000001" id="fechar_taxa"
                                class="form-control" required>
                            <small class="text-muted d-block mt-1">
                                Cotação base do Investing + spread do cliente
                                (<strong>{{ $client->spread_points }}</strong> pts = R$
                                {{ number_format($client->spread_points * 0.01, 2, ',', '.') }}).
                                <span id="fechar_disponivel" class="ms-2"></span>
                            </small>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label for="fechar_brl" class="form-label">Valor a converter (R$)</label>
                                <input type="number" step="0.01" min="0.01" name="amount" id="fechar_brl"
                                    class="form-control" required>
                                <small class="text-muted">
                                    Se for menor que o total selecionado, o registro mais antigo será
                                    quebrado em dois (parte finalizada + sobra).
                                </small>
                            </div>
                            <div class="col-6">
                                <label for="fechar_usd" class="form-label">Valor convertido (US$)</label>
                                <input type="number" step="0.01" min="0.01" id="fechar_usd"
                                    class="form-control" required>
                                <small class="text-muted">
                                    Pode editar — atualiza R$ usando a taxa.
                                </small>
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <label for="fechar_descricao" class="form-label">Descrição</label>
                            <input type="text" name="description" id="fechar_descricao" class="form-control"
                                value="Fechamento Tx" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Confirmar Fechamento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Fechar em dólar: preencher modal com dados das linhas selecionadas + sincronização BRL/USD/Taxa
        document.addEventListener('DOMContentLoaded', function () {
            var fecharBtn = document.getElementById('btn-fechar-dolar');
            var fecharModal = document.getElementById('fecharDolarModal');
            var fecharForm = document.getElementById('fecharDolarForm');
            var fecharData = document.getElementById('fechar_data');
            var fecharTaxa = document.getElementById('fechar_taxa');
            var fecharBrl = document.getElementById('fechar_brl');
            var fecharUsd = document.getElementById('fechar_usd');
            var fecharDescricao = document.getElementById('fechar_descricao');
            var fecharExchangeRate = document.getElementById('fechar_exchange_rate');
            var fecharTransactionIds = document.getElementById('fechar_transaction_ids');
            var fecharDisponivel = document.getElementById('fechar_disponivel');

            var totalDisponivelBrl = 0;
            var syncing = false; // evita loop entre os listeners

            function parseNumber(v) {
                if (typeof v !== 'string') v = String(v);
                v = v.replace(/\s/g, '').replace(/\./g, '').replace(',', '.');
                var n = parseFloat(v);
                return isNaN(n) ? 0 : n;
            }

            function updateFecharDescricao() {
                var brl = parseFloat(fecharBrl.value);
                var taxa = parseFloat(fecharTaxa.value);

                fecharDescricao.value = 'Fechamento Tx (R$ ' +
                    (brl > 0 ? brl : totalDisponivelBrl).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) +
                    ' @ ' + (taxa > 0 ? taxa.toFixed(4) : 'N/A') + ')';
            }

            function recalcFromBrl() {
                if (syncing) return;
                var taxa = parseFloat(fecharTaxa.value);
                var brl = parseFloat(fecharBrl.value);
                if (taxa > 0 && brl > 0) {
                    syncing = true;
                    fecharUsd.value = (brl / taxa).toFixed(2);
                    syncing = false;
                }
                updateFecharDescricao();
            }

            function recalcFromUsd() {
                if (syncing) return;
                var taxa = parseFloat(fecharTaxa.value);
                var usd = parseFloat(fecharUsd.value);
                if (taxa > 0 && usd > 0) {
                    syncing = true;
                    fecharBrl.value = (usd * taxa).toFixed(2);
                    syncing = false;
                }
                updateFecharDescricao();
            }

            function onTaxaChange() {
                // Ao mudar a taxa, recalcula USD a partir do BRL atual.
                recalcFromBrl();
                updateFecharDescricao();
            }

            fecharBrl.addEventListener('input', recalcFromBrl);
            fecharUsd.addEventListener('input', recalcFromUsd);
            fecharTaxa.addEventListener('input', onTaxaChange);

            if (fecharBtn && fecharModal) {
                fecharBtn.addEventListener('click', function () {
                    var checked = Array.from(document.querySelectorAll('.entrada-select-item:checked'));
                    if (checked.length === 0) {
                        alert('Selecione pelo menos uma entrada para fechar.');
                        return;
                    }

                    // Pega taxa do controle de "Aplicar taxa" (acima da tabela), com fallback.
                    var bulkRateInput = document.querySelector('#bulk_rate_form input[name="exchange_rate"]');
                    var taxaSugerida = bulkRateInput ? parseFloat(bulkRateInput.value) : 0;

                    var totalBrl = 0;
                    var maxData = null;

                    checked.forEach(function (cb) {
                        var row = cb.closest('tr');
                        var valorTxt = row.querySelector('.fw-bold.text-success').textContent;
                        var valor = parseNumber(valorTxt);
                        totalBrl += valor;

                        // Pega a data (col 2: data agora — checkbox=1, data=2)
                        var dataStr = row.querySelector('td:nth-child(2)').textContent.trim();
                        var match = dataStr.match(/(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2})/);
                        if (match) {
                            var dt = new Date(match[3] + '-' + match[2] + '-' + match[1] + 'T' + match[4] + ':' + match[5]);
                            if (!maxData || dt > maxData) maxData = dt;
                        }

                        // Se nenhuma taxa sugerida ainda, tenta pegar da própria linha.
                        if (!taxaSugerida || taxaSugerida <= 0) {
                            var rowRate = parseFloat(row.querySelector('.js-rate-input').value);
                            if (rowRate > 0) taxaSugerida = rowRate;
                        }
                    });

                    totalDisponivelBrl = Math.round(totalBrl * 100) / 100;
                    fecharDisponivel.textContent =
                        'Disponível: R$ ' + totalDisponivelBrl.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    fecharBrl.max = totalDisponivelBrl.toFixed(2);
                    fecharBrl.value = totalDisponivelBrl.toFixed(2);
                    fecharTaxa.value = (taxaSugerida && taxaSugerida > 0) ? taxaSugerida : '';

                    recalcFromBrl();

                    fecharTransactionIds.value = checked.map(function (cb) { return cb.value; }).join(',');

                    if (maxData) {
                        // Ajusta para timezone local antes do toISOString
                        var tzOffset = maxData.getTimezoneOffset() * 60000;
                        fecharData.value = new Date(maxData - tzOffset).toISOString().slice(0, 16);
                    } else {
                        fecharData.value = '';
                    }

                    updateFecharDescricao();
                });
            }

            fecharForm.addEventListener('submit', function (e) {
                var taxa = parseFloat(fecharTaxa.value);
                var brl = parseFloat(fecharBrl.value);

                if (!(taxa > 0)) {
                    e.preventDefault();
                    alert('Informe uma taxa válida.');
                    return;
                }
                if (!(brl > 0)) {
                    e.preventDefault();
                    alert('Informe o valor em R$ a converter.');
                    return;
                }
                if (totalDisponivelBrl > 0 && brl > totalDisponivelBrl + 0.005) {
                    e.preventDefault();
                    alert('O valor em R$ não pode ser maior que o total disponível selecionado (R$ ' +
                        totalDisponivelBrl.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + ').');
                    return;
                }

                // Copia a taxa para o hidden enviado ao backend.
                fecharExchangeRate.value = taxa;
            });

            // ============================================================
            // VENDER USD (caixa) — finaliza transações 'aguardando_venda'.
            // ============================================================
            (function () {
                var venderModalEl = document.getElementById('venderDolarModal');
                if (!venderModalEl) return;

                var checkboxes = function () { return Array.from(document.querySelectorAll('.venda-select-item')); };
                var controls   = document.getElementById('venda_pendente_controls');
                var countLbl   = document.getElementById('venda_count');
                var selectAll  = document.getElementById('venda_select_all');
                var totalUsdEl = document.getElementById('venda_total_usd');
                var taxaEl     = document.getElementById('venda_sell_rate');
                var brlEl      = document.getElementById('venda_brl_total');
                var pnlEl      = document.getElementById('venda_pnl_estimado');
                var idsHidden  = document.getElementById('venda_transaction_ids');
                var custoMedio = parseFloat('{{ $treasuryClientSummary['custo_medio_cliente'] ?? '' }}') || 0;
                var custoMedioGeral = parseFloat('{{ $treasurySummary['custo_medio'] ?? '' }}') || 0;
                var usdDisponivelCliente = parseFloat('{{ $treasuryClientSummary['usd_em_caixa_cliente'] ?? 0 }}') || 0;
                var usdDisponivelGeral = parseFloat('{{ $treasurySummary['usd_em_caixa'] ?? 0 }}') || 0;
                var disponivelInfo = document.getElementById('venda_disponivel_info');

                function updateControls() {
                    var selecionados = checkboxes().filter(function (cb) { return cb.checked; });
                    countLbl.textContent = selecionados.length;
                    if (selecionados.length > 0) controls.classList.remove('d-none');
                    else controls.classList.add('d-none');
                }

                function totalUsdSelecionado() {
                    return checkboxes()
                        .filter(function (cb) { return cb.checked; })
                        .reduce(function (acc, cb) { return acc + parseFloat(cb.dataset.usd || 0); }, 0);
                }

                function ids() {
                    return checkboxes()
                        .filter(function (cb) { return cb.checked; })
                        .map(function (cb) { return cb.dataset.txId; });
                }

                function recalc() {
                    var usd = totalUsdSelecionado();
                    var taxa = parseFloat(taxaEl.value) || 0;
                    var brl = usd * taxa;
                    totalUsdEl.textContent = usd.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    brlEl.value = brl > 0 ? brl.toFixed(2) : '';

                    // Estimativa de PnL em USD: lucro R$ convertido na taxa da venda.
                    // pnl_usd = usd * (1 - custo/taxa)
                    var custo = custoMedio > 0 ? custoMedio : custoMedioGeral;
                    if (custo > 0 && taxa > 0 && usd > 0) {
                        var pnlBrl = (taxa - custo) * usd;
                        var pnlUsd = pnlBrl / taxa;
                        var sign = pnlUsd >= 0 ? '+' : '';
                        pnlEl.textContent = sign + 'US$ ' + pnlUsd.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) +
                                            ' (custo médio R$ ' + custo.toLocaleString('pt-BR', { minimumFractionDigits: 4, maximumFractionDigits: 4 }) + ')';
                        pnlEl.className = pnlUsd >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold';
                    } else {
                        pnlEl.textContent = '—';
                        pnlEl.className = 'text-muted';
                    }

                    idsHidden.value = ids().join(',');
                }

                checkboxes().forEach(function (cb) {
                    cb.addEventListener('change', function () {
                        updateControls();
                        recalc();
                    });
                });

                if (selectAll) {
                    selectAll.addEventListener('change', function () {
                        checkboxes().forEach(function (cb) { cb.checked = selectAll.checked; });
                        updateControls();
                        recalc();
                    });
                }

                if (taxaEl) taxaEl.addEventListener('input', recalc);

                venderModalEl.addEventListener('show.bs.modal', function () {
                    if (disponivelInfo) {
                        disponivelInfo.innerHTML =
                            'Caixa <strong>deste cliente</strong>: US$ ' + usdDisponivelCliente.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) +
                            ' | Caixa <strong>total</strong>: US$ ' + usdDisponivelGeral.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                    }
                    recalc();
                });

                // Sincroniza ids no submit final (caso o usuário mude algo depois de abrir).
                document.getElementById('venderDolarForm').addEventListener('submit', function (e) {
                    var selecionados = ids();
                    if (selecionados.length === 0) {
                        e.preventDefault();
                        alert('Selecione pelo menos uma transação aguardando venda.');
                        return;
                    }
                    var taxa = parseFloat(taxaEl.value);
                    if (!(taxa > 0)) {
                        e.preventDefault();
                        alert('Informe uma taxa de venda válida.');
                        return;
                    }
                    // Substitui o hidden por inputs separados para casar com o validator (array).
                    var form = e.target;
                    form.querySelectorAll('input[name="transaction_ids[]"]').forEach(function (n) { n.remove(); });
                    selecionados.forEach(function (id) {
                        var i = document.createElement('input');
                        i.type = 'hidden';
                        i.name = 'transaction_ids[]';
                        i.value = id;
                        form.appendChild(i);
                    });
                });
            })();
        });
    </script>
@endsection