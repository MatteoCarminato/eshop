@extends('layouts.app')
@section('title', 'Grupos')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Grupos de Clientes</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">Grupos</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header border-0">
                            <div class="row g-4 align-items-center">
                                <div class="col-sm-4 d-flex align-items-center gap-2">
                                    <form method="GET" action="{{ route('groups.index') }}" class="w-100 d-flex gap-2">
                                        <input type="text" class="form-control search" name="search" id="searchInput"
                                            placeholder="Buscar por nome..." value="{{ request('search') }}">
                                        <button type="submit" class="btn btn-primary" title="Buscar">
                                            <i class="ri-search-line"></i>
                                        </button>
                                    </form>
                                </div>
                                <div class="col-sm-auto ms-auto">
                                    <a href="{{ route('groups.create') }}" class="btn btn-success">
                                        <i class="ri-add-line align-middle me-1"></i>
                                        Novo Grupo
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            @if (session('success'))
                                <div class="alert alert-success alert-dismissible alert-border-left alert-label-icon fade show"
                                    role="alert">
                                    <i class="ri-check-double-line label-icon"></i>
                                    <strong>Sucesso!</strong> {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif
                            @if (session('error'))
                                <div class="alert alert-danger alert-dismissible alert-border-left alert-label-icon fade show"
                                    role="alert">
                                    <i class="ri-error-warning-line label-icon"></i>
                                    <strong>Erro!</strong> {{ session('error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif
                            <div class="table-responsive">
                                <table class="table table-striped table-nowrap align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">ID</th>
                                            <th scope="col">Nome</th>
                                            <th scope="col">Descrição</th>
                                            <th scope="col">Qtd. Clientes</th>
                                            <th scope="col" class="text-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($groups as $group)
                                            <tr>
                                                <td class="fw-medium">#{{ $group->id }}</td>
                                                <td>{{ $group->name }}</td>
                                                <td>{{ $group->description ?? '—' }}</td>
                                                <td>{{ $group->clients()->count() }}</td>
                                                <td>
                                                    <div class="hstack gap-2 justify-content-center">
                                                        <a href="{{ route('groups.show', $group) }}" class="btn btn-sm btn-info"
                                                            title="Visualizar">
                                                            <i class="ri-eye-line"></i>
                                                        </a>
                                                        <a href="{{ route('groups.edit', $group) }}"
                                                            class="btn btn-sm btn-warning" title="Editar">
                                                            <i class="ri-pencil-line"></i>
                                                        </a>
                                                        <form action="{{ route('groups.destroy', $group) }}" method="POST"
                                                            class="d-inline"
                                                            onsubmit="return confirm('Tem certeza que deseja excluir este grupo?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-danger" title="Excluir">
                                                                <i class="ri-delete-bin-line"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                                        <p class="mb-0">Nenhum grupo encontrado</p>
                                                        @if (request('search'))
                                                            <a href="{{ route('groups.index') }}" class="btn btn-sm btn-link">Limpar
                                                                filtros</a>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if ($groups->hasPages())
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="text-muted">
                                        Mostrando {{ $groups->firstItem() }} até {{ $groups->lastItem() }} de
                                        {{ $groups->total() }} registros
                                    </div>
                                    <div>
                                        {{ $groups->links() }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection