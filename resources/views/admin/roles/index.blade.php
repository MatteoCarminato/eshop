@extends('layouts.app')
@section('title', 'Cargos')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Cargos &amp; Permissões</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">Cargos</li>
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
                                    <form method="GET" action="{{ route('roles.index') }}" class="w-100 d-flex gap-2">
                                        <input type="text" class="form-control" name="search"
                                            placeholder="Buscar por nome ou slug..." value="{{ $search }}">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ri-search-line"></i>
                                        </button>
                                    </form>
                                </div>
                                <div class="col-sm-auto ms-auto">
                                    <a href="{{ route('roles.create') }}" class="btn btn-success">
                                        <i class="ri-add-line align-middle me-1"></i> Novo Cargo
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            @if (session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif
                            @if (session('error'))
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    {{ session('error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            <div class="table-responsive">
                                <table class="table table-striped align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Slug</th>
                                            <th>Descrição</th>
                                            <th class="text-center">Admin</th>
                                            <th class="text-end">Módulos</th>
                                            <th class="text-end">Usuários</th>
                                            <th class="text-center" style="width: 160px">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($roles as $role)
                                            <tr>
                                                <td class="fw-medium">{{ $role->name }}</td>
                                                <td><code>{{ $role->slug }}</code></td>
                                                <td class="text-muted">{{ $role->description ?? '—' }}</td>
                                                <td class="text-center">
                                                    @if ($role->is_admin)
                                                        <span class="badge bg-primary">SIM</span>
                                                    @else
                                                        <span class="badge bg-light text-muted">NÃO</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">{{ $role->module_permissions_count }}</td>
                                                <td class="text-end">{{ $role->users_count }}</td>
                                                <td>
                                                    <div class="hstack gap-2 justify-content-center">
                                                        <a href="{{ route('roles.edit', $role) }}"
                                                            class="btn btn-sm btn-warning" title="Editar">
                                                            <i class="ri-pencil-line"></i>
                                                        </a>
                                                        <form action="{{ route('roles.destroy', $role) }}" method="POST"
                                                            onsubmit="return confirm('Excluir este cargo?');">
                                                            @csrf @method('DELETE')
                                                            <button class="btn btn-sm btn-danger" title="Excluir">
                                                                <i class="ri-delete-bin-line"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center py-4 text-muted">
                                                    Nenhum cargo cadastrado.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if ($roles->hasPages())
                                <div class="mt-3">{{ $roles->links() }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection