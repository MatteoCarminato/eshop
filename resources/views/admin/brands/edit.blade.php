@extends('layouts.app')

@section('title', 'Editar Marca')

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            <!-- start page title -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Editar Marca</h4>

                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('brands.index') }}">Marcas</a></li>
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
                                <i class="ri-price-tag-3-line align-middle me-1"></i>
                                Editar Informações da Marca
                            </h4>
                            <div class="flex-shrink-0">
                                <a href="{{ route('brands.index') }}" class="btn btn-secondary btn-sm">
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
                            <form action="{{ route('brands.update', $brand) }}" method="POST" class="needs-validation"
                                novalidate>
                                @csrf
                                @method('PUT')

                                <div class="row gy-4">
                                    <!-- Nome -->
                                    <div class="col-xxl-6 col-md-6">
                                        <div>
                                            <label for="name" class="form-label">
                                                Nome da Marca <span class="text-danger">*</span>
                                            </label>
                                            <div class="form-icon">
                                                <input type="text"
                                                    class="form-control form-control-icon @error('name') is-invalid @enderror"
                                                    id="name" name="name" value="{{ old('name', $brand->name) }}"
                                                    placeholder="Digite o nome da marca" required>
                                                <i class="ri-price-tag-3-line"></i>
                                            </div>
                                            @error('name')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                <i class="ri-information-line"></i> Alterar o nome gera um novo
                                                identificador (slug) automaticamente
                                            </div>
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Logo -->
                                    <div class="col-xxl-6 col-md-6">
                                        <div>
                                            <label for="logo_url" class="form-label">
                                                URL do Logo
                                            </label>
                                            <div class="form-icon">
                                                <input type="text"
                                                    class="form-control form-control-icon @error('logo_url') is-invalid @enderror"
                                                    id="logo_url" name="logo_url"
                                                    value="{{ old('logo_url', $brand->logo_url) }}"
                                                    placeholder="https://exemplo.com/logo.png">
                                                <i class="ri-image-line"></i>
                                            </div>
                                            @error('logo_url')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                <i class="ri-information-line"></i> Campo opcional - informe a URL de
                                                uma imagem já hospedada
                                            </div>
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Ativo -->
                                    <div class="col-12">
                                        <div class="d-flex align-items-center gap-3 p-3 rounded border bg-light">
                                            <div class="form-check form-switch mb-0">
                                                <input type="hidden" name="active" value="0">
                                                <input class="form-check-input" type="checkbox" role="switch"
                                                    id="active" name="active" value="1"
                                                    {{ old('active', $brand->active) ? 'checked' : '' }}>
                                                <label class="form-check-label fw-semibold" for="active">
                                                    Marca Ativa
                                                </label>
                                            </div>
                                            <small class="text-muted">
                                                <i class="ri-information-line"></i>
                                                Marcas inativas não são exibidas para seleção em novos produtos.
                                            </small>
                                        </div>
                                    </div>
                                    <!--end col-->

                                    @if ($brand->recno)
                                        <div class="col-12">
                                            <div class="alert alert-info">
                                                <div class="d-flex">
                                                    <div class="flex-shrink-0">
                                                        <i class="ri-cloud-line fs-16 align-middle"></i>
                                                    </div>
                                                    <div class="flex-grow-1 ms-2">
                                                        <strong>Origem: ERP</strong>
                                                        <ul class="mb-0 mt-2">
                                                            <li>RECNO: {{ $brand->recno }}</li>
                                                            @if ($brand->synced_at)
                                                                <li>Sincronizado em:
                                                                    {{ $brand->synced_at->displayTz()->format('d/m/Y H:i') }}</li>
                                                            @endif
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!--end col-->
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
                                                        <li>Cadastrado em: {{ $brand->created_at->displayTz()->format('d/m/Y H:i') }}
                                                        </li>
                                                        <li>Última atualização:
                                                            {{ $brand->updated_at->displayTz()->format('d/m/Y H:i') }}
                                                        </li>
                                                        <li>ID: #{{ $brand->id }}</li>
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
                                                Excluir Marca
                                            </button>

                                            <div class="d-flex gap-2">
                                                <a href="{{ route('brands.index') }}" class="btn btn-light">
                                                    <i class="ri-close-line align-middle me-1"></i>
                                                    Cancelar
                                                </a>
                                                <button type="submit" class="btn btn-success">
                                                    <i class="ri-save-line align-middle me-1"></i>
                                                    Atualizar Marca
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
                            Você está prestes a excluir a marca <strong>{{ $brand->name }}</strong>.
                            Esta ação não poderá ser desfeita!
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="ri-close-line me-1"></i>
                        Cancelar
                    </button>
                    <form action="{{ route('brands.destroy', $brand) }}" method="POST" class="d-inline">
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
