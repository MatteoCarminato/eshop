@extends('layouts.app')
@section('title', 'Carteira do Cliente')
@section('content')
    <div class="page-content">
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
                                <div class="col">
                                    <div class="py-4 px-3">
                                        <h5 class="text-muted text-uppercase fs-13">Saldo em USDT
                                        </h5>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <i class="ri-coins-line display-6 text-muted cfs-22"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h2 class="mb-0 cfs-22 text-warning"><span>USDT
                                                        {{ number_format($balances['USDT'], 2, ',', '.') }}</span></h2>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="py-4 px-3">
                                        <h5 class="text-muted text-uppercase fs-13">----
                                        </h5>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <i class="ri-space-ship-line display-6 text-muted cfs-22"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h2 class="mb-0 cfs-22"><span>
                                                        ---</span></h2>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="py-4 px-3">
                                        <h5 class="text-muted text-uppercase fs-13">----
                                        </h5>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <i class="ri-space-ship-line display-6 text-muted cfs-22"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h2 class="mb-0 cfs-22"><span>
                                                        ---</span></h2>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- end row -->
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
                    <a href="{{ route('admin.wallet.exchange') }}?client_id={{ $client->id }}"
                        class="btn btn-info">Converter</a>
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
                                        <option value="BRL">BRL</option>
                                        <option value="USD">USD</option>
                                        <option value="USDT">USDT</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="deposit_amount" class="form-label">Valor</label>
                                    <input type="number" step="0.01" name="amount" id="deposit_amount" class="form-control"
                                        required>
                                </div>
                                <div class="mb-3">
                                    <label for="deposit_payment_method" class="form-label">Forma de Pagamento</label>
                                    <select name="payment_method" id="deposit_payment_method" class="form-select" required>
                                        <option value="">Selecione a forma de pagamento</option>
                                        <option value="pix">Pix</option>
                                        <option value="dinheiro">Dinheiro</option>
                                        <option value="efetivo">Efetivo</option>
                                        <option value="usdt">USDT</option>
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
            <div class="modal fade" id="withdrawModal" tabindex="-1" aria-labelledby="withdrawModalLabel"
                aria-hidden="true">
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
                                        <option value="BRL">BRL</option>
                                        <option value="USD">USD</option>
                                        <option value="USDT">USDT</option>
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
                    payment.innerHTML = '';
                    if (currency === 'BRL') {
                        payment.innerHTML += '<option value="pix">Pix</option>';
                        payment.innerHTML += '<option value="dinheiro">Dinheiro</option>';
                        payment.disabled = false;
                        payment.required = true;
                        payment.value = '';
                    } else if (currency === 'USD') {
                        payment.innerHTML += '<option value="efetivo" selected>Efetivo</option>';
                        payment.readOnly = true;
                        payment.required = true;
                        payment.value = 'efetivo';
                    } else if (currency === 'USDT') {
                        payment.innerHTML += '<option value="usdt" selected>USDT</option>';
                        payment.readOnly = true;
                        payment.required = true;
                        payment.value = 'usdt';
                    } else {
                        payment.innerHTML += '<option value="">Selecione a forma de pagamento</option>';
                        payment.disabled = false;
                        payment.required = true;
                        payment.value = '';
                    }
                }
                document.addEventListener('DOMContentLoaded', function () {
                    document.getElementById('deposit_currency').addEventListener('change', updateDepositPaymentMethod);
                    updateDepositPaymentMethod();

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
                            <option value="USDT" @if(request('currency') == 'USDT') selected @endif>USDT</option>
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
                    'USDT' => $filtered->where('currency', 'USDT')->sum('amount'),
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
                                    <div class="col">
                                        <span class="fw-bold">Total USDT:</span> USDT
                                        {{ number_format($filteredTotals['USDT'], 2, ',', '.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header border-0">
                            <h5 class="mb-0">Transações do Cliente</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Tipo</th>
                                            <th>Moeda</th>
                                            <th>Valor</th>
                                            <th>Método</th>
                                            <th>Conversão</th>
                                            <th>Descrição</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($filtered as $tx)
                                            <tr>
                                                <td>{{ $tx->created_at->format('d/m/Y H:i') }}</td>
                                                <td>{{ __(ucfirst($tx->type)) }}</td>
                                                <td>{{ $tx->currency }}</td>
                                                <td class="fw-bold {{ $tx->amount < 0 ? 'text-danger' : 'text-success' }}">
                                                    {{ number_format($tx->amount, 2, ',', '.') }}
                                                </td>
                                                <td>{{ $tx->payment_method ?? '-' }}</td>
                                                <td>
                                                    @if($tx->exchange_rate)
                                                        {{ $tx->converted_amount }} {{ $tx->converted_currency }}<br>
                                                        <small>Taxa: {{ $tx->exchange_rate }}</small>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>{{ $tx->description ?? '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center">Nenhuma transação encontrada.</td>
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
@endsection