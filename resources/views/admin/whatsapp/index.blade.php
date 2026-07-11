@extends('layouts.app')
@section('title', 'WhatsApp Web')

@section('content')
    @php
        $canManageWhatsapp = auth()->user()->hasModule('whatsapp.manage');
    @endphp
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

            {{-- Abas --}}
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#tab-disparador" role="tab">
                        <i class="ri-send-plane-line me-1"></i> Disparador
                        <span id="badge-disparador" class="badge bg-secondary ms-1">...</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tab-grupos" role="tab">
                        <i class="ri-image-line me-1"></i> Grupos PIX
                        <span id="badge-grupos" class="badge bg-secondary ms-1">...</span>
                    </a>
                </li>
            </ul>

            <div class="tab-content">

                {{-- ── Tab Disparador ────────────────────────────── --}}
                <div class="tab-pane fade show active" id="tab-disparador" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Conexão — Disparador</h5>
                                    <span id="disp-status-badge" class="badge bg-secondary">Carregando...</span>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-2">Instância usada para enviar mensagens e agendamentos. <code>porta 3000</code></p>

                                    @if (!($nodeHealth['success'] ?? false))
                                        <div class="alert alert-danger py-2">
                                            Node não alcançado na inicialização.
                                            <div class="small mt-1">{{ $nodeHealth['error'] ?? 'desconhecido' }}</div>
                                        </div>
                                    @endif

                                    <div class="mb-2"><strong>Estado:</strong> <span id="disp-state">-</span></div>

                                    <div class="d-flex gap-2 flex-wrap mt-3">
                                        @if ($canManageWhatsapp)
                                            <button class="btn btn-success btn-sm" id="disp-btn-connect">Conectar / QR</button>
                                            <button class="btn btn-danger btn-sm" id="disp-btn-disconnect">Desconectar</button>
                                        @endif
                                        <button class="btn btn-outline-primary btn-sm" id="disp-btn-refresh">Atualizar</button>
                                        <a class="btn btn-primary btn-sm" href="{{ route('admin.whatsapp.envio') }}">Ir para Envio</a>
                                    </div>

                                    @if ($canManageWhatsapp)
                                        <hr class="my-3">
                                        <p class="fw-semibold mb-1 fs-14">Conectar por número</p>
                                        <div class="input-group mb-2">
                                            <input type="tel" id="disp-pairing-phone" class="form-control form-control-sm"
                                                placeholder="5511999999999" maxlength="15" inputmode="numeric">
                                            <button class="btn btn-outline-success btn-sm" id="disp-btn-pairing-code">Gerar código</button>
                                        </div>
                                        <div id="disp-pairing-result" class="d-none">
                                            <p class="text-muted small mb-1">Digite no WhatsApp:</p>
                                            <div class="d-flex align-items-center gap-2">
                                                <span id="disp-pairing-code-display" class="fs-2 fw-bold font-monospace text-success"></span>
                                                <button class="btn btn-sm btn-outline-secondary" id="disp-btn-copy-code"><i class="ri-file-copy-line"></i></button>
                                            </div>
                                            <p class="text-muted small mt-1 mb-0" id="disp-pairing-expires"></p>
                                        </div>
                                        <div id="disp-pairing-error" class="d-none alert alert-danger py-2 mt-2 mb-0"></div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">QR Code — Disparador</h5>
                                    <small class="text-muted" id="disp-qr-time">—</small>
                                </div>
                                <div class="card-body d-flex flex-column align-items-center justify-content-center" style="min-height:340px;">
                                    <div id="disp-qr-wrapper" class="border rounded p-3 bg-light" style="width:300px;height:300px;"></div>
                                    <p class="text-muted text-center mt-3 mb-0" id="disp-qr-hint">Aguardando QR code...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Tab Grupos ────────────────────────────────── --}}
                <div class="tab-pane fade" id="tab-grupos" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Conexão — Grupos PIX</h5>
                                    <span id="grup-status-badge" class="badge bg-secondary">Carregando...</span>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-2">Instância que monitora grupos e verifica comprovantes PIX. <code>porta 3001</code></p>

                                    @if (!($nodeHealthGrupos['success'] ?? false))
                                        <div class="alert alert-danger py-2">
                                            Node grupos não alcançado na inicialização.
                                            <div class="small mt-1">{{ $nodeHealthGrupos['error'] ?? 'desconhecido' }}</div>
                                        </div>
                                    @endif

                                    <div class="mb-2"><strong>Estado:</strong> <span id="grup-state">-</span></div>

                                    <div class="d-flex gap-2 flex-wrap mt-3">
                                        @if ($canManageWhatsapp)
                                            <button class="btn btn-success btn-sm" id="grup-btn-connect">Conectar / QR</button>
                                            <button class="btn btn-danger btn-sm" id="grup-btn-disconnect">Desconectar</button>
                                        @endif
                                        <button class="btn btn-outline-primary btn-sm" id="grup-btn-refresh">Atualizar</button>
                                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.whatsapp.extracoes') }}">Ver Extrações</a>
                                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.whatsapp.grupos-wpp') }}">Configurar Grupos</a>
                                    </div>

                                    @if ($canManageWhatsapp)
                                        <hr class="my-3">
                                        <p class="fw-semibold mb-1 fs-14">Conectar por número</p>
                                        <div class="input-group mb-2">
                                            <input type="tel" id="grup-pairing-phone" class="form-control form-control-sm"
                                                placeholder="5511999999999" maxlength="15" inputmode="numeric">
                                            <button class="btn btn-outline-success btn-sm" id="grup-btn-pairing-code">Gerar código</button>
                                        </div>
                                        <div id="grup-pairing-result" class="d-none">
                                            <p class="text-muted small mb-1">Digite no WhatsApp:</p>
                                            <div class="d-flex align-items-center gap-2">
                                                <span id="grup-pairing-code-display" class="fs-2 fw-bold font-monospace text-success"></span>
                                                <button class="btn btn-sm btn-outline-secondary" id="grup-btn-copy-code"><i class="ri-file-copy-line"></i></button>
                                            </div>
                                            <p class="text-muted small mt-1 mb-0" id="grup-pairing-expires"></p>
                                        </div>
                                        <div id="grup-pairing-error" class="d-none alert alert-danger py-2 mt-2 mb-0"></div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">QR Code — Grupos PIX</h5>
                                    <small class="text-muted" id="grup-qr-time">—</small>
                                </div>
                                <div class="card-body d-flex flex-column align-items-center justify-content-center" style="min-height:340px;">
                                    <div id="grup-qr-wrapper" class="border rounded p-3 bg-light" style="width:300px;height:300px;"></div>
                                    <p class="text-muted text-center mt-3 mb-0" id="grup-qr-hint">Aguardando QR code...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>{{-- /tab-content --}}
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const CSRF = '{{ csrf_token() }}';

    async function fetchJson(url, options = {}) {
        const r = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
            ...options,
        });
        return r.json();
    }

    function setBadge(el, state) {
        el.className = 'badge ms-1';
        const s = (state || '').toLowerCase();
        if (s === 'connected')    { el.classList.add('bg-success'); el.textContent = 'Conectado'; }
        else if (s === 'disconnected') { el.classList.add('bg-danger');  el.textContent = 'Desconectado'; }
        else                      { el.classList.add('bg-warning'); el.textContent = 'Aguardando'; }
    }

    function drawQr(wrapper, hint, url, available) {
        wrapper.innerHTML = '';
        if (!available || !url) {
            hint.textContent = 'Nenhum QR disponível.';
            wrapper.innerHTML = '<div class="text-muted small text-center pt-5">Aguardando QR...</div>';
            return;
        }
        const img = document.createElement('img');
        img.alt = 'QR Code'; img.width = 280; img.height = 280;
        img.style.cssText = 'max-width:100%;height:auto;';
        img.src = url.startsWith('data:') ? url : url + '?t=' + Date.now();
        img.onerror = () => { hint.textContent = 'Falha ao carregar QR.'; };
        wrapper.appendChild(img);
        hint.textContent = 'Escaneie com o WhatsApp do celular.';
    }

    /**
     * Cria o controller de uma instância
     */
    function makeInstance(prefix, routes, canManage) {
        const $ = id => document.getElementById(id);
        const els = {
            tabBadge:    $(prefix + '-status-badge') || $(prefix === 'disp' ? 'badge-disparador' : 'badge-grupos'),
            globalBadge: $(prefix === 'disp' ? 'badge-disparador' : 'badge-grupos'),
            badge:       $(prefix + '-status-badge'),
            state:       $(prefix + '-state'),
            qrWrap:      $(prefix + '-qr-wrapper'),
            qrTime:      $(prefix + '-qr-time'),
            qrHint:      $(prefix + '-qr-hint'),
            btnConnect:  $(prefix + '-btn-connect'),
            btnDisconn:  $(prefix + '-btn-disconnect'),
            btnRefresh:  $(prefix + '-btn-refresh'),
            // pairing
            pairingPhone:   $(prefix + '-pairing-phone'),
            btnPairing:     $(prefix + '-btn-pairing-code'),
            pairingResult:  $(prefix + '-pairing-result'),
            pairingDisplay: $(prefix + '-pairing-code-display'),
            pairingExpires: $(prefix + '-pairing-expires'),
            pairingError:   $(prefix + '-pairing-error'),
            btnCopy:        $(prefix + '-btn-copy-code'),
        };

        async function loadStatus() {
            try {
                const p = await fetchJson(routes.status);
                const d = p.data || {};
                if (els.state) els.state.textContent = d.state || '-';
                if (els.badge) setBadge(els.badge, d.state);
                if (els.globalBadge) setBadge(els.globalBadge, d.state);
                return d;
            } catch(e) {
                if (els.badge) setBadge(els.badge, 'disconnected');
                if (els.globalBadge) setBadge(els.globalBadge, 'disconnected');
            }
            return null;
        }

        async function loadQr() {
            try {
                const p = await fetchJson(routes.qr);
                const d = p.data || {};
                drawQr(els.qrWrap, els.qrHint, d.qr_data_url, d.available);
                if (els.qrTime) els.qrTime.textContent = d.available ? 'QR disponível' : (d.message || 'Sem QR');
            } catch(e) {
                if (els.qrWrap) els.qrWrap.innerHTML = '<div class="text-danger small text-center pt-5">Erro ao buscar QR.</div>';
            }
        }

        if (els.btnRefresh) {
            els.btnRefresh.addEventListener('click', async () => { await loadStatus(); await loadQr(); });
        }

        if (canManage && els.btnConnect) {
            els.btnConnect.addEventListener('click', async () => {
                els.btnConnect.disabled = true;
                try { await fetchJson(routes.connect, { method: 'POST' }); await loadStatus(); await loadQr(); }
                finally { els.btnConnect.disabled = false; }
            });
        }

        if (canManage && els.btnDisconn) {
            els.btnDisconn.addEventListener('click', async () => {
                if (!confirm('Desconectar esta instância?')) return;
                els.btnDisconn.disabled = true;
                try { await fetchJson(routes.disconnect, { method: 'POST' }); await loadStatus(); await loadQr(); }
                finally { els.btnDisconn.disabled = false; }
            });
        }

        if (canManage && els.btnPairing) {
            els.btnPairing.addEventListener('click', async () => {
                const phone = (els.pairingPhone.value || '').replace(/\D/g, '');
                if (!phone) {
                    els.pairingError.textContent = 'Digite o número.';
                    els.pairingError.classList.remove('d-none');
                    els.pairingResult.classList.add('d-none');
                    return;
                }
                els.btnPairing.disabled = true; els.btnPairing.textContent = 'Gerando...';
                els.pairingResult.classList.add('d-none'); els.pairingError.classList.add('d-none');
                try {
                    const p = await fetchJson(routes.pairingCode, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({ phone }),
                    });
                    if (!p.success) {
                        els.pairingError.textContent = p.error || 'Erro ao gerar código.';
                        els.pairingError.classList.remove('d-none');
                        return;
                    }
                    els.pairingDisplay.textContent = p.pairing_code || '—';
                    els.pairingResult.classList.remove('d-none');
                    if (p.expires_in_ms) {
                        const mins = Math.round(p.expires_in_ms / 60000);
                        els.pairingExpires.textContent = `Expira em ${mins} minuto(s). WhatsApp > Aparelhos conectados > Conectar com número.`;
                    }
                } catch(e) {
                    els.pairingError.textContent = 'Erro de comunicação.';
                    els.pairingError.classList.remove('d-none');
                } finally {
                    els.btnPairing.disabled = false; els.btnPairing.textContent = 'Gerar código';
                }
            });
        }

        if (canManage && els.btnCopy) {
            els.btnCopy.addEventListener('click', () => {
                const code = (els.pairingDisplay.textContent || '').trim();
                if (!code || code === '—') return;
                navigator.clipboard.writeText(code).then(() => {
                    els.btnCopy.innerHTML = '<i class="ri-check-line"></i>';
                    setTimeout(() => { els.btnCopy.innerHTML = '<i class="ri-file-copy-line"></i>'; }, 2000);
                });
            });
        }

        // carga inicial + polling
        (async function init() {
            await loadStatus();
            await loadQr();
            setInterval(async () => {
                const s = await loadStatus();
                if (!s || s.state !== 'connected') await loadQr();
            }, 8000);
        })();
    }

    const canManage = {{ $canManageWhatsapp ? 'true' : 'false' }};

    makeInstance('disp', {
        status:      '{{ route('admin.whatsapp.status') }}',
        qr:          '{{ route('admin.whatsapp.qr') }}',
        connect:     '{{ route('admin.whatsapp.connect') }}',
        disconnect:  '{{ route('admin.whatsapp.disconnect') }}',
        pairingCode: '{{ route('admin.whatsapp.pairing-code') }}',
    }, canManage);

    makeInstance('grup', {
        status:      '{{ route('admin.whatsapp.grupos-instance.status') }}',
        qr:          '{{ route('admin.whatsapp.grupos-instance.qr') }}',
        connect:     '{{ route('admin.whatsapp.grupos-instance.connect') }}',
        disconnect:  '{{ route('admin.whatsapp.grupos-instance.disconnect') }}',
        pairingCode: '{{ route('admin.whatsapp.grupos-instance.pairing-code') }}',
    }, canManage);
})();
</script>
@endpush
