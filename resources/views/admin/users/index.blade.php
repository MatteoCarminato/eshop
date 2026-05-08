@extends('layouts.app')
@section('title', 'Funcionários')

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Funcionários</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">Funcionários</li>
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
                                    <form method="GET" action="{{ route('users.index') }}" class="w-100 d-flex gap-2">
                                        <input type="text" class="form-control" name="search"
                                            placeholder="Buscar por nome ou email..." value="{{ $search }}">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ri-search-line"></i>
                                        </button>
                                    </form>
                                </div>
                                <div class="col-sm-auto ms-auto">
                                    <a href="{{ route('users.create') }}" class="btn btn-success">
                                        <i class="ri-add-line align-middle me-1"></i> Novo Funcionário
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
                                            <th>E-mail</th>
                                            <th>Cargo</th>
                                            <th>Cadastrado em</th>
                                            <th class="text-center" style="width: 160px">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($users as $employee)
                                            <tr>
                                                <td class="fw-medium">{{ $employee->name }}</td>
                                                <td>{{ $employee->email }}</td>
                                                <td>{{ optional($employee->role)->name ?? 'Sem cargo' }}</td>
                                                <td>{{ $employee->created_at?->format('d/m/Y H:i') }}</td>
                                                <td>
                                                    <div class="hstack gap-2 justify-content-center">
                                                        <a href="{{ route('users.edit', $employee) }}"
                                                            class="btn btn-sm btn-warning" title="Editar">
                                                            <i class="ri-pencil-line"></i>
                                                        </a>
                                                        <form action="{{ route('users.destroy', $employee) }}" method="POST"
                                                            onsubmit="return confirm('Excluir este funcionário?');">
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
                                                <td colspan="5" class="text-center py-4 text-muted">
                                                    Nenhum funcionário cadastrado.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if ($users->hasPages())
                                <div class="mt-3">{{ $users->links() }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection