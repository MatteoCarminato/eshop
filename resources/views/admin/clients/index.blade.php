@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Clientes</h4>

                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">Clientes</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header border-0">
                            <div class="row g-4 align-items-center">
                                <div class="col-sm-4 d-flex align-items-center gap-2">
                                    <form method="GET" action="{{ route('clients.index') }}" class="w-100 d-flex gap-2">
                                        <input type="text" class="form-control search" name="search" id="searchInput"
                                            placeholder="Buscar por nome..." value="{{ request('search') }}">
                                        <button type="submit" class="btn btn-primary" title="Buscar">
                                            <i class="ri-search-line"></i>
                                        </button>
                                    </form>
                                </div>
                                <div class="col-sm-auto ms-auto d-flex gap-2">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#groupSelectModal">
                                        <i class="ri-group-line align-middle me-1"></i>
                                        Adicionar ao Grupo
                                    </button>
                                    <a href="{{ route('clients.create') }}" class="btn btn-success">
                                        <i class="ri-add-line align-middle me-1"></i>
                                        Novo Cliente
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            @if (session('success'))
                                <div class="alert alert-success alert-dismissible alert-border-left alert-label-icon fade show"
                                    role="alert">
                                    <i class="ri-check-double-line label-icon"></i>
                                    <strong>Sucesso!</strong> {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            @if (session('error'))
                                <div class="alert alert-danger alert-dismissible alert-border-left alert-label-icon fade show"
                                    role="alert">
                                    <i class="ri-error-warning-line label-icon"></i>
                                    <strong>Erro!</strong> {{ session('error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            <div class="table-responsive">
                                <table class="table table-striped table-nowrap align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="selectAllClients"></th>
                                            <th scope="col">ID</th>
                                            <th scope="col">Nome</th>
                                            <th scope="col">E-mail</th>
                                            <th scope="col">Telefone</th>
                                            <th scope="col">Cadastrado em</th>
                                            <th scope="col" class="text-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($clients as $client)
                                            <tr>
                                                <td><input type="checkbox" class="client-checkbox" value="{{ $client->id }}">
                                                </td>
                                                <td class="fw-medium">#{{ $client->id }}</td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-2">
                                                            <div class="avatar-xs">
                                                                <div
                                                                    class="avatar-title bg-primary-subtle text-primary rounded-circle fs-13">
                                                                    {{ strtoupper(substr($client->name, 0, 1)) }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            {{ $client->name }}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    @if ($client->email)
                                                        <a href="mailto:{{ $client->email }}" class="text-reset">
                                                            <i class="ri-mail-line align-middle me-1"></i>
                                                            {{ $client->email }}
                                                        </a>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($client->phone)
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span>{{ $client->phone }}</span>
                                                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $client->phone) }}"
                                                                target="_blank" class="btn btn-sm btn-success"
                                                                title="Enviar mensagem no WhatsApp">
                                                                <i class="ri-whatsapp-line"></i>
                                                            </a>
                                                        </div>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>{{ $client->created_at->format('d/m/Y H:i') }}</td>
                                                <td>
                                                    <div class="hstack gap-2 justify-content-center">
                                                        <a href="{{ route('clients.show', $client) }}"
                                                            class="btn btn-sm btn-info" title="Visualizar">
                                                            <i class="ri-eye-line"></i>
                                                        </a>
                                                        <a href="{{ route('clients.edit', $client) }}"
                                                            class="btn btn-sm btn-warning" title="Editar">
                                                            <i class="ri-pencil-line"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-danger"
                                                            onclick="confirmDelete({{ $client->id }}, '{{ $client->name }}')"
                                                            title="Excluir">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                                        <p class="mb-0">Nenhum cliente encontrado</p>
                                                        @if (request('search'))
                                                            <a href="{{ route('clients.index') }}" class="btn btn-sm btn-link">
                                                                Limpar filtros
                                                            </a>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <!-- Modal de seleção de grupo -->
                            <div class="modal fade" id="groupSelectModal" tabindex="-1"
                                aria-labelledby="groupSelectModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="groupSelectModalLabel">Adicionar clientes
                                                selecionados ao grupo</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            @include('admin.clients._group_select')
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if ($clients->hasPages())
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="text-muted">
                                        Mostrando {{ $clients->firstItem() }} até {{ $clients->lastItem() }} de
                                        {{ $clients->total() }} registros
                                    </div>
                                    <div>
                                        {{ $clients->links() }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title text-white" id="deleteModalLabel">
                        <i class="ri-error-warning-line me-1"></i>
                        Confirmar Exclusão
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="ri-delete-bin-line display-4 text-danger"></i>
                        <h4 class="mt-3">Tem certeza?</h4>
                        <p class="text-muted">
                            Você está prestes a excluir o cliente <strong id="clientName"></strong>.
                            Esta ação não poderá ser desfeita!
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="ri-close-line me-1"></i>
                        Cancelar
                    </button>
                    <form id="deleteForm" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="ri-delete-bin-line me-1"></i>
                            Sim, Excluir
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('selectAllClients').addEventListener('change', function () {
            const checked = this.checked;
            document.querySelectorAll('.client-checkbox').forEach(cb => cb.checked = checked);
        });
    </script>
    <script>
        // Auto-hide success/error alerts
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(function () {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function (alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });

        // Busca em tempo real
        const searchInput = document.getElementById('searchInput');
        let searchTimeout;

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                const searchValue = searchInput.value;
                const url = new URL(window.location.href);

                if (searchValue) {
                    url.searchParams.set('search', searchValue);
                } else {
                    url.searchParams.delete('search');
                }

                window.location.href = url.toString();
            }, 500);
        });

        // Limpar busca
        document.getElementById('clearSearch').addEventListener('click', function () {
            window.location.href = '{{ route('clients.index') }}';
        });

        // Confirmação de exclusão
        function confirmDelete(clientId, clientName) {
            document.getElementById('clientName').textContent = clientName;
            document.getElementById('deleteForm').action = '{{ route('clients.index') }}/' + clientId;

            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
@endpush