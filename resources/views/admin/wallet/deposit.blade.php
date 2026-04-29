@extends('layouts.app')
@section('title', 'Depósito')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Depósito</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">Depósito</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header border-0">
                            <form method="POST" action="{{ route('admin.wallet.deposit') }}">
                                @csrf
                                <div class="mb-3">
                                    <label for="client_id" class="form-label">Cliente</label>
                                    <select name="client_id" id="client_id" class="form-select" required>
                                        @foreach(App\Models\Client::orderBy('name')->get() as $client)
                                            <option value="{{ $client->id }}">{{ $client->name }} ({{ $client->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="currency" class="form-label">Moeda</label>
                                    <select name="currency" id="currency" class="form-select" required>
                                        <option value="BRL">BRL</option>
                                        <option value="USD">USD</option>
                                        <option value="USDT">USDT</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Valor</label>
                                    <input type="number" step="0.01" name="amount" id="amount" class="form-control"
                                        required>
                                </div>
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">Forma de Pagamento</label>
                                    <select name="payment_method" id="payment_method" class="form-select" required>
                                        <option value="">Selecione a forma de pagamento</option>
                                        <option value="pix">Pix</option>
                                        <option value="dinheiro">Dinheiro</option>
                                        <option value="efetivo">Efetivo</option>
                                        <option value="usdt">USDT</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success">Depositar</button>
                                <a href="{{ route('admin.wallet.index') }}" class="btn btn-light">Voltar</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updatePaymentMethodOptions() {
            var currency = document.getElementById('currency').value;
            var payment = document.getElementById('payment_method');
            payment.innerHTML = '';
            if (currency === 'BRL') {
                payment.innerHTML += '<option value="pix">Pix</option>';
                payment.innerHTML += '<option value="dinheiro">Dinheiro</option>';
                payment.disabled = false;
            } else if (currency === 'USD') {
                payment.innerHTML += '<option value="efetivo">Efetivo</option>';
                payment.disabled = true;
            } else if (currency === 'USDT') {
                payment.innerHTML += '<option value="usdt">USDT</option>';
                payment.disabled = true;
            } else {
                payment.innerHTML += '<option value="">Selecione a forma de pagamento</option>';
                payment.disabled = false;
            }
        }
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('currency').addEventListener('change', updatePaymentMethodOptions);
            updatePaymentMethodOptions();
        });
    </script>
@endsection