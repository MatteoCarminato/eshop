@extends('layouts.app')
@section('title', 'Auditoria da Carteira')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Auditoria da Carteira</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('admin.wallet.index') }}">Carteira</a></li>
                                <li class="breadcrumb-item active">Auditoria</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase mb-2">Clientes auditados</h6>
                            <h3 class="mb-0">{{ $reconciliation->count() }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase mb-2">Divergências</h6>
                            <h3 class="mb-0 {{ $divergentCount > 0 ? 'text-danger' : 'text-success' }}">
                                {{ $divergentCount }}
                            </h3>
                            <small class="text-muted">
                                {{ $divergentCount > 0 ? 'Saldo salvo difere do recalculado.' : 'Tudo batendo.' }}
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase mb-2">Diferença total BRL</h6>
                            <h3 class="mb-0 {{ abs($reconTotals['brl_diff']) > 0.01 ? 'text-danger' : 'text-success' }}">
                                R$ {{ number_format($reconTotals['brl_diff'], 2, ',', '.') }}
                            </h3>
                            <small class="text-muted">
                                Salvo R$ {{ number_format($reconTotals['brl_atual'], 2, ',', '.') }}
                                · Recalc. R$ {{ number_format($reconTotals['brl_recalc'], 2, ',', '.') }}
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase mb-2">Diferença total USD</h6>
                            <h3 class="mb-0 {{ abs($reconTotals['usd_diff']) > 0.01 ? 'text-danger' : 'text-success' }}">
                                US$ {{ number_format($reconTotals['usd_diff'], 2, ',', '.') }}
                            </h3>
                            <small class="text-muted">
                                Salvo US$ {{ number_format($reconTotals['usd_atual'], 2, ',', '.') }}
                                · Recalc. US$ {{ number_format($reconTotals['usd_recalc'], 2, ',', '.') }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" class="row g-2 align-items-end">
                                <div class="col-auto">
                                    <label class="form-label mb-1">Cliente</label>
                                    <select name="client_id" class="form-select">
                                        <option value="">Todos</option>
                                        @foreach($clients as $c)
                                            <option value="{{ $c->id }}" {{ (string) request('client_id') === (string) $c->id ? 'selected' : '' }}>
                                                {{ $c->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1">Tipo</label>
                                    <select name="type" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="deposit" {{ request('type') === 'deposit' ? 'selected' : '' }}>Entrada</option>
                                        <option value="withdraw" {{ request('type') === 'withdraw' ? 'selected' : '' }}>Saída</option>
                                        <option value="exchange_in" {{ request('type') === 'exchange_in' ? 'selected' : '' }}>Câmbio (entrada)</option>
                                        <option value="exchange_out" {{ request('type') === 'exchange_out' ? 'selected' : '' }}>Câmbio (saída)</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1">Moeda</label>
                                    <select name="currency" class="form-select">
                                        <option value="">Todas</option>
                                        <option value="BRL" {{ request('currency') === 'BRL' ? 'selected' : '' }}>BRL</option>
                                        <option value="USD" {{ request('currency') === 'USD' ? 'selected' : '' }}>USD</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="ambos_abertos" {{ request('status') === 'ambos_abertos' ? 'selected' : '' }}>Ambos abertos</option>
                                        <option value="finalizado" {{ request('status') === 'finalizado' ? 'selected' : '' }}>Finalizado</option>
                                        <option value="fechado" {{ request('status') === 'fechado' ? 'selected' : '' }}>Fechado</option>
                                        <option value="__null__" {{ request('status') === '__null__' ? 'selected' : '' }}>Sem status</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1">De</label>
                                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1">Até</label>
                                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                                </div>
                                <div class="col-auto d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ri-filter-2-line me-1"></i>Filtrar
                                    </button>
                                    @if(request()->anyFilled(['client_id', 'type', 'currency', 'status', 'date_from', 'date_to']))
                                        <a href="{{ route('admin.wallet.audit') }}" class="btn btn-outline-secondary">Limpar</a>
                                    @endif
                                </div>
                            </form>
                            <div class="alert alert-info-subtle border border-info-subtle py-2 px-3 mt-3 mb-0 small">
                                <i class="ri-information-line me-1"></i>
                                <strong>Cliente</strong> filtra a reconciliação e o extrato juntos.
                                <strong>Tipo/Moeda/Status/De/Até</strong> filtram só o extrato: o saldo (salvo e
                                recalculado) é sempre a soma de tudo desde o início, não existe "saldo do período".
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header border-0">
                            <h5 class="mb-0">Reconciliação de saldo por cliente</h5>
                            <small class="text-muted d-block">
                                Saldo salvo na carteira vs. saldo recalculado a partir do histórico de transações.
                                Só conferência — não altera nada.
                            </small>
                            <small class="text-muted d-block mt-1">
                                <strong>Diferença = recalculado − salvo.</strong>
                                Positiva: o saldo salvo está mais baixo do que o histórico de transações indica (falta somar algo no saldo).
                                Negativa: o saldo salvo está mais alto do que o histórico indica (tem valor salvo que as transações não explicam).
                                Idealmente fica em R$ 0,00 / US$ 0,00.
                            </small>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th title="Cliente com módulo de câmbio ativo.">Cliente</th>
                                            <th class="text-end" title="Saldo BRL gravado hoje em wallets.balance.">BRL salvo</th>
                                            <th class="text-end" title="Saldo BRL recalculado do zero somando/subtraindo todas as transações BRL do cliente.">BRL recalculado</th>
                                            <th class="text-end" title="Recalculado − salvo. Positiva = saldo salvo abaixo do esperado. Negativa = saldo salvo acima do esperado.">
                                                Dif. BRL
                                            </th>
                                            <th class="text-end" title="Saldo USD gravado hoje em wallets.balance.">USD salvo</th>
                                            <th class="text-end" title="Saldo USD recalculado do zero somando/subtraindo todas as transações USD do cliente.">USD recalculado</th>
                                            <th class="text-end" title="Recalculado − salvo. Positiva = saldo salvo abaixo do esperado. Negativa = saldo salvo acima do esperado.">
                                                Dif. USD
                                            </th>
                                            <th class="text-center" title="OK = diferença dentro de R$/US$ 0,01. Divergente = acima disso em BRL ou USD.">Status</th>
                                            <th style="width: 130px" title="Abre a carteira desse cliente pra investigar a divergência.">Ação</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($reconciliation as $r)
                                            <tr class="{{ $r['has_diff'] ? 'table-danger' : '' }}">
                                                <td class="fw-medium">{{ $r['client_name'] }}</td>
                                                <td class="text-end">R$ {{ number_format($r['brl']['current_balance'], 2, ',', '.') }}</td>
                                                <td class="text-end">R$ {{ number_format($r['brl']['computed_balance'], 2, ',', '.') }}</td>
                                                <td class="text-end {{ abs($r['brl']['difference']) > 0.01 ? 'text-danger fw-semibold' : 'text-muted' }}">
                                                    R$ {{ number_format($r['brl']['difference'], 2, ',', '.') }}
                                                </td>
                                                <td class="text-end">US$ {{ number_format($r['usd']['current_balance'], 2, ',', '.') }}</td>
                                                <td class="text-end">US$ {{ number_format($r['usd']['computed_balance'], 2, ',', '.') }}</td>
                                                <td class="text-end {{ abs($r['usd']['difference']) > 0.01 ? 'text-danger fw-semibold' : 'text-muted' }}">
                                                    US$ {{ number_format($r['usd']['difference'], 2, ',', '.') }}
                                                </td>
                                                <td class="text-center">
                                                    @if($r['has_diff'])
                                                        <span class="badge bg-danger-subtle text-danger">Divergente</span>
                                                    @else
                                                        <span class="badge bg-success-subtle text-success">OK</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ route('admin.wallet.client', $r['client_id']) }}"
                                                        class="btn btn-sm btn-outline-primary">
                                                        Ver carteira
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-4">
                                                    Nenhum cliente com módulo de câmbio ativo.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header border-0">
                            <h5 class="mb-0">Extrato consolidado (todos os clientes)</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-3 mb-3 small text-muted">
                                <span><strong>{{ $extratoTotals['count'] }}</strong> transações no filtro</span>
                                <span>
                                    Soma BRL:
                                    <strong class="{{ $extratoTotals['brl'] < 0 ? 'text-danger' : 'text-success' }}">
                                        R$ {{ number_format($extratoTotals['brl'], 2, ',', '.') }}
                                    </strong>
                                </span>
                                <span>
                                    Soma USD:
                                    <strong class="{{ $extratoTotals['usd'] < 0 ? 'text-danger' : 'text-info' }}">
                                        US$ {{ number_format($extratoTotals['usd'], 2, ',', '.') }}
                                    </strong>
                                </span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-striped table-bordered align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th title="Data e hora em que a transação foi criada.">Data</th>
                                            <th title="Cliente dono da transação. Clique no nome pra abrir a carteira dele.">Cliente</th>
                                            <th title="Entrada = depósito do cliente. Saída = saque. Câmbio = conversão entre BRL e USD.">Tipo</th>
                                            <th title="Moeda em que o valor desta linha está lançado (BRL ou USD).">Moeda</th>
                                            <th class="text-end" title="Valor da transação na moeda da linha. Negativo em vermelho, positivo em verde.">Valor</th>
                                            <th title="Se a transação teve câmbio: valor convertido, moeda de destino e taxa usada.">Conversão</th>
                                            <th title="Situação do depósito: ambos abertos (falta compra e/ou venda), finalizado (já entregue ao cliente) ou fechado.">Status</th>
                                            <th title="Forma de pagamento (pix, dinheiro, efetivo, usdt etc.).">Método</th>
                                            <th title="Observação livre cadastrada na transação, quando houver.">Descrição</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($transactions as $tx)
                                            @php
                                                $typeLabels = [
                                                    'deposit' => ['label' => 'Entrada', 'class' => 'bg-success-subtle text-success'],
                                                    'withdraw' => ['label' => 'Saída', 'class' => 'bg-danger-subtle text-danger'],
                                                    'exchange_in' => ['label' => 'Câmbio (entrada)', 'class' => 'bg-info-subtle text-info'],
                                                    'exchange_out' => ['label' => 'Câmbio (saída)', 'class' => 'bg-warning-subtle text-warning'],
                                                ];
                                                $typeInfo = $typeLabels[$tx->type] ?? ['label' => $tx->type, 'class' => 'bg-secondary-subtle text-secondary'];

                                                $statusLabels = [
                                                    'ambos_abertos' => ['label' => 'Ambos abertos', 'class' => 'bg-warning-subtle text-warning'],
                                                    'finalizado' => ['label' => 'Finalizado', 'class' => 'bg-light text-dark border'],
                                                    'fechado' => ['label' => 'Fechado', 'class' => 'bg-secondary-subtle text-secondary'],
                                                ];
                                                $statusInfo = $tx->status ? ($statusLabels[$tx->status] ?? ['label' => $tx->status, 'class' => 'bg-secondary-subtle text-secondary']) : null;
                                            @endphp
                                            <tr>
                                                <td>{{ $tx->created_at->format('d/m/Y H:i') }}</td>
                                                <td>
                                                    <a href="{{ route('admin.wallet.client', $tx->client_id) }}">
                                                        {{ $tx->client->name ?? ('#' . $tx->client_id) }}
                                                    </a>
                                                </td>
                                                <td><span class="badge {{ $typeInfo['class'] }}">{{ $typeInfo['label'] }}</span></td>
                                                <td>{{ $tx->currency }}</td>
                                                <td class="text-end fw-semibold {{ $tx->amount < 0 ? 'text-danger' : 'text-success' }}">
                                                    {{ number_format($tx->amount, 2, ',', '.') }}
                                                </td>
                                                <td>
                                                    @if($tx->converted_amount)
                                                        {{ number_format($tx->converted_amount, 2, ',', '.') }} {{ $tx->converted_currency }}
                                                        @if($tx->exchange_rate)
                                                            <small class="text-muted d-block">@ {{ number_format($tx->exchange_rate, 4, ',', '.') }}</small>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($statusInfo)
                                                        <span class="badge {{ $statusInfo['class'] }}">{{ $statusInfo['label'] }}</span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>{{ $tx->payment_method ?? '-' }}</td>
                                                <td>{{ $tx->description ?? '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-4">
                                                    Nenhuma transação encontrada com esses filtros.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-3">
                                {{ $transactions->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
