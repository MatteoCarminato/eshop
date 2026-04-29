@extends('layouts.app')
@section('title', 'Extrato de Transações')
@section('content')

    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Extrato de Transações</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">Extrato de Transações</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header border-0">
                            <div class="col">
                                <div class="py-4 px-3">
                                    <h5 class="text-muted text-uppercase fs-13">Campaign Sent <i
                                            class="ri-arrow-up-circle-line text-success fs-18 float-end align-middle"></i>
                                    </h5>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <i class="ri-space-ship-line display-6 text-muted cfs-22"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h2 class="mb-0 cfs-22"><span class="counter-value" data-target="197">0</span>
                                            </h2>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @isset($transactions)
                                <table class="table table-bordered table-striped">
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
                                        @forelse($transactions as $tx)
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
                            @endisset
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection