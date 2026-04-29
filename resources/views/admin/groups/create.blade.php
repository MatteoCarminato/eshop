@extends('layouts.app')
@section('title', 'Novo Grupo')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Novo Grupo</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('groups.index') }}">Grupos</a></li>
                                <li class="breadcrumb-item active">Novo</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Cadastrar Grupo</h4>
                            <div class="flex-shrink-0">
                                <a href="{{ route('groups.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="ri-arrow-left-line align-middle me-1"></i>
                                    Voltar
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            @if ($errors->any())
                                <div class="alert alert-danger alert-dismissible alert-border-left alert-label-icon fade show"
                                    role="alert">
                                    <i class="ri-error-warning-line label-icon"></i>
                                    <strong>Atenção!</strong> Corrija os erros abaixo:
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    <ul class="mb-0 mt-2">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <form action="{{ route('groups.store') }}" method="POST" class="needs-validation" novalidate>
                                @csrf
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nome <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                                        name="name" value="{{ old('name') }}" required maxlength="100">
                                    @error('name')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Descrição</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror"
                                        id="description" name="description" maxlength="255"
                                        rows="2">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="{{ route('groups.index') }}" class="btn btn-light">
                                        <i class="ri-close-line align-middle me-1"></i>
                                        Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="ri-save-line align-middle me-1"></i>
                                        Salvar Grupo
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection