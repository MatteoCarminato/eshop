@extends('layouts.app')
@section('title', 'Conversão de Moedas')
@section('content')
    <div class="container">
        <h2 class="mb-4">Conversão de Moedas</h2>
        <form method="POST" action="{{ route('admin.wallet.exchange') }}">
            @csrf
            <div class="mb-3">
                <label for="client_id" class="form-label">Cliente (ID)</label>
                <input type="number" name="client_id" id="client_id" class="form-control" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="from_currency" class="form-label">Moeda de Origem</label>
                    <select name="from_currency" id="from_currency" class="form-select" required>
                        <option value="BRL">BRL</option>
                        <option value="USD">USD</option>
                        <option value="USDT">USDT</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="to_currency" class="form-label">Moeda de Destino</label>
                    <select name="to_currency" id="to_currency" class="form-select" required>
                        <option value="BRL">BRL</option>
                        <option value="USD">USD</option>
                        <option value="USDT">USDT</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label for="amount" class="form-label">Valor a Converter</label>
                <input type="number" step="0.01" name="amount" id="amount" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="exchange_rate" class="form-label">Taxa de Conversão</label>
                <input type="number" step="0.0001" name="exchange_rate" id="exchange_rate" class="form-control" required
                    placeholder="Ex: 0.20">
            </div>
            <button type="submit" class="btn btn-info">Converter</button>
            <a href="{{ route('admin.wallet.index') }}" class="btn btn-light">Voltar</a>
        </form>
    </div>
@endsection