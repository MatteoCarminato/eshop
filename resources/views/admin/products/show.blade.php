@extends('layouts.app')

@section('title', 'Produto')

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Produto</h4>

                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Produtos</a></li>
                                <li class="breadcrumb-item active">Produto</li>
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
                                <i class="ri-shopping-bag-line align-middle me-1"></i>
                                Informações do Produto
                            </h4>
                            <div class="flex-shrink-0 d-flex gap-2">
                                <a href="{{ route('products.edit', $product) }}" class="btn btn-warning btn-sm">
                                    <i class="ri-pencil-line align-middle me-1"></i>
                                    Editar
                                </a>
                                <a href="{{ route('products.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="ri-arrow-left-line align-middle me-1"></i>
                                    Voltar
                                </a>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row gy-4">
                                @if ($product->image_url)
                                    <div class="col-12">
                                        <label class="form-label d-block">Foto principal</label>
                                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                                            class="rounded" style="max-height: 180px;">
                                    </div>
                                @endif

                                @if ($product->images->isNotEmpty())
                                    <div class="col-12">
                                        <label class="form-label d-block">Galeria de fotos</label>
                                        <div class="d-flex flex-wrap gap-3">
                                            @foreach ($product->images as $galleryImage)
                                                <img src="{{ $galleryImage->url }}" alt="{{ $product->name }}"
                                                    class="rounded border" style="height: 100px; width: 100px; object-fit: contain;">
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="col-xxl-6 col-md-6">
                                    <label class="form-label">Nome Completo</label>
                                    <input type="text" class="form-control" value="{{ $product->name }}" disabled>
                                </div>

                                <div class="col-xxl-6 col-md-6">
                                    <label class="form-label">Nome Curto</label>
                                    <input type="text" class="form-control" value="{{ $product->short_name ?? '—' }}"
                                        disabled>
                                </div>

                                <div class="col-xxl-6 col-md-6">
                                    <label class="form-label">Marca</label>
                                    <input type="text" class="form-control" value="{{ $product->brand->name ?? '—' }}"
                                        disabled>
                                </div>

                                <div class="col-xxl-6 col-md-6">
                                    <label class="form-label">Preço</label>
                                    <input type="text" class="form-control" value="{{ $product->formatted_price }}"
                                        disabled>
                                </div>

                                <div class="col-xxl-3 col-md-6">
                                    <label class="form-label">Preço de Atacado</label>
                                    <input type="text" class="form-control"
                                        value="{{ $product->wholesale_price !== null ? 'R$ ' . number_format((float) $product->wholesale_price, 2, ',', '.') : '—' }}"
                                        disabled>
                                </div>

                                <div class="col-xxl-3 col-md-6">
                                    <label class="form-label">Preço Web</label>
                                    <input type="text" class="form-control"
                                        value="{{ $product->web_price !== null ? 'R$ ' . number_format((float) $product->web_price, 2, ',', '.') : '—' }}"
                                        disabled>
                                </div>

                                <div class="col-xxl-3 col-md-6">
                                    <label class="form-label">Preço Promocional</label>
                                    <input type="text" class="form-control"
                                        value="{{ $product->sale_price !== null ? 'R$ ' . number_format((float) $product->sale_price, 2, ',', '.') : '—' }}"
                                        disabled>
                                </div>

                                <div class="col-xxl-3 col-md-6">
                                    <label class="form-label">Preço Mínimo</label>
                                    <input type="text" class="form-control"
                                        value="{{ $product->min_price !== null ? 'R$ ' . number_format((float) $product->min_price, 2, ',', '.') : '—' }}"
                                        disabled>
                                </div>

                                <div class="col-xxl-6 col-md-6">
                                    <label class="form-label">Estoque</label>
                                    <input type="text" class="form-control"
                                        value="{{ number_format((float) $product->stock, 4, ',', '.') }}" disabled>
                                </div>

                                <div class="col-xxl-6 col-md-6">
                                    <label class="form-label">Status</label>
                                    <div>
                                        @if ($product->active)
                                            <span class="badge bg-success-subtle text-success fs-12">Ativo</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger fs-12">Inativo</span>
                                        @endif
                                        @if ($product->featured)
                                            <span class="badge bg-warning-subtle text-warning fs-12">Destaque</span>
                                        @endif
                                    </div>
                                </div>

                                @if ($product->description)
                                    <div class="col-12">
                                        <label class="form-label">Descrição</label>
                                        <textarea class="form-control" rows="4" disabled>{{ $product->description }}</textarea>
                                    </div>
                                @endif

                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0">
                                                <i class="ri-information-line fs-16 align-middle"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-2">
                                                <strong>Informações do Registro:</strong>
                                                <ul class="mb-0 mt-2">
                                                    <li>ID: #{{ $product->id }}</li>
                                                    <li>Cadastrado em: {{ $product->created_at->format('d/m/Y H:i') }}
                                                    </li>
                                                    <li>Última atualização:
                                                        {{ $product->updated_at->format('d/m/Y H:i') }}
                                                    </li>
                                                    @if ($product->recno)
                                                        <li>Origem: ERP — RECNO {{ $product->recno }}</li>
                                                        <li>
                                                            Sincronizado em:
                                                            {{ $product->synced_at ? $product->synced_at->format('d/m/Y H:i') : '—' }}
                                                        </li>
                                                    @else
                                                        <li>Origem: Cadastrado manualmente</li>
                                                    @endif
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
