@extends('layouts.app')
@section('title', 'WhatsApp Web')

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">WhatsApp Web</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">WhatsApp</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Conexão</h5>
                            <span id="wpp-status-badge" class="badge bg-secondary">Carregando...</span>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-2">
                                Este painel usa seu serviço existente em <code>whatsapp-node</code>.
                            </p>

                            @if (!($nodeHealth['success'] ?? false))
                                <div class="alert alert-danger py-2">
                                    Não consegui alcançar o Node no carregamento inicial.
                                    <div class="small mt-1">Erro: {{ $nodeHealth['error'] ?? 'desconhecido' }}</div>
                                </div>
                            @endif

                            <div class="mb-2"><strong>Estado:</strong> <span id="wpp-state">-</span></div>
                            <div class="mb-2"><strong>Enviando:</strong> <span id="wpp-sending">-</span></div>
                            <div class="mb-3"><strong>Mensagens enviadas:</strong> <span id="wpp-sent">-</span></div>

                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-success" id="btn-connect">Conectar / Atualizar QR</button>
                                <button class="btn btn-outline-primary" id="btn-refresh">Atualizar Status</button>
                                <a class="btn btn-primary" href="{{ route('admin.whatsapp.envio') }}">Ir para Envio</a>
                            </div>

                            <small class="text-muted d-block mt-3">
                                Se o QR não aparecer, confirme se o processo do Node está rodando e autenticado com a mesma
                                <code>API_KEY</code>.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">QR Code</h5>
                            <small class="text-muted" id="wpp-qr-time">—</small>
                        </div>
                        <div class="card-body d-flex flex-column align-items-center justify-content-center"
                            style="min-height: 380px;">
                            <div id="qr-wrapper" class="border rounded p-3 bg-light" style="width: 300px; height: 300px;">
                            </div>
                            <p class="text-muted text-center mt-3 mb-0" id="qr-hint">
                                Aguardando QR code...
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const routes = {
                status: '{{ route('admin.whatsapp.status') }}',
                qr: '{{ route('admin.whatsapp.qr') }}',
                qrImage: '{{ route('admin.whatsapp.qr-image') }}',
                connect: '{{ route('admin.whatsapp.connect') }}',
            };

            const els = {
                badge: document.getElementById('wpp-status-badge'),
                state: document.getElementById('wpp-state'),
                sending: document.getElementById('wpp-sending'),
                sent: document.getElementById('wpp-sent'),
                qrWrap: document.getElementById('qr-wrapper'),
                qrTime: document.getElementById('wpp-qr-time'),
                qrHint: document.getElementById('qr-hint'),
                btnConnect: document.getElementById('btn-connect'),
                btnRefresh: document.getElementById('btn-refresh'),
            };

            function setBadgeByState(state) {
                const normalized = (state || '').toLowerCase();
                els.badge.className = 'badge';
                if (normalized === 'connected') {
                    els.badge.classList.add('bg-success');
                    els.badge.textContent = 'Conectado';
                } else if (normalized === 'disconnected') {
                    els.badge.classList.add('bg-danger');
                    els.badge.textContent = 'Desconectado';
                } else {
                    els.badge.classList.add('bg-warning');
                    els.badge.textContent = 'Aguardando';
                }
            }

            function drawQrImage(url, available) {
                els.qrWrap.innerHTML = '';

                if (!available || !url) {
                    els.qrHint.textContent = 'Nenhum QR disponível no momento.';
                    els.qrWrap.innerHTML = '<div class="text-muted small text-center">Aguardando QR...</div>';
                    return;
                }

                const img = document.createElement('img');
                img.alt = 'QR Code do WhatsApp';
                img.width = 280;
                img.height = 280;
                img.style.maxWidth = '100%';
                img.style.height = 'auto';
                img.className = 'd-block';
                img.src = url + (url.includes('?') ? '&' : '?') + 't=' + Date.now();
                img.onerror = function () {
                    els.qrHint.textContent = 'Falha ao carregar o QR SVG.';
                    els.qrWrap.innerHTML = '<div class="text-danger small text-center">QR indisponível.</div>';
                };

                els.qrWrap.appendChild(img);
                els.qrHint.textContent = 'Escaneie com o WhatsApp do celular para conectar.';
            }

            async function fetchJson(url, options = {}) {
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    ...options,
                });
                return response.json();
            }

            async function loadStatus() {
                try {
                    const payload = await fetchJson(routes.status);
                    const data = payload.data || {};
                    els.state.textContent = data.state || '-';
                    els.sending.textContent = data.sending ? 'Sim' : 'Não';
                    els.sent.textContent = (data.messagesSent ?? 0).toString();
                    setBadgeByState(data.state || 'unknown');
                    return data;
                } catch (e) {
                    setBadgeByState('disconnected');
                    els.state.textContent = 'erro';
                }
                return null;
            }

            async function loadQr() {
                try {
                    const payload = await fetchJson(routes.qr);
                    const data = payload.data || {};
                    if (data.available && data.qr) {
                        drawQrImage(routes.qrImage, true);
                        els.qrTime.textContent = data.timestamp ? ('Atualizado: ' + data.timestamp) : 'QR disponível';
                    } else {
                        drawQrImage(null, false);
                        els.qrTime.textContent = data.message || 'Sem QR';
                    }
                } catch (e) {
                    els.qrWrap.innerHTML = '<div class="text-danger small text-center">Erro ao buscar QR.</div>';
                    els.qrTime.textContent = 'Erro ao buscar QR';
                }
            }

            async function connectAndRefresh() {
                els.btnConnect.disabled = true;
                const oldText = els.btnConnect.textContent;
                els.btnConnect.textContent = 'Conectando...';

                try {
                    await fetchJson(routes.connect, { method: 'POST' });
                    await loadStatus();
                    await loadQr();
                } finally {
                    els.btnConnect.disabled = false;
                    els.btnConnect.textContent = oldText;
                }
            }

            els.btnRefresh.addEventListener('click', async function () {
                await loadStatus();
                await loadQr();
            });

            els.btnConnect.addEventListener('click', connectAndRefresh);

            // Carga inicial + polling periódico
            (async function init() {
                await loadStatus();
                await loadQr();

                setInterval(async function () {
                    const status = await loadStatus();
                    if (!status || status.state !== 'connected') {
                        await loadQr();
                    }
                }, 8000);
            })();
        })();
    </script>
@endpush