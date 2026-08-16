@extends('layouts.app')
@section('title', 'Carteira')
@section('content')
    @php
        $canViewPnl = auth()->user()->hasModule('wallet.pnl.view');
    @endphp
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Resumo da Carteira</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">Carteira</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase mb-2">Saldo total em Reais</h6>
                            <h3 class="mb-0 text-success">R$ {{ number_format($totals['BRL'] ?? 0, 2, ',', '.') }}</h3>
                            @if(($totals['CLIENTE_DEVE_BRL'] ?? 0) > 0)
                                <small class="text-muted">
                                    Clientes negativos somam <strong>R$ {{ number_format($totals['CLIENTE_DEVE_BRL'], 2, ',', '.') }}</strong> a receber.
                                </small>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase mb-2">Saldo total em Dólar</h6>
                            <h3 class="mb-0 text-info">US$ {{ number_format($totals['USD'] ?? 0, 2, ',', '.') }}</h3>
                            <small class="text-muted">
                                Pré-comprado: <strong>US$ {{ number_format($totals['USD_PRE'] ?? 0, 2, ',', '.') }}</strong>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase mb-2">Devo aos clientes</h6>
                            <h3 class="mb-0 text-danger">R$ {{ number_format($totals['DEVO_BRL'] ?? 0, 2, ',', '.') }}</h3>
                            <small class="text-muted">Depositado e ainda não vendido/entregue ao cliente.</small>
                        </div>
                    </div>
                </div>
                @if($canViewPnl)
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="text-muted text-uppercase mb-2">Lucro realizado</h6>
                                <h3 class="mb-0 {{ ($totals['PNL_USD'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ ($totals['PNL_USD'] ?? 0) >= 0 ? '+' : '' }}US$ {{ number_format($totals['PNL_USD'] ?? 0, 4, ',', '.') }}
                                </h3>
                                <small class="text-muted">
                                    @if($dateFrom || $dateTo)
                                        Período: {{ $dateFrom ? $dateFrom->format('d/m/Y') : 'início' }} — {{ $dateTo ? $dateTo->format('d/m/Y') : 'hoje' }}
                                    @else
                                        Acumulado de todos os fechamentos.
                                    @endif
                                </small>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
            <div class="d-flex flex-wrap mt-2 mb-2">
                <div class="col-md-9">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label class="form-label mb-1">De</label>
                        <input type="date" name="date_from" class="form-control"
                            value="{{ request('date_from') }}">
                    </div>

                    <div class="col-auto">
                        <label class="form-label mb-1">Até</label>
                        <input type="date" name="date_to" class="form-control"
                            value="{{ request('date_to') }}">
                    </div>

                    <div class="col-auto d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-filter-2-line me-1"></i>{{ $canViewPnl ? 'Filtrar lucro' : 'Filtrar período' }}
                        </button>

                        @if($dateFrom || $dateTo)
                            <a href="{{ route('admin.wallet.index') }}"
                                class="btn btn-outline-secondary">
                                Limpar
                            </a>
                        @endif
                    </div>
                </form>
