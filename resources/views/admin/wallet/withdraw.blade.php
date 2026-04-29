@extends('layouts.app')
@section('title', 'Saque')
@section('content')
    <div class="container">
        <h2 class="mb-4">Saque</h2>
        <form method="POST" action="{{ route('admin.wallet.withdraw') }}">
            @csrf
            <div class="mb-3">
                <label for="client_id" class="form-label">Cliente (ID)</label>
                <input type="number" name="client_id" id="client_id" class="form-control" required>
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
                <input type="number" step="0.01" name="amount" id="amount" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-danger">Sacar</button>
            <a href="{{ route('admin.wallet.index') }}" class="btn btn-light">Voltar</a>
        </form>
    </div>
@endsection