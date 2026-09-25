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
                @if ($canViewWhatsappStatus)
                    <a href="{{ route('admin.whatsapp.index') }}" id="wpp-header-icon"
                        class="btn btn-icon btn-topbar material-shadow-none d-none rounded-circle position-relative"
                        data-bs-toggle="tooltip" data-bs-placement="bottom" title="WhatsApp">
                        <i class="ri-whatsapp-line fs-18"></i>
                        <span id="wpp-header-icon-dot"
                            class="position-absolute top-0 start-100 translate-middle p-1 border border-light rounded-circle bg-warning">
                            <span class="visually-hidden">Status do WhatsApp</span>
                        </span>
                    </a>
                @endif
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

    </div>
</header>

@if ($canViewWhatsappStatus)
    <script>
        (function () {
            const statusUrl = '{{ route('admin.whatsapp.status') }}';
            const statusGruposUrl = '{{ route('admin.whatsapp.grupos-instance.status') }}';
            const iconEl = document.getElementById('wpp-header-icon');
            const dotEl = document.getElementById('wpp-header-icon-dot');

            if (!iconEl || !dotEl) {
                return;
            }

            const tooltip = window.bootstrap ? new bootstrap.Tooltip(iconEl) : null;

            const setTooltipTitle = (text) => {
                iconEl.setAttribute('title', text);
                iconEl.setAttribute('data-bs-original-title', text);
                if (tooltip) {
                    tooltip.setContent({ '.tooltip-inner': text });
                }
            };

            const fetchState = async (url) => {
                try {
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        return { state: null, error: true };
                    }

                    const payload = await response.json();
                    return { state: (payload?.data?.state || 'unknown').toLowerCase(), error: false };
                } catch (error) {
                    return { state: null, error: true };
                }
            };

            const checkWhatsappStatus = async () => {
                const [main, grupos] = await Promise.all([
                    fetchState(statusUrl),
                    fetchState(statusGruposUrl),
                ]);

                iconEl.classList.remove('d-none');
                dotEl.classList.remove('bg-warning', 'bg-danger', 'bg-success');

                // Se qualquer uma das instancias estiver conectada, nao ha o que avisar.
                if (main.state === 'connected' || grupos.state === 'connected') {
                    dotEl.classList.add('bg-success');
                    setTooltipTitle('WhatsApp conectado.');
                    return;
                }

                if (main.error && grupos.error) {
                    dotEl.classList.add('bg-danger');
                    setTooltipTitle('Nao foi possivel verificar o WhatsApp agora. Confira a conexao para relogar se necessario.');
                } else if (main.state === 'disconnected' || grupos.state === 'disconnected') {
                    dotEl.classList.add('bg-danger');
                    setTooltipTitle('WhatsApp desconectado. Reconecte para voltar a enviar mensagens.');
                } else {
                    dotEl.classList.add('bg-warning');
                    setTooltipTitle('WhatsApp aguardando autenticacao. Abra a conexao para escanear o QR novamente.');
                }
            };

            checkWhatsappStatus();
            setInterval(checkWhatsappStatus, 15000);
        })();
    </script>
@endif