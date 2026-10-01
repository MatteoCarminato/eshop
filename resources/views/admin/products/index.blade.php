@extends('layouts.app')

@section('title', 'Produtos')

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Produtos</h4>

                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">Produtos</li>
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
                                <div class="col-xl-7 d-flex align-items-center gap-2">
                                    <form method="GET" action="{{ route('products.index') }}" class="w-100 d-flex align-items-center gap-2"
                                        id="filterForm">
                                        <div class="tag-filter-wrapper form-control d-flex flex-wrap align-items-center gap-1" id="tagWrapper">
                                            <span id="tagList" class="d-flex flex-wrap gap-1"></span>
                                            <input type="text" class="border-0 flex-grow-1 p-0" id="searchInput"
                                                placeholder="Buscar por nome... (Enter adiciona)" style="min-width: 120px; outline: none;">
                                        </div>
                                        <select name="brand" id="brandFilter" class="form-select"
                                            style="width: auto; min-width: 160px;">
                                            <option value="">Todas as marcas</option>
                                            @foreach ($brands as $b)
                                                <option value="{{ $b->id }}" {{ (string) $brand === (string) $b->id ? 'selected' : '' }}>
                                                    {{ $b->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <select name="category" id="categoryFilter" class="form-select"
                                            style="width: auto; min-width: 160px;">
                                            <option value="">Todas as categorias</option>
                                            @foreach ($categories as $c)
                                                <option value="{{ $c->id }}" {{ (string) $category === (string) $c->id ? 'selected' : '' }}>
                                                    {{ $c->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <select name="active" id="activeFilter" class="form-select"
                                            style="width: auto; min-width: 120px;">
                                            <option value="">Todos</option>
                                            <option value="1" {{ $active === '1' ? 'selected' : '' }}>Ativos</option>
                                            <option value="0" {{ $active === '0' ? 'selected' : '' }}>Inativos</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary" title="Buscar">
                                            <i class="ri-search-line"></i>
                                        </button>
                                    </form>
                                </div>
                                <div class="col-xl-5 d-flex gap-2 justify-content-xl-end">
                                    <form method="POST" action="{{ route('products.sync') }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-soft-info">
                                            <i class="ri-refresh-line align-middle me-1"></i>
                                            Sincronizar com ERP
                                        </button>
                                    </form>
                                    <a href="{{ route('products.create') }}" class="btn btn-success">
                                        <i class="ri-add-line align-middle me-1"></i>
                                        Novo Produto
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
                                            <th scope="col">Imagem</th>
                                            <th scope="col">Nome</th>
                                            <th scope="col">Marca</th>
                                            <th scope="col">Categoria</th>
                                            <th scope="col">Preço</th>
                                            <th scope="col">Estoque</th>
                                            <th scope="col">Origem</th>
                                            <th scope="col">Ativo</th>
                                            <th scope="col" class="text-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($products as $product)
                                            <tr>
                                                <td class="fw-medium">#{{ $product->id }}</td>
                                                <td>
                                                    @if ($product->image_url)
                                                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                                                            class="avatar-xs rounded object-fit-cover">
                                                    @else
                                                        <div class="avatar-xs">
                                                            <div class="avatar-title bg-light text-muted rounded fs-16">
                                                                <i class="ri-image-line"></i>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div>{{ $product->name }}</div>
                                                    @if ($product->short_name)
                                                        <small class="text-muted">{{ $product->short_name }}</small>
                                                    @endif
                                                </td>
                                                <td>{{ $product->brand->name ?? '—' }}</td>
                                                <td>{{ $product->category->name ?? '—' }}</td>
                                                <td>{{ $product->formatted_price }}</td>
                                                <td>{{ number_format((float) $product->stock, 2, ',', '.') }}</td>
                                                <td>
                                                    @if ($product->recno)
                                                        <span class="badge bg-info-subtle text-info">ERP</span>
                                                    @else
                                                        <span class="badge bg-secondary-subtle text-secondary">Site</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($product->active)
                                                        <span class="badge bg-success-subtle text-success">Ativo</span>
                                                    @else
                                                        <span class="badge bg-danger-subtle text-danger">Inativo</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="hstack gap-2 justify-content-center">
                                                        <a href="{{ route('products.show', $product) }}"
                                                            class="btn btn-sm btn-info" title="Visualizar">
                                                            <i class="ri-eye-line"></i>
                                                        </a>
                                                        <a href="{{ route('products.edit', $product) }}"
                                                            class="btn btn-sm btn-warning" title="Editar">
                                                            <i class="ri-pencil-line"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-danger"
                                                            onclick="confirmDelete({{ $product->id }}, '{{ $product->name }}')"
                                                            title="Excluir">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="10" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                                        <p class="mb-0">Nenhum produto encontrado</p>
                                                        @if (array_filter((array) request('search', [])) || request('brand') || request('category') || request('active'))
                                                            <a href="{{ route('products.index') }}" class="btn btn-sm btn-link">
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

                            @if ($products instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $products->hasPages())
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="text-muted">
                                        Mostrando {{ $products->firstItem() }} até {{ $products->lastItem() }} de
                                        {{ $products->total() }} registros
                                    </div>
                                    <div>
                                        {{ $products->links() }}
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
                            Você está prestes a excluir o produto <strong id="productName"></strong>.
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

        // Filtro de busca por tags: cada Enter adiciona um termo e já filtra.
        // Os termos ficam na URL como search[] (?search[]=swap&search[]=16),
        // então recarregar a página / paginar mantém os filtros aplicados.
        (function () {
            const tags = @json(array_values(array_filter((array) request('search', []))));
            const tagList = document.getElementById('tagList');
            const searchInput = document.getElementById('searchInput');
            const filterForm = document.getElementById('filterForm');

            function renderTags() {
                tagList.querySelectorAll('input[name="search[]"]').forEach(el => el.remove());
                tagList.querySelectorAll('.tag-chip').forEach(el => el.remove());

                tags.forEach((tag, index) => {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'search[]';
                    hidden.value = tag;
                    tagList.appendChild(hidden);

                    const chip = document.createElement('span');
                    chip.className = 'tag-chip badge bg-primary-subtle text-primary d-inline-flex align-items-center gap-1';
                    chip.innerHTML = '<span></span> <i class="ri-close-line" style="cursor:pointer" role="button"></i>';
                    chip.querySelector('span').textContent = tag;
                    chip.querySelector('i').addEventListener('click', function () {
                        tags.splice(index, 1);
                        renderTags();
                        filterForm.submit();
                    });
                    tagList.appendChild(chip);
                });
            }

            renderTags();
            searchInput.focus();

            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const value = searchInput.value.trim();
                    if (value !== '') {
                        tags.push(value);
                        searchInput.value = '';
                        renderTags();
                        filterForm.submit();
                    }
                } else if (e.key === 'Backspace' && searchInput.value === '' && tags.length > 0) {
                    tags.pop();
                    renderTags();
                    filterForm.submit();
                }
            });
        })();

        document.getElementById('brandFilter').addEventListener('change', function () {
            document.getElementById('filterForm').submit();
        });

        document.getElementById('categoryFilter').addEventListener('change', function () {
            document.getElementById('filterForm').submit();
        });

        document.getElementById('activeFilter').addEventListener('change', function () {
            document.getElementById('filterForm').submit();
        });

        // Confirmação de exclusão
        function confirmDelete(productId, productName) {
            document.getElementById('productName').textContent = productName;
            document.getElementById('deleteForm').action = '{{ route('products.index') }}/' + productId;

            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
@endpush
