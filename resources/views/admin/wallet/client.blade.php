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
            {{-- @if(session('success'))
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
            @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show mt-2 mb-0" role="alert">
                <i class="ri-alert-line me-1"></i>{{ session('warning') }}
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
            @endif --}}

            {{-- Linha fina mostrando o caixa USD da empresa (global e do cliente) --}}
            @php
                $caixaTotal = (float) ($treasurySummary['usd_em_caixa'] ?? 0);
                $caixaCliente = (float) ($treasuryClientSummary['usd_em_caixa_cliente'] ?? 0);
                $custoMedioCli = $treasuryClientSummary['custo_medio_cliente'] ?? null;
                $pnlAcumUsd = (float) ($treasurySummary['pnl_acumulado_usd'] ?? 0);
            @endphp
            <div class="row mt-2">
                <div class="col-12">
                    {{-- <div
                        class="alert alert-secondary py-2 px-3 mb-0 d-flex flex-wrap align-items-center justify-content-between gap-3 small">
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
                            <strong
                                class="{{ $pnlAcumUsd > 0 ? 'text-success' : ($pnlAcumUsd < 0 ? 'text-danger' : 'text-muted') }}">
                                {{ $pnlAcumUsd >= 0 ? '+' : '' }}US$ {{ number_format($pnlAcumUsd, 2, ',', '.') }}
                            </strong>
                        </div>
                    </div> --}}
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-xl-12">
                    <div class="card crm-widget">
                        <div class="card-body p-0">
                            <div class="row row-cols-xxl-4 row-cols-md-3 row-cols-1 g-0 text-center">
                                <div class="col">
                                    <div class="py-4 px-3">
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
                                        <h5 class="text-muted text-uppercase fs-13">Devo ao Cliente (R$)</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <i class="ri-hand-coin-line display-6 text-muted cfs-22"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h2 class="mb-0 cfs-22 text-danger">
                                                    R$
                                                    {{ number_format($prePurchaseSummary['brl_em_aberto'], 2, ',', '.') }}
                                                </h2>
                                                <small class="text-muted">Disp. p/ comprar: R$
                                                    {{ number_format($brlAvailableForPrePurchase, 2, ',', '.') }}</small>
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
                                                <h2
                                                    class="mb-0 cfs-22 {{ $pnlUsdCard >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ $pnlUsdCard >= 0 ? '+' : '' }}US$
                                                    {{ number_format($pnlUsdCard, 4, ',', '.') }}
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
                            <button type="submit" class="btn btn-success" id="depositModalSubmitBtn">Depositar</button>
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
                            <div class="alert alert-info py-2 mb-3">
                                <small>
                                    <i class="ri-information-line"></i>
                                    Saque sempre em <strong>Dólar (USD)</strong>. Será debitado do saldo USD do cliente.<br>
                                    Saldo USD disponível:
                                    <strong>U$ {{ number_format($balances['USD'] ?? 0, 2, ',', '.') }}</strong>
                                </small>
                            </div>
                            <div class="mb-3">
                                <label for="withdraw_payment_method" class="form-label">Tipo de envio</label>
                                <select name="payment_method" id="withdraw_payment_method" class="form-select" required
                                    onchange="updateWithdrawDescription()">
                                    <option value="efetivo">Efetivo (papel)</option>
                                    <option value="usdt">USDT</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="withdraw_amount" class="form-label">Valor (US$)</label>
                                <input type="number" step="0.01" min="0.01" name="amount" id="withdraw_amount"
                                    class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="withdraw_description" class="form-label">Descrição</label>
                                <input type="text" name="description" id="withdraw_description" class="form-control"
                                    value="Efetivo Enviado" maxlength="255">
                                <small class="text-muted">Auto-preenchido conforme o tipo. Pode ser editado
                                    livremente.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Sacar</button>
                        </div>
                    </form>
                    <script>
                        function updateWithdrawDescription() {
                            var m = document.getElementById('withdraw_payment_method').value;
                            var d = document.getElementById('withdraw_description');
                            // só sobrescreve se ainda estiver com um dos defaults
                            if (d.value === '' || d.value === 'Efetivo Enviado' || d.value === 'USDT Enviado') {
                                d.value = m === 'usdt' ? 'USDT Enviado' : 'Efetivo Enviado';
                            }
                        }
                    </script>
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
                    var vendaTaxa = document.getElementById('venda_ant_taxa');
                    var vendaStatus = document.getElementById('venda_ant_taxa_status');
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
                ['comprarDolarModal', 'fecharDolarModal', 'venderDolarAntecipadoModal'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.addEventListener('shown.bs.modal', fetchGlobalRate);
                });

                // Atualiza a página ao submeter os formulários dos modais
                document.querySelectorAll('#depositModal form, #withdrawModal form').forEach(function (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        var formEl = this;
                        var submitBtn = formEl.querySelector('[type="submit"]');
                        var isDepositForm = formEl.closest('#depositModal') !== null;

                        // Bloqueia o botão durante a submissão
                        submitBtn.disabled = true;
                        var originalText = submitBtn.textContent;
                        submitBtn.textContent = isDepositForm ? 'Processando...' : 'Enviando...';

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
                                        // Desbloqueia se houver erro
                                        submitBtn.disabled = false;
                                        submitBtn.textContent = originalText;
                                    });
                                }
                            })
                            .catch(function () {
                                alert('Erro ao processar a operação.');
                                // Desbloqueia se houver erro
                                submitBtn.disabled = false;
                                submitBtn.textContent = originalText;
                            });
                    });
                });
            });
        </script>

        <form method="GET" class="mb-3">
            <div class="row g-2 align-items-end">
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
                    <a class="btn btn-outline-success" title="Exportar para Excel (.xlsx) usando o template oficial"
                        href="{{ route('admin.wallet.client.export-xlsx', array_merge(['client' => $client->id], request()->only(['date_from', 'date_to', 'currency', 'payment_method', 'type']))) }}">
                        <i class="ri-file-excel-2-line"></i>
                    </a>
                    <a class="btn btn-outline-danger" title="Exportar para PDF (mesmo layout)"
                        href="{{ route('admin.wallet.client.export-pdf', array_merge(['client' => $client->id], request()->only(['date_from', 'date_to', 'currency', 'payment_method', 'type']))) }}">
                        <i class="ri-file-pdf-2-line"></i>
                    </a>
                </div>
                <div class="col-6 d-flex flex-wrap justify-content-md-end align-items-center gap-2">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal"
                        data-bs-target="#depositModal">Depositar</button>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                        data-bs-target="#withdrawModal">Sacar</button>

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
                            <div id="bulk_rate_controls" class="d-flex align-items-center gap-2 d-none flex-wrap">
                                <button type="button" class="btn btn-sm btn-success" id="btn-comprar-dolar"
                                    title="Pré-compra: dono compra USD a uma taxa (custo)" data-bs-toggle="modal"
                                    data-bs-target="#comprarDolarModal">
                                    <i class="ri-arrow-down-circle-line me-1"></i>Comprar DÓLAR
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" id="btn-vender-dolar-ant"
                                    title="Vende USD ao cliente na taxa informada (cria Entrada U$ imediatamente)"
                                    data-bs-toggle="modal" data-bs-target="#venderDolarAntecipadoModal">
                                    <i class="ri-arrow-up-circle-line me-1"></i>Vender DÓLAR
                                </button>
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
                                            $brlSold = (float) ($tx->brl_pre_sold ?? 0);
                                            $brlLivreCompra = max(0, (float) $tx->amount - $brlPre);
                                            $brlLivreVenda = max(0, (float) $tx->amount - $brlSold);
                                            $hasPre = $brlPre > 0.005;
                                            $hasSold = $brlSold > 0.005;
                                            $bothComplete = $hasPre && $hasSold
                                                && abs($brlPre - (float) $tx->amount) < 0.01
                                                && abs($brlSold - (float) $tx->amount) < 0.01;

                                            $lotesPre = $prePurchasesByDeposit[$tx->id] ?? collect();
                                            $lotesSell = $preSellsByDeposit[$tx->id] ?? collect();
                                            $taxaMediaPre = $lotesPre->sum('brl_remaining') > 0
                                                ? $lotesPre->sum('brl_remaining') / max(0.0001, $lotesPre->sum('usd_remaining'))
                                                : null;
                                            $taxaMediaSell = $lotesSell->sum('brl_remaining') > 0
                                                ? $lotesSell->sum('brl_remaining') / max(0.0001, $lotesSell->sum('usd_remaining'))
                                                : null;

                                            if ($tx->converted_currency === 'USD' && $tx->converted_amount !== null) {
                                                $valorConvertido = $tx->converted_amount;
                                            } elseif ($taxa && $taxa > 0) {
                                                $valorConvertido = $tx->amount / $taxa;
                                            }

                                            // Cor da linha — prioridade:
                                            //   completo (compra+venda cobrindo tudo) → azul "pronto p/ fechar"
                                            //   só compra → verde
                                            //   só venda → vermelho
                                            //   ambos parciais → amarelo
                                            //   sem nada → padrão
                                            $rowClass = '';
                                            if ($isLocked) {
                                                $rowClass = 'table-light';
                                            } elseif ($bothComplete) {
                                                $rowClass = 'table-info-pronto';
                                            } elseif ($hasPre && $hasSold) {
                                                $rowClass = 'table-warning';
                                            } elseif ($hasPre) {
                                                $rowClass = 'table-pre-purchased';
                                            } elseif ($hasSold) {
                                                $rowClass = 'table-pre-sold';
                                            }
                                        @endphp
                                        <tr class="{{ $rowClass }}" data-locked="{{ $isLocked ? '1' : '0' }}"
                                            data-pre-purchased="{{ number_format($brlPre, 2, '.', '') }}"
                                            data-pre-sold="{{ number_format($brlSold, 2, '.', '') }}"
                                            data-brl-livre-compra="{{ number_format($brlLivreCompra, 2, '.', '') }}"
                                            data-brl-livre-venda="{{ number_format($brlLivreVenda, 2, '.', '') }}">
                                            <td>
                                                <input type="checkbox" class="entrada-select-item" form="bulk_rate_form"
                                                    name="transaction_ids[]" value="{{ $tx->id }}" @if($isLocked) disabled
                                                    @endif>
                                            </td>
                                            <td>
                                                <div>{{ $tx->created_at->format('d/m/Y H:i') }}</div>
                                                @if($hasPre)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle"
                                                        title="Comprou R$ {{ number_format($brlPre, 2, ',', '.') }} @ {{ $taxaMediaPre ? number_format($taxaMediaPre, 4, ',', '.') : '' }}">
                                                        <i class="ri-arrow-down-circle-line"></i>
                                                        C: {{ number_format($brlPre, 2, ',', '.') }}
                                                        @if($taxaMediaPre)<small>@
                                                        {{ number_format($taxaMediaPre, 4, ',', '.') }}</small>@endif
                                                    </span>
                                                @endif
                                                @if($hasSold)
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"
                                                        title="Vendeu R$ {{ number_format($brlSold, 2, ',', '.') }} @ {{ $taxaMediaSell ? number_format($taxaMediaSell, 4, ',', '.') : '' }}">
                                                        <i class="ri-arrow-up-circle-line"></i>
                                                        V: {{ number_format($brlSold, 2, ',', '.') }}
                                                        @if($taxaMediaSell)<small>@
                                                        {{ number_format($taxaMediaSell, 4, ',', '.') }}</small>@endif
                                                    </span>
                                                @endif
                                                @if($bothComplete)
                                                    <span class="badge bg-info text-white"
                                                        title="Pronto para fechar — compra e venda já registradas">
                                                        <i class="ri-check-double-line"></i> pronto
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
                                                        style="min-width: 110px" required @if($isLocked) disabled readonly
                                                        @endif>
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
                        <small class="text-muted">Operações finalizadas — visão do cliente</small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0" id="tabela_entrada_usd">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Valor U$</th>
                                        <th>Taxa venda</th>
                                        <th>Descrição</th>
                                        <th class="text-center" style="width: 36px">
                                            <i class="ri-information-line"
                                                title="PnL deste fechamento (apenas para o admin)"></i>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($entradaUsd as $tx)
                                        <tr>
                                            <td>{{ $tx->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="fw-bold text-success">
                                                {{ number_format($tx->amount, 2, ',', '.') }}
                                            </td>
                                            <td>
                                                @if($tx->exchange_rate)
                                                    {{ number_format($tx->exchange_rate, 4, ',', '.') }}
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $tx->description ?? '-' }}</td>
                                            <td class="text-center">
                                                @php
                                                    $pnlBrl = $tx->realized_pnl_brl;
                                                    $pnlUsd = $tx->realized_pnl_usd;
                                                @endphp
                                                @if($pnlBrl !== null || $pnlUsd !== null)
                                                    @php
                                                        $vBrl = (float) ($pnlBrl ?? 0);
                                                        $vUsd = (float) ($pnlUsd ?? 0);
                                                        $color = $vBrl > 0 ? 'text-success' : ($vBrl < 0 ? 'text-danger' : 'text-muted');
                                                        $sign = $vBrl > 0 ? '+' : '';
                                                        $tip = 'PnL: ' . $sign . 'R$ ' . number_format($vBrl, 2, ',', '.') .
                                                            ' (' . $sign . 'US$ ' . number_format($vUsd, 4, ',', '.') . ')';
                                                    @endphp
                                                    <i class="ri-information-line {{ $color }}" data-bs-toggle="tooltip"
                                                        data-bs-placement="left" title="{{ $tip }}"></i>
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

        /* Pré-venda registrada */
        .table-pre-sold {
            background-color: #f8d7da !important;
        }

        .table-pre-sold:hover {
            background-color: #f5c6cb !important;
        }

        /* Pré-compra E pré-venda completas (pronto p/ fechar) */
        .table-info-pronto {
            background-color: #cfe9fb !important;
            border-left: 3px solid #0d6efd !important;
        }

        .table-info-pronto:hover {
            background-color: #b6dffb !important;
        }

        /* Badges nas linhas: separador visual */
        td .badge {
            margin-left: 4px;
            font-weight: 500;
        }

        td .badge small {
            font-weight: 400;
            opacity: .85;
            margin-left: 2px;
        }
    </style>
    <!-- Modal Vender DÓLAR ANTECIPADO (cria lote de pré-venda — fixa a taxa cobrada do cliente) -->
    <div class="modal fade" id="venderDolarAntecipadoModal" tabindex="-1" aria-labelledby="venderDolarAntecipadoLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="venderDolarAntecipadoForm" method="POST" action="{{ route('admin.wallet.pre-sell-dollar') }}">
                    @csrf
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <div id="vender_ant_transaction_ids_container"></div>
                    <div class="modal-header bg-danger-subtle">
                        <h5 class="modal-title" id="venderDolarAntecipadoLabel">
                            <i class="ri-arrow-up-circle-line me-1"></i>Vender DÓLAR (pré-venda)
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2 small mb-3">
                            Esta operação <strong>não altera o saldo do cliente</strong>. Apenas reserva R$
                            dos depósitos selecionados (FIFO) e fixa a <strong>taxa que será cobrada</strong>
                            do cliente no fechamento. O lucro/prejuízo aparece quando o R$ for fechado.
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-flex justify-content-between">
                                <span>Taxa de venda ao cliente</span>
                                <small class="ms-auto small" id="venda_ant_taxa_status"></small>
                            </label>
                            <input type="number" step="0.000001" min="0.000001" name="sell_rate" id="venda_ant_taxa"
                                class="form-control" required>
                            <small class="text-muted d-block mt-1">
                                Cotação base do Investing + spread do cliente
                                (<strong>{{ $client->spread_points }}</strong> pts).
                                <span id="venda_ant_disponivel" class="ms-2">Disp. seleção: R$ <span
                                        id="venda_ant_disp_valor">0,00</span></span>
                            </small>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Valor a vender (R$)</label>
                                <input type="number" step="0.01" min="0.01" name="amount" id="venda_ant_brl"
                                    class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">USD que será entregue</label>
                                <input type="number" step="0.01" min="0.01" id="venda_ant_usd" class="form-control"
                                    required>
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <label class="form-label">Observação (opcional)</label>
                            <input type="text" name="description" id="venda_ant_descricao" class="form-control"
                                placeholder="Ex.: vendi dólar antecipado, etc.">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="ri-check-line me-1"></i>Confirmar pré-venda
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal Vender DÓLAR antecipado — espelho do Comprar.
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('btn-vender-dolar-ant');
            var modal = document.getElementById('venderDolarAntecipadoModal');
            var form = document.getElementById('venderDolarAntecipadoForm');
            var taxa = document.getElementById('venda_ant_taxa');
            var brl = document.getElementById('venda_ant_brl');
            var usd = document.getElementById('venda_ant_usd');
            var desc = document.getElementById('venda_ant_descricao');
            var dispEl = document.getElementById('venda_ant_disp_valor');

            var disp = 0; var sync = false;

            function updateDesc() {
                if (!desc) return;
                var b = parseFloat(brl.value), t = parseFloat(taxa.value);
                desc.value = 'Venda DÓLAR (R$ ' +
                    (b > 0 ? b : 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) +
                    ' @ ' + (t > 0 ? t.toFixed(4) : 'N/A') + ')';
            }
            function recalcUsd() {
                if (sync) return;
                var t = parseFloat(taxa.value), b = parseFloat(brl.value);
                if (t > 0 && b > 0) { sync = true; usd.value = (b / t).toFixed(2); sync = false; }
                updateDesc();
            }
            function recalcBrl() {
                if (sync) return;
                var t = parseFloat(taxa.value), u = parseFloat(usd.value);
                if (t > 0 && u > 0) { sync = true; brl.value = (u * t).toFixed(2); sync = false; }
                updateDesc();
            }
            if (brl) brl.addEventListener('input', recalcUsd);
            if (usd) usd.addEventListener('input', recalcBrl);
            if (taxa) taxa.addEventListener('input', recalcUsd);

            if (btn) {
                btn.addEventListener('click', function () {
                    var checked = Array.from(document.querySelectorAll('.entrada-select-item:checked'));
                    disp = 0;
                    var hidden = document.getElementById('vender_ant_transaction_ids_container');
                    if (hidden) hidden.innerHTML = '';

                    if (checked.length > 0) {
                        checked.forEach(function (cb) {
                            var row = cb.closest('tr');
                            disp += parseFloat(row.getAttribute('data-brl-livre-venda')) || 0;
                            if (hidden) {
                                var i = document.createElement('input');
                                i.type = 'hidden'; i.name = 'transaction_ids[]'; i.value = cb.value;
                                hidden.appendChild(i);
                            }
                        });
                    } else {
                        document.querySelectorAll('.entrada-select-item').forEach(function (cb) {
                            if (cb.disabled) return;
                            disp += parseFloat(cb.closest('tr').getAttribute('data-brl-livre-venda')) || 0;
                        });
                    }
                    disp = Math.round(disp * 100) / 100;
                    dispEl.textContent = disp.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    var bulkRateInput = document.querySelector('#bulk_rate_form input[name="exchange_rate"]');
                    var sug = bulkRateInput ? parseFloat(bulkRateInput.value) : 0;
                    if (sug > 0) taxa.value = sug;

                    brl.max = disp.toFixed(2);
                    if (!brl.value || parseFloat(brl.value) > disp) brl.value = disp.toFixed(2);
                    recalcUsd();
                });
            }
            if (form) {
                form.addEventListener('submit', function (e) {
                    var t = parseFloat(taxa.value), b = parseFloat(brl.value);
                    if (!(t > 0)) { e.preventDefault(); alert('Informe uma taxa válida.'); return; }
                    if (!(b > 0)) { e.preventDefault(); alert('Informe o valor em R$.'); return; }
                    if (disp > 0 && b > disp + 0.005) {
                        e.preventDefault();
                        alert('Valor maior que o disponível para pré-venda (R$ ' +
                            disp.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + ').');
                    }
                });
            }
        });
    </script>

    <!-- Modal Comprar Dólar (pré-compra pelo dono — não altera saldo do cliente) -->
    <div class="modal fade" id="comprarDolarModal" tabindex="-1" aria-labelledby="comprarDolarModalLabel"
        aria-hidden="true">
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
                            <input type="number" step="0.000001" min="0.000001" name="exchange_rate" id="comprar_taxa"
                                class="form-control" required>
                            <small class="text-muted d-block mt-1">
                                Cotação base do Investing + spread do cliente
                                (<strong>{{ $client->spread_points }}</strong> pts = R$
                                {{ number_format($client->spread_points * 0.01, 2, ',', '.') }}).
                                <span id="comprar_disponivel" class="ms-2">Disp. seleção: R$ <span
                                        id="comprar_disp_valor">0,00</span></span>
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
                                <input type="number" step="0.01" min="0.01" id="comprar_usd" class="form-control" required>
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
                            var livre = parseFloat(row.getAttribute('data-brl-livre-compra')) || 0;
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
                            var livre = parseFloat(row.getAttribute('data-brl-livre-compra')) || 0;
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
                            <input type="number" step="0.000001" min="0.000001" id="fechar_taxa" class="form-control"
                                required>
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
                                <input type="number" step="0.01" min="0.01" id="fechar_usd" class="form-control" required>
                                <small class="text-muted">
                                    Pode editar — atualiza R$ usando a taxa.
                                </small>
                            </div>
                        </div>

                        {{-- Preview de PnL: visão do admin separando lucro da compra e da venda --}}
                        <div class="alert alert-secondary mt-3 mb-2 py-2 small" id="fechar_pnl_preview">
                            <div class="fw-bold mb-1"><i class="ri-eye-line me-1"></i>Resumo da operação (admin):</div>
                            <div class="row g-1">
                                <div class="col-6">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted"><i class="ri-arrow-down-circle-line text-success"></i>
                                            Comprou:</span>
                                        <span><strong id="prev_compra_usd">—</strong> @ <span
                                                id="prev_compra_taxa">—</span></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Custo R$:</span>
                                        <span id="prev_compra_brl">—</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted"><i class="ri-arrow-up-circle-line text-danger"></i>
                                            Venderá:</span>
                                        <span><strong id="prev_venda_usd">—</strong> @ <span
                                                id="prev_venda_taxa">—</span></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Receita R$:</span>
                                        <span id="prev_venda_brl">—</span>
                                    </div>
                                </div>
                            </div>
                            <hr class="my-1">
                            <div class="d-flex justify-content-between fw-bold">
                                <span>Lucro estimado:</span>
                                <span id="prev_pnl">—</span>
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <label for="fechar_descricao" class="form-label">Descrição (vista pelo cliente)</label>
                            <input type="text" name="description" id="fechar_descricao" class="form-control"
                                value="Fechamento Tx" required>
                            <small class="text-muted">
                                A "Entrada U$" do cliente mostra apenas a taxa de venda — sem custo.
                            </small>
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
                    // Acumula pré-compra/venda das linhas selecionadas para o preview.
                    var totalPreCompraBrl = 0, totalPreVendaBrl = 0;
                    var pondCompraBrl = 0, pondCompraUsd = 0; // para taxa média compra
                    var pondVendaBrl = 0, pondVendaUsd = 0;   // para taxa média venda

                    checked.forEach(function (cb) {
                        var row = cb.closest('tr');
                        var valorTxt = row.querySelector('.fw-bold.text-success').textContent;
                        var valor = parseNumber(valorTxt);
                        totalBrl += valor;

                        var preBrl = parseFloat(row.getAttribute('data-pre-purchased')) || 0;
                        var sldBrl = parseFloat(row.getAttribute('data-pre-sold')) || 0;
                        totalPreCompraBrl += preBrl;
                        totalPreVendaBrl += sldBrl;
                        // Estimativa pela taxa média via badge "C: x @ y" / "V: x @ y"
                        var badges = row.querySelectorAll('.badge');
                        badges.forEach(function (b) {
                            var m = b.textContent.match(/([CV]):\s*([\d.,]+)\s*@\s*([\d.,]+)/);
                            if (!m) return;
                            var v = parseNumber(m[2]);
                            var tx = parseNumber(m[3]);
                            if (tx <= 0) return;
                            if (m[1] === 'C') { pondCompraBrl += v; pondCompraUsd += v / tx; }
                            else { pondVendaBrl += v; pondVendaUsd += v / tx; }
                        });

                        // Pega a data
                        var dataStr = row.querySelector('td:nth-child(2)').textContent.trim();
                        var match = dataStr.match(/(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2})/);
                        if (match) {
                            var dt = new Date(match[3] + '-' + match[2] + '-' + match[1] + 'T' + match[4] + ':' + match[5]);
                            if (!maxData || dt > maxData) maxData = dt;
                        }

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

                    // Guarda totais para o preview de PnL.
                    window._fecharPreview = {
                        totalBrl: totalDisponivelBrl,
                        preCompraBrl: Math.round(totalPreCompraBrl * 100) / 100,
                        preVendaBrl: Math.round(totalPreVendaBrl * 100) / 100,
                        pondCompraBrl: pondCompraBrl, pondCompraUsd: pondCompraUsd,
                        pondVendaBrl: pondVendaBrl, pondVendaUsd: pondVendaUsd
                    };

                    recalcFromBrl();
                    updatePnlPreview();

                    fecharTransactionIds.value = checked.map(function (cb) { return cb.value; }).join(',');

                    if (maxData) {
                        var tzOffset = maxData.getTimezoneOffset() * 60000;
                        fecharData.value = new Date(maxData - tzOffset).toISOString().slice(0, 16);
                    } else {
                        fecharData.value = '';
                    }

                    updateFecharDescricao();
                });
            }

            // Calcula preview de lucro = (receita venda) - (custo compra), em R$ e US$.
            function updatePnlPreview() {
                var p = window._fecharPreview;
                if (!p) return;
                var newRate = parseFloat(fecharTaxa.value) || 0;
                var brlFechar = parseFloat(fecharBrl.value) || p.totalBrl;

                // R$ que tem compra registrada (limitado pelo R$ a fechar)
                var brlComCompra = Math.min(p.preCompraBrl, brlFechar);
                var brlSemCompra = Math.max(0, brlFechar - brlComCompra);
                // R$ que tem venda registrada
                var brlComVenda = Math.min(p.preVendaBrl, brlFechar);
                var brlSemVenda = Math.max(0, brlFechar - brlComVenda);

                // USD comprado: lotes (taxa média) + residual à newRate
                var taxaMediaCompra = p.pondCompraUsd > 0 ? (p.pondCompraBrl / p.pondCompraUsd) : newRate;
                var taxaMediaVenda = p.pondVendaUsd > 0 ? (p.pondVendaBrl / p.pondVendaUsd) : newRate;

                var usdLoteCompra = (taxaMediaCompra > 0 && brlComCompra > 0)
                    ? Math.min(p.pondCompraUsd, brlComCompra / taxaMediaCompra) : 0;
                var usdLoteVenda = (taxaMediaVenda > 0 && brlComVenda > 0)
                    ? Math.min(p.pondVendaUsd, brlComVenda / taxaMediaVenda) : 0;

                var usdResCompra = (newRate > 0 && brlSemCompra > 0) ? brlSemCompra / newRate : 0;
                var usdResVenda = (newRate > 0 && brlSemVenda > 0) ? brlSemVenda / newRate : 0;

                var totalUsdComprado = usdLoteCompra + usdResCompra;
                var totalUsdVendido = usdLoteVenda + usdResVenda;
                var custoBrl = (usdLoteCompra * taxaMediaCompra) + (usdResCompra * newRate);
                var receitaBrl = (usdLoteVenda * taxaMediaVenda) + (usdResVenda * newRate);

                var pnlBrl = receitaBrl - custoBrl;
                // PnL em USD: lucro do dono em dólares = USD comprado - USD entregue
                var pnlUsd = totalUsdComprado - totalUsdVendido;

                var fmt = function (v, d) {
                    return v.toLocaleString('pt-BR', { minimumFractionDigits: d || 2, maximumFractionDigits: d || 2 });
                };
                var setText = function (id, t) { var el = document.getElementById(id); if (el) el.textContent = t; };

                setText('prev_compra_usd', 'US$ ' + fmt(totalUsdComprado));
                setText('prev_compra_taxa', (taxaMediaCompra > 0 ? fmt(taxaMediaCompra, 4) : '—'));
                setText('prev_compra_brl', 'R$ ' + fmt(custoBrl));
                setText('prev_venda_usd', 'US$ ' + fmt(totalUsdVendido));
                setText('prev_venda_taxa', (taxaMediaVenda > 0 ? fmt(taxaMediaVenda, 4) : '—'));
                setText('prev_venda_brl', 'R$ ' + fmt(receitaBrl));

                var pnlEl = document.getElementById('prev_pnl');
                if (pnlEl) {
                    var sign = pnlBrl >= 0 ? '+' : '';
                    pnlEl.textContent = sign + 'R$ ' + fmt(pnlBrl) + '  (' + sign + 'US$ ' + fmt(pnlUsd) + ')';
                    pnlEl.className = pnlBrl >= 0 ? 'text-success' : 'text-danger';
                }
            }

            // Recalcula preview quando muda valor/taxa.
            fecharBrl.addEventListener('input', updatePnlPreview);
            fecharUsd.addEventListener('input', updatePnlPreview);
            fecharTaxa.addEventListener('input', updatePnlPreview);

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
        });
    </script>
@endsection