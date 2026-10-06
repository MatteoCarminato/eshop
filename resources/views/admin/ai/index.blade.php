@extends('layouts.app')
@section('title', 'Verificar Comprovante PIX')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        {{-- Header --}}
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                    <h4 class="mb-sm-0">
                        <i class="mdi mdi-image-search-outline me-2 text-warning"></i>Verificar Comprovante PIX
                    </h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">Verificar Comprovante</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-xl-8 col-lg-10">

                {{-- Alert errors --}}
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                {{-- Verificação de comprovante duplicado --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="mdi mdi-image-search-outline me-2"></i>Verificar Comprovante Duplicado
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="{{ route('admin.ai.check-receipt') }}" enctype="multipart/form-data" id="checkForm">
                            @csrf

                            <div id="checkDropZone"
                                 class="border border-2 border-dashed rounded-3 p-5 text-center mb-4 position-relative"
                                 style="border-color: #fde68a !important; background: #fffbeb; cursor: pointer; transition: all 0.3s;">
                                <input type="file"
                                       name="file"
                                       id="checkFileInput"
                                       accept=".jpg,.jpeg,.png,.webp,.pdf"
                                       class="position-absolute top-0 start-0 w-100 h-100 opacity-0"
                                       style="cursor: pointer; z-index: 2;">

                                <div id="checkDropPlaceholder">
                                    <i class="mdi mdi-image-search-outline display-4 text-warning d-block mb-2"></i>
                                    <p class="mb-1 fw-semibold text-dark fs-15">Solte aqui o comprovante pra checar se já foi usado</p>
                                    <p class="text-muted mb-0 fs-13">Aceito: JPG, PNG, WEBP, PDF &mdash; Máximo 15MB</p>
                                </div>

                                <div id="checkFilePreview" class="d-none">
                                    <i class="mdi mdi-file-check-outline display-4 text-success d-block mb-2" id="checkPreviewIcon"></i>
                                    <p class="mb-1 fw-semibold text-dark fs-15" id="checkFileName"></p>
                                    <p class="text-muted mb-2 fs-13" id="checkFileSize"></p>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="checkClearFile">
                                        <i class="mdi mdi-close me-1"></i>Remover
                                    </button>
                                </div>
                            </div>

                            @error('file', 'checkReceipt')
                                <div class="text-danger small mb-3">
                                    <i class="mdi mdi-alert-circle-outline me-1"></i>{{ $message }}
                                </div>
                            @enderror

                            <div class="d-grid">
                                <button type="submit" class="btn btn-warning" id="checkSubmitBtn">
                                    <i class="mdi mdi-magnify me-2"></i>Checar se já foi usado
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Resultado da verificação --}}
                @if(session('receiptCheck'))
                    @php
                        $rc = session('receiptCheck');
                        $matches = collect($rc['matches']);
                    @endphp
                    <div class="card border-0 shadow-sm mb-4" id="checkResultCard">
                        @if($matches->isEmpty())
                            <div class="card-header" style="background: #ecfdf5;">
                                <h5 class="mb-0 text-success">
                                    <i class="mdi mdi-check-circle-outline me-2"></i>Comprovante inédito
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <strong>{{ $rc['filename'] }}</strong> não bate com nenhum comprovante já
                                registrado no sistema. Pode seguir com o depósito.
                            </div>
                        @else
                            <div class="card-header" style="background: #fef2f2;">
                                <h5 class="mb-0 text-danger">
                                    <i class="mdi mdi-alert-circle-outline me-2"></i>
                                    Esse comprovante já apareceu {{ $matches->count() }}
                                    {{ $matches->count() === 1 ? 'vez' : 'vezes' }}
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <p class="text-muted mb-3"><strong>{{ $rc['filename'] }}</strong> bate com a
                                    imagem destes registros:</p>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Data</th>
                                                <th>Status</th>
                                                <th>Pagador</th>
                                                <th>Valor</th>
                                                <th>Grupo / Cliente</th>
                                                <th>Virou depósito?</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($matches as $m)
                                                @php
                                                    $badge = match($m['status']) {
                                                        'confirmed' => 'success',
                                                        'duplicate' => 'warning',
                                                        'false_payment' => 'danger',
                                                        'unverified' => 'secondary',
                                                        default => 'light',
                                                    };
                                                    $label = match($m['status']) {
                                                        'confirmed' => 'Confirmado',
                                                        'duplicate' => 'Duplicado',
                                                        'false_payment' => 'Não localizado',
                                                        'unverified' => 'Não verificado',
                                                        default => $m['status'],
                                                    };
                                                @endphp
                                                <tr>
                                                    <td class="text-nowrap">{{ $m['created_at'] }}</td>
                                                    <td><span class="badge bg-{{ $badge }}-subtle text-{{ $badge }}">{{ $label }}</span></td>
                                                    <td>{{ $m['pix_nome'] ?? '—' }}</td>
                                                    <td>{{ $m['pix_valor'] ?? '—' }}</td>
                                                    <td>{{ $m['client_name'] ?? $m['group_name'] ?? '—' }}</td>
                                                    <td>
                                                        @if($m['transaction_id'])
                                                            <span class="badge bg-success-subtle text-success">Sim</span>
                                                        @else
                                                            <span class="badge bg-light text-muted">Não</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end text-nowrap">
                                                        <a href="{{ $m['image_url'] }}" target="_blank" rel="noopener"
                                                           class="btn btn-sm btn-outline-secondary" title="Ver imagem do comprovante">
                                                            <i class="mdi mdi-image-outline"></i>
                                                        </a>
                                                        @if($m['transaction_id'] && $m['client_id'])
                                                            <a href="{{ route('admin.wallet.client', $m['client_id']) }}#tx-{{ $m['transaction_id'] }}"
                                                               class="btn btn-sm btn-outline-primary" title="Ver no extrato do cliente">
                                                                <i class="mdi mdi-wallet-outline"></i>
                                                            </a>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    #checkDropZone:hover,
    #checkDropZone.dragover {
        border-color: #f59e0b !important;
        background: #fef3c7 !important;
    }
    .border-dashed {
        border-style: dashed !important;
    }
