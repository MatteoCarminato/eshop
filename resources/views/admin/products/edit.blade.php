@extends('layouts.app')

@section('title', 'Editar Produto')

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            <!-- start page title -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Editar Produto</h4>

                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Produtos</a></li>
                                <li class="breadcrumb-item active">Editar</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end page title -->

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">
                                <i class="ri-shopping-bag-line align-middle me-1"></i>
                                Editar Informações do Produto
                            </h4>
                            <div class="flex-shrink-0">
                                <a href="{{ route('products.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="ri-arrow-left-line align-middle me-1"></i>
                                    Voltar
                                </a>
                            </div>
                        </div><!-- end card header -->

                        <div class="card-body">
                            <!-- Mensagens de Erro -->
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

                            <!-- Formulário -->
                            <form action="{{ route('products.update', $product) }}" method="POST"
                                class="needs-validation" novalidate>
                                @csrf
                                @method('PUT')

                                <div class="row gy-4">
                                    <!-- Nome -->
                                    <div class="col-xxl-6 col-md-6">
                                        <div>
                                            <label for="name" class="form-label">
                                                Nome Completo <span class="text-danger">*</span>
                                            </label>
                                            <div class="form-icon">
                                                <input type="text"
                                                    class="form-control form-control-icon @error('name') is-invalid @enderror"
                                                    id="name" name="name" value="{{ old('name', $product->name) }}"
                                                    placeholder="Digite o nome completo do produto" required>
                                                <i class="ri-shopping-bag-line"></i>
                                            </div>
                                            @error('name')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                <i class="ri-information-line"></i> Nome longo, exibido nas telas
                                                internas (equivalente ao NOMELONG do ERP)
                                            </div>
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Nome curto -->
                                    <div class="col-xxl-6 col-md-6">
                                        <div>
                                            <label for="short_name" class="form-label">
                                                Nome Curto
                                            </label>
                                            <div class="form-icon">
                                                <input type="text"
                                                    class="form-control form-control-icon @error('short_name') is-invalid @enderror"
                                                    id="short_name" name="short_name"
                                                    value="{{ old('short_name', $product->short_name) }}"
                                                    placeholder="Nome resumido para listagens">
                                                <i class="ri-price-tag-3-line"></i>
                                            </div>
                                            @error('short_name')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                <i class="ri-information-line"></i> Campo opcional (equivalente ao
                                                NOMPRO do ERP)
                                            </div>
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Marca -->
                                    <div class="col-xxl-6 col-md-6">
                                        <div>
                                            <label for="brand_id" class="form-label">
                                                Marca
                                            </label>
                                            <select class="form-select @error('brand_id') is-invalid @enderror"
                                                id="brand_id" name="brand_id">
                                                <option value="">— Sem marca —</option>
                                                @foreach ($brands as $b)
                                                    <option value="{{ $b->id }}" {{ (string) old('brand_id', $product->brand_id) === (string) $b->id ? 'selected' : '' }}>
                                                        {{ $b->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('brand_id')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Categoria -->
                                    <div class="col-xxl-6 col-md-6">
                                        <div>
                                            <label for="category_id" class="form-label">
                                                Categoria
                                            </label>
                                            <select class="form-select @error('category_id') is-invalid @enderror"
                                                id="category_id" name="category_id">
                                                <option value="">— Sem categoria —</option>
                                                @foreach ($categories as $c)
                                                    <option value="{{ $c->id }}" {{ (string) old('category_id', $product->category_id) === (string) $c->id ? 'selected' : '' }}>
                                                        {{ $c->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('category_id')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Preço -->
                                    <div class="col-xxl-6 col-md-6">
                                        <div>
                                            <label for="price" class="form-label">
                                                Preço <span class="text-danger">*</span>
                                            </label>
                                            <div class="form-icon">
                                                <input type="number" step="0.01" min="0"
                                                    class="form-control form-control-icon @error('price') is-invalid @enderror"
                                                    id="price" name="price" value="{{ old('price', $product->price) }}"
                                                    placeholder="0.00" required>
                                                <i class="ri-money-dollar-circle-line"></i>
                                            </div>
                                            @error('price')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                <i class="ri-information-line"></i> Preço principal de venda
                                            </div>
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Preços (subseção) -->
                                    <div class="col-12">
                                        <h5 class="fs-14 text-muted text-uppercase mb-0 mt-2">Preços</h5>
                                        <p class="text-muted fs-12 mb-0">
                                            Campos opcionais, normalmente sincronizados automaticamente pelo ERP.
                                        </p>
                                    </div>

                                    <div class="col-xxl-3 col-md-6">
                                        <div>
                                            <label for="wholesale_price" class="form-label">Preço de Atacado</label>
                                            <input type="number" step="0.01" min="0"
                                                class="form-control @error('wholesale_price') is-invalid @enderror"
                                                id="wholesale_price" name="wholesale_price"
                                                value="{{ old('wholesale_price', $product->wholesale_price) }}"
                                                placeholder="0.00">
                                            @error('wholesale_price')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">Opcional — venda em quantidade</div>
                                        </div>
                                    </div>

                                    <div class="col-xxl-3 col-md-6">
                                        <div>
                                            <label for="web_price" class="form-label">Preço Web</label>
                                            <input type="number" step="0.01" min="0"
                                                class="form-control @error('web_price') is-invalid @enderror"
                                                id="web_price" name="web_price"
                                                value="{{ old('web_price', $product->web_price) }}" placeholder="0.00">
                                            @error('web_price')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">Opcional — preço exibido na loja online</div>
                                        </div>
                                    </div>

                                    <div class="col-xxl-3 col-md-6">
                                        <div>
                                            <label for="sale_price" class="form-label">Preço Promocional</label>
                                            <input type="number" step="0.01" min="0"
                                                class="form-control @error('sale_price') is-invalid @enderror"
                                                id="sale_price" name="sale_price"
                                                value="{{ old('sale_price', $product->sale_price) }}" placeholder="0.00">
                                            @error('sale_price')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">Opcional — usado em promoções</div>
                                        </div>
                                    </div>

                                    <div class="col-xxl-3 col-md-6">
                                        <div>
                                            <label for="min_price" class="form-label">Preço Mínimo</label>
                                            <input type="number" step="0.01" min="0"
                                                class="form-control @error('min_price') is-invalid @enderror"
                                                id="min_price" name="min_price"
                                                value="{{ old('min_price', $product->min_price) }}" placeholder="0.00">
                                            @error('min_price')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">Opcional — piso permitido para descontos</div>
                                        </div>
                                    </div>
                                    <!--end preços-->

                                    <!-- Estoque -->
                                    <div class="col-xxl-6 col-md-6">
                                        <div>
                                            <label for="stock" class="form-label">
                                                Estoque
                                            </label>
                                            <div class="form-icon">
                                                <input type="number" step="0.0001" min="0"
                                                    class="form-control form-control-icon @error('stock') is-invalid @enderror"
                                                    id="stock" name="stock" value="{{ old('stock', $product->stock) }}"
                                                    placeholder="0">
                                                <i class="ri-archive-line"></i>
                                            </div>
                                            @error('stock')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                <i class="ri-information-line"></i> Campo opcional, normalmente
                                                sincronizado pelo ERP
                                            </div>
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Imagem -->
                                    <div class="col-xxl-6 col-md-6">
                                        <div>
                                            <label for="image_url" class="form-label">
                                                Imagem (URL)
                                            </label>
                                            <div class="form-icon">
                                                <input type="text"
                                                    class="form-control form-control-icon @error('image_url') is-invalid @enderror"
                                                    id="image_url" name="image_url"
                                                    value="{{ old('image_url', $product->image_url) }}"
                                                    placeholder="https://exemplo.com/imagem.jpg">
                                                <i class="ri-image-line"></i>
                                            </div>
                                            @error('image_url')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                <i class="ri-information-line"></i> Campo opcional — informe a URL de
                                                uma imagem já hospedada
                                            </div>
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Descrição -->
                                    <div class="col-12">
                                        <div>
                                            <label for="description" class="form-label">
                                                Descrição
                                            </label>
                                            <textarea class="form-control @error('description') is-invalid @enderror"
                                                id="description" name="description" rows="4"
                                                placeholder="Descrição do produto">{{ old('description', $product->description) }}</textarea>
                                            @error('description')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Ativo -->
                                    <div class="col-xxl-6 col-md-6">
                                        <div class="d-flex align-items-center gap-3 p-3 rounded border bg-light">
                                            <div class="form-check form-switch mb-0">
                                                <input type="hidden" name="active" value="0">
                                                <input class="form-check-input" type="checkbox" role="switch"
                                                    id="active" name="active" value="1"
                                                    {{ old('active', $product->active) ? 'checked' : '' }}>
                                                <label class="form-check-label fw-semibold" for="active">
                                                    Ativo
                                                </label>
                                            </div>
                                            <small class="text-muted">
                                                <i class="ri-information-line"></i>
                                                Produtos inativos não aparecem na loja.
                                            </small>
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Destaque -->
                                    <div class="col-xxl-6 col-md-6">
                                        <div class="d-flex align-items-center gap-3 p-3 rounded border bg-light">
                                            <div class="form-check form-switch mb-0">
                                                <input type="hidden" name="featured" value="0">
                                                <input class="form-check-input" type="checkbox" role="switch"
                                                    id="featured" name="featured" value="1"
                                                    {{ old('featured', $product->featured) ? 'checked' : '' }}>
                                                <label class="form-check-label fw-semibold" for="featured">
                                                    Destaque
                                                </label>
                                            </div>
                                            <small class="text-muted">
                                                <i class="ri-information-line"></i>
                                                Ative para destacar este produto na vitrine.
                                            </small>
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <div class="col-12">
                                        <div class="alert alert-info">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0">
                                                    <i class="ri-information-line fs-16 align-middle"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-2">
                                                    <strong>Informações do Registro:</strong>
                                                    <ul class="mb-0 mt-2">
                                                        <li>Cadastrado em:
                                                            {{ $product->created_at->format('d/m/Y H:i') }}</li>
                                                        <li>Última atualização:
                                                            {{ $product->updated_at->format('d/m/Y H:i') }}</li>
                                                        <li>ID: #{{ $product->id }}</li>
                                                        <li>
                                                            Origem:
                                                            {{ $product->recno ? 'ERP (RECNO ' . $product->recno . ')' : 'Cadastrado manualmente' }}
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end col-->
                                </div>
                                <!--end row-->

                                <!-- Botões de Ação -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="d-flex gap-2 justify-content-between">
                                            <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                                                data-bs-target="#deleteModal">
                                                <i class="ri-delete-bin-line align-middle me-1"></i>
                                                Excluir Produto
                                            </button>

                                            <div class="d-flex gap-2">
                                                <a href="{{ route('products.index') }}" class="btn btn-light">
                                                    <i class="ri-close-line align-middle me-1"></i>
                                                    Cancelar
                                                </a>
                                                <button type="submit" class="btn btn-success">
                                                    <i class="ri-save-line align-middle me-1"></i>
                                                    Atualizar Produto
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>

                        </div><!-- end card-body -->
                    </div><!-- end card -->

                </div>
                <!--end col-->
            </div>
            <!--end row-->

        </div> <!-- container-fluid -->
    </div><!-- End Page-content -->

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title text-white" id="deleteModalLabel">
                        <i class="ri-error-warning-line me-1"></i>
                        Confirmar Exclusão
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="ri-delete-bin-line display-4 text-danger"></i>
                        <h4 class="mt-3">Tem certeza?</h4>
                        <p class="text-muted">
                            Você está prestes a excluir o produto <strong>{{ $product->name }}</strong>.
                            Esta ação não poderá ser desfeita!
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="ri-close-line me-1"></i>
                        Cancelar
                    </button>
                    <form action="{{ route('products.destroy', $product) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="ri-delete-bin-line me-1"></i>
                            Sim, Excluir
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Validação do formulário
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
@endpush
