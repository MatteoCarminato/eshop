@extends('layouts.app')
@section('title', 'Análise de Extrato PIX com IA')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        {{-- Header --}}
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                    <h4 class="mb-sm-0">
                        <i class="mdi mdi-robot-outline me-2 text-primary"></i>Análise de Extrato PIX com IA
                    </h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">Análise IA</li>
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

                {{-- Upload Card --}}
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="mdi mdi-cloud-upload-outline me-2"></i>Enviar Extrato para Análise
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="{{ route('admin.ai.analyze') }}" enctype="multipart/form-data" id="analyzeForm">
                            @csrf

                            {{-- Drop Zone --}}
                            <div id="dropZone"
                                 class="border border-2 border-dashed rounded-3 p-5 text-center mb-4 position-relative"
                                 style="border-color: #c7d2fe !important; background: #f8f9ff; cursor: pointer; transition: all 0.3s;">
                                <input type="file"
                                       name="file"
                                       id="fileInput"
                                       accept=".jpg,.jpeg,.png,.webp,.pdf"
                                       class="position-absolute top-0 start-0 w-100 h-100 opacity-0"
                                       style="cursor: pointer; z-index: 2;">

                                <div id="dropPlaceholder">
                                    <i class="mdi mdi-file-upload-outline display-4 text-primary d-block mb-2"></i>
                                    <p class="mb-1 fw-semibold text-dark fs-15">Arraste e solte ou clique para selecionar</p>
                                    <p class="text-muted mb-0 fs-13">Aceito: JPG, PNG, WEBP, PDF &mdash; Máximo 15MB</p>
                                </div>

                                <div id="filePreview" class="d-none">
                                    <i class="mdi mdi-file-check-outline display-4 text-success d-block mb-2" id="previewIcon"></i>
                                    <p class="mb-1 fw-semibold text-dark fs-15" id="fileName"></p>
                                    <p class="text-muted mb-2 fs-13" id="fileSize"></p>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="clearFile">
                                        <i class="mdi mdi-close me-1"></i>Remover
                                    </button>
                                </div>
                            </div>

                            @error('file')
                                <div class="text-danger small mb-3">
                                    <i class="mdi mdi-alert-circle-outline me-1"></i>{{ $message }}
                                </div>
                            @enderror

                            {{-- Info Box --}}
                            <div class="alert alert-info border-0 d-flex align-items-start gap-2 mb-4">
                                <i class="mdi mdi-information-outline fs-18 mt-1 flex-shrink-0"></i>
                                <div class="fs-13">
                                    <strong>Como funciona:</strong> O arquivo será enviado ao GPT-4o-mini que irá
                                    identificar e organizar todas as transações PIX, calculando entradas, saídas e
                                    saldo do período automaticamente.
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="mdi mdi-robot-outline me-2"></i>Analisar com IA
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Result Card --}}
                @if(session('result'))
                    <div class="card border-0 shadow-sm" id="resultCard">
                        <div class="card-header d-flex align-items-center justify-content-between" style="background: #ecfdf5;">
                            <h5 class="mb-0 text-success">
                                <i class="mdi mdi-check-circle-outline me-2"></i>Resultado da Análise
                            </h5>
                            <div class="d-flex align-items-center gap-2">
                                @if(session('filename'))
                                    <span class="badge bg-success-subtle text-success">
                                        <i class="mdi mdi-file me-1"></i>{{ session('filename') }}
                                    </span>
                                @endif
                                @if(session('usage'))
                                    <span class="badge bg-light text-muted" title="Tokens usados">
                                        <i class="mdi mdi-lightning-bolt me-1"></i>{{ session('usage')['total_tokens'] ?? '?' }} tokens
                                    </span>
                                @endif
                                <button class="btn btn-sm btn-outline-secondary" onclick="copyResult()" title="Copiar resultado">
                                    <i class="mdi mdi-content-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <div id="aiResult" class="ai-result-content">
                                {!! nl2br(e(session('result'))) !!}
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    #dropZone:hover,
    #dropZone.dragover {
        border-color: #6366f1 !important;
        background: #eef2ff !important;
    }
    .ai-result-content {
        font-size: 14px;
        line-height: 1.8;
        white-space: pre-wrap;
        color: #374151;
        font-family: inherit;
    }
    .border-dashed {
        border-style: dashed !important;
    }
</style>
@endpush

@push('scripts')
<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const dropPlaceholder = document.getElementById('dropPlaceholder');
const filePreview = document.getElementById('filePreview');
const fileNameEl = document.getElementById('fileName');
const fileSizeEl = document.getElementById('fileSize');
const previewIcon = document.getElementById('previewIcon');
const clearFileBtn = document.getElementById('clearFile');
const submitBtn = document.getElementById('submitBtn');
const analyzeForm = document.getElementById('analyzeForm');

function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function showFile(file) {
    fileNameEl.textContent = file.name;
    fileSizeEl.textContent = formatBytes(file.size);
    const isPdf = file.type === 'application/pdf';
    previewIcon.className = isPdf
        ? 'mdi mdi-file-pdf-box display-4 text-danger d-block mb-2'
        : 'mdi mdi-file-image-outline display-4 text-primary d-block mb-2';
    dropPlaceholder.classList.add('d-none');
    filePreview.classList.remove('d-none');
}

function clearFile() {
    fileInput.value = '';
    dropPlaceholder.classList.remove('d-none');
    filePreview.classList.add('d-none');
}

fileInput.addEventListener('change', function () {
    if (this.files.length > 0) showFile(this.files[0]);
});

clearFileBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    clearFile();
});

['dragenter', 'dragover'].forEach(evt => {
    dropZone.addEventListener(evt, e => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
});

['dragleave', 'drop'].forEach(evt => {
    dropZone.addEventListener(evt, e => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
    });
});

dropZone.addEventListener('drop', function (e) {
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const dt = new DataTransfer();
        dt.items.add(files[0]);
        fileInput.files = dt.files;
        showFile(files[0]);
    }
});

analyzeForm.addEventListener('submit', function () {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Analisando...';
});

@if(session('result'))
    document.getElementById('resultCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
@endif

function copyResult() {
    const text = document.getElementById('aiResult').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.currentTarget;
        btn.innerHTML = '<i class="mdi mdi-check text-success"></i>';
        setTimeout(() => btn.innerHTML = '<i class="mdi mdi-content-copy"></i>', 2000);
    });
}
</script>
@endpush
