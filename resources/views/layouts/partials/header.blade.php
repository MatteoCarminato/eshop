<header id="page-topbar">
    @php
        $canViewWhatsappStatus = auth()->check() && auth()->user()->hasModule('whatsapp.view');
    @endphp
    <div class="layout-width">
        <div class="navbar-header">
            <div class="d-flex">
                <!-- LOGO -->
                <div class="navbar-brand-box horizontal-logo">
                    <a href="index.html" class="logo logo-dark">
                        <span class="logo-sm">
                            <img src="{{ asset('assets/images/logo-eshop.png') }}" alt="" height="22">
                        </span>
                        <span class="logo-lg">
                            <img src="{{ asset('assets/images/logo-eshop.png') }}" alt="" height="40">
                        </span>
                    </a>

                    <a href="index.html" class="logo logo-light">
                        <span class="logo-sm">
                            <img src="{{ asset('assets/images/logo-eshop.png') }}" alt="" height="22">
                        </span>
                        <span class="logo-lg">
                            <img src="{{ asset('assets/images/logo-eshop.png') }}" alt="" height="40">
                        </span>
                    </a>
                </div>

                <button type="button"
                    class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger material-shadow-none"
                    id="topnav-hamburger-icon">
                    <span class="hamburger-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>
            </div>

            <div class="d-flex align-items-center">
                <div class="dropdown ms-sm-3 header-item topbar-user">
                    <button type="button" class="btn material-shadow-none" id="page-header-user-dropdown"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <img class="rounded-circle header-profile-user"
                                src="{{ asset('assets/images/users/avatar-1.jpg') }}" alt="Header Avatar">
                            <span class="text-start ms-xl-2">
                                <span
                                    class="d-none d-xl-inline-block ms-1 fw-medium user-name-text">{{ Auth::user()->name }}</span>
                                <span class="d-none d-xl-block ms-1 fs-12 user-name-sub-text">Founder</span>
                            </span>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <!-- item-->
                        <h6 class="dropdown-header">Bem-vindo {{ Auth::user()->name }}!</h6>
                        <a class="dropdown-item" href="pages-profile.html"><i
                                class="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i> <span
                                class="align-middle">Profile</span></a>
                        <form method="POST" action="{{ route('logout') }}" id="logout-form">
                            @csrf
                            <button type="submit" class="dropdown-item"><i
                                    class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i> <span
                                    class="align-middle" data-key="t-logout">Logout</span></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if ($canViewWhatsappStatus)
            <div id="wpp-header-alert" class="alert alert-warning py-2 px-3 mb-0 rounded-0 d-none" role="alert">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ri-whatsapp-line fs-18"></i>
                        <span id="wpp-header-alert-text" class="fw-medium">WhatsApp desconectado. Reconecte para voltar a
                            enviar mensagens.</span>
                    </div>
                    <a href="{{ route('admin.whatsapp.index') }}" class="btn btn-sm btn-warning">Abrir conexão</a>
                </div>
            </div>
        @endif
    </div>
</header>

@if ($canViewWhatsappStatus)
    <script>
        (function () {
            const statusUrl = '{{ route('admin.whatsapp.status') }}';
            const alertEl = document.getElementById('wpp-header-alert');
            const textEl = document.getElementById('wpp-header-alert-text');

            if (!alertEl || !textEl) {
                return;
            }

            const updateAlert = (state, hasError = false) => {
                const normalized = String(state || '').toLowerCase();

                if (hasError) {
                    textEl.textContent = 'Nao foi possivel verificar o WhatsApp agora. Confira a conexao para relogar se necessario.';
                    alertEl.classList.remove('d-none');
                    return;
                }

                if (normalized === 'connected') {
                    alertEl.classList.add('d-none');
                    return;
                }

                if (normalized === 'disconnected') {
                    textEl.textContent = 'WhatsApp desconectado. Reconecte para voltar a enviar mensagens.';
                } else {
                    textEl.textContent = 'WhatsApp aguardando autenticacao. Abra a conexao para escanear o QR novamente.';
                }

                alertEl.classList.remove('d-none');
            };

            const checkWhatsappStatus = async () => {
                try {
                    const response = await fetch(statusUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        updateAlert(null, true);
                        return;
                    }

                    const payload = await response.json();
                    const state = payload?.data?.state || 'unknown';
                    updateAlert(state, false);
                } catch (error) {
                    updateAlert(null, true);
                }
            };

            checkWhatsappStatus();
            setInterval(checkWhatsappStatus, 15000);
        })();
    </script>
@endif