</style>
@endpush

@push('scripts')
<script>
function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// ---------- Verificar comprovante duplicado ----------
const checkDropZone = document.getElementById('checkDropZone');
const checkFileInput = document.getElementById('checkFileInput');
const checkDropPlaceholder = document.getElementById('checkDropPlaceholder');
const checkFilePreview = document.getElementById('checkFilePreview');
const checkFileNameEl = document.getElementById('checkFileName');
const checkFileSizeEl = document.getElementById('checkFileSize');
const checkPreviewIcon = document.getElementById('checkPreviewIcon');
const checkClearFileBtn = document.getElementById('checkClearFile');
const checkSubmitBtn = document.getElementById('checkSubmitBtn');
const checkForm = document.getElementById('checkForm');

function showCheckFile(file) {
    checkFileNameEl.textContent = file.name;
    checkFileSizeEl.textContent = formatBytes(file.size);
    const isPdf = file.type === 'application/pdf';
    checkPreviewIcon.className = isPdf
        ? 'mdi mdi-file-pdf-box display-4 text-danger d-block mb-2'
        : 'mdi mdi-file-image-outline display-4 text-primary d-block mb-2';
    checkDropPlaceholder.classList.add('d-none');
    checkFilePreview.classList.remove('d-none');
}

function clearCheckFile() {
    checkFileInput.value = '';
    checkDropPlaceholder.classList.remove('d-none');
    checkFilePreview.classList.add('d-none');
}

checkFileInput.addEventListener('change', function () {
    if (this.files.length > 0) showCheckFile(this.files[0]);
});

checkClearFileBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    clearCheckFile();
});

['dragenter', 'dragover'].forEach(evt => {
    checkDropZone.addEventListener(evt, e => {
        e.preventDefault();
        checkDropZone.classList.add('dragover');
    });
});

['dragleave', 'drop'].forEach(evt => {
    checkDropZone.addEventListener(evt, e => {
        e.preventDefault();
        checkDropZone.classList.remove('dragover');
    });
});

checkDropZone.addEventListener('drop', function (e) {
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const dt = new DataTransfer();
        dt.items.add(files[0]);
        checkFileInput.files = dt.files;
        showCheckFile(files[0]);
    }
});

checkForm.addEventListener('submit', function () {
    checkSubmitBtn.disabled = true;
    checkSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Checando...';
});

@if(session('receiptCheck'))
    document.getElementById('checkResultCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
@endif
</script>
@endpush
