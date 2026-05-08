@extends('layouts.app')
@section('title', 'Caixa em Dólar')
@section('content')
    <div class="page-content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Caixa Próprio (USD)</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">Caixa USD</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Cards resumo --}}
            <div class="row g-3 mb-3">
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase mb-2">USD em Caixa</h6>
                            <h3 class="mb-0 text-info">US$ {{ number_format($summary['usd_em_caixa'] ?? 0, 4, ',', '.') }}</h3>
                            <small class="text-muted">{{ $summary['lotes_abertos'] ?? 0 }} lote(s) abertos.</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase mb-2">Custo médio</h6>
                            <h3 class="mb-0 text-primary">
                                {{ $summary['custo_medio'] ? number_format($summary['custo_medio'], 4, ',', '.') : '—' }}
                            </h3>
                            <small class="text-muted">R$/USD ponderado pelo saldo restante.</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase mb-2">Investido (em aberto)</h6>
                            <h3 class="mb-0 text-warning">R$ {{ number_format($summary['brl_investido_aberto'] ?? 0, 2, ',', '.') }}</h3>
                            <small class="text-muted">R$ ainda parado em USD não vendido.</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase mb-2">Lucro realizado (US$)</h6>
                            @php
                                $pnlUsdAcum = (float) ($summary['pnl_acumulado_usd'] ?? 0);
                            @endphp
                            <h3 class="mb-0 {{ $pnlUsdAcum >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $pnlUsdAcum >= 0 ? '+' : '' }}US$ {{ number_format($pnlUsdAcum, 2, ',', '.') }}
                            </h3>
                            <small class="text-muted">
                                Equivalente em R$ no período: <strong>{{ $pnlPeriodo >= 0 ? '+' : '' }}R$ {{ number_format($pnlPeriodo, 2, ',', '.') }}</strong>
                                @if($dateFrom || $dateTo)
                                    <br>{{ $dateFrom ? $dateFrom->format('d/m/Y') : 'início' }} — {{ $dateTo ? $dateTo->format('d/m/Y') : 'hoje' }}
                                @endif
                                <br>US$ vendido no período: {{ number_format($usdVendidoPeriodo, 2, ',', '.') }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filtro + ações --}}
            <div class="row g-2 mb-3">
                <form method="GET" class="col-lg-7">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label mb-1">De</label>
                            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">Até</label>
                            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button class="btn btn-primary"><i class="ri-filter-2-line me-1"></i>Filtrar</button>
                            @if($dateFrom || $dateTo)
                                <a href="{{ route('admin.treasury.index') }}" class="btn btn-outline-secondary">Limpar</a>
                            @endif
                        </div>
                    </div>
                </form>
                @auth
                    @if(auth()->user()->hasModule('treasury.manage'))
                        <div class="col-lg-5 d-flex gap-2 justify-content-lg-end align-items-end">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#aportModal">
                                <i class="ri-add-line me-1"></i>Aportar USD
                            </button>
                            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#sellModal"
                                @if(($summary['usd_em_caixa'] ?? 0) <= 0) disabled title="Caixa vazio" @endif>
                                <i class="ri-arrow-right-up-line me-1"></i>Vender p/ cliente
                            </button>
                        </div>
                    @endif
                @endauth
            </div>

            {{-- Movimentação (vendas) em destaque + Lotes (aportes) compactos à direita --}}
            <div class="row">
                {{-- Vendas (movimentação das carteiras dos clientes) --}}
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="ri-exchange-dollar-line me-1"></i>Movimentação (vendas para clientes)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Cliente</th>
                                            <th class="text-end">USD</th>
                                            <th class="text-end">Taxa</th>
                                            <th class="text-end">R$ pago</th>
                                            <th class="text-end">Lucro US$</th>
                                            <th class="text-end">Lucro R$</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($vendas as $v)
                                            @php
                                                $pnlU = (float) ($v->realized_pnl_usd ?? 0);
                                                $pnlR = (float) $v->realized_pnl_brl;
                                            @endphp
                                            <tr>
                                                <td>{{ $v->created_at->format('d/m/Y H:i') }}</td>
                                                <td>{{ optional($v->client)->name ?? '—' }}</td>
                                                <td class="text-end">{{ number_format($v->usd_amount, 2, ',', '.') }}</td>
                                                <td class="text-end">{{ number_format($v->sell_rate, 4, ',', '.') }}</td>
                                                <td class="text-end">R$ {{ number_format($v->brl_total, 2, ',', '.') }}</td>
                                                <td class="text-end fw-bold {{ $pnlU >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ $pnlU >= 0 ? '+' : '' }}{{ number_format($pnlU, 2, ',', '.') }}
                                                </td>
                                                <td class="text-end {{ $pnlR >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ $pnlR >= 0 ? '+' : '' }}{{ number_format($pnlR, 2, ',', '.') }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="text-center text-muted py-3">Sem vendas no período.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Lotes (aportes) compactos --}}
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0 text-uppercase text-muted"><i class="ri-stack-line me-1"></i>Lotes (aportes)</h6>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0 align-middle small">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Origem</th>
                                            <th class="text-end">USD rest.</th>
                                            <th class="text-end">Custo</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($lotes as $l)
                                            @php
                                                $srcLabel = match($l->source ?? 'owner') {
                                                    'owner'        => ['Aporte', 'bg-success-subtle text-success'],
                                                    'pre_purchase' => ['Pré-compra', 'bg-info-subtle text-info'],
                                                    'close'        => ['Fechamento', 'bg-primary-subtle text-primary'],
                                                    'profit'       => ['Lucro', 'bg-warning-subtle text-warning'],
                                                    default        => [$l->source, 'bg-secondary-subtle text-secondary'],
                                                };
                                            @endphp
                                            <tr>
                                                <td>{{ optional($l->purchased_at ?? $l->created_at)->format('d/m H:i') }}</td>
                                                <td><span class="badge {{ $srcLabel[1] }}">{{ $srcLabel[0] }}</span></td>
                                                <td class="text-end">{{ number_format($l->usd_remaining, 2, ',', '.') }}</td>
                                                <td class="text-end">{{ $l->cost_rate > 0 ? number_format($l->cost_rate, 4, ',', '.') : '—' }}</td>
                                                <td>
                                                    @if($l->status === 'open')
                                                        <span class="badge bg-info-subtle text-info">Aberto</span>
                                                    @elseif($l->status === 'partial')
                                                        <span class="badge bg-warning-subtle text-warning">Parcial</span>
                                                    @else
                                                        <span class="badge bg-secondary-subtle text-secondary">Fechado</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="text-center text-muted py-3">Nenhum lote.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Aportar --}}
    <div class="modal fade" id="aportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.treasury.aport') }}">
                    @csrf
                    <div class="modal-header bg-success-subtle">
                        <h5 class="modal-title"><i class="ri-add-line me-1"></i>Aportar USD no caixa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2 small">
                            Você está colocando seu próprio dinheiro para comprar USD a uma taxa de custo.
                            Esses dólares ficam disponíveis para venda futura aos clientes.
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">USD comprado</label>
                                <input type="number" step="0.01" min="0.01" name="usd_amount" id="aport_usd" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Taxa de custo (R$/USD)</label>
                                <input type="number" step="0.0001" min="0.0001" name="cost_rate" id="aport_rate" class="form-control" required>
                            </div>
                        </div>
                        <div class="mt-2 small text-muted">
                            Custo total: <strong id="aport_total">R$ 0,00</strong>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Data da compra (opcional)</label>
                            <input type="datetime-local" name="purchased_at" class="form-control">
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Observação</label>
                            <input type="text" name="notes" class="form-control" placeholder="Ex.: comprei via USDT/Wise/etc.">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Confirmar aporte</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal Vender --}}
    <div class="modal fade" id="sellModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.treasury.sell') }}">
                    @csrf
                    <div class="modal-header bg-warning-subtle">
                        <h5 class="modal-title"><i class="ri-arrow-right-up-line me-1"></i>Vender USD para cliente</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2 small">
                            Tira USD do seu caixa, credita na carteira USD do cliente e debita o R$ correspondente
                            (ele paga). Lucro = (taxa de venda − custo médio dos lotes consumidos).
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Cliente</label>
                            <select name="client_id" class="form-select" required>
                                <option value="">— selecione —</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">USD a vender</label>
                                <input type="number" step="0.01" min="0.01" max="{{ $summary['usd_em_caixa'] ?? 0 }}"
                                    name="usd_amount" id="sell_usd" class="form-control" required>
                                <small class="text-muted">Disponível: US$ {{ number_format($summary['usd_em_caixa'] ?? 0, 2, ',', '.') }}</small>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Taxa de venda (R$/USD)</label>
                                <input type="number" step="0.0001" min="0.0001" name="sell_rate" id="sell_rate" class="form-control" required>
                                @if($summary['custo_medio'])
                                    <small class="text-muted">Custo médio atual: {{ number_format($summary['custo_medio'], 4, ',', '.') }}</small>
                                @endif
                            </div>
                        </div>
                        <div class="mt-2 small text-muted">
                            Cliente pagará: <strong id="sell_total">R$ 0,00</strong> — Lucro estimado:
                            <strong id="sell_pnl" class="text-success">R$ 0,00</strong>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Observação</label>
                            <input type="text" name="notes" class="form-control" placeholder="Ex.: cliente pediu antecipado / cotação travada">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Confirmar venda</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var fmt = function (v) {
                return 'R$ ' + (Number(v) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };
            var custoMedio = parseFloat('{{ $summary['custo_medio'] ?? 0 }}') || 0;

            function bind(usdId, rateId, totalId, pnlId) {
                var u = document.getElementById(usdId);
                var r = document.getElementById(rateId);
                var t = document.getElementById(totalId);
                var p = pnlId ? document.getElementById(pnlId) : null;
                if (!u || !r || !t) return;
                function update() {
                    var uv = parseFloat(u.value) || 0;
                    var rv = parseFloat(r.value) || 0;
                    var total = uv * rv;
                    t.textContent = fmt(total);
                    if (p && custoMedio > 0) {
                        var pnl = uv * (rv - custoMedio);
                        p.textContent = (pnl >= 0 ? '+' : '') + fmt(pnl).replace('R$ ', 'R$ ');
                        p.className = pnl >= 0 ? 'text-success' : 'text-danger';
                    }
                }
                u.addEventListener('input', update);
                r.addEventListener('input', update);
            }

            document.addEventListener('DOMContentLoaded', function () {
                bind('aport_usd', 'aport_rate', 'aport_total');
                bind('sell_usd', 'sell_rate', 'sell_total', 'sell_pnl');
            });
        })();
    </script>
@endsection
