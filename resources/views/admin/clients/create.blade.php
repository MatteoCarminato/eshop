@extends('layouts.app')

@section('title', 'Cadastrar Cliente')

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            <!-- start page title -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Cadastrar Cliente</h4>

                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">Clientes</a></li>
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
                                <i class="ri-user-add-line align-middle me-1"></i>
                                Informações do Cliente
                            </h4>
                            <div class="flex-shrink-0">
                                <a href="{{ route('clients.index') }}" class="btn btn-secondary btn-sm">
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
                            <form action="{{ route('clients.store') }}" method="POST" class="needs-validation" novalidate>
                                @csrf

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
                                                    id="name" name="name" value="{{ old('name') }}"
                                                    placeholder="Digite o nome completo" required>
                                                <i class="ri-user-line"></i>
                                            </div>
                                            @error('name')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                <i class="ri-information-line"></i> Nome completo do cliente
                                            </div>
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Email -->
                                    <div class="col-xxl-6 col-md-6">
                                        <div>
                                            <label for="email" class="form-label">
                                                E-mail
                                            </label>
                                            <div class="form-icon">
                                                <input type="email"
                                                    class="form-control form-control-icon @error('email') is-invalid @enderror"
                                                    id="email" name="email" value="{{ old('email') }}"
                                                    placeholder="exemplo@email.com">
                                                <i class="ri-mail-line"></i>
                                            </div>
                                            @error('email')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                <i class="ri-information-line"></i> Campo opcional - será usado para
                                                comunicação
                                            </div>
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Telefone -->
                                    <div class="col-xxl-6 col-md-6">
                                        <div>
                                            <label for="phone" class="form-label">
                                                Telefone <i class="ri-whatsapp-line text-success"></i>
                                            </label>
                                            <div class="form-icon">
                                                <input type="text"
                                                    class="form-control form-control-icon @error('phone') is-invalid @enderror"
                                                    id="phone" name="phone" value="{{ old('phone') }}"
                                                    placeholder="+595 21 234567 ou (61) 98765-4321" maxlength="25">
                                                <i class="ri-phone-line"></i>
                                            </div>
                                            @error('phone')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                <i class="ri-information-line"></i> Campo opcional - Aceita números do
                                                Brasil e Paraguai<br>
                                                <i class="ri-alert-line text-warning"></i> <small>Use formato correto com
                                                    DDD/código país para integração com WhatsApp</small>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Pontos de Spread -->
                                    <div class="col-xxl-6 col-md-6">
                                        <div>
                                            <label for="spread_points" class="form-label">
                                                Pontos de Spread <span class="text-muted fs-12">(centavos acima do
                                                    dólar)</span>
                                            </label>
                                            <div class="form-icon">
                                                <input type="number" min="0" max="9999"
                                                    class="form-control form-control-icon @error('spread_points') is-invalid @enderror"
                                                    id="spread_points" name="spread_points"
                                                    value="{{ old('spread_points', 0) }}"
                                                    placeholder="Ex: 10 = R$ 0,10 acima da taxa">
                                                <i class="ri-arrow-up-circle-line"></i>
                                            </div>
                                            @error('spread_points')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                <i class="ri-information-line"></i>
                                                Cada ponto = R$ 0,01 somado à taxa de câmbio do dia.
                                                Ex: 10 pontos + taxa R$ 5,00 = R$ 5,10 para este cliente.
                                            </div>
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Cliente de Câmbio -->
                                    <div class="col-12">
                                        <div class="d-flex align-items-center gap-3 p-3 rounded border bg-light">
                                            <div class="form-check form-switch mb-0">
                                                <input type="hidden" name="is_exchange_client" value="0">
                                                <input class="form-check-input" type="checkbox" role="switch"
                                                    id="is_exchange_client" name="is_exchange_client" value="1"
                                                    {{ old('is_exchange_client') ? 'checked' : '' }}>
                                                <label class="form-check-label fw-semibold" for="is_exchange_client">
                                                    Cliente de Câmbio
                                                </label>
                                            </div>
                                            <small class="text-muted">
                                                <i class="ri-information-line"></i>
                                                Ative para habilitar operações de câmbio (carteira, depósito, conversão) para este cliente.
                                            </small>
                                        </div>
                                    </div>
                                    <!--end col-->

                                    <!-- Tipo -->
                                    <div class="col-xxl-6 col-md-6">
                                        <div>
                                            <label for="type" class="form-label">
                                                Tipo <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select @error('type') is-invalid @enderror"
                                                id="type" name="type" required>
                                                <option value="cliente" {{ old('type', 'cliente') === 'cliente' ? 'selected' : '' }}>
                                                    Cliente
                                                </option>
                                                <option value="fornecedor" {{ old('type') === 'fornecedor' ? 'selected' : '' }}>
                                                    Fornecedor
                                                </option>
                                            </select>
                                            @error('type')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <!--end col-->
                                </div>
                                <!--end row-->

                                <!-- Botões de Ação -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <a href="{{ route('clients.index') }}" class="btn btn-light">
                                                <i class="ri-close-line align-middle me-1"></i>
                                                Cancelar
                                            </a>
                                            <button type="reset" class="btn btn-secondary">
                                                <i class="ri-refresh-line align-middle me-1"></i>
                                                Limpar
                                            </button>
                                            <button type="submit" class="btn btn-success">
                                                <i class="ri-save-line align-middle me-1"></i>
                                                Salvar Cliente
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
                                        <li>E-mail e telefone são opcionais, mas recomendados para contato</li>
                                        <li>Telefone aceita formatos do Brasil: (61) 98765-4321 ou Paraguai: +595 21 234567
                                        </li>
                                        <li>Use o formato correto com código de área para integração com WhatsApp</li>
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

        // Formatação de telefone para Brasil e Paraguai
        document.getElementById('phone').addEventListener('input', function (e) {
            let value = e.target.value;

            // Se começar com +, permite formato internacional (Paraguai: +595)
            if (value.startsWith('+')) {
                // Apenas remove caracteres não numéricos exceto o +
                e.target.value = value.replace(/[^\d+\s]/g, '');
            } else {
                // Formato Brasil
                value = value.replace(/\D/g, '');
                if (value.length <= 11) {
                    value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
                    value = value.replace(/(\d)(\d{4})$/, '$1-$2');
                }
                e.target.value = value;
            }
        });
    </script>
@endpush