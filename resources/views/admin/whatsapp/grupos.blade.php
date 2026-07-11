@extends('layouts.app')
@section('title', 'Grupos WhatsApp')

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Grupos WhatsApp</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.index') }}">WhatsApp</a></li>
                                <li class="breadcrumb-item active">Grupos</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success py-2">{{ session('success') }}</div>
            @endif

            @if ($error)
                <div class="alert alert-danger py-2">
                    <i class="ri-error-warning-line me-1"></i> {{ $error }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.whatsapp.grupos-wpp.save') }}" id="form-grupos">
                @csrf

                <div class="row mb-3">
                    <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted fs-14">
                                <span id="selected-count">0</span> selecionado(s) de {{ count($groups) }} grupo(s)
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-select-all">
                                Selecionar todos
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-deselect-all">
                                Limpar seleção
                            </button>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-refresh">
                                <i class="ri-refresh-line me-1"></i> Recarregar
                            </button>
                            <button type="submit" class="btn btn-sm btn-success" id="btn-save">
                                <i class="ri-save-line me-1"></i> Salvar selecionados para IA
                            </button>
                        </div>
                    </div>
                </div>

                @if (count($groups) === 0 && !$error)
                    <div class="card">
                        <div class="card-body text-center py-5 text-muted">
                            <i class="ri-group-line fs-1 mb-2 d-block"></i>
                            Nenhum grupo encontrado no WhatsApp conectado.
                        </div>
                    </div>
                @endif

                <div class="row g-3" id="groups-container">
                    @foreach ($groups as $group)
                        @php
                            $chatId  = $group['chatId'] ?? '';
                            $name    = $group['name'] ?? $chatId;
                            $count   = $group['participantsCount'] ?? null;
                            $dbGroup = $saved->get($chatId);
                            $isActive = $dbGroup?->ai_active ?? false;
                            $isSaved  = $dbGroup !== null;
                        @endphp
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <label class="group-card card h-100 mb-0 border {{ $isActive ? 'border-success bg-success-subtle' : '' }}"
                                   style="cursor:pointer; user-select:none;"
                                   data-chat-id="{{ $chatId }}">
                                <div class="card-body d-flex flex-column gap-2 p-3">
                                    <div class="d-flex align-items-start gap-2">
                                        <input type="checkbox"
                                               class="form-check-input group-checkbox mt-1 flex-shrink-0"
                                               {{ $isActive ? 'checked' : '' }}
                                               id="group-{{ $loop->index }}">

                                        {{-- campos enviados no POST --}}
                                        <input type="hidden" name="groups[{{ $loop->index }}][chat_id]" value="{{ $chatId }}">
                                        <input type="hidden" name="groups[{{ $loop->index }}][name]" value="{{ $name }}">
                                        @if ($count !== null)
                                            <input type="hidden" name="groups[{{ $loop->index }}][participants_count]" value="{{ $count }}">
                                        @endif

                                        <div class="min-w-0 flex-grow-1">
                                            <div class="fw-semibold lh-sm text-break">{{ $name }}</div>

                                            <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                                                @if ($count !== null)
                                                    <span class="text-muted fs-12">
                                                        <i class="ri-user-line"></i> {{ $count }}
                                                    </span>
                                                @endif
                                                @if ($isActive)
                                                    <span class="badge bg-success-subtle text-success fs-11">
                                                        <i class="ri-robot-line"></i> IA ativa
                                                    </span>
                                                @elseif ($isSaved)
                                                    <span class="badge bg-secondary-subtle text-secondary fs-11">
                                                        Salvo / inativo
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="text-muted fs-11 mt-1 text-truncate" title="{{ $chatId }}">
                                                {{ $chatId }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    @endforeach
                </div>

            </form>

        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            // grupos já ativos no banco (pre-checked)
            const initiallyActive = new Set(@json($saved->where('ai_active', true)->keys()->values()));

            function checkboxes() {
                return document.querySelectorAll('.group-checkbox');
            }

            function updateCount() {
                document.getElementById('selected-count').textContent =
                    document.querySelectorAll('.group-checkbox:checked').length;
            }

            function applyCardStyle(checkbox) {
                const card = checkbox.closest('.group-card');
                if (!card) return;
                const chatId = card.dataset.chatId;
                const checked = checkbox.checked;

                card.classList.toggle('border-primary', checked && !initiallyActive.has(chatId));
                card.classList.toggle('bg-primary-subtle', checked && !initiallyActive.has(chatId));
                card.classList.toggle('border-success', checked && initiallyActive.has(chatId));
                card.classList.toggle('bg-success-subtle', checked && initiallyActive.has(chatId));
                card.classList.toggle('border', !checked);
            }

            // clique no card inteiro
            document.querySelectorAll('.group-card').forEach((card) => {
                card.addEventListener('click', function (e) {
                    if (e.target.type === 'checkbox') return;
                    const cb = this.querySelector('.group-checkbox');
                    if (cb) { cb.checked = !cb.checked; applyCardStyle(cb); updateCount(); }
                });
            });

            checkboxes().forEach((cb) => {
                cb.addEventListener('change', function () { applyCardStyle(this); updateCount(); });
                applyCardStyle(cb);
            });

            document.getElementById('btn-select-all').addEventListener('click', () => {
                checkboxes().forEach((cb) => { cb.checked = true; applyCardStyle(cb); });
                updateCount();
            });

            document.getElementById('btn-deselect-all').addEventListener('click', () => {
                checkboxes().forEach((cb) => { cb.checked = false; applyCardStyle(cb); });
                updateCount();
            });

            document.getElementById('btn-refresh').addEventListener('click', () => window.location.reload());

            // ao submeter, remove os hidden inputs dos não-selecionados
            // para não enviar grupos desmarcados
            document.getElementById('form-grupos').addEventListener('submit', function () {
                document.querySelectorAll('.group-card').forEach((card) => {
                    const cb = card.querySelector('.group-checkbox');
                    if (cb && !cb.checked) {
                        card.querySelectorAll('input[type="hidden"]').forEach(el => el.disabled = true);
                    }
                });
            });

            updateCount();
        })();
    </script>
@endpush
