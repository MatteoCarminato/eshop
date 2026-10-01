@extends('layouts.app')

@section('title', 'Marcas')

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Marcas</h4>

                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">Marcas</li>
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
                                <div class="col-sm-6 d-flex align-items-center gap-2">
                                    <form method="GET" action="{{ route('brands.index') }}" class="w-100 d-flex gap-2" id="filterForm">
                                        <input type="text" class="form-control search" name="search" id="searchInput"
                                            placeholder="Buscar por nome..." value="{{ request('search') }}">
                                        <button type="submit" class="btn btn-primary" title="Buscar">
                                            <i class="ri-search-line"></i>
                                        </button>
                                    </form>
                                </div>
                                <div class="col-sm-auto ms-auto d-flex gap-2">
                                    <form method="POST" action="{{ route('brands.sync') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-soft-primary">
                                            <i class="ri-refresh-line align-middle me-1"></i>
                                            Sincronizar com ERP
                                        </button>
                                    </form>
                                    <a href="{{ route('brands.create') }}" class="btn btn-success">
                                        <i class="ri-add-line align-middle me-1"></i>
                                        Nova Marca
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
                                            <th scope="col">ID</th>
                                            <th scope="col">Logo</th>
                                            <th scope="col">Nome</th>
                                            <th scope="col">Origem</th>
                                            <th scope="col">Produtos</th>
                                            <th scope="col">Ativo</th>
                                            <th scope="col" class="text-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($brands as $brand)
                                            @php
                                                $productsCount = null;
                                                if (class_exists(\App\Models\Product::class)) {
                                                    $productsCount = $brand->products()->count();
                                                }
                                            @endphp
                                            <tr>
                                                <td class="fw-medium">#{{ $brand->id }}</td>
                                                <td>
                                                    @if ($brand->logo_url)
                                                        <img src="{{ $brand->logo_url }}" alt="{{ $brand->name }}"
                                                            class="avatar-xs rounded" style="object-fit: contain;">
                                                    @else
                                                        <div class="avatar-xs">
                                                            <div class="avatar-title bg-light text-muted rounded-circle fs-13">
                                                                <i class="ri-image-line"></i>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>{{ $brand->name }}</td>
                                                <td>
                                                    @if ($brand->recno)
                                                        <span class="badge bg-info-subtle text-info">ERP</span>
                                                    @else
                                                        <span class="badge bg-secondary-subtle text-secondary">Site</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ route('products.index', ['brand' => $brand->id]) }}" class="text-reset">
                                                        {{ $productsCount ?? 0 }}
                                                    </a>
                                                </td>
                                                <td>
                                                    @if ($brand->active)
                                                        <span class="badge bg-success-subtle text-success">Ativo</span>
                                                    @else
                                                        <span class="badge bg-danger-subtle text-danger">Inativo</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="hstack gap-2 justify-content-center">
                                                        <a href="{{ route('brands.show', $brand) }}"
                                                            class="btn btn-sm btn-info" title="Visualizar">
                                                            <i class="ri-eye-line"></i>
                                                        </a>
                                                        <a href="{{ route('brands.edit', $brand) }}"
                                                            class="btn btn-sm btn-warning" title="Editar">
                                                            <i class="ri-pencil-line"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-danger"
                                                            onclick="confirmDelete({{ $brand->id }}, '{{ $brand->name }}')"
                                                            title="Excluir">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                                        <p class="mb-0">Nenhuma marca encontrada</p>
                                                        @if (request('search'))
                                                            <a href="{{ route('brands.index') }}" class="btn btn-sm btn-link">
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

                            @if ($brands instanceof \Illuminate\Pagination\LengthAwarePaginator && $brands->hasPages())
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="text-muted">
                                        Mostrando {{ $brands->firstItem() }} até {{ $brands->lastItem() }} de
                                        {{ $brands->total() }} registros
                                    </div>
                                    <div>
                                        {{ $brands->links() }}
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
                            Você está prestes a excluir a marca <strong id="brandName"></strong>.
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

        // Manter foco no input após busca
        const searchInput = document.getElementById('searchInput');
        if (searchInput.value) {
            searchInput.focus();
            const len = searchInput.value.length;
            searchInput.setSelectionRange(len, len);
        }
        let searchTimeout;

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                const url = new URL(window.location.href);

                if (searchInput.value) {
                    url.searchParams.set('search', searchInput.value);
                } else {
                    url.searchParams.delete('search');
                }

                window.location.href = url.toString();
            }, 1500);
        });

        // Confirmação de exclusão
        function confirmDelete(brandId, brandName) {
            document.getElementById('brandName').textContent = brandName;
            document.getElementById('deleteForm').action = '{{ route('brands.index') }}/' + brandId;

            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
@endpush
