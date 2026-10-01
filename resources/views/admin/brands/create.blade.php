@extends('layouts.app')

@section('title', 'Cadastrar Marca')

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            <!-- start page title -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Cadastrar Marca</h4>

                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('brands.index') }}">Marcas</a></li>
                                <li class="breadcrumb-item active">Cadastrar</li>
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
                                Informações da Marca
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
                            <form action="{{ route('brands.store') }}" method="POST" class="needs-validation" novalidate>
                                @csrf

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
                                                    id="name" name="name" value="{{ old('name') }}"
                                                    placeholder="Digite o nome da marca" required>
                                                <i class="ri-price-tag-3-line"></i>
                                            </div>
                                            @error('name')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                <i class="ri-information-line"></i> O identificador (slug) é gerado
                                                automaticamente a partir do nome
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
                                                    id="logo_url" name="logo_url" value="{{ old('logo_url') }}"
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
                                                    {{ old('active', true) ? 'checked' : '' }}>
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
                                </div>
                                <!--end row-->

                                <!-- Botões de Ação -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <a href="{{ route('brands.index') }}" class="btn btn-light">
                                                <i class="ri-close-line align-middle me-1"></i>
                                                Cancelar
                                            </a>
                                            <button type="reset" class="btn btn-secondary">
                                                <i class="ri-refresh-line align-middle me-1"></i>
                                                Limpar
                                            </button>
                                            <button type="submit" class="btn btn-success">
                                                <i class="ri-save-line align-middle me-1"></i>
                                                Salvar Marca
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>

                        </div><!-- end card-body -->
                    </div><!-- end card -->

                    <!-- Card de Ajuda -->
                    <div class="card border border-dashed border-info">
                        <div class="card-body">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="ri-information-line display-6 text-info"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5 class="fs-14 text-info">Informações Importantes</h5>
                                    <ul class="text-muted mb-0">
                                        <li>Os campos marcados com <span class="text-danger">*</span> são obrigatórios</li>
                                        <li>O nome da marca deve ser único</li>
                                        <li>Marcas também podem ser importadas automaticamente do ERP via
                                            sincronização</li>
                                        <li>Todos os dados podem ser editados posteriormente</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div><!-- end card -->

                </div>
                <!--end col-->
            </div>
            <!--end row-->

        </div> <!-- container-fluid -->
    </div><!-- End Page-content -->
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
