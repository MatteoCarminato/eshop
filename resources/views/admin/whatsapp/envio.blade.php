@extends('layouts.app')
@section('title', 'WhatsApp Envio')

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Disparo WhatsApp</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.index') }}">WhatsApp</a></li>
                                <li class="breadcrumb-item active">Envio</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    @if (session('success'))
                        <div class="alert alert-success py-2">{{ session('success') }}</div>
                    @endif
                    @if (session('warning'))
                        <div class="alert alert-warning py-2">{{ session('warning') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger py-2">{{ session('error') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger py-2 mb-0">
                            <strong>Não foi possível enviar:</strong>
                            <ul class="mb-0 mt-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            @php
                $oldClientIds = collect(old('client_ids', []))->map(fn($id) => (int) $id)->all();
                $oldGroupIds = collect(old('group_ids', []))->map(fn($id) => (int) $id)->all();
            @endphp

            <div class="row g-3 mt-1">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Disparo para Clientes/Grupos</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.whatsapp.send') }}" enctype="multipart/form-data">
                                @csrf

                                <div class="row g-3">
                                    <div class="col-lg-6">
                                        <label class="form-label">Selecionar clientes</label>
                                        <select name="client_ids[]" class="form-select" multiple size="12">
                                            @foreach ($clients as $client)
                                                <option value="{{ $client->id }}" @selected(in_array((int) $client->id, $oldClientIds, true))>
                                                    {{ $client->name }}
                                                    @if (!empty($client->phone))
                                                        — {{ $client->phone }}
                                                    @else
                                                        — sem telefone
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Use Ctrl/Cmd para selecionar múltiplos clientes.</small>
                                    </div>

                                    <div class="col-lg-6">
                                        <label class="form-label">Selecionar grupos</label>
                                        <select name="group_ids[]" class="form-select" multiple size="12">
                                            @foreach ($groups as $group)
                                                <option value="{{ $group->id }}" @selected(in_array((int) $group->id, $oldGroupIds, true))>
                                                    {{ $group->name }} ({{ (int) ($group->clients_count ?? 0) }} clientes)
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Clientes selecionados e clientes dos grupos serão
                                            unificados sem duplicar.</small>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Mensagem (texto)</label>
                                        <textarea name="message" class="form-control" rows="5" maxlength="4000"
                                            placeholder="Digite a mensagem que será enviada...">{{ old('message') }}</textarea>
                                        <small class="text-muted">Se anexar imagem/arquivo, o texto será usado como
                                            legenda.</small>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Anexo (imagem ou arquivo)</label>
                                        <input type="file" name="attachment" class="form-control"
                                            accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv">
                                        <small class="text-muted">Opcional. Máximo: 15MB.</small>
                                    </div>

                                    <div class="col-12 d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ri-send-plane-2-line me-1"></i>
                                            Disparar Mensagens
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection