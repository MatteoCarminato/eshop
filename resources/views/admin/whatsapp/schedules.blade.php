@extends('layouts.app')
@section('title', 'WhatsApp Agendamentos')

@section('content')
    @php
        $canManageWhatsapp = auth()->user()->hasModule('whatsapp.manage');
        $oldClientIds = collect(old('client_ids', []))->map(fn($id) => (int) $id)->all();
        $oldGroupIds = collect(old('group_ids', []))->map(fn($id) => (int) $id)->all();
        $oldWeekdays = collect(old('weekdays', []))->map(fn($day) => (int) $day)->all();
        $weekdayLabels = [
            0 => 'Dom',
            1 => 'Seg',
            2 => 'Ter',
            3 => 'Qua',
            4 => 'Qui',
            5 => 'Sex',
            6 => 'Sab',
        ];
    @endphp

    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Agendamentos WhatsApp</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.index') }}">WhatsApp</a></li>
                                <li class="breadcrumb-item active">Agendamentos</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-2">
                <div class="col-12 d-flex justify-content-end">
                    <a href="{{ route('admin.whatsapp.envio') }}" class="btn btn-outline-primary btn-sm">
                        <i class="ri-send-plane-2-line me-1"></i> Ir para disparo imediato
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
                            <strong>Não foi possível salvar:</strong>
                            <ul class="mb-0 mt-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Nova Configuração de Agendamento</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.whatsapp.schedules.store') }}">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-lg-3">
                                        <label class="form-label fw-semibold">Grupos</label>
                                        <div class="border rounded p-2" style="height: 420px; overflow-y: auto;" id="groups_container">
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
                                    </div>

                                    <div class="col-lg-3">
                                        <label class="form-label fw-semibold">
                                            Clientes
                                            <span class="text-muted fw-normal small ms-1" id="clients_counter"></span>
                                        </label>
                                        <div class="border rounded p-2" style="height: 420px; overflow-y: auto;" id="clients_container">
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
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Nome da configuração (opcional)</label>
                                            <input type="text" name="schedule_name" class="form-control" maxlength="120"
                                                value="{{ old('schedule_name') }}"
                                                placeholder="Ex: Cobrança semanal">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Mensagem (texto)</label>
                                            <textarea name="message" class="form-control" rows="6" maxlength="4000"
                                                placeholder="Mensagem que será enviada todo dia agendado...">{{ old('message') }}</textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Dias do envio automático</label>
                                            <div class="d-flex gap-2 flex-wrap">
                                                @foreach ($weekdayLabels as $weekdayValue => $weekdayLabel)
                                                    <div class="form-check form-check-inline m-0">
                                                        <input class="form-check-input" type="checkbox"
                                                            name="weekdays[]"
                                                            value="{{ $weekdayValue }}"
                                                            id="weekday_{{ $weekdayValue }}"
                                                            @checked(in_array($weekdayValue, $oldWeekdays, true))>
                                                        <label class="form-check-label" for="weekday_{{ $weekdayValue }}">{{ $weekdayLabel }}</label>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <small class="text-muted d-block mt-1">O job roda diariamente às 07:30 (America/Sao_Paulo).</small>
                                        </div>

                                        <div class="d-flex justify-content-end">
                                            @if($canManageWhatsapp)
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="ri-calendar-check-line me-1"></i>
                                                    Salvar Agendamento
                                                </button>
                                            @else
                                                <div class="alert alert-warning py-2 mb-0">Você não possui permissão para gerenciar agendamentos.</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Disparos Agendados</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 32%">Configuração</th>
                                            <th style="width: 18%">Dias</th>
                                            <th style="width: 20%">Status</th>
                                            <th style="width: 18%">Último disparo</th>
                                            <th style="width: 12%" class="text-end">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($schedules as $schedule)
                                            @php
                                                $scheduleWeekdays = collect($schedule->weekdays ?? [])->map(fn($day) => (int) $day)->all();
                                            @endphp
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ $schedule->name ?: 'Sem nome' }}</div>
                                                    <small class="text-muted d-block">{{ $schedule->message }}</small>
                                                </td>
                                                <td>
                                                    @foreach ($scheduleWeekdays as $day)
                                                        <span class="badge bg-light text-dark border me-1">{{ $weekdayLabels[$day] ?? ('Dia ' . $day) }}</span>
                                                    @endforeach
                                                </td>
                                                <td>
                                                    @if($schedule->is_active)
                                                        <span class="badge bg-success-subtle text-success">Ativo</span>
                                                    @else
                                                        <span class="badge bg-secondary-subtle text-secondary">Pausado</span>
                                                    @endif
                                                    <small class="text-muted d-block">Executa às 07:30 (Brasil)</small>
                                                </td>
                                                <td>
                                                    @if($schedule->last_run_at)
                                                        {{ $schedule->last_run_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                                                    @else
                                                        <span class="text-muted">Ainda não executou</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    @if($canManageWhatsapp)
                                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editScheduleModal_{{ $schedule->id }}">
                                                            Editar
                                                        </button>
                                                        <form method="POST" action="{{ route('admin.whatsapp.schedules.toggle', $schedule) }}" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm {{ $schedule->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                                                {{ $schedule->is_active ? 'Pausar' : 'Ativar' }}
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="{{ route('admin.whatsapp.schedules.destroy', $schedule) }}" class="d-inline"
                                                            onsubmit="return confirm('Remover esta configuração agendada?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                                                        </form>
                                                    @else
                                                        <span class="text-muted">Sem permissão</span>
                                                    @endif
                                                </td>
                                            </tr>

                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">Nenhuma configuração agendada cadastrada.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($canManageWhatsapp)
                @foreach ($schedules as $schedule)
                    @php
                        $editClientIds = collect($schedule->client_ids ?? [])->map(fn($id) => (int) $id)->all();
                        $editGroupIds = collect($schedule->group_ids ?? [])->map(fn($id) => (int) $id)->all();
                        $editWeekdays = collect($schedule->weekdays ?? [])->map(fn($day) => (int) $day)->all();
                    @endphp
                    <div class="modal fade" id="editScheduleModal_{{ $schedule->id }}" tabindex="-1"
                        aria-labelledby="editScheduleModalLabel_{{ $schedule->id }}" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editScheduleModalLabel_{{ $schedule->id }}">
                                        Editar Disparo Agendado
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                </div>
                                <form method="POST" action="{{ route('admin.whatsapp.schedules.update', $schedule) }}">
                                    @csrf
                                    @method('PATCH')
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Nome (opcional)</label>
                                            <input type="text" name="schedule_name" class="form-control"
                                                maxlength="120" value="{{ $schedule->name }}"
                                                placeholder="Ex: Cobrança semanal">
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Grupos</label>
                                                <select name="group_ids[]" class="form-select" multiple size="6">
                                                    @foreach ($groups as $group)
                                                        <option value="{{ $group->id }}" @selected(in_array((int) $group->id, $editGroupIds, true))>
                                                            {{ $group->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Clientes</label>
                                                <select name="client_ids[]" class="form-select" multiple size="6">
                                                    @foreach ($clients as $client)
                                                        <option value="{{ $client->id }}" @selected(in_array((int) $client->id, $editClientIds, true))>
                                                            {{ $client->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Dias da semana</label>
                                            <div class="d-flex gap-2 flex-wrap">
                                                @foreach ($weekdayLabels as $weekdayValue => $weekdayLabel)
                                                    <div class="form-check form-check-inline m-0">
                                                        <input class="form-check-input" type="checkbox"
                                                            name="weekdays[]"
                                                            value="{{ $weekdayValue }}"
                                                            id="edit_weekday_{{ $schedule->id }}_{{ $weekdayValue }}"
                                                            @checked(in_array($weekdayValue, $editWeekdays, true))>
                                                        <label class="form-check-label" for="edit_weekday_{{ $schedule->id }}_{{ $weekdayValue }}">{{ $weekdayLabel }}</label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="mb-0">
                                            <label class="form-label">Mensagem</label>
                                            <textarea name="message" class="form-control" rows="6" maxlength="4000"
                                                required>{{ $schedule->message }}</textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Salvar alterações</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const groupChecks = document.querySelectorAll('.group-check');
            const clientChecks = document.querySelectorAll('.client-check');
            const counter = document.getElementById('clients_counter');
            let groupDrivenIds = new Set();

            function updateCounter() {
                if (!counter) return;
                const total = clientChecks.length;
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
                    const id = Number(clientCb.value);
                    const wasGroupDriven = groupDrivenIds.has(id);
                    const isGroupDriven = newGroupDrivenIds.has(id);

                    if (isGroupDriven) {
                        clientCb.checked = true;
                    } else if (wasGroupDriven) {
                        clientCb.checked = false;
                    }
                });

                groupDrivenIds = newGroupDrivenIds;
                updateCounter();
            }

            groupChecks.forEach(cb => cb.addEventListener('change', recalcGroupDriven));
            clientChecks.forEach(cb => cb.addEventListener('change', updateCounter));
            recalcGroupDriven();
        })();
    </script>
@endpush
