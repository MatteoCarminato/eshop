@extends('layouts.app')
@section('title', 'Carteira')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Carteira do Cliente</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">Carteira</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header border-0">
                            <form method="GET" action="" class="row g-2 align-items-end">
                                <div class="col-md-6">
                                    <label for="client_id" class="form-label">Cliente (ID)</label>
                                    <input type="text" name="client_id" id="client_id" class="form-control"
                                        placeholder="ID do cliente" value="{{ request('client_id') }}">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">Buscar</button>
                                </div>
                            </form>
                        </div>
                        <div class="card-body">
                            @isset($wallets)
                                <div class="row">
                                    @forelse($wallets as $wallet)
                                        <div class="col-md-4 mb-3">
                                            <div class="card border shadow-sm">
                                                <div class="card-body">
                                                    <h5 class="card-title">{{ $wallet->currency }}</h5>
                                                    <p class="card-text">Saldo:
                                                        <strong>{{ number_format($wallet->balance, 2, ',', '.') }}</strong>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="col-12">
                                            <div class="alert alert-warning">Nenhuma carteira encontrada para este cliente.
                                            </div>
                                        </div>
                                    @endforelse
                                </div>
                                <div class="mt-4">
                                    <a href="{{ route('admin.wallet.deposit') }}" class="btn btn-success me-2">Depósito</a>
                                    <a href="{{ route('admin.wallet.withdraw') }}" class="btn btn-danger me-2">Saque</a>
                                    <a href="{{ route('admin.wallet.exchange') }}" class="btn btn-info">Conversão</a>
                                    <a href="{{ route('admin.wallet.transactions', ['client_id' => request('client_id')]) }}"
                                        class="btn btn-secondary float-end">Ver Extrato</a>
                                </div>
                            @endisset
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection