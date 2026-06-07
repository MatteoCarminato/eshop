@extends('layouts.app')
@section('title', 'WhatsApp Envio')

@section('content')
    @php
        $canManageWhatsapp = auth()->user()->hasModule('whatsapp.manage');
    @endphp
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Disparo WhatsApp</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.index') }}">WhatsApp</a></li>
                                <li class="breadcrumb-item active">Envio</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-2">
                <div class="col-12 d-flex justify-content-end">
                    <a href="{{ route('admin.whatsapp.schedules') }}" class="btn btn-outline-primary btn-sm">
                        <i class="ri-calendar-check-line me-1"></i> Ir para agendamentos
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    @if (session('success'))
                        <div class="alert alert-success py-2">{{ session('success') }}</div>
                    @endif
                    @if (session('warning'))
                        <div class="alert alert-warning py-2">{{ session('warning') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger py-2">{{ session('error') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger py-2 mb-0">
                            <strong>Não foi possível enviar:</strong>
                            <ul class="mb-0 mt-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            @php
                $oldClientIds = collect(old('client_ids', []))->map(fn($id) => (int) $id)->all();
                $oldGroupIds = collect(old('group_ids', []))->map(fn($id) => (int) $id)->all();
            @endphp

            <div class="row g-3 mt-1">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Disparo para Clientes/Grupos</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.whatsapp.send') }}" enctype="multipart/form-data">
                                @csrf

                                <div class="row g-3">

                                    {{-- Coluna 1: Grupos --}}
                                    <div class="col-lg-3">
                                        <label class="form-label fw-semibold">Grupos</label>
                                        <div class="border rounded p-2" style="height: 480px; overflow-y: auto;" id="groups_container">
                                            @forelse ($groups as $group)
                                                <div class="form-check py-1">
                                                    <input class="form-check-input group-check" type="checkbox"
                                                        name="group_ids[]"
                                                        value="{{ $group->id }}"
                                                        id="group_{{ $group->id }}"
                                                        data-client-ids="{{ json_encode($groupClients->get($group->id, [])) }}"
                                                        @checked(in_array((int) $group->id, $oldGroupIds, true))>
                                                    <label class="form-check-label" for="group_{{ $group->id }}">
                                                        {{ $group->name }}
                                                        <span class="badge bg-secondary ms-1">{{ (int) ($group->clients_count ?? 0) }}</span>
                                                    </label>
                                                </div>
                                            @empty
                                                <p class="text-muted small mb-0">Nenhum grupo cadastrado.</p>
                                            @endforelse
                                        </div>
                                        <small class="text-muted">Selecione um grupo para marcar seus clientes automaticamente.</small>
                                    </div>

                                    {{-- Coluna 2: Clientes --}}
                                    <div class="col-lg-3">
                                        <label class="form-label fw-semibold">
                                            Clientes
                                            <span class="text-muted fw-normal small ms-1" id="clients_counter"></span>
                                        </label>
                                        <div class="border rounded p-2" style="height: 480px; overflow-y: auto;" id="clients_container">
                                            @foreach ($clients as $client)
                                                <div class="form-check py-1 client-item" data-client-id="{{ $client->id }}">
                                                    <input class="form-check-input client-check" type="checkbox"
                                                        name="client_ids[]"
                                                        value="{{ $client->id }}"
                                                        id="client_{{ $client->id }}"
                                                        @checked(in_array((int) $client->id, $oldClientIds, true))>
                                                    <label class="form-check-label" for="client_{{ $client->id }}">
                                                        {{ $client->name }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                        <small class="text-muted">Clientes dos grupos selecionados são marcados automaticamente. Você também pode marcar individualmente.</small>
                                    </div>

                                    {{-- Coluna 3: Mensagem e Anexo --}}
                                    <div class="col-lg-6">
                                        <div class="d-flex flex-column" style="height: 100%;">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Mensagem (texto)</label>
                                                <textarea id="wpp_message" name="message" class="form-control" rows="10"
                                                    maxlength="4000"
                                                    placeholder="Digite a mensagem que será enviada...">{{ old('message') }}</textarea>
                                                <small class="text-muted">Se anexar imagem/arquivo, o texto será usado como legenda.</small>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Anexo (imagem ou arquivo)</label>
                                                <input id="wpp_attachment" type="file" name="attachment" class="form-control"
                                                    accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv">
                                                <div class="mt-2 d-flex gap-2 flex-wrap">
                                                    <button type="button" id="btn_paste_image"
                                                        class="btn btn-outline-secondary btn-sm">
                                                        <i class="ri-clipboard-line me-1"></i> Colar print/Imagem
                                                    </button>
                                                </div>
                                                <small class="text-muted d-block mt-2">Opcional. Máximo: 15MB.</small>
                                                <small class="text-muted d-block mt-1">
                                                    Dica: use <kbd>Ctrl+V</kbd> (ou <kbd>⌘+V</kbd> no Mac) para colar uma imagem.
                                                </small>
                                                <div id="paste_feedback" class="small mt-2 text-success d-none"></div>
                                                <div id="paste_preview_wrapper" class="mt-2 d-none">
                                                    <img id="paste_preview" alt="Prévia da imagem colada" class="img-thumbnail"
                                                        style="max-height: 220px;">
                                                </div>
                                            </div>

                                            <div class="mt-auto d-flex justify-content-end">
                                                @if($canManageWhatsapp)
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="ri-send-plane-2-line me-1"></i>
                                                        Disparar Mensagens
                                                    </button>
                                                @else
                                                    <div class="alert alert-warning py-2 mb-0">
                                                        Você não possui permissão para disparar mensagens.
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // ── Lógica grupos → clientes ──────────────────────────────────────────────
        (function () {
            const groupChecks  = document.querySelectorAll('.group-check');
            const clientChecks = document.querySelectorAll('.client-check');
            const counter      = document.getElementById('clients_counter');

            // IDs atualmente marcados por causa de grupos
            let groupDrivenIds = new Set();

            function updateCounter() {
                if (!counter) return;
                const total    = clientChecks.length;
                const selected = Array.from(clientChecks).filter(cb => cb.checked).length;
                counter.textContent = selected > 0 ? `(${selected}/${total} selecionados)` : `(${total} no total)`;
            }

            function recalcGroupDriven() {
                const newGroupDrivenIds = new Set();
                groupChecks.forEach(cb => {
                    if (cb.checked) {
                        const ids = JSON.parse(cb.dataset.clientIds || '[]');
                        ids.forEach(id => newGroupDrivenIds.add(Number(id)));
                    }
                });

                clientChecks.forEach(clientCb => {
                    const id           = Number(clientCb.value);
                    const wasGroupDriven = groupDrivenIds.has(id);
                    const isGroupDriven  = newGroupDrivenIds.has(id);

                    if (isGroupDriven) {
                        clientCb.checked = true;
                    } else if (wasGroupDriven) {
                        // Estava marcado por grupo mas não está mais — desmarcar
                        clientCb.checked = false;
                    }
                    // Se era manual e não muda de grupo: deixa como está
                });

                groupDrivenIds = newGroupDrivenIds;
                updateCounter();
            }

            groupChecks.forEach(cb => cb.addEventListener('change', recalcGroupDriven));
            clientChecks.forEach(cb => cb.addEventListener('change', updateCounter));

            // Inicializa contagem e grupos já marcados (ex: validação falhou e recarregou)
            recalcGroupDriven();
        })();
    </script>

    <script>
        // ── Lógica de paste / anexo ───────────────────────────────────────────────
        (function () {
            const messageInput = document.getElementById('wpp_message');
            const attachmentInput = document.getElementById('wpp_attachment');
            const pasteButton = document.getElementById('btn_paste_image');
            const feedbackEl = document.getElementById('paste_feedback');
            const previewWrap = document.getElementById('paste_preview_wrapper');
            const previewImg = document.getElementById('paste_preview');

            if (!attachmentInput) return;

            function formatBytes(bytes) {
                if (!bytes || bytes <= 0) return '0 B';
                const units = ['B', 'KB', 'MB', 'GB'];
                const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
                const value = bytes / Math.pow(1024, index);
                return value.toFixed(index === 0 ? 0 : 2) + ' ' + units[index];
            }

            function showFeedback(text, isError) {
                if (!feedbackEl) return;
                feedbackEl.textContent = text;
                feedbackEl.classList.remove('d-none', 'text-success', 'text-danger');
                feedbackEl.classList.add(isError ? 'text-danger' : 'text-success');
            }

            function toPastedFile(file) {
                const extension = (file.type.split('/')[1] || 'png').replace(/[^a-z0-9]/gi, '');
                return new File([file], `print-colado-${Date.now()}.${extension}`, {
                    type: file.type,
                    lastModified: Date.now(),
                });
            }

            function setAttachmentFile(file) {
                const transfer = new DataTransfer();
                transfer.items.add(file);
                attachmentInput.files = transfer.files;
                attachmentInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function showPreviewFromFile(file) {
                if (!previewImg || !previewWrap) return;

                if (!file || !file.type || !file.type.startsWith('image/')) {
                    previewWrap.classList.add('d-none');
                    previewImg.removeAttribute('src');
                    return;
                }

                const objectUrl = URL.createObjectURL(file);
                previewImg.src = objectUrl;
                previewWrap.classList.remove('d-none');
            }

            function attachPastedImage(file) {
                const pastedFile = toPastedFile(file);
                setAttachmentFile(pastedFile);
                showFeedback(`Imagem/print colado com sucesso (${formatBytes(pastedFile.size)}).`, false);
                showPreviewFromFile(pastedFile);
            }

            function getImageFromItems(items) {
                if (!items || !items.length) return null;
                const imageItem = Array.from(items).find((item) => item.type && item.type.startsWith('image/'));
                return imageItem ? imageItem.getAsFile() : null;
            }

            async function readClipboardImage() {
                if (!navigator.clipboard || !navigator.clipboard.read) {
                    showFeedback('Seu navegador não permite leitura direta da área de transferência. Use Ctrl+V no campo de mensagem.', true);
                    return;
                }

                try {
                    const clipboardItems = await navigator.clipboard.read();
                    for (const item of clipboardItems) {
                        const imageType = item.types.find((type) => type.startsWith('image/'));
                        if (!imageType) continue;

                        const blob = await item.getType(imageType);
                        attachPastedImage(blob);
                        return;
                    }

                    showFeedback('Nenhuma imagem encontrada na área de transferência.', true);
                } catch (error) {
                    showFeedback('Não consegui ler a área de transferência. Tente Ctrl+V com o campo de mensagem focado.', true);
                }
            }

            attachmentInput.addEventListener('change', function () {
                const file = this.files && this.files[0] ? this.files[0] : null;
                if (!file) {
                    showFeedback('', false);
                    if (previewWrap) previewWrap.classList.add('d-none');
                    return;
                }

                showFeedback(`Anexo selecionado: ${file.name} (${formatBytes(file.size)})`, false);
                showPreviewFromFile(file);
            });

            document.addEventListener('paste', function (event) {
                const clipboard = event.clipboardData;
                if (!clipboard) return;

                const directFile = clipboard.files && clipboard.files[0] ? clipboard.files[0] : null;
                const imageFile = directFile && directFile.type && directFile.type.startsWith('image/')
                    ? directFile
                    : getImageFromItems(clipboard.items || []);

                if (!imageFile) return;

                event.preventDefault();

                try {
                    attachPastedImage(imageFile);
                } catch (error) {
                    showFeedback('Não foi possível anexar a imagem colada neste navegador.', true);
                }
            });

            if (pasteButton) {
                pasteButton.addEventListener('click', readClipboardImage);
            }

            if (messageInput) {
                messageInput.addEventListener('focus', function () {
                    showFeedback('Dica: cole prints com Ctrl+V/⌘+V ou use o botão "Colar print/Imagem".', false);
                });
            }
        })();
    </script>
@endpush