</div>
 <div class="col-md-3">
                <form method="GET"
                    action="{{ route('admin.wallet.pix-daily-pdf') }}"
                    class="row g-2 align-items-end">

                    <div class="col-auto">
                        <label class="form-label mb-1">Data do PIX</label>
                        <input type="date"
                            name="pix_date"
                            class="form-control"
                            value="{{ request('pix_date', now()->subHours(3)->format('Y-m-d')) }}"
                            required>
                    </div>

                    <div class="col-auto">
                        <button type="submit" class="btn btn-danger">
                            <i class="ri-printer-line me-1"></i>Imprimir PDF
                        </button>
                    </div>

                </form>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header border-0 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                            <h5 class="mb-0">Clientes com módulo de câmbio ativo</h5>
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                                data-bs-target="#depositGlobalModal">
                                Adicionar valor
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Cliente</th>
                                            <th class="text-end">Saldo BRL</th>
                                            <th class="text-end">Saldo USD</th>
                                            <th class="text-end" title="Comprado − vendido dos depósitos abertos. Quando negativo, mostra R$ 0,00 com um '?' ao lado indicando o valor bruto.">
                                                Devo (R$)
                                            </th>
                                            <th class="text-end" title="USD comprado pelo dono (pré-compra) que ainda não usou pra cobrir uma venda">
                                                USD pré-comprado
                                            </th>
                                            @if($canViewPnl)
                                                <th class="text-end" title="Lucro/prejuízo já realizado nos fechamentos">PnL (US$)</th>
                                            @endif
                                            <th style="width: 160px">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($clients as $client)
                                            @php
                                                $clientWallets = $walletsByClient[$client->id] ?? ['BRL' => 0, 'USD' => 0];
                                                $pp = $prePurchaseByClient[$client->id] ?? [
                                                    'usd_pre_comprado' => 0, 'brl_em_aberto' => 0, 'pnl_realizado_usd' => 0,
                                                    'devido_ao_cliente' => 0, 'devido_ao_cliente_raw' => 0,
                                                ];
                                                $brl = (float) ($clientWallets['BRL'] ?? 0);
                                                $usd = (float) ($clientWallets['USD'] ?? 0);
                                                $ppPnlUsd = (float) ($pnlByClient[$client->id] ?? 0);
                                            @endphp
                                            <tr>
                                                <td class="fw-medium">
                                                    {{ $client->name }}
                                                    @if($brl < 0 || $usd < 0)
                                                        <span class="badge bg-warning-subtle text-warning ms-1"
                                                            title="Cliente está negativo — você deu saída maior que o saldo, então ele te deve.">
                                                            Cliente me deve
                                                        </span>
                                                    @endif
                                                    @if($pp['devido_ao_cliente'] > 0)
                                                        <span class="badge bg-danger-subtle text-danger ms-1"
                                                            title="Ainda falta vender/entregar parte do depósito deste cliente em dólar.">
                                                            Devo
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="text-end fw-semibold {{ $brl < 0 ? 'text-danger' : 'text-success' }}">
                                                    {{ $brl < 0 ? '-' : '' }}R$
                                                    {{ number_format(abs($brl), 2, ',', '.') }}
                                                </td>
                                                <td class="text-end fw-semibold {{ $usd < 0 ? 'text-danger' : 'text-info' }}">
                                                    {{ $usd < 0 ? '-' : '' }}US$
                                                    {{ number_format(abs($usd), 2, ',', '.') }}
                                                </td>
                                                <td class="text-end {{ $pp['devido_ao_cliente'] > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                                                    R$ {{ number_format($pp['devido_ao_cliente'], 2, ',', '.') }}
                                                    @if($pp['devido_ao_cliente_raw'] < 0)
                                                        <i class="ri-question-line text-warning ms-1"
                                                            title="Valor bruto (comprado − vendido) ficou negativo: -R$ {{ number_format(abs($pp['devido_ao_cliente_raw']), 2, ',', '.') }}. Mostrando R$ 0,00 em vez de negativo."></i>
                                                    @endif
                                                </td>
                                                <td class="text-end {{ $pp['usd_pre_comprado'] > 0 ? 'fw-semibold' : 'text-muted' }}">
                                                    US$ {{ number_format($pp['usd_pre_comprado'], 2, ',', '.') }}
                                                </td>
                                                @if($canViewPnl)
                                                    <td class="text-end {{ $ppPnlUsd >= 0 ? 'text-success' : 'text-danger' }}">
                                                        {{ $ppPnlUsd >= 0 ? '+' : '' }}US$
                                                        {{ number_format($ppPnlUsd, 4, ',', '.') }}
                                                    </td>
                                                @endif
                                                <td>
                                                    <a href="{{ route('admin.wallet.client', $client) }}"
                                                        class="btn btn-sm btn-primary">
                                                        Acessar carteira
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ $canViewPnl ? 7 : 6 }}" class="text-center text-muted py-4">
                                                    Nenhum cliente com módulo de câmbio ativo.
                                                </td>
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

    <div class="modal fade" id="depositGlobalModal" tabindex="-1" aria-labelledby="depositGlobalModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="depositGlobalModalLabel">Adicionar valor para cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form id="depositGlobalForm" method="POST" action="{{ url('admin/wallet/deposit') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="global_client_id" class="form-label">Cliente</label>
                            <select name="client_id" id="global_client_id" class="form-select" required>
                                <option value="">Selecione o cliente</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" data-spread="{{ $client->spread_points }}">
                                        {{ $client->name }}{{ $client->email ? ' - ' . $client->email : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="global_currency" class="form-label">Moeda</label>
                            <select name="currency" id="global_currency" class="form-select" required>
                                <option value="BRL">Reais (BRL)</option>
                                <option value="USD">Dólar (USD)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="global_amount" class="form-label">Valor</label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="global_amount"
                                class="form-control" required>
                        </div>

                        <div class="mb-3" id="global_fee_group">
                            <label for="global_fee" class="form-label d-flex align-items-center gap-1">
                                Taxa
                                <span id="global_fee_status" class="ms-auto small text-muted"></span>
                            </label>
                            <input type="number" step="0.0001" min="0.0001" name="fee" id="global_fee"
                                class="form-control" value="4.9311" placeholder="4,9311" required>
                            <small class="text-muted d-block mt-1">
                                Cotação base + spread do cliente selecionado (<span id="global_spread_label">0</span> pts).
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="global_payment_method" class="form-label">Forma de Pagamento</label>
                            <select name="payment_method" id="global_payment_method" class="form-select" required>
                                <option value="pix">Pix</option>
                                <option value="dinheiro">Dinheiro</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('depositGlobalForm');
            var currency = document.getElementById('global_currency');
            var payment = document.getElementById('global_payment_method');
            var feeGroup = document.getElementById('global_fee_group');
            var feeInput = document.getElementById('global_fee');
            var feeStatus = document.getElementById('global_fee_status');
            var clientSelect = document.getElementById('global_client_id');
            var spreadLabel = document.getElementById('global_spread_label');

            function currentSpreadPoints() {
                var selected = clientSelect.options[clientSelect.selectedIndex];
                if (!selected) return 0;
                var spread = parseFloat(selected.getAttribute('data-spread'));
                return isNaN(spread) ? 0 : spread;
            }

            function updatePaymentMethod() {
                payment.innerHTML = '';

                if (currency.value === 'BRL') {
                    payment.innerHTML += '<option value="pix" selected>Pix</option>';
                    payment.innerHTML += '<option value="dinheiro">Dinheiro</option>';
                    feeGroup.style.display = '';
                    feeInput.disabled = false;
                    feeInput.required = true;
                } else {
                    payment.innerHTML += '<option value="efetivo" selected>Efetivo</option>';
                    payment.innerHTML += '<option value="usdt">USDT</option>';
                    feeGroup.style.display = 'none';
                    feeInput.disabled = true;
                    feeInput.required = false;
                }
            }

            function updateSpreadLabel() {
                spreadLabel.textContent = currentSpreadPoints();
            }

            function fetchUsdBrlRate() {
                if (currency.value !== 'BRL') {
                    return;
                }

                feeStatus.textContent = 'Buscando cotação...';
                feeStatus.className = 'ms-auto small text-muted';

                fetch('{{ route('admin.wallet.usd-brl-rate', [], false) }}', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.success && data.rate) {
                            var base = parseFloat(data.rate);
                            var spreadValue = currentSpreadPoints() * 0.01;
                            feeInput.value = (base + spreadValue).toFixed(4);
                            feeStatus.textContent = 'Base ' + base.toFixed(4) + ' + spread ' + spreadValue.toFixed(2);
                            feeStatus.className = 'ms-auto small text-success';
                        } else {
                            feeStatus.textContent = 'Falha ao obter cotação. Edite manualmente.';
                            feeStatus.className = 'ms-auto small text-danger';
                        }
                    })
                    .catch(function () {
                        feeStatus.textContent = 'Erro ao consultar cotação. Edite manualmente.';
                        feeStatus.className = 'ms-auto small text-danger';
                    });
            }

            currency.addEventListener('change', function () {
                updatePaymentMethod();
                fetchUsdBrlRate();
            });

            clientSelect.addEventListener('change', function () {
                updateSpreadLabel();
                fetchUsdBrlRate();
            });

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var formData = new FormData(form);
                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': form.querySelector('[name=_token]').value
                    },
                    body: formData
                })
                    .then(function (response) {
                        if (response.ok) {
                            location.reload();
                            return;
                        }

                        return response.json().then(function (data) {
                            alert(data.message || 'Erro ao processar depósito.');
                        });
                    })
                    .catch(function () {
                        alert('Erro ao processar depósito.');
                    });
            });

            updatePaymentMethod();
            updateSpreadLabel();
            fetchUsdBrlRate();
        });
    </script>
@endsection