@extends('layouts.app')
@section('title', $isEdit ? 'Editar Cargo' : 'Novo Cargo')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">{{ $isEdit ? 'Editar Cargo' : 'Novo Cargo' }}</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('roles.index') }}">Cargos</a></li>
                                <li class="breadcrumb-item active">{{ $isEdit ? 'Editar' : 'Novo' }}</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Erros de validação:</strong>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST"
                action="{{ $isEdit ? route('roles.update', $role) : route('roles.store') }}">
                @csrf
                @if ($isEdit) @method('PUT') @endif

                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header border-0">
                                <h5 class="mb-0">Dados do Cargo</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" required
                                        value="{{ old('name', $role->name) }}" placeholder="Ex.: Operador de câmbio">
                                </div>

                                @if ($isEdit)
                                    <div class="mb-3">
                                        <label class="form-label">Slug</label>
                                        <input type="text" class="form-control" value="{{ $role->slug }}" disabled>
                                    </div>
                                @endif

                                <div class="mb-3">
                                    <label class="form-label">Descrição</label>
                                    <textarea name="description" rows="3" class="form-control"
                                        placeholder="Curta descrição do cargo">{{ old('description', $role->description) }}</textarea>
                                </div>

                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_admin" value="0">
                                    <input class="form-check-input" type="checkbox" id="is_admin" name="is_admin"
                                        value="1"
                                        {{ old('is_admin', $role->is_admin) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_admin">
                                        Cargo administrador (acesso total a todos os módulos)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header border-0 d-flex align-items-center justify-content-between">
                                <h5 class="mb-0">Permissões de módulos</h5>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                        id="btnSelectAll">Selecionar todos</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                        id="btnClearAll">Limpar</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">
                                    Marque os módulos que este cargo poderá acessar. Quando o cargo for
                                    <strong>administrador</strong>, todos os módulos ficam liberados automaticamente.
                                </p>

                                @forelse ($modules as $group => $items)
                                    <div class="mb-4">
                                        <h6 class="text-uppercase text-muted mb-2">{{ $group }}</h6>
                                        <div class="row g-2">
                                            @foreach ($items as $key => $meta)
                                                <div class="col-md-6">
                                                    <div class="form-check border rounded p-3 h-100">
                                                        <input class="form-check-input module-checkbox" type="checkbox"
                                                            name="modules[]" value="{{ $key }}"
                                                            id="module_{{ $key }}"
                                                            {{ in_array($key, old('modules', $selected), true) ? 'checked' : '' }}>
                                                        <label class="form-check-label w-100" for="module_{{ $key }}">
                                                            <span class="d-block fw-medium">{{ $meta['label'] }}</span>
                                                            @if (!empty($meta['description']))
                                                                <small class="text-muted">{{ $meta['description'] }}</small>
                                                            @endif
                                                            <small class="d-block text-muted mt-1">
                                                                <code>{{ $key }}</code>
                                                            </small>
                                                        </label>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @empty
                                    <div class="alert alert-warning mb-0">
                                        Nenhum módulo definido em <code>config/modules.php</code>.
                                    </div>
                                @endforelse
                            </div>
                            <div class="card-footer d-flex justify-content-end gap-2">
                                <a href="{{ route('roles.index') }}" class="btn btn-light">Cancelar</a>
                                <button type="submit" class="btn btn-success">
                                    <i class="ri-save-line me-1"></i>
                                    {{ $isEdit ? 'Salvar alterações' : 'Criar cargo' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var all = document.querySelectorAll('.module-checkbox');
            var btnAll = document.getElementById('btnSelectAll');
            var btnClear = document.getElementById('btnClearAll');
            if (btnAll) btnAll.addEventListener('click', function () { all.forEach(function (c) { c.checked = true; }); });
            if (btnClear) btnClear.addEventListener('click', function () { all.forEach(function (c) { c.checked = false; }); });
        });
    </script>
@endsection
