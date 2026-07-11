@extends('layouts.app')
@section('title', 'Extrações PIX')

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Extrações PIX</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.index') }}">WhatsApp</a></li>
                                <li class="breadcrumb-item active">Extrações PIX</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filtros --}}
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.whatsapp.extracoes') }}" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label mb-1">Busca</label>
                            <input type="text" name="search" class="form-control form-control-sm"
                                   placeholder="Nome, valor, transação, remetente..."
                                   value="{{ request('search') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-1">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <option value="confirmed"     {{ request('status') === 'confirmed'     ? 'selected' : '' }}>Confirmado</option>
                                <option value="duplicate"     {{ request('status') === 'duplicate'     ? 'selected' : '' }}>Duplicado</option>
                                <option value="false_payment" {{ request('status') === 'false_payment' ? 'selected' : '' }}>Não localizado</option>
                                <option value="unverified"    {{ request('status') === 'unverified'    ? 'selected' : '' }}>Não verificado</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-1">Grupo</label>
                            <select name="group_id" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                @foreach ($groups as $group)
                                    <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>
                                        {{ $group->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-1">De</label>
                            <input type="date" name="data_inicio" class="form-control form-control-sm"
                                   value="{{ request('data_inicio') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-1">Até</label>
                            <input type="date" name="data_fim" class="form-control form-control-sm"
                                   value="{{ request('data_fim') }}">
                        </div>
                        <div class="col-md-1 d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="ri-search-line"></i>
                            </button>
                            <a href="{{ route('admin.whatsapp.extracoes') }}" class="btn btn-light btn-sm w-100">
                                <i class="ri-refresh-line"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            @if ($extractions->isEmpty())
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="ri-file-search-line fs-1 text-muted mb-3 d-block"></i>
                                <p class="text-muted mb-0">Nenhuma extração encontrada.</p>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="row g-3">
                    @foreach ($extractions as $item)
                        @php
                            $data    = $item->ai_data ?? [];
                            $isPdf   = str_contains($item->mimetype ?? '', 'pdf');
                            $imgUrl  = route('admin.whatsapp.extracoes.imagem', $item);
                        @endphp
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-body">
                                    <div class="row g-3 align-items-start">

                                        {{-- Imagem ou ícone PDF --}}
                                        <div class="col-md-4 col-lg-3 text-center">
                                            @if ($isPdf)
                                                <a href="{{ $imgUrl }}" target="_blank" class="d-block text-muted">
                                                    <i class="ri-file-pdf-line" style="font-size:5rem;color:#e74c3c;"></i>
                                                    <br><small>Abrir PDF</small>
                                                </a>
                                            @else
                                                <a href="{{ $imgUrl }}" target="_blank">
                                                    <img src="{{ $imgUrl }}"
                                                         alt="Comprovante"
                                                         class="img-fluid rounded border"
                                                         style="max-height:260px;object-fit:contain;cursor:zoom-in;">
                                                </a>
                                            @endif
                                            <div class="mt-2 text-muted" style="font-size:0.75rem;">
                                                {{ $item->group?->name ?? $item->whatsapp_group_id }}<br>
                                                {{ $item->created_at->format('d/m/Y H:i') }}
                                            </div>
                                        </div>

                                        {{-- Dados extraídos pela IA --}}
                                        <div class="col-md-8 col-lg-9">
                                            @if (!empty($data))
                                                <table class="table table-sm table-bordered mb-0">
                                                    <tbody>
                                                        <tr>
                                                            <th style="width:35%">Pagador</th>
                                                            <td>{{ $data['nome_pagador'] ?? '—' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <th>CPF / CNPJ</th>
                                                            <td>{{ $data['cpf_cnpj'] ?? '—' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <th>Instituição</th>
                                                            <td>{{ $data['instituicao'] ?? '—' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <th>N° Transação</th>
                                                            <td style="word-break:break-all;">{{ $data['numero_transacao'] ?? '—' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <th>Data / Hora</th>
                                                            <td>{{ $data['data_hora'] ?? '—' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <th>Valor</th>
                                                            <td><strong>{{ $data['valor'] ?? '—' }}</strong></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            @else
                                                <p class="text-muted mb-0">IA não retornou dados estruturados para este arquivo.</p>
                                                @if ($item->ai_raw)
                                                    <pre class="mt-2 p-2 bg-light rounded" style="font-size:0.75rem;white-space:pre-wrap;">{{ $item->ai_raw }}</pre>
                                                @endif
                                            @endif

                                            @if ($item->bank_transaction_id)
                                                <div class="mt-2 text-muted" style="font-size:0.75rem;">
                                                    🏦 ID banco: <code>{{ $item->bank_transaction_id }}</code>
                                                </div>
                                            @endif
                                            <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                                                @if ($item->status === 'confirmed')
                                                    <span class="badge bg-success">✔ Confirmado</span>
                                                @elseif ($item->status === 'duplicate')
                                                    <span class="badge bg-warning text-dark">⚠️ Duplicado</span>
                                                @elseif ($item->status === 'false_payment')
                                                    <span class="badge bg-danger">❌ Não localizado</span>
                                                @elseif ($item->status === 'unverified')
                                                    <span class="badge bg-secondary">? Não verificado</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ $item->status }}</span>
                                                @endif
                                                <span class="text-muted" style="font-size:0.75rem;">
                                                    De: {{ $item->from ?? '—' }} &nbsp;|&nbsp;
                                                    ID: {{ $item->message_id ?? '—' }}
                                                </span>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        {{ $extractions->firstItem() }}–{{ $extractions->lastItem() }} de {{ $extractions->total() }} registros
                    </small>
                    {{ $extractions->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
