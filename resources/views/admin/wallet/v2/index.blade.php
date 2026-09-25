@extends('layouts.app')
@section('title', 'Carteira · v2')
@section('content')
    @php
        $canViewPnl = auth()->user()->hasModule('wallet.pnl.view');
    @endphp
    <div class="page-content">
        <div class="container-fluid">
            @include('admin.wallet.v2.partials.style')

            <div class="wv2">
                <div class="wv2-head">
                    <div class="wv2-crumb">
                        <a href="{{ route('dashboard') }}">Dashboard</a><span>›</span>
                        <a href="{{ route('admin.wallet.index') }}">Carteira</a><span>›</span>
                        <b>v2</b>
                    </div>
                    <div class="wv2-title">
                        <h1>Resumo da Carteira</h1>
                        <span class="sub">{{ $clients->count() }} clientes com câmbio ativo</span>
                        <div class="wv2-toolbar">
                            <a href="{{ route('admin.wallet.index') }}" class="wv2-act ghost">Tela clássica</a>
                            <button type="button" class="wv2-act" data-bs-toggle="modal" data-bs-target="#depositGlobalModal">
                                Adicionar valor
                            </button>
                        </div>
                    </div>
                </div>

                <div class="wv2-stats">
                    <div>
                        <div class="l">Saldo total BRL</div>
                        <div class="v pos">R$ {{ number_format($totals['BRL'] ?? 0, 2, ',', '.') }}</div>
                        @if(($totals['CLIENTE_DEVE_BRL'] ?? 0) > 0)
                            <div class="hint">Negativos: R$ {{ number_format($totals['CLIENTE_DEVE_BRL'], 2, ',', '.') }}</div>
                        @endif
                    </div>
                    <div>
                        <div class="l">Saldo total USD</div>
                        <div class="v">US$ {{ number_format($totals['USD'] ?? 0, 2, ',', '.') }}</div>
                        <div class="hint">Pré-comprado: US$ {{ number_format($totals['USD_PRE'] ?? 0, 2, ',', '.') }}</div>
                    </div>
                    <div>
                        <div class="l">Devo aos clientes</div>
                        <div class="v neg">R$ {{ number_format($totals['DEVO_BRL'] ?? 0, 2, ',', '.') }}</div>
                        <div class="hint">Depositado, ainda não vendido</div>
                    </div>
                    @if($canViewPnl)
                        <div>
                            <div class="l">Lucro realizado</div>
                            <div class="v {{ ($totals['PNL_USD'] ?? 0) >= 0 ? 'pos' : 'neg' }}">
                                {{ ($totals['PNL_USD'] ?? 0) >= 0 ? '+' : '' }}US$ {{ number_format($totals['PNL_USD'] ?? 0, 4, ',', '.') }}
                            </div>
                            <div class="hint">
                                @if($dateFrom || $dateTo)
                                    {{ $dateFrom ? $dateFrom->format('d/m/Y') : 'início' }} — {{ $dateTo ? $dateTo->format('d/m/Y') : 'hoje' }}
                                @else
                                    Acumulado de todos os fechamentos
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <div class="wv2-head" style="padding:8px 14px;border-bottom:0">
                    <div class="wv2-title">
                        <div class="wv2-busca" id="wv2Busca">
                            <span>🔎</span>
                            <input id="wv2Search" type="text" placeholder="Buscar cliente…  (ex.: nome, e-mail)" autocomplete="off">
                            <button type="button" class="x" id="wv2SearchX">✕</button>
                        </div>
                    </div>
                </div>
                <div class="wv2-chips" id="wv2Chips">
                    <button type="button" class="wv2-chip on" data-f="todos">Todos <b id="cnt-todos">0</b></button>
                    <button type="button" class="wv2-chip" data-f="negativos">Negativos <b id="cnt-negativos">0</b></button>
                    <button type="button" class="wv2-chip" data-f="devendo">Devo pendente <b id="cnt-devendo">0</b></button>
                    <button type="button" class="wv2-chip" data-f="pre">Com USD pré-comprado <b id="cnt-pre">0</b></button>
                    @if($canViewPnl)
                        <button type="button" class="wv2-chip" data-f="pnlpos">PnL positivo <b id="cnt-pnlpos">0</b></button>
                        <button type="button" class="wv2-chip" data-f="pnlneg">PnL negativo <b id="cnt-pnlneg">0</b></button>
                    @endif
                    <span class="wv2-cnt" id="wv2Cnt"></span>
                </div>

                <div class="wv2-gridwrap">
                    <table class="wv2-rows" id="wv2Table">
                        <thead>
                            <tr>
                                <th data-k="nome">Cliente <span class="ar"></span></th>
                                <th class="r" data-k="brl">Saldo BRL</th>
                                <th class="r" data-k="usd">Saldo USD</th>
                                <th class="r" data-k="devo">Devo (R$)</th>
                                <th class="r" data-k="pre">USD pré-comprado</th>
                                @if($canViewPnl)
                                    <th class="r" data-k="pnl">PnL (US$)</th>
                                @endif
                                <th style="width:110px">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($clients as $client)
                                @php
                                    $clientWallets = $walletsByClient[$client->id] ?? ['BRL' => 0, 'USD' => 0];
                                    $pp = $prePurchaseByClient[$client->id] ?? [
                                        'usd_pre_comprado' => 0, 'usd_pre_comprado_raw' => 0,
                                        'brl_em_aberto' => 0, 'pnl_realizado_usd' => 0,
                                        'devido_ao_cliente' => 0, 'devido_ao_cliente_raw' => 0,
                                    ];
                                    $brl = (float) ($clientWallets['BRL'] ?? 0);
                                    $usd = (float) ($clientWallets['USD'] ?? 0);
                                    $ppPnlUsd = (float) ($pnlByClient[$client->id] ?? 0);
                                    $isNeg = $brl < 0 || $usd < 0;
                                    $isDevendo = $pp['devido_ao_cliente'] > 0;
                                    $isPre = $pp['usd_pre_comprado'] > 0;
                                @endphp
                                <tr class="wv2-row"
                                    data-nome="{{ mb_strtolower($client->name . ' ' . $client->email) }}"
                                    data-negativos="{{ $isNeg ? 1 : 0 }}"
                                    data-devendo="{{ $isDevendo ? 1 : 0 }}"
                                    data-pre="{{ $isPre ? 1 : 0 }}"
                                    data-pnlpos="{{ $ppPnlUsd > 0 ? 1 : 0 }}"
                                    data-pnlneg="{{ $ppPnlUsd < 0 ? 1 : 0 }}"
                                    data-v-nome="{{ $client->name }}"
                                    data-v-brl="{{ $brl }}"
                                    data-v-usd="{{ $usd }}"
                                    data-v-devo="{{ $pp['devido_ao_cliente'] }}"
                                    data-v-pre="{{ $pp['usd_pre_comprado'] }}"
                                    data-v-pnl="{{ $ppPnlUsd }}">
                                    <td>
                                        <span class="wv2-name">{{ $client->name }}</span>
                                        @if($isNeg)
                                            <span class="wv2-pill al" title="Saldo negativo — cliente te deve">me deve</span>
                                        @endif
                                        @if($isDevendo)
                                            <span class="wv2-pill bad" title="Ainda falta vender/entregar dólar deste depósito">devo</span>
                                        @endif
                                    </td>
                                    <td class="num {{ $brl < 0 ? 'neg' : '' }}" style="{{ $brl < 0 ? 'color:var(--wv2-red);font-weight:700' : 'font-weight:700;color:var(--wv2-green)' }}">
                                        {{ $brl < 0 ? '-' : '' }}R$ {{ number_format(abs($brl), 2, ',', '.') }}
                                    </td>
                                    <td class="num" style="{{ $usd < 0 ? 'color:var(--wv2-red);font-weight:700' : 'font-weight:700;color:var(--wv2-accent)' }}">
                                        {{ $usd < 0 ? '-' : '' }}US$ {{ number_format(abs($usd), 2, ',', '.') }}
                                    </td>
                                    <td class="num" style="{{ $isDevendo ? 'color:var(--wv2-red);font-weight:700' : 'color:var(--wv2-muted)' }}">
                                        R$ {{ number_format($pp['devido_ao_cliente'], 2, ',', '.') }}
                                        @if($pp['devido_ao_cliente_raw'] < 0)
                                            <span title="Valor bruto ficou negativo: -R$ {{ number_format(abs($pp['devido_ao_cliente_raw']), 2, ',', '.') }}">⚠</span>
                                        @endif
                                    </td>
                                    <td class="num" style="{{ $isPre ? 'font-weight:700' : 'color:var(--wv2-muted)' }}">
                                        US$ {{ number_format($pp['usd_pre_comprado'], 2, ',', '.') }}
                                        @if($pp['usd_pre_comprado_raw'] < 0)
                                            <span title="Já vendeu mais do que comprou: -US$ {{ number_format(abs($pp['usd_pre_comprado_raw']), 2, ',', '.') }}">⚠</span>
                                        @endif
                                    </td>
                                    @if($canViewPnl)
                                        <td class="num" style="{{ $ppPnlUsd >= 0 ? 'color:var(--wv2-green)' : 'color:var(--wv2-red)' }}">
                                            {{ $ppPnlUsd >= 0 ? '+' : '' }}US$ {{ number_format($ppPnlUsd, 4, ',', '.') }}
                                        </td>
                                    @endif
                                    <td>
                                        <a href="{{ route('admin.wallet.client.v2', $client) }}" class="wv2-act">Carteira</a>
                                    </td>
                                </tr>
                            @empty
                            @endforelse
                        </tbody>
                    </table>
                    <div class="wv2-empty" id="wv2Empty" style="display:none">Nenhum cliente encontrado.</div>
                </div>

                <div class="wv2-foot">
                    <span><b id="wv2FootCount">0</b> clientes exibidos</span>
                    <span>Total BRL: <b>R$ {{ number_format($totals['BRL'] ?? 0, 2, ',', '.') }}</b></span>
                    <span>Total USD: <b>US$ {{ number_format($totals['USD'] ?? 0, 2, ',', '.') }}</b></span>
                </div>
            </div>

            <form method="GET" class="row g-2 align-items-end mt-3">
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
                        <i class="ri-filter-2-line me-1"></i>{{ $canViewPnl ? 'Filtrar lucro' : 'Filtrar período' }}
                    </button>
                    @if($dateFrom || $dateTo)
                        <a href="{{ route('admin.wallet.index.v2') }}" class="btn btn-outline-secondary">Limpar</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Mesmo modal "Adicionar valor" da tela clássica --}}
    <div class="modal fade" id="depositGlobalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adicionar valor para cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form id="depositGlobalFormV2" method="POST" action="{{ url('admin/wallet/deposit') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="v2_global_client_id" class="form-label">Cliente</label>
                            <select name="client_id" id="v2_global_client_id" class="form-select" required>
                                <option value="">Selecione o cliente</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" data-spread="{{ $client->spread_points }}">
                                        {{ $client->name }}{{ $client->email ? ' - ' . $client->email : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="v2_global_currency" class="form-label">Moeda</label>
                            <select name="currency" id="v2_global_currency" class="form-select" required>
                                <option value="BRL">Reais (BRL)</option>
                                <option value="USD">Dólar (USD)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="v2_global_amount" class="form-label">Valor</label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="v2_global_amount" class="form-control" required>
                        </div>
                        <div class="mb-3" id="v2_global_fee_group">
                            <label for="v2_global_fee" class="form-label d-flex align-items-center gap-1">
                                Taxa
                                <span id="v2_global_fee_status" class="ms-auto small text-muted"></span>
                            </label>
                            <input type="number" step="0.0001" min="0.0001" name="fee" id="v2_global_fee" class="form-control" value="4.9311" placeholder="4,9311" required>
                            <small class="text-muted d-block mt-1">
                                Cotação base + spread do cliente selecionado (<span id="v2_global_spread_label">0</span> pts).
                            </small>
                        </div>
                        <div class="mb-3">
                            <label for="v2_global_payment_method" class="form-label">Forma de Pagamento</label>
                            <select name="payment_method" id="v2_global_payment_method" class="form-select" required>
                                <option value="pix">Pix</option>
                                <option value="dinheiro">Dinheiro</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            /* ---------- modal "adicionar valor" (mesmo comportamento da v1) ---------- */
            var form = document.getElementById('depositGlobalFormV2');
            var currency = document.getElementById('v2_global_currency');
            var payment = document.getElementById('v2_global_payment_method');
            var feeGroup = document.getElementById('v2_global_fee_group');
            var feeInput = document.getElementById('v2_global_fee');
            var feeStatus = document.getElementById('v2_global_fee_status');
            var clientSelect = document.getElementById('v2_global_client_id');
            var spreadLabel = document.getElementById('v2_global_spread_label');

            function currentSpreadPoints() {
                var selected = clientSelect.options[clientSelect.selectedIndex];
                if (!selected) return 0;
                var spread = parseFloat(selected.getAttribute('data-spread'));
                return isNaN(spread) ? 0 : spread;
            }
            function updatePaymentMethod() {
                payment.innerHTML = '';
                if (currency.value === 'BRL') {
                    payment.innerHTML += '<option value="pix" selected>Pix</option><option value="dinheiro">Dinheiro</option>';
                    feeGroup.style.display = ''; feeInput.disabled = false; feeInput.required = true;
                } else {
                    payment.innerHTML += '<option value="efetivo" selected>Efetivo</option><option value="usdt">USDT</option>';
                    feeGroup.style.display = 'none'; feeInput.disabled = true; feeInput.required = false;
                }
            }
            function updateSpreadLabel() { spreadLabel.textContent = currentSpreadPoints(); }
            function fetchUsdBrlRate() {
                if (currency.value !== 'BRL') return;
                feeStatus.textContent = 'Buscando cotação...'; feeStatus.className = 'ms-auto small text-muted';
                fetch('{{ route('admin.wallet.usd-brl-rate', [], false) }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.success && data.rate) {
                            var base = parseFloat(data.rate);
                            var spreadValue = currentSpreadPoints() * 0.01;
                            feeInput.value = (base + spreadValue).toFixed(4);
                            feeStatus.textContent = 'Base ' + base.toFixed(4) + ' + spread ' + spreadValue.toFixed(2);
                            feeStatus.className = 'ms-auto small text-success';
                        } else {
                            feeStatus.textContent = 'Falha ao obter cotação. Edite manualmente.';
                            feeStatus.className = 'ms-auto small text-danger';
                        }
                    })
                    .catch(function () {
                        feeStatus.textContent = 'Erro ao consultar cotação. Edite manualmente.';
                        feeStatus.className = 'ms-auto small text-danger';
                    });
            }
            currency.addEventListener('change', function () { updatePaymentMethod(); fetchUsdBrlRate(); });
            clientSelect.addEventListener('change', function () { updateSpreadLabel(); fetchUsdBrlRate(); });
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var formData = new FormData(form);
                fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value },
                    body: formData
                }).then(function (response) {
                    if (response.ok) { location.reload(); return; }
                    return response.json().then(function (data) { alert(data.message || 'Erro ao processar depósito.'); });
                }).catch(function () { alert('Erro ao processar depósito.'); });
            });
            updatePaymentMethod(); updateSpreadLabel(); fetchUsdBrlRate();

            /* ---------- busca + chips + ordenação da grade ---------- */
            var rows = Array.from(document.querySelectorAll('#wv2Table tbody tr.wv2-row'));
            var search = document.getElementById('wv2Search');
            var searchBox = document.getElementById('wv2Busca');
            var searchX = document.getElementById('wv2SearchX');
            var chips = Array.from(document.querySelectorAll('#wv2Chips .wv2-chip'));
            var activeFilter = 'todos';
            var sortState = { k: null, dir: 1 };

            function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }

            function passesFilter(row) {
                if (activeFilter === 'todos') return true;
                return row.getAttribute('data-' + activeFilter) === '1';
            }

            function applyFiltersAndSearch() {
                var term = norm(search.value.trim());
                var visible = 0;
                rows.forEach(function (row) {
                    var okFiltro = passesFilter(row);
                    var okBusca = !term || norm(row.getAttribute('data-nome')).indexOf(term) >= 0;
                    var show = okFiltro && okBusca;
                    row.classList.toggle('wv2-hide', !show);
                    if (show) visible++;
                });
                document.getElementById('wv2Empty').style.display = visible === 0 ? '' : 'none';
                document.getElementById('wv2FootCount').textContent = visible;
                document.getElementById('wv2Cnt').textContent = visible + ' de ' + rows.length;
                searchBox.classList.toggle('tem', !!search.value);
            }

            function updateChipCounts() {
                ['todos', 'negativos', 'devendo', 'pre', 'pnlpos', 'pnlneg'].forEach(function (f) {
                    var el = document.getElementById('cnt-' + f);
                    if (!el) return;
                    var n = f === 'todos' ? rows.length : rows.filter(function (r) { return r.getAttribute('data-' + f) === '1'; }).length;
                    el.textContent = n;
                });
            }

            search.addEventListener('input', applyFiltersAndSearch);
            searchX.addEventListener('click', function () { search.value = ''; applyFiltersAndSearch(); search.focus(); });
            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    chips.forEach(function (c) { c.classList.remove('on'); });
                    chip.classList.add('on');
                    activeFilter = chip.getAttribute('data-f');
                    applyFiltersAndSearch();
                });
            });

            document.querySelectorAll('#wv2Table thead th[data-k]').forEach(function (th) {
                th.addEventListener('click', function () {
                    var k = th.getAttribute('data-k');
                    sortState.dir = (sortState.k === k) ? -sortState.dir : 1;
                    sortState.k = k;
                    document.querySelectorAll('#wv2Table thead th .ar').forEach(function (a) { a.textContent = ''; });
                    var arrow = th.querySelector('.ar');
                    if (arrow) arrow.textContent = sortState.dir === 1 ? '▲' : '▼';

                    var tbody = document.querySelector('#wv2Table tbody');
                    var sorted = rows.slice().sort(function (a, b) {
                        var av = a.getAttribute('data-v-' + k), bv = b.getAttribute('data-v-' + k);
                        var an = parseFloat(av), bn = parseFloat(bv);
                        var cmp = (!isNaN(an) && !isNaN(bn)) ? (an - bn) : String(av).localeCompare(String(bv), 'pt-BR');
                        return cmp * sortState.dir;
                    });
                    sorted.forEach(function (row) { tbody.appendChild(row); });
                });
            });

            updateChipCounts();
            applyFiltersAndSearch();
        });
    </script>
@endsection
