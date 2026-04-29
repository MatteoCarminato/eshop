@extends('layouts.app')

@section('title', 'Título da Página')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                    <h4 class="mb-sm-0">Título Principal</h4>

                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="#">Módulo</a></li>
                            <li class="breadcrumb-item active">Ação</li>
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
                            <i class="ri-file-add-line align-middle me-1"></i>
                            Título do Card
                        </h4>
                        <div class="flex-shrink-0">
                            <a href="#" class="btn btn-secondary btn-sm">
                                <i class="ri-arrow-left-line align-middle me-1"></i>
                                Voltar
                            </a>
                        </div>
                    </div><!-- end card header -->

                    <div class="card-body">
                        <!-- Mensagens de Sucesso -->
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible alert-border-left alert-label-icon fade show" role="alert">
                                <i class="ri-check-double-line label-icon"></i>
                                <strong>Sucesso!</strong> {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <!-- Mensagens de Erro -->
                        @if ($errors->any())
                            <div class="alert alert-danger alert-dismissible alert-border-left alert-label-icon fade show" role="alert">
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
                        <form action="#" method="POST" class="needs-validation" novalidate>
                            @csrf
                            {{-- @method('PUT') <!-- Para edição --> --}}

                            <div class="row gy-4">
                                
                                <!-- Campo de Texto Simples -->
                                <div class="col-xxl-6 col-md-6">
                                    <div>
                                        <label for="field_text" class="form-label">
                                            Campo Texto <span class="text-danger">*</span>
                                        </label>
                                        <input 
                                            type="text" 
                                            class="form-control @error('field_text') is-invalid @enderror" 
                                            id="field_text" 
                                            name="field_text"
                                            value="{{ old('field_text') }}"
                                            placeholder="Digite aqui"
                                            required
                                        >
                                        @error('field_text')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">
                                            <i class="ri-information-line"></i> Texto de ajuda
                                        </div>
                                    </div>
                                </div>
                                <!--end col-->

                                <!-- Campo com Ícone -->
                                <div class="col-xxl-6 col-md-6">
                                    <div>
                                        <label for="field_icon" class="form-label">
                                            Campo com Ícone <span class="text-danger">*</span>
                                        </label>
                                        <div class="form-icon">
                                            <input 
                                                type="text" 
                                                class="form-control form-control-icon @error('field_icon') is-invalid @enderror" 
                                                id="field_icon" 
                                                name="field_icon"
                                                value="{{ old('field_icon') }}"
                                                placeholder="Digite aqui"
                                                required
                                            >
                                            <i class="ri-pencil-line"></i>
                                        </div>
                                        @error('field_icon')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <!--end col-->

                                <!-- Email -->
                                <div class="col-xxl-6 col-md-6">
                                    <div>
                                        <label for="email" class="form-label">
                                            E-mail <span class="text-danger">*</span>
                                        </label>
                                        <div class="form-icon">
                                            <input 
                                                type="email" 
                                                class="form-control form-control-icon @error('email') is-invalid @enderror" 
                                                id="email" 
                                                name="email"
                                                value="{{ old('email') }}"
                                                placeholder="exemplo@email.com"
                                                required
                                            >
                                            <i class="ri-mail-line"></i>
                                        </div>
                                        @error('email')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <!--end col-->

                                <!-- Telefone -->
                                <div class="col-xxl-6 col-md-6">
                                    <div>
                                        <label for="phone" class="form-label">Telefone</label>
                                        <div class="form-icon">
                                            <input 
                                                type="text" 
                                                class="form-control form-control-icon @error('phone') is-invalid @enderror" 
                                                id="phone" 
                                                name="phone"
                                                value="{{ old('phone') }}"
                                                placeholder="(00) 00000-0000"
                                            >
                                            <i class="ri-phone-line"></i>
                                        </div>
                                        @error('phone')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <!--end col-->

                                <!-- Select -->
                                <div class="col-xxl-6 col-md-6">
                                    <div>
                                        <label for="select_field" class="form-label">
                                            Selecione <span class="text-danger">*</span>
                                        </label>
                                        <select 
                                            class="form-select @error('select_field') is-invalid @enderror" 
                                            id="select_field" 
                                            name="select_field"
                                            required
                                        >
                                            <option value="">Escolha uma opção</option>
                                            <option value="1" {{ old('select_field') == '1' ? 'selected' : '' }}>Opção 1</option>
                                            <option value="2" {{ old('select_field') == '2' ? 'selected' : '' }}>Opção 2</option>
                                        </select>
                                        @error('select_field')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <!--end col-->

                                <!-- Data -->
                                <div class="col-xxl-6 col-md-6">
                                    <div>
                                        <label for="date_field" class="form-label">Data</label>
                                        <input 
                                            type="date" 
                                            class="form-control @error('date_field') is-invalid @enderror" 
                                            id="date_field" 
                                            name="date_field"
                                            value="{{ old('date_field') }}"
                                        >
                                        @error('date_field')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <!--end col-->

                                <!-- Textarea -->
                                <div class="col-12">
                                    <div>
                                        <label for="textarea_field" class="form-label">Observações</label>
                                        <textarea 
                                            class="form-control @error('textarea_field') is-invalid @enderror" 
                                            id="textarea_field" 
                                            name="textarea_field"
                                            rows="3"
                                            placeholder="Digite suas observações aqui"
                                        >{{ old('textarea_field') }}</textarea>
                                        @error('textarea_field')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <!--end col-->

                                <!-- Checkbox -->
                                <div class="col-12">
                                    <div class="form-check">
                                        <input 
                                            class="form-check-input @error('checkbox_field') is-invalid @enderror" 
                                            type="checkbox" 
                                            id="checkbox_field" 
                                            name="checkbox_field"
                                            value="1"
                                            {{ old('checkbox_field') ? 'checked' : '' }}
                                        >
                                        <label class="form-check-label" for="checkbox_field">
                                            Aceito os termos e condições
                                        </label>
                                        @error('checkbox_field')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <!--end col-->

                                <!-- Radio Buttons -->
                                <div class="col-12">
                                    <label class="form-label">Tipo</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input 
                                                class="form-check-input" 
                                                type="radio" 
                                                name="radio_field" 
                                                id="radio1" 
                                                value="option1"
                                                {{ old('radio_field') == 'option1' ? 'checked' : '' }}
                                            >
                                            <label class="form-check-label" for="radio1">
                                                Opção 1
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input 
                                                class="form-check-input" 
                                                type="radio" 
                                                name="radio_field" 
                                                id="radio2" 
                                                value="option2"
                                                {{ old('radio_field') == 'option2' ? 'checked' : '' }}
                                            >
                                            <label class="form-check-label" for="radio2">
                                                Opção 2
                                            </label>
                                        </div>
                                    </div>
                                    @error('radio_field')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <!--end col-->

                            </div>
                            <!--end row-->

                            <!-- Botões de Ação -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <a href="#" class="btn btn-light">
                                            <i class="ri-close-line align-middle me-1"></i>
                                            Cancelar
                                        </a>
                                        <button type="reset" class="btn btn-secondary">
                                            <i class="ri-refresh-line align-middle me-1"></i>
                                            Limpar
                                        </button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="ri-save-line align-middle me-1"></i>
                                            Salvar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>

                    </div><!-- end card-body -->
                </div><!-- end card -->

                <!-- Card de Ajuda (Opcional) -->
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
                                    <li>Informação adicional importante</li>
                                    <li>Mais uma dica útil</li>
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

@push('styles')
<!-- Adicione estilos customizados aqui se necessário -->
@endpush

@push('scripts')
<script>
    // Validação do formulário Bootstrap
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

    // Máscara para telefone (exemplo simples)
    document.getElementById('phone')?.addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length <= 11) {
            value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
            value = value.replace(/(\d)(\d{4})$/, '$1-$2');
        }
        e.target.value = value;
    });

    // Auto-hide alerts após 5 segundos
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
</script>
@endpush
