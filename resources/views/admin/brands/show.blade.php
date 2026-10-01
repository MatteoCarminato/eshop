@extends('layouts.app')

@section('title', 'Marca')

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Marca</h4>

                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('brands.index') }}">Marcas</a></li>
                                <li class="breadcrumb-item active">Marca</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">
                                <i class="ri-price-tag-3-line align-middle me-1"></i>
                                Informações da Marca
                            </h4>
                            <div class="flex-shrink-0 d-flex gap-2">
                                <a href="{{ route('brands.edit', $brand) }}" class="btn btn-warning btn-sm">
                                    <i class="ri-pencil-line align-middle me-1"></i>
                                    Editar
                                </a>
                                <a href="{{ route('brands.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="ri-arrow-left-line align-middle me-1"></i>
                                    Voltar
                                </a>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row gy-4">
                                @if ($brand->logo_url)
                                    <div class="col-12">
                                        <img src="{{ $brand->logo_url }}" alt="{{ $brand->name }}"
                                            class="img-thumbnail" style="max-height: 120px; object-fit: contain;">
                                    </div>
                                @endif

                                <div class="col-xxl-6 col-md-6">
                                    <div>
                                        <label class="form-label">Nome</label>
                                        <input type="text" class="form-control" value="{{ $brand->name }}" disabled>
                                    </div>
                                </div>

                                <div class="col-xxl-6 col-md-6">
                                    <div>
                                        <label class="form-label">Situação</label>
                                        <div>
                                            @if ($brand->active)
                                                <span class="badge bg-success-subtle text-success fs-12">Ativo</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger fs-12">Inativo</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="col-xxl-6 col-md-6">
                                    <div>
                                        <label class="form-label">Origem</label>
                                        <div>
                                            @if ($brand->recno)
                                                <span class="badge bg-info-subtle text-info fs-12">ERP</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary fs-12">Site</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="col-xxl-6 col-md-6">
                                    <div>
                                        <label class="form-label">Produtos</label>
                                        <div>
                                            @php
                                                $productsCount = class_exists(\App\Models\Product::class)
                                                    ? $brand->products()->count()
                                                    : 0;
                                            @endphp
                                            <a href="{{ route('products.index', ['brand' => $brand->id]) }}">
                                                {{ $productsCount }} produto(s)
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0">
                                                <i class="ri-information-line fs-16 align-middle"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-2">
                                                <strong>Informações do Registro:</strong>
                                                <ul class="mb-0 mt-2">
                                                    @if ($brand->recno)
                                                        <li>RECNO (ERP): {{ $brand->recno }}</li>
                                                        @if ($brand->synced_at)
                                                            <li>Sincronizado do ERP em:
                                                                {{ $brand->synced_at->format('d/m/Y H:i') }}</li>
                                                        @endif
                                                    @else
                                                        <li>Marca criada diretamente no site (sem vínculo com o ERP)
                                                        </li>
                                                    @endif
                                                    <li>Slug: {{ $brand->slug }}</li>
                                                    <li>Cadastrado em: {{ $brand->created_at->format('d/m/Y H:i') }}
                                                    </li>
                                                    <li>Última atualização:
                                                        {{ $brand->updated_at->format('d/m/Y H:i') }}
                                                    </li>
                                                    <li>ID: #{{ $brand->id }}</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
