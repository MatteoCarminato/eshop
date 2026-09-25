@extends('layouts.app')
@section('title', 'Carteira do Cliente')
@section('content')
    @php
        $canViewPnl = auth()->user()->hasModule('wallet.pnl.view');
    @endphp
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


            @include('admin.wallet.partials.client-body')
@endsection
