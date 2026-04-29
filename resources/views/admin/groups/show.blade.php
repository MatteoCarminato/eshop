@extends('layouts.app')
@section('title', 'Detalhes do Grupo')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Detalhes do Grupo</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('groups.index') }}">Grupos</a></li>
                                <li class="breadcrumb-item active">Detalhes</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">{{ $group->name }}</h4>
                            <div class="flex-shrink-0">
                                <a href="{{ route('groups.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="ri-arrow-left-line align-middle me-1"></i>
                                    Voltar
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-3">Nome</dt>
                                <dd class="col-sm-9">{{ $group->name }}</dd>
                                <dt class="col-sm-3">Descrição</dt>
                                <dd class="col-sm-9">{{ $group->description ?? '—' }}</dd>
                                <dt class="col-sm-3">Criado em</dt>
                                <dd class="col-sm-9">{{ $group->created_at->format('d/m/Y H:i') }}</dd>
                                <dt class="col-sm-3">Atualizado em</dt>
                                <dd class="col-sm-9">{{ $group->updated_at->format('d/m/Y H:i') }}</dd>
                            </dl>
                            <hr>
                            <h5 class="mb-3">Adicionar clientes ao grupo</h5>
                            <form action="{{ route('groups.addClients', $group) }}" method="POST">
                                @csrf
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered align-middle">
                                        <thead>
                                            <tr>
                                                <th></th>
                                                <th>Nome</th>
                                                <th>E-mail</th>
                                                <th>Telefone</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach (\App\Models\Client::all() as $client)
                                                <tr>
                                                    <td>
                                                        @if (!$group->clients->contains($client->id))
                                                            <input type="checkbox" name="clients[]" value="{{ $client->id }}">
                                                        @else
                                                            <input type="checkbox" checked disabled>
                                                        @endif
                                                    </td>
                                                    <td>{{ $client->name }}</td>
                                                    <td>{{ $client->email ?? '—' }}</td>
                                                    <td>{{ $client->phone ?? '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" class="btn btn-primary">Adicionar ao Grupo</button>
                            </form>

                            <hr>
                            <h5 class="mb-3">Clientes já neste grupo</h5>
                            @if ($group->clients->count())
                                <ul class="list-group">
                                    @foreach ($group->clients as $client)
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>{{ $client->name }}</span>
                                            <a href="{{ route('clients.show', $client) }}" class="btn btn-sm btn-info">
                                                <i class="ri-eye-line"></i> Ver Cliente
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="text-muted">Nenhum cliente neste grupo.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection