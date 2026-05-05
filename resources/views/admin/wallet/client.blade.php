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
                    item.checked = master.checked;
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
                    item.addEventListener('change', updateBulkRateControlsVisibility);
                });

                updateBulkRateControlsVisibility();

                document.querySelectorAll('.js-rate-input').forEach(function (input) {
                    input.addEventListener('blur', function () {
                        saveSingleRate(input);
                    });
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
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
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

                                            if ($tx->converted_currency === 'USD' && $tx->converted_amount !== null) {
                                                $valorConvertido = $tx->converted_amount;
                                            } elseif ($taxa && $taxa > 0) {
                                                $valorConvertido = $tx->amount / $taxa;
                                            }
                                        @endphp
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="entrada-select-item" form="bulk_rate_form"
                                                    name="transaction_ids[]" value="{{ $tx->id }}">
                                            </td>
                                            <td>{{ $tx->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="fw-bold text-success">{{ number_format($tx->amount, 2, ',', '.') }}</td>
                                            <td>
                                                <div class="d-flex align-items-center gap-1">
                                                    <input type="number" step="0.000001" min="0.000001"
                                                        value="{{ $taxa ? number_format($taxa, 6, '.', '') : '' }}"
                                                        class="form-control form-control-sm js-rate-input"
                                                        data-url="{{ route('admin.wallet.update-rate', $tx) }}"
                                                        data-original-value="{{ $taxa ? number_format($taxa, 6, '.', '') : '' }}"
                                                        style="min-width: 120px" required>
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
                    <div class="card-header border-0">
                        <h5 class="mb-0 text-uppercase">Entrada U$</h5>
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
                                    @forelse($entradaUsd as $tx)
                                        <tr>
                                            <td>{{ $tx->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="fw-bold text-success">{{ number_format($tx->amount, 2, ',', '.') }}</td>
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
    </style>
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
                            <label for="fechar_valor" class="form-label">Valor convertido (USD)</label>
                            <input type="number" step="0.01" name="amount" id="fechar_valor" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
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
        // Fechar em dólar: preencher modal com dados das linhas selecionadas
        document.addEventListener('DOMContentLoaded', function () {
            var fecharBtn = document.getElementById('btn-fechar-dolar');
            var fecharModal = document.getElementById('fecharDolarModal');
            var fecharForm = document.getElementById('fecharDolarForm');
            var fecharData = document.getElementById('fechar_data');
            var fecharValor = document.getElementById('fechar_valor');
            var fecharDescricao = document.getElementById('fechar_descricao');
            var fecharExchangeRate = document.getElementById('fechar_exchange_rate');
            var fecharTransactionIds = document.getElementById('fechar_transaction_ids');

            if (fecharBtn && fecharModal) {
                fecharBtn.addEventListener('click', function () {
                    var checked = Array.from(document.querySelectorAll('.entrada-select-item:checked'));
                    if (checked.length === 0) {
                        alert('Selecione pelo menos uma entrada para fechar.');
                        return;
                    }
                    // Pega taxas e valores das linhas selecionadas
                    var taxas = [];
                    var valores = [];
                    var datas = [];
                    checked.forEach(function (cb) {
                        var row = cb.closest('tr');
                        var valor = parseFloat(row.querySelector('.fw-bold.text-success').textContent.replace('.', '').replace(',', '.'));
                        var taxa = parseFloat(row.querySelector('.js-rate-input').value.replace(',', '.'));
                        var data = row.querySelector('td:nth-child(3)').textContent;
                        if (!isNaN(valor) && !isNaN(taxa)) {
                            valores.push({ valor: valor, taxa: taxa });
                            taxas.push(taxa);
                        }
                        datas.push(data);
                    });
                    // Agrupa por taxa
                    var taxaUnica = taxas.length ? taxas[0] : 0;
                    var mesmaTaxa = taxas.every(function (t) { return t === taxaUnica; });
                    if (!mesmaTaxa) {
                        alert('Selecione apenas entradas com a mesma taxa para fechar em dólar.');
                        return;
                    }
                    var totalUSD = valores.reduce(function (acc, v) { return acc + (v.valor / v.taxa); }, 0);
                    fecharValor.value = totalUSD.toFixed(2);
                    fecharExchangeRate.value = taxaUnica;
                    fecharTransactionIds.value = checked.map(function (cb) { return cb.value; }).join(',');
                    // Data padrão: mais recente
                    var maxData = null;
                    checked.forEach(function (cb) {
                        var row = cb.closest('tr');
                        var dataStr = row.querySelector('td:nth-child(3)').textContent;
                        var match = dataStr.match(/(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2})/);
                        if (match) {
                            var [_, d, m, y, h, min] = match;
                            var dt = new Date(`${y}-${m}-${d}T${h}:${min}`);
                            if (!maxData || dt > maxData) maxData = dt;
                        }
                    });
                    if (maxData) {
                        fecharData.value = maxData.toISOString().slice(0, 16);
                    } else {
                        fecharData.value = '';
                    }
                    fecharDescricao.value = 'Fechamento Tx (' + taxaUnica + ')';
                });
            }
        });
    </script>
@endsection