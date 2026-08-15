<!-- ========== App Menu ========== -->
<div class="app-menu navbar-menu">
    <!-- LOGO -->
    <div class="navbar-brand-box">
        <!-- Dark Logo-->
        <a href="index.html" class="logo logo-dark">
            <span class="logo-sm">
                <img src="{{ asset('assets/images/logo-eshop.png') }}" alt="" height="22">
            </span>
            <span class="logo-lg">
                <img src="{{ asset('assets/images/logo-eshop.png') }}" alt="" height="40">
            </span>
        </a>
        <!-- Light Logo-->
        <a href="index.html" class="logo logo-light">
            <span class="logo-sm">
                <img src="{{ asset('assets/images/logo-eshop.png') }}" alt="" height="22">
            </span>
            <span class="logo-lg">
                <img src="{{ asset('assets/images/logo-eshop.png') }}" alt="" height="40">
            </span>
        </a>
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover"
            id="vertical-hover">
            <i class="ri-record-circle-line"></i>
        </button>
    </div>

    <div class="dropdown sidebar-user m-1 rounded">
        <button type="button" class="btn material-shadow-none" id="page-header-user-dropdown" data-bs-toggle="dropdown"
            aria-haspopup="true" aria-expanded="false">
            <span class="d-flex align-items-center gap-2">
                <img class="rounded header-profile-user" src="{{ asset('assets/images/users/avatar-1.jpg') }}"
                    alt="Header Avatar">
                <span class="text-start">
                    <span class="d-block fw-medium sidebar-user-name-text">Anna Adame</span>
                    <span class="d-block fs-14 sidebar-user-name-sub-text"><i
                            class="ri ri-circle-fill fs-10 text-success align-baseline"></i> <span
                            class="align-middle">Online</span></span>
                </span>
            </span>
        </button>
        <div class="dropdown-menu dropdown-menu-end">
            <!-- item-->
            <h6 class="dropdown-header">Welcome Anna!</h6>
            <a class="dropdown-item" href="pages-profile.html"><i
                    class="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i> <span
                    class="align-middle">Profile</span></a>
            <a class="dropdown-item" href="apps-chat.html"><i
                    class="mdi mdi-message-text-outline text-muted fs-16 align-middle me-1"></i> <span
                    class="align-middle">Messages</span></a>
            <a class="dropdown-item" href="apps-tasks-kanban.html"><i
                    class="mdi mdi-calendar-check-outline text-muted fs-16 align-middle me-1"></i> <span
                    class="align-middle">Taskboard</span></a>
            <a class="dropdown-item" href="pages-faqs.html"><i
                    class="mdi mdi-lifebuoy text-muted fs-16 align-middle me-1"></i> <span
                    class="align-middle">Help</span></a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="pages-profile.html"><i
                    class="mdi mdi-wallet text-muted fs-16 align-middle me-1"></i> <span class="align-middle">Balance :
                    <b>$5971.67</b></span></a>
            <a class="dropdown-item" href="pages-profile-settings.html"><span
                    class="badge bg-success-subtle text-success mt-1 float-end">New</span><i
                    class="mdi mdi-cog-outline text-muted fs-16 align-middle me-1"></i> <span
                    class="align-middle">Settings</span></a>
            <a class="dropdown-item" href="auth-lockscreen-basic.html"><i
                    class="mdi mdi-lock text-muted fs-16 align-middle me-1"></i> <span class="align-middle">Lock
                    screen</span></a>
            <a class="dropdown-item" href="auth-logout-basic.html"><i
                    class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i> <span class="align-middle"
                    data-key="t-logout">Logout</span></a>
        </div>
    </div>
    <div id="scrollbar">
        <div class="container-fluid">


            <div id="two-column-menu">
            </div>
            <ul class="navbar-nav" id="navbar-nav">
                <li class="menu-title"><span data-key="t-menu">Menu</span></li>
                @if (auth()->user()->hasModule('clients.view') || auth()->user()->hasModule('clients.manage'))
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarDashboards" data-bs-toggle="collapse" role="button"
                            aria-expanded="false" aria-controls="sidebarDashboards">
                            <i data-feather="users"></i> <span data-key="t-dashboards">Clientes</span>
                        </a>
                        <div class="collapse menu-dropdown" id="sidebarDashboards">
                            <ul class="nav nav-sm flex-column">
                                @if (auth()->user()->hasModule('clients.view'))
                                    <li class="nav-item">
                                        <a href="{{ route('clients.index') }}" class="nav-link">
                                            Listar </a>
                                    </li>
                                @endif
                                @if (auth()->user()->hasModule('clients.manage'))
                                    <li class="nav-item">
                                        <a href="{{ route('clients.create') }}" class="nav-link">
                                            Cadastrar </a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </li> <!-- end Dashboard Menu -->
                @endif

                @if (auth()->user()->hasModule('groups.view') || auth()->user()->hasModule('groups.manage'))
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarGroups" data-bs-toggle="collapse" role="button"
                            aria-expanded="false" aria-controls="sidebarGroups">
                            <i data-feather="radio"></i> <span>Grupos</span>
                        </a>
                        <div class="collapse menu-dropdown" id="sidebarGroups">
                            <ul class="nav nav-sm flex-column">
                                @if (auth()->user()->hasModule('groups.view'))
                                    <li class="nav-item">
                                        <a href="{{ route('groups.index') }}" class="nav-link">
                                            Listar
                                        </a>
                                    </li>
                                @endif
                                @if (auth()->user()->hasModule('groups.manage'))
                                    <li class="nav-item">
                                        <a href="{{ route('groups.create') }}" class="nav-link">
                                            Cadastrar
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @auth
                    @if (auth()->user()->hasModule('roles.manage'))
                        <li class="nav-item">
                            <a class="nav-link menu-link" href="#sidebarRoles" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarRoles">
                                <i class="ri-shield-user-line"></i> <span>Cargos &amp; Permissões</span>
                            </a>
                            <div class="collapse menu-dropdown" id="sidebarRoles">
                                <ul class="nav nav-sm flex-column">
                                    <li class="nav-item">
                                        <a href="{{ route('roles.index') }}" class="nav-link">
                                            Cargos
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="{{ route('roles.index') }}" class="nav-link">
                                            Gerenciar Permissões
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    @endif
                @endauth

                @auth
                    @if (auth()->user()->hasModule('users.manage'))
                        <li class="nav-item">
                            <a class="nav-link menu-link" href="#sidebarUsers" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarUsers">
                                <i class="ri-team-line"></i> <span>Funcionários</span>
                            </a>
                            <div class="collapse menu-dropdown" id="sidebarUsers">
                                <ul class="nav nav-sm flex-column">
                                    <li class="nav-item">
                                        <a href="{{ route('users.index') }}" class="nav-link">Listar</a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="{{ route('users.create') }}" class="nav-link">Cadastrar</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    @endif
                @endauth

                @if (auth()->user()->hasModule('wallet.view') || auth()->user()->hasModule('wallet.manage'))
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="#sidebarWallet" data-bs-toggle="collapse" role="button"
                            aria-expanded="false" aria-controls="sidebarWallet">
                            <i class="mdi mdi-cash-multiple"></i> <span>Carteira/Câmbio</span>
                        </a>
                        <div class="collapse menu-dropdown" id="sidebarWallet">
                            <ul class="nav nav-sm flex-column">
                                @if (auth()->user()->hasModule('wallet.view'))
                                    <li class="nav-item">
                                        <a href="{{ route('admin.wallet.index') }}" class="nav-link">Saldo</a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="{{ route('admin.wallet.audit') }}" class="nav-link">Auditoria</a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @auth
                    <li class="nav-item">
                        <a href="{{ route('admin.ai.index') }}" class="nav-link menu-link">
                            <i class="mdi mdi-robot-outline"></i> <span>Análise IA (PIX)</span>
                        </a>
                    </li>
                @endauth

                @auth
                    @if (auth()->user()->hasModule('whatsapp.view') || auth()->user()->hasModule('whatsapp.manage'))
                        <li class="nav-item">
                            <a class="nav-link menu-link" href="#sidebarWhatsApp" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarWhatsApp">
                                <i class="ri-whatsapp-line"></i> <span>WhatsApp</span>
                            </a>
                            <div class="collapse menu-dropdown" id="sidebarWhatsApp">
                                <ul class="nav nav-sm flex-column">
                                    @if (auth()->user()->hasModule('whatsapp.view'))
                                        <li class="nav-item">
                                            <a href="{{ route('admin.whatsapp.envio') }}" class="nav-link menu-link">Enviar
                                                Mensagens</a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="{{ route('admin.whatsapp.schedules') }}"
                                                class="nav-link menu-link">Agendamentos</a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="{{ route('admin.whatsapp.grupos-wpp') }}"
                                                class="nav-link menu-link">Grupos WPP</a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="{{ route('admin.whatsapp.extracoes') }}"
                                                class="nav-link menu-link">Extrações PIX</a>
                                        </li>
                                    @endif
                                    @if (auth()->user()->hasModule('whatsapp.manage'))
                                        <li class="nav-item">
                                            <a class="nav-link menu-link" href="{{ route('admin.whatsapp.index') }}">
                                                Configurar WPP
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </li>

                    @endif
                @endauth
            </ul>
        </div>
        <!-- Sidebar -->
    </div>

    <div class="sidebar-background"></div>
</div>