@extends('layouts.app')
@section('title', $isEdit ? 'Editar Funcionário' : 'Novo Funcionário')

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">{{ $isEdit ? 'Editar Funcionário' : 'Novo Funcionário' }}</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Funcionários</a></li>
                                <li class="breadcrumb-item active">{{ $isEdit ? 'Editar' : 'Novo' }}</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

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

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header border-0">
                            <h5 class="mb-0">Dados do Funcionário</h5>
                        </div>
                        <form method="POST" action="{{ $isEdit ? route('users.update', $user) : route('users.store') }}">
                            @csrf
                            @if ($isEdit)
                                @method('PUT')
                            @endif

                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" required
                                        value="{{ old('name', $user->name) }}" placeholder="Nome do funcionário">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">E-mail <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" required
                                        value="{{ old('email', $user->email) }}" placeholder="email@empresa.com">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Cargo <span class="text-danger">*</span></label>
                                    <select name="role_id" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}"
                                                {{ (string) old('role_id', $user->role_id) === (string) $role->id ? 'selected' : '' }}>
                                                {{ $role->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">
                                        Senha @if (!$isEdit)
                                            <span class="text-danger">*</span>
                                        @endif
                                    </label>
                                    <input type="password" name="password" class="form-control"
                                        {{ $isEdit ? '' : 'required' }}
                                        placeholder="{{ $isEdit ? 'Preencha apenas para alterar a senha' : 'Defina uma senha segura' }}">
                                </div>
                            </div>

                            <div class="card-footer d-flex justify-content-end gap-2">
                                <a href="{{ route('users.index') }}" class="btn btn-light">Cancelar</a>
                                <button type="submit" class="btn btn-success">
                                    <i class="ri-save-line me-1"></i>
                                    {{ $isEdit ? 'Salvar alterações' : 'Cadastrar funcionário' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
